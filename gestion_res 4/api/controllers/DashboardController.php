<?php
// En-têtes requis
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Inclure les fichiers de configuration et d'utilitaires
include_once '../config/database.php';

class DashboardController {
    // Propriétés de la base de données
    private $conn;
    
    // Constructeur avec une connexion à la base de données
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Méthode pour obtenir les statistiques du tableau de bord
    public function getStats() {
        try {
            // Compter les navires
            $navireQuery = "SELECT COUNT(*) as total FROM navire";
            $navireStmt = $this->conn->prepare($navireQuery);
            $navireStmt->execute();
            $navireTotal = $navireStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Compter les opérations
            $operationQuery = "SELECT COUNT(*) as total FROM operation";
            $operationStmt = $this->conn->prepare($operationQuery);
            $operationStmt->execute();
            $operationTotal = $operationStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Compter les équipes
            $equipeQuery = "SELECT COUNT(*) as total FROM equipe";
            $equipeStmt = $this->conn->prepare($equipeQuery);
            $equipeStmt->execute();
            $equipeTotal = $equipeStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Compter les conteneurs
            $conteneurQuery = "SELECT COUNT(*) as total FROM conteneure";
            $conteneurStmt = $this->conn->prepare($conteneurQuery);
            $conteneurStmt->execute();
            $conteneurTotal = $conteneurStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Navires en escale
            $escaleQuery = "SELECT COUNT(*) as active FROM escale WHERE DATE_sortie > NOW()";
            $escaleStmt = $this->conn->prepare($escaleQuery);
            $escaleStmt->execute();
            $navireActive = $escaleStmt->fetch(PDO::FETCH_ASSOC)['active'];
            
            // Opérations terminées
            $completedQuery = "SELECT COUNT(*) as completed FROM operation WHERE status = 'Terminé'";
            $completedStmt = $this->conn->prepare($completedQuery);
            $completedStmt->execute();
            $operationCompleted = $completedStmt->fetch(PDO::FETCH_ASSOC)['completed'];
            
            // Équipes actives aujourd'hui
            $activeEquipeQuery = "SELECT COUNT(DISTINCT ID_equipe) as active FROM operation WHERE DATE(DATE_debut) = CURDATE()";
            $activeEquipeStmt = $this->conn->prepare($activeEquipeQuery);
            $activeEquipeStmt->execute();
            $equipeActive = $activeEquipeStmt->fetch(PDO::FETCH_ASSOC)['active'];
            
            // Réponse
            return [
                "success" => true,
                "stats" => [
                    "navires" => [
                        "total" => $navireTotal,
                        "active" => $navireActive,
                        "percent" => $navireTotal > 0 ? round(($navireActive / $navireTotal) * 100) : 0
                    ],
                    "operations" => [
                        "total" => $operationTotal,
                        "completed" => $operationCompleted,
                        "percent" => $operationTotal > 0 ? round(($operationCompleted / $operationTotal) * 100) : 0
                    ],
                    "equipes" => [
                        "total" => $equipeTotal,
                        "active" => $equipeActive,
                        "percent" => $equipeTotal > 0 ? round(($equipeActive / $equipeTotal) * 100) : 0
                    ],
                    "conteneurs" => [
                        "total" => $conteneurTotal,
                        "capacity" => "1250 EVP", // À remplacer par la capacité réelle
                        "percent" => 66 // À calculer en fonction de la capacité réelle
                    ]
                ]
            ];
        } catch(PDOException $e) {
            return [
                "success" => false,
                "message" => "Erreur lors de la récupération des statistiques: " . $e->getMessage()
            ];
        }
    }
    
    // Méthode pour obtenir les opérations récentes
    public function getRecentOperations() {
        try {
            $query = "
                SELECT 
                    o.ID_operation, 
                    o.TYPE_operation, 
                    n.NOM_navire, 
                    o.DATE_debut, 
                    o.DATE_fin, 
                    o.status
                FROM 
                    operation o
                JOIN 
                    escale e ON o.ID_escale = e.NUM_escale
                JOIN 
                    navire n ON e.MATRICULE_navire = n.MATRICULE_navire
                ORDER BY 
                    o.DATE_debut DESC
                LIMIT 5
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $operations = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $operations[] = $row;
            }
            
            return [
                "success" => true,
                "operations" => $operations
            ];
        } catch(PDOException $e) {
            return [
                "success" => false,
                "message" => "Erreur lors de la récupération des opérations: " . $e->getMessage()
            ];
        }
    }
    
    // Méthode pour obtenir les escales en cours
    public function getCurrentEscales() {
        try {
            $query = "
                SELECT 
                    e.NUM_escale as id, 
                    n.NOM_navire as navire, 
                    e.DATE_accostage as accostage, 
                    e.DATE_sortie as depart,
                    'A1' as quai  -- À remplacer par le vrai quai si disponible
                FROM 
                    escale e
                JOIN 
                    navire n ON e.MATRICULE_navire = n.MATRICULE_navire
                WHERE 
                    e.DATE_accostage <= NOW() AND e.DATE_sortie >= NOW()
                ORDER BY 
                    e.DATE_accostage DESC
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $escales = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $escales[] = $row;
            }
            
            return [
                "success" => true,
                "escales" => $escales
            ];
        } catch(PDOException $e) {
            return [
                "success" => false,
                "message" => "Erreur lors de la récupération des escales: " . $e->getMessage()
            ];
        }
    }
}

// Traitement de la requête
// Instantiation de la base de données
$database = new Database();
$db = $database->getConnection();

// Instantiation de l'objet contrôleur
$controller = new DashboardController($db);

// Point d'entrée de l'API
$request_method = $_SERVER["REQUEST_METHOD"];

switch($request_method) {
    case 'GET':
        // Déterminer quelle action est demandée
        $action = isset($_GET['action']) ? $_GET['action'] : 'stats';
        
        switch($action) {
            case 'stats':
                echo json_encode($controller->getStats());
                break;
            case 'operations':
                echo json_encode($controller->getRecentOperations());
                break;
            case 'escales':
                echo json_encode($controller->getCurrentEscales());
                break;
            default:
                echo json_encode([
                    "success" => false,
                    "message" => "Action non reconnue"
                ]);
        }
        break;
    default:
        // Méthode non autorisée
        header("HTTP/1.0 405 Method Not Allowed");
        echo json_encode([
            "success" => false,
            "message" => "Méthode non autorisée"
        ]);
        break;
}
?>