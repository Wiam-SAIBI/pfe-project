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
            getOneOperation($db, $id);
        } else {
            getAllOperations($db);
        }
        break;
    
    case 'POST':
        addOperation($db);
        break;
    
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID opération manquant.'
            ]);
            exit;
        }
        updateOperation($db, $id);
        break;
    
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID opération manquant.'
            ]);
            exit;
        }
        deleteOperation($db, $id);
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
 * Récupérer toutes les opérations
 */
function getAllOperations($db) {
    try {
        $query = "SELECT o.*, 
                  e.NOM_navire, 
                  s.NOM_shift,
                  eq.NOM_equipe,
                  COUNT(a.ID_arret) as NOMBRE_ARRETS
                  FROM operation o 
                  LEFT JOIN escale es ON o.ID_escale = es.NUM_escale 
                  LEFT JOIN escale e ON es.NUM_escale = e.NUM_escale
                  LEFT JOIN shift s ON o.ID_shift = s.ID_shift 
                  LEFT JOIN equipe eq ON o.ID_equipe = eq.ID_equipe
                  LEFT JOIN arret a ON o.ID_operation = a.ID_operation
                  GROUP BY o.ID_operation, o.TYPE_operation, o.ID_shift, o.ID_escale, o.ID_conteneure, o.ID_engin, o.ID_equipe, o.DATE_debut, o.DATE_fin, o.status
                  ORDER BY o.DATE_debut DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $operations_arr = array();
        $operations_arr["records"] = array();
        $operations_arr["count"] = $stmt->rowCount();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $operation_item = array(
                "ID_operation" => $row['ID_operation'],
                "TYPE_operation" => $row['TYPE_operation'],
                "ID_shift" => $row['ID_shift'],
                "ID_escale" => $row['ID_escale'],
                "ID_conteneure" => $row['ID_conteneure'],
                "ID_engin" => $row['ID_engin'],
                "ID_equipe" => $row['ID_equipe'],
                "DATE_debut" => $row['DATE_debut'],
                "DATE_fin" => $row['DATE_fin'],
                "status" => $row['status'],
                "NOM_navire" => $row['NOM_navire'] ?? 'Navire inconnu',
                "NOM_shift" => $row['NOM_shift'] ?? 'Shift inconnu',
                "NOM_equipe" => $row['NOM_equipe'] ?? 'Équipe inconnue',
                "NOMBRE_ARRETS" => $row['NOMBRE_ARRETS']
            );
            array_push($operations_arr["records"], $operation_item);
        }
        
        http_response_code(200);
        echo json_encode(array_merge(
            ["success" => true, "message" => 'Opérations récupérées avec succès'],
            $operations_arr
        ));
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
        ]);
    }
}

/**
 * Récupérer une opération par ID
 */
function getOneOperation($db, $id) {
    try {
        $query = "SELECT o.*, 
                  e.NOM_navire, 
                  s.NOM_shift,
                  eq.NOM_equipe,
                  COUNT(a.ID_arret) as NOMBRE_ARRETS
                  FROM operation o 
                  LEFT JOIN escale es ON o.ID_escale = es.NUM_escale 
                  LEFT JOIN escale e ON es.NUM_escale = e.NUM_escale
                  LEFT JOIN shift s ON o.ID_shift = s.ID_shift 
                  LEFT JOIN equipe eq ON o.ID_equipe = eq.ID_equipe
                  LEFT JOIN arret a ON o.ID_operation = a.ID_operation
                  WHERE o.ID_operation = :id 
                  GROUP BY o.ID_operation, o.TYPE_operation, o.ID_shift, o.ID_escale, o.ID_conteneure, o.ID_engin, o.ID_equipe, o.DATE_debut, o.DATE_fin, o.status
                  LIMIT 0,1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $operation_arr = array(
                "ID_operation" => $row['ID_operation'],
                "TYPE_operation" => $row['TYPE_operation'],
                "ID_shift" => $row['ID_shift'],
                "ID_escale" => $row['ID_escale'],
                "ID_conteneure" => $row['ID_conteneure'],
                "ID_engin" => $row['ID_engin'],
                "ID_equipe" => $row['ID_equipe'],
                "DATE_debut" => $row['DATE_debut'],
                "DATE_fin" => $row['DATE_fin'],
                "status" => $row['status'],
                "NOM_navire" => $row['NOM_navire'] ?? 'Navire inconnu',
                "NOM_shift" => $row['NOM_shift'] ?? 'Shift inconnu',
                "NOM_equipe" => $row['NOM_equipe'] ?? 'Équipe inconnue',
                "NOMBRE_ARRETS" => $row['NOMBRE_ARRETS']
            );
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Opération trouvée',
                'record' => $operation_arr
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Opération non trouvée.'
            ]);
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
 * Ajouter une nouvelle opération
 */
