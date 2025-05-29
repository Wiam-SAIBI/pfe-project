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
$operations = isset($_GET['operations']) && $_GET['operations'] == 1;

switch ($method) {
    case 'GET':
        if ($id && $operations) {
            getOperationsEngin($db, $id);
        } elseif ($id) {
            getOneEngin($db, $id);
        } else {
            getAllEngins($db);
        }
        break;
    
    case 'POST':
        addEngin($db);
        break;
    
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID engin manquant.'
            ]);
            exit;
        }
        updateEngin($db, $id);
        break;
    
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID engin manquant.'
            ]);
            exit;
        }
        deleteEngin($db, $id);
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
 * Récupérer tous les engins
 */
function getAllEngins($db) {
    try {
        // Requête pour récupérer tous les engins
        $query = "SELECT * FROM engin ORDER BY NOM_engin ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            // Tableau pour les données
            $engins_arr = array();
            $engins_arr["records"] = array();
            $engins_arr["count"] = $num;
            
            // Récupérer les résultats
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                // Vérifier si l'engin est actuellement utilisé dans une opération en cours
                $query_status = "
                    SELECT COUNT(*) as count 
                    FROM operation 
                    WHERE ID_engin LIKE :id
                    AND status = 'En cours'
                ";
                $stmt_status = $db->prepare($query_status);
                $stmt_status->bindValue(':id', '%' . $ID_engin . '%');
                $stmt_status->execute();
                $status = ($stmt_status->fetch(PDO::FETCH_ASSOC)['count'] > 0) ? 'En service' : 'Disponible';
                
                $engin_item = array(
                    "ID_engin" => $ID_engin,
                    "NOM_engin" => $NOM_engin,
                    "TYPE_engin" => $TYPE_engin,
                    "status" => $status
                );
                
                array_push($engins_arr["records"], $engin_item);
            }
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode(array_merge(
                ["success" => true, "message" => 'Engins récupérés avec succès'],
                $engins_arr
            ));
            return;
        } else {
            // Aucun engin trouvé
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Aucun engin trouvé.',
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
 * Récupérer un engin par ID
 */
function getOneEngin($db, $id) {
    try {
        // Requête pour lire un seul enregistrement
        $query = "
            SELECT * FROM engin 
            WHERE ID_engin = :id
            LIMIT 0,1
        ";
        
        $stmt = $db->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':id', $id);
        
        // Exécuter la requête
        $stmt->execute();
        
        // Récupérer l'enregistrement
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Vérifier si l'engin est actuellement utilisé dans une opération en cours
            $query_status = "
                SELECT COUNT(*) as count 
                FROM operation 
                WHERE ID_engin LIKE :id
                AND status = 'En cours'
            ";
            $stmt_status = $db->prepare($query_status);
            $stmt_status->bindValue(':id', '%' . $id . '%');
            $stmt_status->execute();
            $status = ($stmt_status->fetch(PDO::FETCH_ASSOC)['count'] > 0) ? 'En service' : 'Disponible';
            
            // Créer un tableau
            $engin_arr = array(
                "ID_engin" => $row['ID_engin'],
                "NOM_engin" => $row['NOM_engin'],
                "TYPE_engin" => $row['TYPE_engin'],
                "status" => $status
            );
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Engin trouvé',
                'record' => $engin_arr
            ]);
            return;
        } else {
            // Engin non trouvé
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Engin non trouvé.'
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
 * Récupérer les opérations utilisant un engin
 */
function getOperationsEngin($db, $id) {
    try {
        // Vérifier si l'engin existe
        $checkQuery = "SELECT COUNT(*) as count FROM engin WHERE ID_engin = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Engin non trouvé.'
            ]);
            return;
        }
        
        // Requête pour récupérer les opérations utilisant l'engin
        $query = "
            SELECT o.* 
            FROM operation o
            WHERE o.ID_engin LIKE :id
            ORDER BY o.DATE_debut DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindValue(':id', '%' . $id . '%');
        $stmt->execute();
        
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            // Tableau pour les données
            $operations_arr = array();
            $operations_arr["records"] = array();
            $operations_arr["count"] = $num;
            
            // Récupérer les résultats
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $operation_item = array(
                    "ID_operation" => $ID_operation,
                    "TYPE_operation" => $TYPE_operation,
                    "ID_escale" => $ID_escale,
                    "ID_equipe" => $ID_equipe,
                    "DATE_debut" => $DATE_debut,
                    "DATE_fin" => $DATE_fin,
                    "status" => $status
                );
                
                array_push($operations_arr["records"], $operation_item);
            }
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode(array_merge(
                ["success" => true, "message" => 'Opérations de l\'engin récupérées avec succès'],
                $operations_arr
            ));
            return;
        } else {
            // Aucune opération trouvée
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Cet engin n\'est utilisé dans aucune opération.',
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
 * Ajouter un nouvel engin
 */
function addEngin($db) {
    // Récupérer les données JSON envoyées
    $data = json_decode(file_get_contents("php://input"));
    
    // Vérifier si les données nécessaires sont fournies
    if (!isset($data->nom) || empty(trim($data->nom)) || 
        !isset($data->type) || empty(trim($data->type))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données incomplètes. Nom et type sont requis.'
        ]);
        return;
    }
    
    try {
        // Vérifier si un engin avec ce nom existe déjà
        $checkQuery = "SELECT COUNT(*) as count FROM engin WHERE NOM_engin = :nom";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':nom', $data->nom);
        $checkStmt->execute();
        
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Un engin avec ce nom existe déjà.'
            ]);
            return;
        }
        
        // Requête pour insérer un nouvel engin
        $query = "INSERT INTO engin (NOM_engin, TYPE_engin) VALUES (:nom, :type)";
        $stmt = $db->prepare($query);
        
        // Nettoyer les données
        $nom = htmlspecialchars(strip_tags($data->nom));
        $type = htmlspecialchars(strip_tags($data->type));
        
        // Liaison des valeurs
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':type', $type);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Récupérer l'ID généré
            $query = "SELECT ID_engin FROM engin WHERE NOM_engin = :nom LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nom', $nom);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $id = $row ? $row['ID_engin'] : null;
            
            // Réponse - succès
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Engin créé avec succès.',
                'id' => $id
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de créer l\'engin.'
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
 * Mettre à jour un engin
 */
