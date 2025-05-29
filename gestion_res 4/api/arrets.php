<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// En-têtes requis
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Gérer la méthode OPTIONS (pré-vérification CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit;
}

// Inclure la connexion à la base de données
require_once 'config/database.php';

// Créer une connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Vérifier si la connexion a réussi
if (!$db) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données.'
    ]);
    exit;
}

// Déterminer la méthode HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Récupérer l'ID en paramètre si présent
$id = isset($_GET['id']) ? $_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($id) {
            getOneArret($db, $id);
        } else {
            getAllArrets($db);
        }
        break;
    
    case 'POST':
        addArret($db);
        break;
    
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID arrêt manquant.'
            ]);
            exit;
        }
        updateArret($db, $id);
        break;
    
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID arrêt manquant.'
            ]);
            exit;
        }
        deleteArret($db, $id);
        break;
    
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Méthode non autorisée.'
        ]);
        break;
}

/**
 * Récupérer tous les arrêts
 */
function getAllArrets($db) {
    try {
        // Requête pour récupérer tous les enregistrements avec les informations de l'escale, navire et opération
        $query = "SELECT a.*, e.NOM_navire, o.TYPE_operation, o.DATE_debut as OPERATION_DATE_DEBUT, o.DATE_fin as OPERATION_DATE_FIN
                  FROM arret a 
                  LEFT JOIN escale e ON a.NUM_escale = e.NUM_escale 
                  LEFT JOIN operation o ON a.ID_operation = o.ID_operation 
                  ORDER BY a.DATE_DEBUT_arret DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            // Tableau pour les données
            $arrets_arr = array();
            $arrets_arr["records"] = array();
            $arrets_arr["count"] = $num;
            
            // Récupérer les résultats
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $arret_item = array(
                    "ID_arret" => $ID_arret,
                    "ID_operation" => $ID_operation,
                    "NUM_escale" => $NUM_escale,
                    "MOTIF_arret" => $MOTIF_arret,
                    "DURE_arret" => $DURE_arret,
                    "DATE_DEBUT_arret" => $DATE_DEBUT_arret,
                    "DATE_FIN_arret" => $DATE_FIN_arret,
                    "NOM_navire" => $NOM_navire ?? 'Navire inconnu',
                    "TYPE_operation" => $TYPE_operation ?? 'Aucune opération',
                    "OPERATION_DATE_DEBUT" => $OPERATION_DATE_DEBUT,
                    "OPERATION_DATE_FIN" => $OPERATION_DATE_FIN
                );
                
                array_push($arrets_arr["records"], $arret_item);
            }
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode(array_merge(
                ["success" => true, "message" => 'Arrêts récupérés avec succès'],
                $arrets_arr
            ));
            return;
        } else {
            // Aucun arrêt trouvé
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Aucun arrêt trouvé.',
                'records' => [],
                'count' => 0
            ]);
            return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
        ]);
    }
}

/**
 * Récupérer un arrêt par ID
 */
function getOneArret($db, $id) {
    try {
        // Requête pour lire un seul enregistrement
        $query = "SELECT a.*, e.NOM_navire, o.TYPE_operation, o.DATE_debut as OPERATION_DATE_DEBUT, o.DATE_fin as OPERATION_DATE_FIN
                  FROM arret a 
                  LEFT JOIN escale e ON a.NUM_escale = e.NUM_escale 
                  LEFT JOIN operation o ON a.ID_operation = o.ID_operation 
                  WHERE a.ID_arret = :id LIMIT 0,1";
        $stmt = $db->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':id', $id);
        
        // Exécuter la requête
        $stmt->execute();
        
        // Récupérer l'enregistrement
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Créer un tableau
            $arret_arr = array(
                "ID_arret" => $row['ID_arret'],
                "ID_operation" => $row['ID_operation'],
                "NUM_escale" => $row['NUM_escale'],
                "MOTIF_arret" => $row['MOTIF_arret'],
                "DURE_arret" => $row['DURE_arret'],
                "DATE_DEBUT_arret" => $row['DATE_DEBUT_arret'],
                "DATE_FIN_arret" => $row['DATE_FIN_arret'],
                "NOM_navire" => $row['NOM_navire'] ?? 'Navire inconnu',
                "TYPE_operation" => $row['TYPE_operation'] ?? 'Aucune opération',
                "OPERATION_DATE_DEBUT" => $row['OPERATION_DATE_DEBUT'],
                "OPERATION_DATE_FIN" => $row['OPERATION_DATE_FIN']
            );
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Arrêt trouvé',
                'record' => $arret_arr
            ]);
            return;
        } else {
            // Arrêt non trouvé
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Arrêt non trouvé.'
            ]);
            return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
        ]);
    }
}