function addOperation($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    if ($data === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données JSON invalides.'
        ]);
        return;
    }
    
    if (!isset($data->type_operation) || !isset($data->id_escale) || !isset($data->id_equipe)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données incomplètes. Type, escale et équipe sont requis.'
        ]);
        return;
    }
    
    try {
        // Vérifier que l'escale existe
        $check_escale_query = "SELECT NUM_escale FROM escale WHERE NUM_escale = :id_escale";
        $check_escale_stmt = $db->prepare($check_escale_query);
        $check_escale_stmt->bindParam(':id_escale', $data->id_escale);
        $check_escale_stmt->execute();
        
        if ($check_escale_stmt->rowCount() == 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'L\'escale spécifiée n\'existe pas.'
            ]);
            return;
        }
        
        // Vérifier que l'équipe existe
        $check_equipe_query = "SELECT ID_equipe FROM equipe WHERE ID_equipe = :id_equipe";
        $check_equipe_stmt = $db->prepare($check_equipe_query);
        $check_equipe_stmt->bindParam(':id_equipe', $data->id_equipe);
        $check_equipe_stmt->execute();
        
        if ($check_equipe_stmt->rowCount() == 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'L\'équipe spécifiée n\'existe pas.'
            ]);
            return;
        }
        
        // Insérer l'opération
        $query = "INSERT INTO operation (TYPE_operation, ID_shift, ID_escale, ID_conteneure, ID_engin, ID_equipe, DATE_debut, DATE_fin, status) 
                  VALUES (:type_operation, :id_shift, :id_escale, :id_conteneure, :id_engin, :id_equipe, :date_debut, :date_fin, :status)";
        $stmt = $db->prepare($query);
        
        $type_operation = htmlspecialchars(strip_tags($data->type_operation));
        $id_shift = isset($data->id_shift) && !empty($data->id_shift) ? htmlspecialchars(strip_tags($data->id_shift)) : null;
        $id_escale = htmlspecialchars(strip_tags($data->id_escale));
        $id_conteneure = isset($data->id_conteneure) && !empty($data->id_conteneure) ? $data->id_conteneure : null;
        $id_engin = isset($data->id_engin) && !empty($data->id_engin) ? $data->id_engin : null;
        $id_equipe = htmlspecialchars(strip_tags($data->id_equipe));
        $date_debut = isset($data->date_debut) && !empty($data->date_debut) ? $data->date_debut : null;
        $date_fin = isset($data->date_fin) && !empty($data->date_fin) ? $data->date_fin : null;
        $status = isset($data->status) && !empty($data->status) ? htmlspecialchars(strip_tags($data->status)) : 'En cours';
        
        $stmt->bindParam(':type_operation', $type_operation);
        $stmt->bindParam(':id_shift', $id_shift);
        $stmt->bindParam(':id_escale', $id_escale);
        $stmt->bindParam(':id_conteneure', $id_conteneure);
        $stmt->bindParam(':id_engin', $id_engin);
        $stmt->bindParam(':id_equipe', $id_equipe);
        $stmt->bindParam(':date_debut', $date_debut);
        $stmt->bindParam(':date_fin', $date_fin);
        $stmt->bindParam(':status', $status);
        
        if ($stmt->execute()) {
            // Récupérer l'ID généré
            $query = "SELECT ID_operation FROM operation WHERE TYPE_operation = :type AND ID_escale = :escale ORDER BY ID_operation DESC LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':type', $type_operation);
            $stmt->bindParam(':escale', $id_escale);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Opération créée avec succès.',
                'id_operation' => $row ? $row['ID_operation'] : null
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de créer l\'opération.'
            ]);
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
 * Mettre à jour une opération
 */
