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
$action = isset($_GET['action']) ? $_GET['action'] : null;

switch ($method) {
    case 'GET':
        if ($action === 'stats') {
            getStats($db);
        } elseif ($id) {
            getOnePersonnel($db, $id);
        } else {
            getAllPersonnel($db);
        }
        break;
    
    case 'POST':
        addPersonnel($db);
        break;
    
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID personnel manquant.'
            ]);
            exit;
        }
        updatePersonnel($db, $id);
        break;
    
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID personnel manquant.'
            ]);
            exit;
        }
        deletePersonnel($db, $id);
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
 * Récupérer tous les membres du personnel
 */
function getAllPersonnel($db) {
    try {
        // Requête pour récupérer tous les enregistrements
        $query = "SELECT p.* FROM personnel p ORDER BY p.NOM_personnel ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            // Tableau pour les données
            $personnel_arr = array();
            $personnel_arr["records"] = array();
            $personnel_arr["count"] = $num;
            
            // Récupérer les résultats
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $personnel_item = array(
                    "ID_personnel" => $ID_personnel,
                    "MATRICULE_personnel" => $MATRICULE_personnel,
                    "NOM_personnel" => $NOM_personnel,
                    "PRENOM_personnel" => $PRENOM_personnel,
                    "FONCTION_personnel" => $FONCTION_personnel,
                    "CONTACT_personnel" => $CONTACT_personnel ?? null
                );
                
                array_push($personnel_arr["records"], $personnel_item);
            }
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode(array_merge(
                ["success" => true, "message" => 'Personnel récupéré avec succès'],
                $personnel_arr
            ));
            return;
        } else {
            // Aucun personnel trouvé
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Aucun personnel trouvé.',
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
 * Récupérer un membre du personnel par ID
 */
function getOnePersonnel($db, $id) {
    try {
        // Requête pour lire un seul enregistrement
        $query = "SELECT p.* FROM personnel p WHERE p.ID_personnel = :id OR p.MATRICULE_personnel = :matricule LIMIT 0,1";
        $stmt = $db->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':matricule', $id);
        
        // Exécuter la requête
        $stmt->execute();
        
        // Récupérer l'enregistrement
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Créer un tableau
            $personnel_arr = array(
                "ID_personnel" => $row['ID_personnel'],
                "MATRICULE_personnel" => $row['MATRICULE_personnel'],
                "NOM_personnel" => $row['NOM_personnel'],
                "PRENOM_personnel" => $row['PRENOM_personnel'],
                "FONCTION_personnel" => $row['FONCTION_personnel'],
                "CONTACT_personnel" => $row['CONTACT_personnel'] ?? null
            );
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Personnel trouvé',
                'record' => $personnel_arr
            ]);
            return;
        } else {
            // Personnel non trouvé
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Personnel non trouvé.'
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
 * Ajouter un nouveau membre du personnel
 */
function addPersonnel($db) {
    // Récupérer les données JSON envoyées
    $data = json_decode(file_get_contents("php://input"));
    
    // Vérifier si les données nécessaires sont fournies
    if (!isset($data->nom) || !isset($data->prenom) || !isset($data->fonction)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données incomplètes. Nom, prénom et fonction sont requis.'
        ]);
        return;
    }
    
    try {
        // Requête pour insérer un nouvel enregistrement
        $query = "INSERT INTO personnel (NOM_personnel, PRENOM_personnel, FONCTION_personnel, CONTACT_personnel) VALUES (:nom, :prenom, :fonction, :contact)";
        $stmt = $db->prepare($query);
        
        // Nettoyer les données
        $nom = htmlspecialchars(strip_tags($data->nom));
        $prenom = htmlspecialchars(strip_tags($data->prenom));
        $fonction = htmlspecialchars(strip_tags($data->fonction));
        $contact = isset($data->contact) ? htmlspecialchars(strip_tags($data->contact)) : null;
        
        // Liaison des valeurs
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':prenom', $prenom);
        $stmt->bindParam(':fonction', $fonction);
        $stmt->bindParam(':contact', $contact);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Récupérer l'ID généré
            $id = $db->lastInsertId();
            
            // Récupérer le matricule généré
            $query = "SELECT MATRICULE_personnel FROM personnel WHERE ID_personnel = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $matricule = $row ? $row['MATRICULE_personnel'] : null;
            
            // Réponse - succès
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Personnel créé avec succès.',
                'id' => $id,
                'matricule' => $matricule
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de créer le personnel.'
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
 * Mettre à jour un membre du personnel
 */