/**
 * Ajouter un nouvel arrêt
 */
function addArret($db) {
    // Récupérer les données JSON envoyées
    $data = json_decode(file_get_contents("php://input"));
    
    // Debug : afficher les données reçues
    error_log("Données reçues: " . print_r($data, true));
    
    // Vérifier si les données JSON sont valides
    if ($data === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données JSON invalides.'
        ]);
        return;
    }
    
    // Vérifier si les données nécessaires sont fournies
    if (!isset($data->num_escale) || !isset($data->motif_arret) || 
        !isset($data->duree_arret) || !isset($data->date_debut) || !isset($data->date_fin)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données incomplètes. Escale, motif, durée, date début et date fin sont requis.',
            'received_data' => $data
        ]);
        return;
    }
    
    try {
        // Debug : log des données reçues
        error_log("=== DEBUT DEBUG ARRET ===");
        error_log("Escale reçue: " . $data->num_escale);
        error_log("Motif reçu: " . $data->motif_arret);
        error_log("Durée reçue: " . $data->duree_arret);
        error_log("Date début reçue: " . $data->date_debut);
        error_log("Date fin reçue: " . $data->date_fin);
        error_log("Opération reçue: " . ($data->id_operation ?? 'null'));
        
        // Vérifier que l'escale existe
        $check_escale_query = "SELECT NUM_escale FROM escale WHERE NUM_escale = :num_escale";
        $check_escale_stmt = $db->prepare($check_escale_query);
        $check_escale_stmt->bindParam(':num_escale', $data->num_escale);
        $check_escale_stmt->execute();
        $escale_row = $check_escale_stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Escale trouvée: " . print_r($escale_row, true));
        
        if (!$escale_row) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'L\'escale avec le numéro "' . $data->num_escale . '" n\'existe pas.',
                'error_code' => 'ESCALE_NOT_FOUND',
                'num_escale_recherche' => $data->num_escale
            ]);
            return;
        }
        
        // Vérifier que l'opération existe si fournie
        if (isset($data->id_operation) && !empty($data->id_operation)) {
            $check_operation_query = "SELECT ID_operation FROM operation WHERE ID_operation = :id_operation";
            $check_operation_stmt = $db->prepare($check_operation_query);
            $check_operation_stmt->bindParam(':id_operation', $data->id_operation);
            $check_operation_stmt->execute();
            $operation_row = $check_operation_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$operation_row) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'L\'opération avec l\'ID "' . $data->id_operation . '" n\'existe pas.',
                    'error_code' => 'OPERATION_NOT_FOUND'
                ]);
                return;
            }
        }
        
        // Vérifier que les dates sont valides
        $date_debut = DateTime::createFromFormat('Y-m-d\TH:i', $data->date_debut);
        $date_fin = DateTime::createFromFormat('Y-m-d\TH:i', $data->date_fin);
        
        // Si le format Y-m-d\TH:i ne fonctionne pas, essayer d'autres formats
        if (!$date_debut) {
            $date_debut = DateTime::createFromFormat('d/m/Y H:i', $data->date_debut);
        }
        if (!$date_fin) {
            $date_fin = DateTime::createFromFormat('d/m/Y H:i', $data->date_fin);
        }
        
        // Si toujours pas bon, essayer le format ISO
        if (!$date_debut) {
            $date_debut = new DateTime($data->date_debut);
        }
        if (!$date_fin) {
            $date_fin = new DateTime($data->date_fin);
        }
        
        if (!$date_debut || !$date_fin) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Format de date invalide. Utilisez le format YYYY-MM-DDTHH:MM.',
                'date_debut' => $data->date_debut,
                'date_fin' => $data->date_fin
            ]);
            return;
        }
        
        if ($date_debut >= $date_fin) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'La date de fin doit être postérieure à la date de début.'
            ]);
            return;
        }
        
        // Vérifier que la durée est cohérente
        $duree_calculee = ($date_fin->getTimestamp() - $date_debut->getTimestamp()) / 60; // en minutes
        $duree_fournie = intval($data->duree_arret);
        
        // Tolérance de 5 minutes pour les arrondis
        if (abs($duree_calculee - $duree_fournie) > 5) {
            error_log("Durée calculée: " . $duree_calculee . " minutes");
            error_log("Durée fournie: " . $duree_fournie . " minutes");
            // Avertissement mais pas d'erreur bloquante
        }
        
        // Requête pour insérer un nouvel enregistrement
        $query = "INSERT INTO arret (ID_operation, NUM_escale, MOTIF_arret, DURE_arret, DATE_DEBUT_arret, DATE_FIN_arret) 
                  VALUES (:id_operation, :num_escale, :motif_arret, :duree_arret, :date_debut, :date_fin)";
        $stmt = $db->prepare($query);
        
        // Nettoyer les données
        $id_operation = isset($data->id_operation) && !empty($data->id_operation) ? htmlspecialchars(strip_tags($data->id_operation)) : null;
        $num_escale = htmlspecialchars(strip_tags($data->num_escale));
        $motif_arret = htmlspecialchars(strip_tags($data->motif_arret));
        $duree_arret = intval($data->duree_arret);
        $date_debut_str = $date_debut->format('Y-m-d H:i:s');
        $date_fin_str = $date_fin->format('Y-m-d H:i:s');
        
        // Liaison des valeurs
        $stmt->bindParam(':id_operation', $id_operation);
        $stmt->bindParam(':num_escale', $num_escale);
        $stmt->bindParam(':motif_arret', $motif_arret);
        $stmt->bindParam(':duree_arret', $duree_arret, PDO::PARAM_INT);
        $stmt->bindParam(':date_debut', $date_debut_str);
        $stmt->bindParam(':date_fin', $date_fin_str);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Récupérer l'ID arrêt généré
            $query = "SELECT ID_arret FROM arret WHERE NUM_escale = :num_escale AND DATE_DEBUT_arret = :date_debut ORDER BY ID_arret DESC LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':num_escale', $num_escale);
            $stmt->bindParam(':date_debut', $date_debut_str);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $id_arret = $row ? $row['ID_arret'] : null;
            
            // Réponse - succès
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Arrêt créé avec succès.',
                'id_arret' => $id_arret
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de créer l\'arrêt.',
                'sql_error' => $stmt->errorInfo()
            ]);
            return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la création: ' . $e->getMessage()
        ]);
    }
}

