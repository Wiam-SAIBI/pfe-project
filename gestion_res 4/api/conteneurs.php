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
            getOneConteneur($db, $id);
        } else {
            getAllConteneurs($db);
        }
        break;
    
    case 'POST':
        addConteneur($db);
        break;
    
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID conteneur manquant.'
            ]);
            exit;
        }
        updateConteneur($db, $id);
        break;
    
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID conteneur manquant.'
            ]);
            exit;
        }
        deleteConteneur($db, $id);
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
 * Récupérer tous les conteneurs
 */
function getAllConteneurs($db) {
    try {
        // Requête pour récupérer tous les enregistrements avec les informations du navire et de la dernière opération
        $query = "SELECT c.*, n.NOM_navire, o.TYPE_operation, o.DATE_debut as DERNIERE_OPERATION_DATE 
                  FROM conteneure c 
                  LEFT JOIN navire n ON c.ID_navire = n.ID_navire 
                  LEFT JOIN operation o ON c.DERNIERE_OPERATION = o.ID_operation 
                  ORDER BY c.DATE_AJOUT DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            // Tableau pour les données
            $conteneurs_arr = array();
            $conteneurs_arr["records"] = array();
            $conteneurs_arr["count"] = $num;
            
            // Récupérer les résultats
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $conteneur_item = array(
                    "ID_conteneure" => $ID_conteneure,
                    "NOM_conteneure" => $NOM_conteneure,
                    "TYPE_conteneure" => $TYPE_conteneure,
                    "ID_type" => $ID_type,
                    "ID_navire" => $ID_navire ?? null,
                    "NOM_navire" => $NOM_navire ?? 'Aucun navire',
                    "DATE_AJOUT" => $DATE_AJOUT,
                    "DERNIERE_OPERATION" => $DERNIERE_OPERATION ?? null,
                    "TYPE_operation" => $TYPE_operation ?? 'Aucune opération',
                    "DERNIERE_OPERATION_DATE" => $DERNIERE_OPERATION_DATE ?? null
                );
                
                array_push($conteneurs_arr["records"], $conteneur_item);
            }
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode(array_merge(
                ["success" => true, "message" => 'Conteneurs récupérés avec succès'],
                $conteneurs_arr
            ));
            return;
        } else {
            // Aucun conteneur trouvé
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Aucun conteneur trouvé.',
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
 * Récupérer un conteneur par ID
 */
function getOneConteneur($db, $id) {
    try {
        // Requête pour lire un seul enregistrement
        $query = "SELECT c.*, n.NOM_navire, o.TYPE_operation, o.DATE_debut as DERNIERE_OPERATION_DATE 
                  FROM conteneure c 
                  LEFT JOIN navire n ON c.ID_navire = n.ID_navire 
                  LEFT JOIN operation o ON c.DERNIERE_OPERATION = o.ID_operation 
                  WHERE c.ID_conteneure = :id LIMIT 0,1";
        $stmt = $db->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':id', $id);
        
        // Exécuter la requête
        $stmt->execute();
        
        // Récupérer l'enregistrement
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Créer un tableau
            $conteneur_arr = array(
                "ID_conteneure" => $row['ID_conteneure'],
                "NOM_conteneure" => $row['NOM_conteneure'],
                "TYPE_conteneure" => $row['TYPE_conteneure'],
                "ID_type" => $row['ID_type'],
                "ID_navire" => $row['ID_navire'] ?? null,
                "NOM_navire" => $row['NOM_navire'] ?? 'Aucun navire',
                "DATE_AJOUT" => $row['DATE_AJOUT'],
                "DERNIERE_OPERATION" => $row['DERNIERE_OPERATION'] ?? null,
                "TYPE_operation" => $row['TYPE_operation'] ?? 'Aucune opération',
                "DERNIERE_OPERATION_DATE" => $row['DERNIERE_OPERATION_DATE'] ?? null
            );
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Conteneur trouvé',
                'record' => $conteneur_arr
            ]);
            return;
        } else {
            // Conteneur non trouvé
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Conteneur non trouvé.'
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
 * Ajouter un nouveau conteneur
 */
function addConteneur($db) {
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
    if (!isset($data->nom_conteneure) || !isset($data->type_conteneure)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données incomplètes. Nom et type de conteneur sont requis.',
            'received_data' => $data
        ]);
        return;
    }
    
    try {
        // Debug : log des données reçues
        error_log("=== DEBUT DEBUG CONTENEUR ===");
        error_log("Nom reçu: " . $data->nom_conteneure);
        error_log("Type reçu: " . $data->type_conteneure);
        error_log("ID navire reçu: " . ($data->id_navire ?? 'null'));
        error_log("ID type reçu: " . ($data->id_type ?? 'null'));
        
        // Vérifier que le navire existe si fourni
        if (isset($data->id_navire) && !empty($data->id_navire)) {
            $check_navire_query = "SELECT ID_navire, NOM_navire FROM navire WHERE ID_navire = :id_navire";
            $check_navire_stmt = $db->prepare($check_navire_query);
            $check_navire_stmt->bindParam(':id_navire', $data->id_navire);
            $check_navire_stmt->execute();
            $navire_row = $check_navire_stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Navire trouvé: " . print_r($navire_row, true));
            
            if (!$navire_row) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Le navire avec l\'ID "' . $data->id_navire . '" n\'existe pas.',
                    'error_code' => 'NAVIRE_NOT_FOUND',
                    'id_navire_recherche' => $data->id_navire
                ]);
                return;
            }
        }
        
        // Requête pour insérer un nouvel enregistrement
        $query = "INSERT INTO conteneure (NOM_conteneure, TYPE_conteneure, ID_type, ID_navire) 
                  VALUES (:nom_conteneure, :type_conteneure, :id_type, :id_navire)";
        $stmt = $db->prepare($query);
        
        // Nettoyer les données
        $nom_conteneure = htmlspecialchars(strip_tags($data->nom_conteneure));
        $type_conteneure = htmlspecialchars(strip_tags($data->type_conteneure));
        $id_type = isset($data->id_type) && !empty($data->id_type) ? intval($data->id_type) : null;
        $id_navire = isset($data->id_navire) && !empty($data->id_navire) ? htmlspecialchars(strip_tags($data->id_navire)) : null;
        
        // Liaison des valeurs
        $stmt->bindParam(':nom_conteneure', $nom_conteneure);
        $stmt->bindParam(':type_conteneure', $type_conteneure);
        $stmt->bindParam(':id_type', $id_type, PDO::PARAM_INT);
        $stmt->bindParam(':id_navire', $id_navire);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Récupérer l'ID conteneur généré
            $query = "SELECT ID_conteneure FROM conteneure WHERE NOM_conteneure = :nom AND TYPE_conteneure = :type ORDER BY DATE_AJOUT DESC LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nom', $nom_conteneure);
            $stmt->bindParam(':type', $type_conteneure);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $id_conteneur = $row ? $row['ID_conteneure'] : null;
            
            // Réponse - succès
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Conteneur créé avec succès.',
                'id_conteneur' => $id_conteneur
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de créer le conteneur.',
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
 * Mettre à jour un conteneur
 */
