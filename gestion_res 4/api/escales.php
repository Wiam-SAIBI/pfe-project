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
            getOneEscale($db, $id);
        } else {
            getAllEscales($db);
        }
        break;
    
    case 'POST':
        addEscale($db);
        break;
    
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID escale manquant.'
            ]);
            exit;
        }
        updateEscale($db, $id);
        break;
    
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID escale manquant.'
            ]);
            exit;
        }
        deleteEscale($db, $id);
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
 * Récupérer toutes les escales
 */
function getAllEscales($db) {
    try {
        // Requête pour récupérer tous les enregistrements avec les informations du navire
        $query = "SELECT e.*, n.NOM_navire FROM escale e 
                  LEFT JOIN navire n ON e.MATRICULE_navire = n.MATRICULE_navire 
                  ORDER BY e.DATE_accostage DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            // Tableau pour les données
            $escales_arr = array();
            $escales_arr["records"] = array();
            $escales_arr["count"] = $num;
            
            // Récupérer les résultats
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $escale_item = array(
                    "NUM_escale" => $NUM_escale,
                    "NOM_navire" => $NOM_navire ?? 'Navire inconnu',
                    "MATRICULE_navire" => $MATRICULE_navire,
                    "DATE_accostage" => $DATE_accostage,
                    "DATE_sortie" => $DATE_sortie
                );
                
                array_push($escales_arr["records"], $escale_item);
            }
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode(array_merge(
                ["success" => true, "message" => 'Escales récupérées avec succès'],
                $escales_arr
            ));
            return;
        } else {
            // Aucune escale trouvée
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Aucune escale trouvée.',
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
 * Récupérer une escale par numéro
 */
function getOneEscale($db, $id) {
    try {
        // Requête pour lire un seul enregistrement
        $query = "SELECT e.*, n.NOM_navire FROM escale e 
                  LEFT JOIN navire n ON e.MATRICULE_navire = n.MATRICULE_navire 
                  WHERE e.NUM_escale = :id LIMIT 0,1";
        $stmt = $db->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':id', $id);
        
        // Exécuter la requête
        $stmt->execute();
        
        // Récupérer l'enregistrement
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Créer un tableau
            $escale_arr = array(
                "NUM_escale" => $row['NUM_escale'],
                "NOM_navire" => $row['NOM_navire'] ?? 'Navire inconnu',
                "MATRICULE_navire" => $row['MATRICULE_navire'],
                "DATE_accostage" => $row['DATE_accostage'],
                "DATE_sortie" => $row['DATE_sortie']
            );
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Escale trouvée',
                'record' => $escale_arr
            ]);
            return;
        } else {
            // Escale non trouvée
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Escale non trouvée.'
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
 * Ajouter une nouvelle escale
 */
function addEscale($db) {
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
    if (!isset($data->matricule_navire) || !isset($data->nom_navire) || 
        !isset($data->date_accostage) || !isset($data->date_sortie)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données incomplètes. Matricule navire, nom navire, date d\'accostage et date de sortie sont requis.',
            'received_data' => $data
        ]);
        return;
    }
    
    try {
        // Debug : log des données reçues
        error_log("=== DEBUT DEBUG ESCALE ===");
        error_log("Matricule reçu: " . $data->matricule_navire);
        error_log("Nom reçu: " . $data->nom_navire);
        error_log("Date accostage reçue: " . $data->date_accostage);
        error_log("Date sortie reçue: " . $data->date_sortie);
        
        // Vérifier que le navire existe avec un SELECT plus détaillé
        $check_navire_query = "SELECT MATRICULE_navire, NOM_navire FROM navire WHERE MATRICULE_navire = :matricule";
        $check_navire_stmt = $db->prepare($check_navire_query);
        $check_navire_stmt->bindParam(':matricule', $data->matricule_navire);
        $check_navire_stmt->execute();
        $navire_row = $check_navire_stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Navire trouvé: " . print_r($navire_row, true));
        
        // Si le navire n'existe pas, lister tous les navires pour debug
        if (!$navire_row) {
            $list_navires = "SELECT MATRICULE_navire, NOM_navire FROM navire LIMIT 5";
            $list_stmt = $db->prepare($list_navires);
            $list_stmt->execute();
            $all_navires = $list_stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Navires disponibles: " . print_r($all_navires, true));
            
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Le navire avec le matricule "' . $data->matricule_navire . '" n\'existe pas.',
                'error_code' => 'NAVIRE_NOT_FOUND',
                'matricule_recherche' => $data->matricule_navire,
                'navires_disponibles' => $all_navires
            ]);
            return;
        }
        
        // Vérifier que les dates sont valides
        $date_accostage = DateTime::createFromFormat('Y-m-d\TH:i', $data->date_accostage);
        $date_sortie = DateTime::createFromFormat('Y-m-d\TH:i', $data->date_sortie);
        
        // Si le format Y-m-d\TH:i ne fonctionne pas, essayer d'autres formats
        if (!$date_accostage) {
            $date_accostage = DateTime::createFromFormat('d/m/Y H:i', $data->date_accostage);
        }
        if (!$date_sortie) {
            $date_sortie = DateTime::createFromFormat('d/m/Y H:i', $data->date_sortie);
        }
        
        // Si toujours pas bon, essayer le format ISO
        if (!$date_accostage) {
            $date_accostage = new DateTime($data->date_accostage);
        }
        if (!$date_sortie) {
            $date_sortie = new DateTime($data->date_sortie);
        }
        
        if (!$date_accostage || !$date_sortie) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Format de date invalide. Utilisez le format YYYY-MM-DDTHH:MM.',
                'date_accostage' => $data->date_accostage,
                'date_sortie' => $data->date_sortie
            ]);
            return;
        }
        
        if ($date_accostage >= $date_sortie) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'La date de sortie doit être postérieure à la date d\'accostage.'
            ]);
            return;
        }
        
        // Requête pour insérer un nouvel enregistrement
        $query = "INSERT INTO escale (NOM_navire, MATRICULE_navire, DATE_accostage, DATE_sortie) VALUES (:nom_navire, :matricule_navire, :date_accostage, :date_sortie)";
        $stmt = $db->prepare($query);
        
        // Nettoyer les données
        $nom_navire = htmlspecialchars(strip_tags($data->nom_navire));
        $matricule_navire = htmlspecialchars(strip_tags($data->matricule_navire));
        $date_accostage_str = $date_accostage->format('Y-m-d H:i:s');
        $date_sortie_str = $date_sortie->format('Y-m-d H:i:s');
        
        // Liaison des valeurs
        $stmt->bindParam(':nom_navire', $nom_navire);
        $stmt->bindParam(':matricule_navire', $matricule_navire);
        $stmt->bindParam(':date_accostage', $date_accostage_str);
        $stmt->bindParam(':date_sortie', $date_sortie_str);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Récupérer le numéro d'escale généré
            $query = "SELECT NUM_escale FROM escale WHERE MATRICULE_navire = :matricule AND DATE_accostage = :date_accostage ORDER BY NUM_escale DESC LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':matricule', $matricule_navire);
            $stmt->bindParam(':date_accostage', $date_accostage_str);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $num_escale = $row ? $row['NUM_escale'] : null;
            
            // Réponse - succès
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Escale créée avec succès.',
                'num_escale' => $num_escale
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de créer l\'escale.',
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
 * Mettre à jour une escale
 */