/**
 * Mettre à jour un arrêt
 */
function updateArret($db, $id) {
    // Récupérer les données JSON envoyées
    $data = json_decode(file_get_contents("php://input"));
    
    try {
        // Vérifier si l'arrêt existe
        $check_query = "SELECT COUNT(*) as count FROM arret WHERE ID_arret = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->execute();
        $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Arrêt non trouvé.'
            ]);
            return;
        }
        
        // Construire la requête de mise à jour dynamiquement
        $query = "UPDATE arret SET ";
        $params = [];
        
        if (isset($data->id_operation)) {
            // Vérifier que l'opération existe si fournie
            if (!empty($data->id_operation)) {
                $check_operation_query = "SELECT COUNT(*) as count FROM operation WHERE ID_operation = :id_operation";
                $check_operation_stmt = $db->prepare($check_operation_query);
                $check_operation_stmt->bindParam(':id_operation', $data->id_operation);
                $check_operation_stmt->execute();
                $operation_row = $check_operation_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($operation_row['count'] == 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'L\'opération spécifiée n\'existe pas.'
                    ]);
                    return;
                }
            }
            
            $query .= "ID_operation = :id_operation, ";
            $params[':id_operation'] = !empty($data->id_operation) ? htmlspecialchars(strip_tags($data->id_operation)) : null;
        }
        
        if (isset($data->num_escale)) {
            // Vérifier que l'escale existe
            $check_escale_query = "SELECT COUNT(*) as count FROM escale WHERE NUM_escale = :num_escale";
            $check_escale_stmt = $db->prepare($check_escale_query);
            $check_escale_stmt->bindParam(':num_escale', $data->num_escale);
            $check_escale_stmt->execute();
            $escale_row = $check_escale_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($escale_row['count'] == 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'L\'escale spécifiée n\'existe pas.'
                ]);
                return;
            }
            
            $query .= "NUM_escale = :num_escale, ";
            $params[':num_escale'] = htmlspecialchars(strip_tags($data->num_escale));
        }
        
        if (isset($data->motif_arret)) {
            $query .= "MOTIF_arret = :motif_arret, ";
            $params[':motif_arret'] = htmlspecialchars(strip_tags($data->motif_arret));
        }
        
        if (isset($data->duree_arret)) {
            $query .= "DURE_arret = :duree_arret, ";
            $params[':duree_arret'] = intval($data->duree_arret);
        }
        
        if (isset($data->date_debut)) {
            $date_debut = DateTime::createFromFormat('Y-m-d\TH:i', $data->date_debut);
            if (!$date_debut) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Format de date de début invalide.'
                ]);
                return;
            }
            
            $query .= "DATE_DEBUT_arret = :date_debut, ";
            $params[':date_debut'] = $date_debut->format('Y-m-d H:i:s');
        }
        
        if (isset($data->date_fin)) {
            $date_fin = DateTime::createFromFormat('Y-m-d\TH:i', $data->date_fin);
            if (!$date_fin) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Format de date de fin invalide.'
                ]);
                return;
            }
            
            $query .= "DATE_FIN_arret = :date_fin, ";
            $params[':date_fin'] = $date_fin->format('Y-m-d H:i:s');
        }
        
        // Vérifier que les dates sont cohérentes si les deux sont fournies
        if (isset($data->date_debut) && isset($data->date_fin)) {
            if ($date_debut >= $date_fin) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'La date de fin doit être postérieure à la date de début.'
                ]);
                return;
            }
        }
        
        // Supprimer la dernière virgule et espace
        $query = rtrim($query, ", ");
        
        // Ajouter la condition WHERE
        $query .= " WHERE ID_arret = :id";
        $params[':id'] = $id;
        
        // Préparer la requête
        $stmt = $db->prepare($query);
        
        // Liaison des paramètres
        foreach ($params as $key => $value) {
            if ($key === ':duree_arret') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        // Exécuter la requête
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Arrêt mis à jour avec succès.'
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de mettre à jour l\'arrêt.'
            ]);
            return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
        ]);
    }
}

/**
 * Supprimer un arrêt
 */
function deleteArret($db, $id) {
    try {
        // Vérifier si l'arrêt existe
        $check_query = "SELECT COUNT(*) as count FROM arret WHERE ID_arret = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->execute();
        $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Arrêt non trouvé.'
            ]);
            return;
        }
        
        // Requête pour supprimer un enregistrement
        $query = "DELETE FROM arret WHERE ID_arret = :id";
        $stmt = $db->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':id', $id);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Arrêt supprimé avec succès.'
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer l\'arrêt.'
            ]);
            return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
        ]);
    }
}
?>