function updateConteneur($db, $id) {
    // Récupérer les données JSON envoyées
    $data = json_decode(file_get_contents("php://input"));
    
    try {
        // Vérifier si le conteneur existe
        $check_query = "SELECT COUNT(*) as count FROM conteneure WHERE ID_conteneure = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->execute();
        $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Conteneur non trouvé.'
            ]);
            return;
        }
        
        // Construire la requête de mise à jour dynamiquement
        $query = "UPDATE conteneure SET ";
        $params = [];
        
        if (isset($data->nom_conteneure)) {
            $query .= "NOM_conteneure = :nom_conteneure, ";
            $params[':nom_conteneure'] = htmlspecialchars(strip_tags($data->nom_conteneure));
        }
        
        if (isset($data->type_conteneure)) {
            $query .= "TYPE_conteneure = :type_conteneure, ";
            $params[':type_conteneure'] = htmlspecialchars(strip_tags($data->type_conteneure));
        }
        
        if (isset($data->id_type)) {
            $query .= "ID_type = :id_type, ";
            $params[':id_type'] = !empty($data->id_type) ? intval($data->id_type) : null;
        }
        
        if (isset($data->id_navire)) {
            // Vérifier que le navire existe si fourni
            if (!empty($data->id_navire)) {
                $check_navire_query = "SELECT COUNT(*) as count FROM navire WHERE ID_navire = :id_navire";
                $check_navire_stmt = $db->prepare($check_navire_query);
                $check_navire_stmt->bindParam(':id_navire', $data->id_navire);
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
            }
            
            $query .= "ID_navire = :id_navire, ";
            $params[':id_navire'] = !empty($data->id_navire) ? htmlspecialchars(strip_tags($data->id_navire)) : null;
        }
        
        // Supprimer la dernière virgule et espace
        $query = rtrim($query, ", ");
        
        // Ajouter la condition WHERE
        $query .= " WHERE ID_conteneure = :id";
        $params[':id'] = $id;
        
        // Préparer la requête
        $stmt = $db->prepare($query);
        
        // Liaison des paramètres
        foreach ($params as $key => $value) {
            if ($key === ':id_type') {
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
                'message' => 'Conteneur mis à jour avec succès.'
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de mettre à jour le conteneur.'
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
 * Supprimer un conteneur
 */
function deleteConteneur($db, $id) {
    try {
        // Vérifier si le conteneur existe
        $check_query = "SELECT COUNT(*) as count FROM conteneure WHERE ID_conteneure = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->execute();
        $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Conteneur non trouvé.'
            ]);
            return;
        }
        
        // Vérifier s'il y a des opérations liées via ID_conteneure dans la table operation
        // Note: ID_conteneure est stocké comme TEXT dans operation, il peut contenir plusieurs IDs séparés par des virgules
        $check_operations = "SELECT COUNT(*) as count FROM operation WHERE ID_conteneure LIKE :id";
        $check_operations_stmt = $db->prepare($check_operations);
        $like_pattern = '%' . $id . '%';
        $check_operations_stmt->bindParam(':id', $like_pattern);
        $check_operations_stmt->execute();
        $operations_count = $check_operations_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($operations_count['count'] > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer ce conteneur car il est utilisé dans des opérations.'
            ]);
            return;
        }
        
        // Requête pour supprimer un enregistrement
        $query = "DELETE FROM conteneure WHERE ID_conteneure = :id";
        $stmt = $db->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':id', $id);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Conteneur supprimé avec succès.'
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer le conteneur.'
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