function updateEscale($db, $id) {
    // Récupérer les données JSON envoyées
    $data = json_decode(file_get_contents("php://input"));
    
    try {
        // Vérifier si l'escale existe
        $check_query = "SELECT COUNT(*) as count FROM escale WHERE NUM_escale = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->execute();
        $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Escale non trouvée.'
            ]);
            return;
        }
        
        // Construire la requête de mise à jour dynamiquement
        $query = "UPDATE escale SET ";
        $params = [];
        
        if (isset($data->nom_navire)) {
            $query .= "NOM_navire = :nom_navire, ";
            $params[':nom_navire'] = htmlspecialchars(strip_tags($data->nom_navire));
        }
        
        if (isset($data->matricule_navire)) {
            // Vérifier que le navire existe
            $check_navire_query = "SELECT COUNT(*) as count FROM navire WHERE MATRICULE_navire = :matricule";
            $check_navire_stmt = $db->prepare($check_navire_query);
            $check_navire_stmt->bindParam(':matricule', $data->matricule_navire);
            $check_navire_stmt->execute();
            $navire_row = $check_navire_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($navire_row['count'] == 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Le navire spécifié n\'existe pas.'
                ]);
                return;
            }
            
            $query .= "MATRICULE_navire = :matricule_navire, ";
            $params[':matricule_navire'] = htmlspecialchars(strip_tags($data->matricule_navire));
        }
        
        if (isset($data->date_accostage)) {
            $date_accostage = DateTime::createFromFormat('Y-m-d\TH:i', $data->date_accostage);
            if (!$date_accostage) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Format de date d\'accostage invalide.'
                ]);
                return;
            }
            
            $query .= "DATE_accostage = :date_accostage, ";
            $params[':date_accostage'] = $date_accostage->format('Y-m-d H:i:s');
        }
        
        if (isset($data->date_sortie)) {
            $date_sortie = DateTime::createFromFormat('Y-m-d\TH:i', $data->date_sortie);
            if (!$date_sortie) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Format de date de sortie invalide.'
                ]);
                return;
            }
            
            $query .= "DATE_sortie = :date_sortie, ";
            $params[':date_sortie'] = $date_sortie->format('Y-m-d H:i:s');
        }
        
        // Vérifier que les dates sont cohérentes si les deux sont fournies
        if (isset($data->date_accostage) && isset($data->date_sortie)) {
            if ($date_accostage >= $date_sortie) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'La date de sortie doit être postérieure à la date d\'accostage.'
                ]);
                return;
            }
        }
        
        // Supprimer la dernière virgule et espace
        $query = rtrim($query, ", ");
        
        // Ajouter la condition WHERE
        $query .= " WHERE NUM_escale = :id";
        $params[':id'] = $id;
        
        // Préparer la requête
        $stmt = $db->prepare($query);
        
        // Liaison des paramètres
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        // Exécuter la requête
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Escale mise à jour avec succès.'
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de mettre à jour l\'escale.'
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
 * Supprimer une escale
 */
function deleteEscale($db, $id) {
    try {
        // Vérifier si l'escale existe
        $check_query = "SELECT COUNT(*) as count FROM escale WHERE NUM_escale = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->execute();
        $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Escale non trouvée.'
            ]);
            return;
        }
        
        // Vérifier s'il y a des opérations liées
        $check_operations = "SELECT COUNT(*) as count FROM operation WHERE ID_escale = :id";
        $check_operations_stmt = $db->prepare($check_operations);
        $check_operations_stmt->bindParam(':id', $id);
        $check_operations_stmt->execute();
        $operations_count = $check_operations_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($operations_count['count'] > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer cette escale car elle a des opérations associées.'
            ]);
            return;
        }
        
        // Requête pour supprimer un enregistrement
        $query = "DELETE FROM escale WHERE NUM_escale = :id";
        $stmt = $db->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':id', $id);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Escale supprimée avec succès.'
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer l\'escale.'
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