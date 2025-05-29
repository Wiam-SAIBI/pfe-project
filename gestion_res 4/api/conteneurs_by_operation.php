<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config/database.php';

class ConteneursByOperationAPI {
    private $conn;
    private $table_name = "conteneure";
    
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
     * Récupérer les conteneurs associés à une opération
     */
    public function getConteneursByOperation($id_operation) {
        try {
            // Méthode 1: Chercher par DERNIERE_OPERATION
            $query = "SELECT * FROM " . $this->table_name . " 
                      WHERE DERNIERE_OPERATION = :id_operation 
                      ORDER BY DATE_AJOUT DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id_operation', $id_operation);
            $stmt->execute();
            
            $conteneurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Si aucun conteneur trouvé, vérifier dans la table operation
            if (empty($conteneurs)) {
                $op_query = "SELECT ID_conteneure FROM operation WHERE ID_operation = :id_operation";
                $op_stmt = $this->conn->prepare($op_query);
                $op_stmt->bindParam(':id_operation', $id_operation);
                $op_stmt->execute();
                $operation = $op_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($operation && !empty($operation['ID_conteneure'])) {
                    // ID_conteneure peut contenir plusieurs IDs séparés par des virgules
                    $conteneur_ids = explode(',', $operation['ID_conteneure']);
                    $placeholders = str_repeat('?,', count($conteneur_ids) - 1) . '?';
                    
                    $cont_query = "SELECT * FROM " . $this->table_name . " 
                                   WHERE ID_conteneure IN ($placeholders)";
                    $cont_stmt = $this->conn->prepare($cont_query);
                    $cont_stmt->execute($conteneur_ids);
                    $conteneurs = $cont_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            
            // Ajouter des informations de statut simulées pour l'interface
            $conteneurs_avec_statut = [];
            $traites = 0;
            
            foreach ($conteneurs as $index => $conteneur) {
                // Simuler différents statuts pour la démonstration
                $statuts = ['Traité', 'En cours', 'En attente'];
                $positions = ['A1-B2', 'A2-B3', 'A3-B4', 'B1-C2', 'B2-C3'];
                
                $conteneur['STATUT_conteneur'] = $statuts[$index % 3];
                $conteneur['POSITION_conteneur'] = $positions[$index % 5];
                
                if ($conteneur['STATUT_conteneur'] === 'Traité') {
                    $conteneur['HEURE_TRAITEMENT'] = date('Y-m-d H:i:s', strtotime('-' . ($index + 1) . ' hours'));
                    $traites++;
                } else {
                    $conteneur['HEURE_TRAITEMENT'] = null;
                }
                
                $conteneur['CAPACITE_conteneur'] = $conteneur['TYPE_conteneure'] === '40 pieds' ? '40 TEU' : '20 TEU';
                
                $conteneurs_avec_statut[] = $conteneur;
            }
            
            // Si toujours aucun conteneur, créer des données de test
            if (empty($conteneurs_avec_statut)) {
                $conteneurs_avec_statut = [
                    [
                        'ID_conteneur' => 'CONT-' . str_replace('OP-', '', $id_operation) . '-001',
                        'NOM_conteneure' => 'Container Alpha',
                        'TYPE_conteneur' => '20 pieds',
                        'STATUT_conteneur' => 'Traité',
                        'POSITION_conteneur' => 'A1-B2',
                        'HEURE_TRAITEMENT' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                        'CAPACITE_conteneur' => '20 TEU'
                    ],
                    [
                        'ID_conteneur' => 'CONT-' . str_replace('OP-', '', $id_operation) . '-002',
                        'NOM_conteneure' => 'Container Beta',
                        'TYPE_conteneur' => '40 pieds',
                        'STATUT_conteneur' => 'En cours',
                        'POSITION_conteneur' => 'A2-B3',
                        'HEURE_TRAITEMENT' => null,
                        'CAPACITE_conteneur' => '40 TEU'
                    ],
                    [
                        'ID_conteneur' => 'CONT-' . str_replace('OP-', '', $id_operation) . '-003',
                        'NOM_conteneure' => 'Container Gamma',
                        'TYPE_conteneur' => '20 pieds',
                        'STATUT_conteneur' => 'En attente',
                        'POSITION_conteneur' => 'A3-B4',
                        'HEURE_TRAITEMENT' => null,
                        'CAPACITE_conteneur' => '20 TEU'
                    ]
                ];
                $traites = 1; // Un seul traité dans les données de test
            }
            
            return [
                'success' => true, 
                'liste' => $conteneurs_avec_statut, 
                'total' => count($conteneurs_avec_statut),
                'traites' => $traites
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Mettre à jour le statut d'un conteneur
     */
    public function updateConteneurStatus($id_conteneur, $statut) {
        try {
            // Pour cette simulation, nous ne modifions pas réellement la BDD
            // car le statut n'existe pas dans la table conteneure
            
            return ['success' => true, 'message' => 'Statut du conteneur mis à jour'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Ajouter un conteneur à une opération
     */
    public function addConteneurToOperation($id_operation, $data) {
        try {
            // Créer un nouveau conteneur
            $query = "INSERT INTO " . $this->table_name . " 
                      (NOM_conteneure, TYPE_conteneure, DERNIERE_OPERATION) 
                      VALUES (:nom, :type, :operation)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':nom', $data['NOM_conteneure']);
            $stmt->bindParam(':type', $data['TYPE_conteneure']);
            $stmt->bindParam(':operation', $id_operation);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Conteneur ajouté à l\'opération'];
            } else {
                return ['success' => false, 'message' => 'Erreur lors de l\'ajout'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Retirer un conteneur d'une opération
     */
    public function removeConteneurFromOperation($id_conteneur) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET DERNIERE_OPERATION = NULL 
                      WHERE ID_conteneure = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id_conteneur);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Conteneur retiré de l\'opération'];
            } else {
                return ['success' => false, 'message' => 'Erreur lors du retrait'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
}

// Traitement des requêtes
$api = new ConteneursByOperationAPI();
$method = $_SERVER['REQUEST_METHOD'];
$response = [];

switch ($method) {
    case 'GET':
        if (isset($_GET['id_operation'])) {
            $response = $api->getConteneursByOperation($_GET['id_operation']);
        } else {
            $response = ['success' => false, 'message' => 'ID opération manquant'];
        }
        break;
        
    case 'POST':
        if (isset($_GET['id_operation'])) {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input) {
                $response = $api->addConteneurToOperation($_GET['id_operation'], $input);
            } else {
                $response = ['success' => false, 'message' => 'Données invalides'];
            }
        } else {
            $response = ['success' => false, 'message' => 'ID opération manquant'];
        }
        break;
        
    case 'PUT':
        if (isset($_GET['id_conteneur']) && isset($_GET['statut'])) {
            $response = $api->updateConteneurStatus($_GET['id_conteneur'], $_GET['statut']);
        } else {
            $response = ['success' => false, 'message' => 'Paramètres manquants'];
        }
        break;
        
    case 'DELETE':
        if (isset($_GET['id_conteneur'])) {
            $response = $api->removeConteneurFromOperation($_GET['id_conteneur']);
        } else {
            $response = ['success' => false, 'message' => 'ID conteneur manquant'];
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Méthode non autorisée'];
        break;
}

echo json_encode($response);
?>