function updateOperation($db, $id) {
    $data = json_decode(file_get_contents("php://input"));
    
    try {
        // Vérifier si l'opération existe
        $check_query = "SELECT COUNT(*) as count FROM operation WHERE ID_operation = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->execute();
        $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Opération non trouvée.'
            ]);
            return;
        }
        
        $query = "UPDATE operation SET ";
        $params = [];
        
        if (isset($data->type_operation)) {
            $query .= "TYPE_operation = :type_operation, ";
            $params[':type_operation'] = htmlspecialchars(strip_tags($data->type_operation));
        }
        
        if (isset($data->id_shift)) {
            $query .= "ID_shift = :id_shift, ";
            $params[':id_shift'] = !empty($data->id_shift) ? htmlspecialchars(strip_tags($data->id_shift)) : null;
        }
        
        if (isset($data->id_escale)) {
            $query .= "ID_escale = :id_escale, ";
            $params[':id_escale'] = htmlspecialchars(strip_tags($data->id_escale));
        }
        
        if (isset($data->id_conteneure)) {
            $query .= "ID_conteneure = :id_conteneure, ";
            $params[':id_conteneure'] = !empty($data->id_conteneure) ? $data->id_conteneure : null;
        }
        
        if (isset($data->id_engin)) {
            $query .= "ID_engin = :id_engin, ";
            $params[':id_engin'] = !empty($data->id_engin) ? $data->id_engin : null;
        }
        
        if (isset($data->id_equipe)) {
            $query .= "ID_equipe = :id_equipe, ";
            $params[':id_equipe'] = htmlspecialchars(strip_tags($data->id_equipe));
        }
        
        if (isset($data->date_debut)) {
            $query .= "DATE_debut = :date_debut, ";
            $params[':date_debut'] = !empty($data->date_debut) ? $data->date_debut : null;
        }
        
        if (isset($data->date_fin)) {
            $query .= "DATE_fin = :date_fin, ";
            $params[':date_fin'] = !empty($data->date_fin) ? $data->date_fin : null;
        }
        
        if (isset($data->status)) {
            $query .= "status = :status, ";
            $params[':status'] = htmlspecialchars(strip_tags($data->status));
        }
        
        $query = rtrim($query, ", ");
        $query .= " WHERE ID_operation = :id";
        $params[':id'] = $id;
        
        $stmt = $db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Opération mise à jour avec succès.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de mettre à jour l\'opération.'
            ]);
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
 * Supprimer une opération
 */
function deleteOperation($db, $id) {
    try {
        // Vérifier si l'opération existe
        $check_query = "SELECT COUNT(*) as count FROM operation WHERE ID_operation = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->execute();
        $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Opération non trouvée.'
            ]);
            return;
        }
        
        // Vérifier s'il y a des arrêts liés
        $check_arrets = "SELECT COUNT(*) as count FROM arret WHERE ID_operation = :id";
        $check_arrets_stmt = $db->prepare($check_arrets);
        $check_arrets_stmt->bindParam(':id', $id);
        $check_arrets_stmt->execute();
        $arrets_count = $check_arrets_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($arrets_count['count'] > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer cette opération car elle a des arrêts associés.'
            ]);
            return;
        }
        
        $query = "DELETE FROM operation WHERE ID_operation = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Opération supprimée avec succès.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer l\'opération.'
            ]);
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