function updatePersonnel($db, $id) {
    // Récupérer les données JSON envoyées
    $data = json_decode(file_get_contents("php://input"));
    
    try {
        // Vérifier si le personnel existe
        $check_query = "SELECT COUNT(*) as count FROM personnel WHERE ID_personnel = :id OR MATRICULE_personnel = :matricule";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->bindParam(':matricule', $id);
        $check_stmt->execute();
        $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Personnel non trouvé.'
            ]);
            return;
        }
        
        // Construire la requête de mise à jour dynamiquement
        $query = "UPDATE personnel SET ";
        $params = [];
        
        if (isset($data->nom)) {
            $query .= "NOM_personnel = :nom, ";
            $params[':nom'] = htmlspecialchars(strip_tags($data->nom));
        }
        
        if (isset($data->prenom)) {
            $query .= "PRENOM_personnel = :prenom, ";
            $params[':prenom'] = htmlspecialchars(strip_tags($data->prenom));
        }
        
        if (isset($data->fonction)) {
            $query .= "FONCTION_personnel = :fonction, ";
            $params[':fonction'] = htmlspecialchars(strip_tags($data->fonction));
        }
        
        if (isset($data->contact)) {
            $query .= "CONTACT_personnel = :contact, ";
            $params[':contact'] = htmlspecialchars(strip_tags($data->contact));
        }
        
        // Supprimer la dernière virgule et espace
        $query = rtrim($query, ", ");
        
        // Ajouter la condition WHERE
        $query .= " WHERE ID_personnel = :id OR MATRICULE_personnel = :matricule";
        $params[':id'] = $id;
        $params[':matricule'] = $id;
        
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
                'message' => 'Personnel mis à jour avec succès.'
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de mettre à jour le personnel.'
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
 * Supprimer un membre du personnel
 */
function deletePersonnel($db, $id) {
    try {
        // Vérifier si le personnel existe
        $check_query = "SELECT COUNT(*) as count FROM personnel WHERE ID_personnel = :id OR MATRICULE_personnel = :matricule";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->bindParam(':matricule', $id);
        $check_stmt->execute();
        $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Personnel non trouvé.'
            ]);
            return;
        }
        
        // Requête pour supprimer un enregistrement
        $query = "DELETE FROM personnel WHERE ID_personnel = :id OR MATRICULE_personnel = :matricule";
        $stmt = $db->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':matricule', $id);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Personnel supprimé avec succès.'
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer le personnel.'
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

/**
 * Obtenir des statistiques sur le personnel
 */
function getStats($db) {
    try {
        // Requête pour obtenir le nombre total d'employés
        $total_query = "SELECT COUNT(*) as total FROM personnel";
        $total_stmt = $db->prepare($total_query);
        $total_stmt->execute();
        $total_row = $total_stmt->fetch(PDO::FETCH_ASSOC);
        $total = $total_row['total'];
        
        // Requête pour obtenir les statistiques par fonction
        $function_query = "SELECT FONCTION_personnel, COUNT(*) as count FROM personnel GROUP BY FONCTION_personnel ORDER BY count DESC";
        $function_stmt = $db->prepare($function_query);
        $function_stmt->execute();
        
        $by_function = [];
        while ($row = $function_stmt->fetch(PDO::FETCH_ASSOC)) {
            $by_function[] = $row;
        }
        
        // Requête pour obtenir le dernier membre du personnel ajouté
        $last_added_query = "SELECT * FROM personnel ORDER BY ID_personnel DESC LIMIT 1";
        $last_added_stmt = $db->prepare($last_added_query);
        $last_added_stmt->execute();
        $last_added = $last_added_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Réponse - succès
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Statistiques récupérées avec succès',
            'total' => $total,
            'by_function' => $by_function,
            'last_added' => $last_added
        ]);
        return;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
        ]);
    }
}
?>