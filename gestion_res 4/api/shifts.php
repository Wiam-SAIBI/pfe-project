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
            getOneShift($db, $id);
        } else {
            getAllShifts($db);
        }
        break;
    
    case 'POST':
        addShift($db);
        break;
    
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID shift manquant.'
            ]);
            exit;
        }
        updateShift($db, $id);
        break;
    
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID shift manquant.'
            ]);
            exit;
        }
        deleteShift($db, $id);
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
 * Récupérer tous les shifts
 */
function getAllShifts($db) {
    try {
        $query = "SELECT s.*, COUNT(o.ID_operation) as NOMBRE_OPERATIONS
                  FROM shift s 
                  LEFT JOIN operation o ON s.ID_shift = o.ID_shift 
                  GROUP BY s.ID_shift, s.NOM_shift, s.HEURE_debut, s.HEURE_fin
                  ORDER BY s.HEURE_debut ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $shifts_arr = array();
        $shifts_arr["records"] = array();
        $shifts_arr["count"] = $stmt->rowCount();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $shift_item = array(
                "ID_shift" => $row['ID_shift'],
                "NOM_shift" => $row['NOM_shift'],
                "HEURE_debut" => $row['HEURE_debut'],
                "HEURE_fin" => $row['HEURE_fin'],
                "NOMBRE_OPERATIONS" => $row['NOMBRE_OPERATIONS']
            );
            array_push($shifts_arr["records"], $shift_item);
        }
        
        http_response_code(200);
        echo json_encode(array_merge(
            ["success" => true, "message" => 'Shifts récupérés avec succès'],
            $shifts_arr
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
 * Récupérer un shift par ID
 */
function getOneShift($db, $id) {
    try {
        $query = "SELECT s.*, COUNT(o.ID_operation) as NOMBRE_OPERATIONS
                  FROM shift s 
                  LEFT JOIN operation o ON s.ID_shift = o.ID_shift 
                  WHERE s.ID_shift = :id 
                  GROUP BY s.ID_shift, s.NOM_shift, s.HEURE_debut, s.HEURE_fin
                  LIMIT 0,1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $shift_arr = array(
                "ID_shift" => $row['ID_shift'],
                "NOM_shift" => $row['NOM_shift'],
                "HEURE_debut" => $row['HEURE_debut'],
                "HEURE_fin" => $row['HEURE_fin'],
                "NOMBRE_OPERATIONS" => $row['NOMBRE_OPERATIONS']
            );
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Shift trouvé',
                'record' => $shift_arr
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Shift non trouvé.'
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
 * Ajouter un nouveau shift
 */
function addShift($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    if ($data === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données JSON invalides.'
        ]);
        return;
    }
    
    if (!isset($data->nom_shift) || !isset($data->heure_debut) || !isset($data->heure_fin)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données incomplètes. Nom, heure de début et heure de fin sont requis.'
        ]);
        return;
    }
    
    try {
        // Vérifier que le nom n'existe pas déjà
        $check_nom_query = "SELECT COUNT(*) as count FROM shift WHERE NOM_shift = :nom_shift";
        $check_nom_stmt = $db->prepare($check_nom_query);
        $check_nom_stmt->bindParam(':nom_shift', $data->nom_shift);
        $check_nom_stmt->execute();
        $nom_row = $check_nom_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($nom_row['count'] > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Un shift avec ce nom existe déjà.'
            ]);
            return;
        }
        
        // Valider et nettoyer les heures
        $heure_debut = trim($data->heure_debut);
        $heure_fin = trim($data->heure_fin);
        
        // Accepter différents formats et les normaliser
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $heure_debut, $matches_debut)) {
            $heure_debut = sprintf('%02d:%02d', $matches_debut[1], $matches_debut[2]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Format d\'heure de début invalide. Utilisez le format HH:MM. Reçu: ' . $heure_debut
            ]);
            return;
        }
        
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $heure_fin, $matches_fin)) {
            $heure_fin = sprintf('%02d:%02d', $matches_fin[1], $matches_fin[2]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Format d\'heure de fin invalide. Utilisez le format HH:MM. Reçu: ' . $heure_fin
            ]);
            return;
        }
        
        // Valider les valeurs des heures
        $debut_parts = explode(':', $heure_debut);
        $fin_parts = explode(':', $heure_fin);
        
        if (intval($debut_parts[0]) > 23 || intval($debut_parts[1]) > 59) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Heure de début invalide. Les heures doivent être entre 00:00 et 23:59.'
            ]);
            return;
        }
        
        if (intval($fin_parts[0]) > 23 || intval($fin_parts[1]) > 59) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Heure de fin invalide. Les heures doivent être entre 00:00 et 23:59.'
            ]);
            return;
        }
        
        // Insérer le shift
        $query = "INSERT INTO shift (NOM_shift, HEURE_debut, HEURE_fin) VALUES (:nom_shift, :heure_debut, :heure_fin)";
        $stmt = $db->prepare($query);
        
        $nom_shift = htmlspecialchars(strip_tags($data->nom_shift));
        $heure_debut_sql = $heure_debut . ':00'; // Ajouter les secondes
        $heure_fin_sql = $heure_fin . ':00';     // Ajouter les secondes
        
        $stmt->bindParam(':nom_shift', $nom_shift);
        $stmt->bindParam(':heure_debut', $heure_debut_sql);
        $stmt->bindParam(':heure_fin', $heure_fin_sql);
        
        if ($stmt->execute()) {
            // Récupérer l'ID généré
            $query = "SELECT ID_shift FROM shift WHERE NOM_shift = :nom_shift ORDER BY ID_shift DESC LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nom_shift', $nom_shift);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Shift créé avec succès.',
                'id_shift' => $row ? $row['ID_shift'] : null
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de créer le shift.'
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
 * Mettre à jour un shift
 */
function updateShift($db, $id) {
    $data = json_decode(file_get_contents("php://input"));
    
    try {
        // Vérifier si le shift existe
        $check_query = "SELECT COUNT(*) as count FROM shift WHERE ID_shift = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->execute();
        $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Shift non trouvé.'
            ]);
            return;
        }
        
        $query = "UPDATE shift SET ";
        $params = [];
        
        if (isset($data->nom_shift)) {
            $query .= "NOM_shift = :nom_shift, ";
            $params[':nom_shift'] = htmlspecialchars(strip_tags($data->nom_shift));
        }
        
        if (isset($data->heure_debut)) {
            // Valider et normaliser l'heure de début
            $heure_debut = trim($data->heure_debut);
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $heure_debut, $matches)) {
                $heure_debut = sprintf('%02d:%02d', $matches[1], $matches[2]);
                $debut_parts = explode(':', $heure_debut);
                if (intval($debut_parts[0]) > 23 || intval($debut_parts[1]) > 59) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Heure de début invalide.'
                    ]);
                    return;
                }
                $query .= "HEURE_debut = :heure_debut, ";
                $params[':heure_debut'] = $heure_debut . ':00';
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Format d\'heure de début invalide.'
                ]);
                return;
            }
        }
        
        if (isset($data->heure_fin)) {
            // Valider et normaliser l'heure de fin
            $heure_fin = trim($data->heure_fin);
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $heure_fin, $matches)) {
                $heure_fin = sprintf('%02d:%02d', $matches[1], $matches[2]);
                $fin_parts = explode(':', $heure_fin);
                if (intval($fin_parts[0]) > 23 || intval($fin_parts[1]) > 59) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Heure de fin invalide.'
                    ]);
                    return;
                }
                $query .= "HEURE_fin = :heure_fin, ";
                $params[':heure_fin'] = $heure_fin . ':00';
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Format d\'heure de fin invalide.'
                ]);
                return;
            }
        }
        
        $query = rtrim($query, ", ");
        $query .= " WHERE ID_shift = :id";
        $params[':id'] = $id;
        
        $stmt = $db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Shift mis à jour avec succès.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de mettre à jour le shift.'
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
 * Supprimer un shift
 */
function deleteShift($db, $id) {
    try {
        // Vérifier si le shift existe
        $check_query = "SELECT COUNT(*) as count FROM shift WHERE ID_shift = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->execute();
        $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Shift non trouvé.'
            ]);
            return;
        }
        
        // Vérifier s'il y a des opérations liées
        $check_operations = "SELECT COUNT(*) as count FROM operation WHERE ID_shift = :id";
        $check_operations_stmt = $db->prepare($check_operations);
        $check_operations_stmt->bindParam(':id', $id);
        $check_operations_stmt->execute();
        $operations_count = $check_operations_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($operations_count['count'] > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer ce shift car il a des opérations associées.'
            ]);
            return;
        }
        
        $query = "DELETE FROM shift WHERE ID_shift = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Shift supprimé avec succès.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer le shift.'
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