function updateEngin($db, $id) {
    // Récupérer les données JSON envoyées
    $data = json_decode(file_get_contents("php://input"));
    
    // Vérifier si les données nécessaires sont fournies
    if (!isset($data->nom) || empty(trim($data->nom)) || 
        !isset($data->type) || empty(trim($data->type))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données incomplètes. Nom et type sont requis.'
        ]);
        return;
    }
    
    try {
        // Vérifier si l'engin existe
        $checkQuery = "SELECT COUNT(*) as count FROM engin WHERE ID_engin = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Engin non trouvé.'
            ]);
            return;
        }
        
        // Vérifier si un autre engin avec ce nom existe déjà
        $checkQuery = "SELECT COUNT(*) as count FROM engin WHERE NOM_engin = :nom AND ID_engin != :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':nom', $data->nom);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Un autre engin avec ce nom existe déjà.'
            ]);
            return;
        }
        
        // Requête pour mettre à jour l'engin
        $query = "UPDATE engin SET NOM_engin = :nom, TYPE_engin = :type WHERE ID_engin = :id";
        $stmt = $db->prepare($query);
        
        // Nettoyer les données
        $nom = htmlspecialchars(strip_tags($data->nom));
        $type = htmlspecialchars(strip_tags($data->type));
        
        // Liaison des valeurs
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':id', $id);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Engin mis à jour avec succès.'
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de mettre à jour l\'engin.'
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
 * Supprimer un engin
 */
function deleteEngin($db, $id) {
    try {
        // Vérifier si l'engin existe
        $checkQuery = "SELECT COUNT(*) as count FROM engin WHERE ID_engin = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Engin non trouvé.'
            ]);
            return;
        }
        
        // Vérifier si l'engin est utilisé dans des opérations
        $checkQuery = "SELECT COUNT(*) as count FROM operation WHERE ID_engin LIKE :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindValue(':id', '%' . $id . '%');
        $checkStmt->execute();
        
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer cet engin car il est utilisé dans des opérations.'
            ]);
            return;
        }
        
        // Requête pour supprimer l'engin
        $query = "DELETE FROM engin WHERE ID_engin = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Engin supprimé avec succès.'
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer l\'engin.'
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