<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config/database.php';

class ArretsByOperationAPI {
    private $conn;
    private $table_name = "arret";
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        if (!$this->conn) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
            exit();
        }
    }
    
    /**
     * Récupérer tous les arrêts d'une opération avec statistiques
     */
    public function getArretsByOperation($id_operation) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                      WHERE ID_operation = :id_operation 
                      ORDER BY DATE_DEBUT_arret DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id_operation', $id_operation);
            $stmt->execute();
            
            $arrets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculer le temps total d'arrêt
            $temps_total = 0;
            foreach ($arrets as $arret) {
                $temps_total += intval($arret['DURE_arret']);
            }
            
            return [
                'success' => true, 
                'liste' => $arrets, 
                'temps_total' => $temps_total,
                'count' => count($arrets)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Ajouter un nouvel arrêt à une opération
     */
    public function addArret($data) {
        try {
            // Validation des données requises
            if (empty($data['ID_operation']) || empty($data['MOTIF_arret']) || empty($data['DATE_DEBUT_arret'])) {
                return ['success' => false, 'message' => 'Données obligatoires manquantes'];
            }
            
            // Calculer automatiquement la durée si les dates sont fournies
            if (!empty($data['DATE_DEBUT_arret']) && !empty($data['DATE_FIN_arret'])) {
                $debut = new DateTime($data['DATE_DEBUT_arret']);
                $fin = new DateTime($data['DATE_FIN_arret']);
                $diff = $fin->diff($debut);
                $duree_minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
                $data['DURE_arret'] = $duree_minutes;
            } else {
                $data['DURE_arret'] = $data['DURE_arret'] ?? 0;
            }
            
            // Récupérer l'escale de l'opération
            $escale_query = "SELECT ID_escale FROM operation WHERE ID_operation = :id_operation";
            $escale_stmt = $this->conn->prepare($escale_query);
            $escale_stmt->bindParam(':id_operation', $data['ID_operation']);
            $escale_stmt->execute();
            $operation = $escale_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$operation) {
                return ['success' => false, 'message' => 'Opération non trouvée'];
            }
            
            $query = "INSERT INTO " . $this->table_name . " 
                      (ID_operation, NUM_escale, MOTIF_arret, DURE_arret, DATE_DEBUT_arret, DATE_FIN_arret) 
                      VALUES (:id_operation, :num_escale, :motif, :duree, :debut, :fin)";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':id_operation', $data['ID_operation']);
            $stmt->bindParam(':num_escale', $operation['ID_escale']);
            $stmt->bindParam(':motif', $data['MOTIF_arret']);
            $stmt->bindParam(':duree', $data['DURE_arret']);
            $stmt->bindParam(':debut', $data['DATE_DEBUT_arret']);
            $stmt->bindParam(':fin', $data['DATE_FIN_arret']);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Arrêt ajouté avec succès'];
            } else {
                return ['success' => false, 'message' => 'Erreur lors de l\'ajout'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un arrêt
     */
    public function deleteArret($id_arret) {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE ID_arret = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id_arret);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return ['success' => true, 'message' => 'Arrêt supprimé avec succès'];
                } else {
                    return ['success' => false, 'message' => 'Arrêt non trouvé'];
                }
            } else {
                return ['success' => false, 'message' => 'Erreur lors de la suppression'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
}

// Traitement des requêtes
$api = new ArretsByOperationAPI();
$method = $_SERVER['REQUEST_METHOD'];
$response = [];

switch ($method) {
    case 'GET':
        if (isset($_GET['id_operation'])) {
            $response = $api->getArretsByOperation($_GET['id_operation']);
        } else {
            $response = ['success' => false, 'message' => 'ID opération manquant'];
        }
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            $response = $api->addArret($input);
        } else {
            $response = ['success' => false, 'message' => 'Données invalides'];
        }
        break;
        
    case 'DELETE':
        if (isset($_GET['id'])) {
            $response = $api->deleteArret($_GET['id']);
        } else {
            $response = ['success' => false, 'message' => 'ID arrêt manquant'];
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Méthode non autorisée'];
        break;
}

echo json_encode($response);
?>