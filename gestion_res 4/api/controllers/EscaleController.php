<?php
// En-têtes requis
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Inclure les fichiers de configuration et d'utilitaires
include_once '../config/database.php';

class EscaleController {
    // Propriétés de la base de données
    private $conn;
    
    // Constructeur avec connexion à la base de données
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Méthode pour obtenir toutes les escales
    public function getAll($filters = []) {
        try {
            // Construire la requête de base
            $query = "
                SELECT 
                    e.NUM_escale,
                    e.MATRICULE_navire,
                    n.NOM_navire,
                    e.DATE_accostage,
                    e.DATE_sortie,
                    e.OBSERVATIONS,
                    (SELECT COUNT(*) FROM operation o WHERE o.NUM_escale = e.NUM_escale) as operations_count
                FROM 
                    escale e
                JOIN 
                    navire n ON e.MATRICULE_navire = n.MATRICULE_navire
            ";
            
            // Ajouter les conditions de filtrage
            $whereConditions = [];
            $params = [];
            
            // Filtre de recherche
            if (!empty($filters['search'])) {
                $whereConditions[] = "(e.NUM_escale LIKE :search OR n.NOM_navire LIKE :search OR n.MATRICULE_navire LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            // Filtre d'état
            if (!empty($filters['etat'])) {
                $now = date('Y-m-d H:i:s');
                switch ($filters['etat']) {
                    case 'en_cours':
                        $whereConditions[] = "(:now BETWEEN e.DATE_accostage AND e.DATE_sortie)";
                        $params[':now'] = $now;
                        break;
                    case 'planifiee':
                        $whereConditions[] = "(e.DATE_accostage > :now)";
                        $params[':now'] = $now;
                        break;
                    case 'terminee':
                        $whereConditions[] = "(e.DATE_sortie < :now)";
                        $params[':now'] = $now;
                        break;
                }
            }
            
            // Filtre de période
            if (!empty($filters['period'])) {
                $today = date('Y-m-d');
                switch ($filters['period']) {
                    case 'today':
                        $whereConditions[] = "(DATE(e.DATE_accostage) = :today OR DATE(e.DATE_sortie) = :today OR (:today BETWEEN DATE(e.DATE_accostage) AND DATE(e.DATE_sortie)))";
                        $params[':today'] = $today;
                        break;
                    case 'this_week':
                        $startOfWeek = date('Y-m-d', strtotime('monday this week'));
                        $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
                        $whereConditions[] = "((DATE(e.DATE_accostage) BETWEEN :start_week AND :end_week) OR (DATE(e.DATE_sortie) BETWEEN :start_week AND :end_week) OR (:start_week BETWEEN DATE(e.DATE_accostage) AND DATE(e.DATE_sortie)))";
                        $params[':start_week'] = $startOfWeek;
                        $params[':end_week'] = $endOfWeek;
                        break;
                    case 'this_month':
                        $startOfMonth = date('Y-m-01');
                        $endOfMonth = date('Y-m-t');
                        $whereConditions[] = "((DATE(e.DATE_accostage) BETWEEN :start_month AND :end_month) OR (DATE(e.DATE_sortie) BETWEEN :start_month AND :end_month) OR (:start_month BETWEEN DATE(e.DATE_accostage) AND DATE(e.DATE_sortie)))";
                        $params[':start_month'] = $startOfMonth;
                        $params[':end_month'] = $endOfMonth;
                        break;
                    case 'custom':
                        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                            $whereConditions[] = "((DATE(e.DATE_accostage) BETWEEN :start_date AND :end_date) OR (DATE(e.DATE_sortie) BETWEEN :start_date AND :end_date) OR (:start_date BETWEEN DATE(e.DATE_accostage) AND DATE(e.DATE_sortie)))";
                            $params[':start_date'] = $filters['start_date'];
                            $params[':end_date'] = $filters['end_date'];
                        }
                        break;
                }
            }
            
            // Ajouter les conditions WHERE à la requête
            if (!empty($whereConditions)) {
                $query .= " WHERE " . implode(" AND ", $whereConditions);
            }
            
            // Ajouter l'ordre de tri
            $query .= " ORDER BY e.DATE_accostage DESC";
            
            // Préparer et exécuter la requête
            $stmt = $this->conn->prepare($query);
            
            // Lier les paramètres
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            $stmt->execute();
            
            $escales = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $escales[] = $row;
            }
            
            return [
                'success' => true,
                'count' => count($escales),
                'escales' => $escales
            ];
        } catch(PDOException $e) {
            return [
                'success' => false,
                'message' => "Erreur lors de la récupération des escales: " . $e->getMessage()
            ];
        }
    }
    
    // Méthode pour obtenir une escale par numéro
    public function getByNum($num) {
        try {
            // Récupérer les informations de l'escale
            $query = "
                SELECT 
                    e.NUM_escale,
                    e.MATRICULE_navire,
                    n.NOM_navire,
                    e.DATE_accostage,
                    e.DATE_sortie,
                    e.OBSERVATIONS
                FROM 
                    escale e
                JOIN 
                    navire n ON e.MATRICULE_navire = n.MATRICULE_navire
                WHERE 
                    e.NUM_escale = :num
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':num', $num);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $escale = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Récupérer les opérations associées à cette escale
                $operationsQuery = "
                    SELECT 
                        o.ID_operation,
                        o.TYPE_operation,
                        o.DATE_debut,
                        o.DATE_fin,
                        o.STATUT
                    FROM 
                        operation o
                    WHERE 
                        o.NUM_escale = :num
                    ORDER BY 
                        o.DATE_debut
                ";
                
                $operationsStmt = $this->conn->prepare($operationsQuery);
                $operationsStmt->bindParam(':num', $num);
                $operationsStmt->execute();
                
                $operations = [];
                while ($operationRow = $operationsStmt->fetch(PDO::FETCH_ASSOC)) {
                    $operations[] = $operationRow;
                }
                
                $escale['operations'] = $operations;
                
                return [
                    'success' => true,
                    'escale' => $escale
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Escale avec numéro $num non trouvée"
                ];
            }
        } catch(PDOException $e) {
            return [
                'success' => false,
                'message' => "Erreur lors de la récupération de l'escale: " . $e->getMessage()
            ];
        }
    }
    
    // Méthode pour créer une escale
    public function create($data) {
        try {
            // Validation des données
            if (empty($data['matricule_navire']) || empty($data['date_accostage']) || empty($data['date_sortie'])) {
                return [
                    'success' => false,
                    'message' => "Les champs 'matricule_navire', 'date_accostage' et 'date_sortie' sont obligatoires"
                ];
            }
            
            // Vérifier que le navire existe
            $checkNavireQuery = "SELECT COUNT(*) as count FROM navire WHERE MATRICULE_navire = :matricule";
            $checkNavireStmt = $this->conn->prepare($checkNavireQuery);
            $checkNavireStmt->bindParam(':matricule', $data['matricule_navire']);
            $checkNavireStmt->execute();
            
            if ($checkNavireStmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
                return [
                    'success' => false,
                    'message' => "Le navire avec le matricule " . $data['matricule_navire'] . " n'existe pas"
                ];
            }
            
            // Vérifier que la date de sortie est postérieure à la date d'accostage
            $dateAccostage = new DateTime($data['date_accostage']);
            $dateSortie = new DateTime($data['date_sortie']);
            
            if ($dateSortie <= $dateAccostage) {
                return [
                    'success' => false,
                    'message' => "La date de sortie doit être postérieure à la date d'accostage"
                ];
            }
            
            // Vérifier s'il y a chevauchement avec une autre escale pour ce navire
            $checkOverlapQuery = "
                SELECT COUNT(*) as count 
                FROM escale 
                WHERE 
                    MATRICULE_navire = :matricule 
                    AND (
                        (:date_accostage BETWEEN DATE_accostage AND DATE_sortie)
                        OR (:date_sortie BETWEEN DATE_accostage AND DATE_sortie)
                        OR (DATE_accostage BETWEEN :date_accostage AND :date_sortie)
                    )
            ";
            
            $checkOverlapStmt = $this->conn->prepare($checkOverlapQuery);
            $checkOverlapStmt->bindParam(':matricule', $data['matricule_navire']);
            $checkOverlapStmt->bindParam(':date_accostage', $data['date_accostage']);
            $checkOverlapStmt->bindParam(':date_sortie', $data['date_sortie']);
            $checkOverlapStmt->execute();
            
            if ($checkOverlapStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                return [
                    'success' => false,
                    'message' => "Le navire a déjà une escale prévue qui chevauche cette période"
                ];
            }
            
            // Générer un numéro d'escale unique
            $numEscale = 'ESC-' . date('Ymd') . '-' . rand(1000, 9999);
            
            // Vérifier que le numéro d'escale est unique
            $checkNumQuery = "SELECT COUNT(*) as count FROM escale WHERE NUM_escale = :num";
            $checkNumStmt = $this->conn->prepare($checkNumQuery);
            $checkNumStmt->bindParam(':num', $numEscale);
            $checkNumStmt->execute();
            
            // Si le numéro existe déjà, en générer un nouveau
            while ($checkNumStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                $numEscale = 'ESC-' . date('Ymd') . '-' . rand(1000, 9999);
                $checkNumStmt->bindParam(':num', $numEscale);
                $checkNumStmt->execute();
            }
            
            // Insérer la nouvelle escale
            $query = "
                INSERT INTO escale (
                    NUM_escale, 
                    MATRICULE_navire, 
                    DATE_accostage, 
                    DATE_sortie, 
                    OBSERVATIONS
                ) VALUES (
                    :num_escale,
                    :matricule_navire,
                    :date_accostage,
                    :date_sortie,
                    :observations
                )
            ";
            
            $stmt = $this->conn->prepare($query);
            
            // Nettoyer et lier les paramètres
            $matricule = htmlspecialchars(strip_tags($data['matricule_navire']));
            $observations = isset($data['observations']) ? htmlspecialchars(strip_tags($data['observations'])) : null;
            
            $stmt->bindParam(':num_escale', $numEscale);
            $stmt->bindParam(':matricule_navire', $matricule);
            $stmt->bindParam(':date_accostage', $data['date_accostage']);
            $stmt->bindParam(':date_sortie', $data['date_sortie']);
            $stmt->bindParam(':observations', $observations);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => "Escale créée avec succès",
                    'num_escale' => $numEscale
                ];
            }
            
            return [
                'success' => false,
                'message' => "Impossible de créer l'escale"
            ];
        } catch(PDOException $e) {
            return [
                'success' => false,
                'message' => "Erreur lors de la création de l'escale: " . $e->getMessage()
            ];
        }
    }
    
    // Méthode pour mettre à jour une escale
    public function update($num, $data) {
        try {
            // Vérifier si l'escale existe
            $checkQuery = "SELECT COUNT(*) as count FROM escale WHERE NUM_escale = :num";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':num', $num);
            $checkStmt->execute();
            
            if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
                return [
                    'success' => false,
                    'message' => "Escale avec numéro $num non trouvée"
                ];
            }
            
            // Récupérer les données actuelles de l'escale
            $getCurrentDataQuery = "
                SELECT 
                    MATRICULE_navire,
                    DATE_accostage,
                    DATE_sortie
                FROM 
                    escale
                WHERE 
                    NUM_escale = :num
            ";
            
            $getCurrentDataStmt = $this->conn->prepare($getCurrentDataQuery);
            $getCurrentDataStmt->bindParam(':num', $num);
            $getCurrentDataStmt->execute();
            $currentData = $getCurrentDataStmt->fetch(PDO::FETCH_ASSOC);
            
            // Données pour la mise à jour
            $dateAccostage = isset($data['date_accostage']) ? $data['date_accostage'] : $currentData['DATE_accostage'];
            $dateSortie = isset($data['date_sortie']) ? $data['date_sortie'] : $currentData['DATE_sortie'];
            $observations = isset($data['observations']) ? htmlspecialchars(strip_tags($data['observations'])) : null;
            
            // Vérifier que la date de sortie est postérieure à la date d'accostage
            $dateAccostageObj = new DateTime($dateAccostage);
            $dateSortieObj = new DateTime($dateSortie);
            
            if ($dateSortieObj <= $dateAccostageObj) {
                return [
                    'success' => false,
                    'message' => "La date de sortie doit être postérieure à la date d'accostage"
                ];
            }
            
            // Vérifier s'il y a chevauchement avec une autre escale pour ce navire
            $checkOverlapQuery = "
                SELECT COUNT(*) as count 
                FROM escale 
                WHERE 
                    MATRICULE_navire = :matricule 
                    AND NUM_escale != :num_escale
                    AND (
                        (:date_accostage BETWEEN DATE_accostage AND DATE_sortie)
                        OR (:date_sortie BETWEEN DATE_accostage AND DATE_sortie)
                        OR (DATE_accostage BETWEEN :date_accostage AND :date_sortie)
                    )
            ";
            
            $checkOverlapStmt = $this->conn->prepare($checkOverlapQuery);
            $checkOverlapStmt->bindParam(':matricule', $currentData['MATRICULE_navire']);
            $checkOverlapStmt->bindParam(':num_escale', $num);
            $checkOverlapStmt->bindParam(':date_accostage', $dateAccostage);
            $checkOverlapStmt->bindParam(':date_sortie', $dateSortie);
            $checkOverlapStmt->execute();
            
            if ($checkOverlapStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                return [
                    'success' => false,
                    'message' => "Le navire a déjà une escale prévue qui chevauche cette période"
                ];
            }
            
            // Mettre à jour l'escale
            $query = "
                UPDATE escale 
                SET 
                    DATE_accostage = :date_accostage,
                    DATE_sortie = :date_sortie,
                    OBSERVATIONS = :observations
                WHERE 
                    NUM_escale = :num_escale
            ";
            
            $stmt = $this->conn->prepare($query);
            
            // Lier les paramètres
            $stmt->bindParam(':date_accostage', $dateAccostage);
            $stmt->bindParam(':date_sortie', $dateSortie);
            $stmt->bindParam(':observations', $observations);
            $stmt->bindParam(':num_escale', $num);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => "Escale mise à jour avec succès"
                ];
            }
            
            return [
                'success' => false,
                'message' => "Impossible de mettre à jour l'escale"
            ];
        } catch(PDOException $e) {
            return [
                'success' => false,
                'message' => "Erreur lors de la mise à jour de l'escale: " . $e->getMessage()
            ];
        }
    }
    
    // Méthode pour supprimer une escale
    public function delete($num) {
        try {
            // Vérifier si l'escale existe
            $checkQuery = "SELECT COUNT(*) as count FROM escale WHERE NUM_escale = :num";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':num', $num);
            $checkStmt->execute();
            
            if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
                return [
                    'success' => false,
                    'message' => "Escale avec numéro $num non trouvée"
                ];
            }
            
            // Vérifier si l'escale a des opérations associées
            $checkOperationsQuery = "SELECT COUNT(*) as count FROM operation WHERE NUM_escale = :num";
            $checkOperationsStmt = $this->conn->prepare($checkOperationsQuery);
            $checkOperationsStmt->bindParam(':num', $num);
            $checkOperationsStmt->execute();
            
            if ($checkOperationsStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                return [
                    'success' => false,
                    'message' => "Impossible de supprimer cette escale car elle a des opérations associées"
                ];
            }
            
            // Supprimer l'escale
            $query = "DELETE FROM escale WHERE NUM_escale = :num";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':num', $num);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => "Escale supprimée avec succès"
                ];
            }
            
            return [
                'success' => false,
                'message' => "Impossible de supprimer l'escale"
            ];
        } catch(PDOException $e) {
            return [
                'success' => false,
                'message' => "Erreur lors de la suppression de l'escale: " . $e->getMessage()
            ];
        }
    }
    
    // Méthode pour obtenir les statistiques des escales
    public function getStats() {
        try {
            $now = date('Y-m-d H:i:s');
            
            // Escales en cours
            $enCoursQuery = "
                SELECT COUNT(*) as count
                FROM escale
                WHERE :now BETWEEN DATE_accostage AND DATE_sortie
            ";
            $enCoursStmt = $this->conn->prepare($enCoursQuery);
            $enCoursStmt->bindParam(':now', $now);
            $enCoursStmt->execute();
            $enCours = $enCoursStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Escales planifiées
            $planifieesQuery = "
                SELECT COUNT(*) as count
                FROM escale
                WHERE DATE_accostage > :now
            ";
            $planifieesStmt = $this->conn->prepare($planifieesQuery);
            $planifieesStmt->bindParam(':now', $now);
            $planifieesStmt->execute();
            $planifiees = $planifieesStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Escales terminées
            $termineesQuery = "
                SELECT COUNT(*) as count
                FROM escale
                WHERE DATE_sortie < :now
            ";
            $termineesStmt = $this->conn->prepare($termineesQuery);
            $termineesStmt->bindParam(':now', $now);
            $termineesStmt->execute();
            $terminees = $termineesStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Escales par mois
            $escalesParMoisQuery = "
                SELECT 
                    YEAR(DATE_accostage) as year,
                    MONTH(DATE_accostage) as month,
                    COUNT(*) as count
                FROM 
                    escale
                WHERE
                    DATE_accostage > DATE_SUB(:now, INTERVAL 12 MONTH)
                GROUP BY 
                    YEAR(DATE_accostage), MONTH(DATE_accostage)
                ORDER BY 
                    year ASC, month ASC
            ";
            $escalesParMoisStmt = $this->conn->prepare($escalesParMoisQuery);
            $escalesParMoisStmt->bindParam(':now', $now);
            $escalesParMoisStmt->execute();
            
            $escalesParMois = [];
            while ($row = $escalesParMoisStmt->fetch(PDO::FETCH_ASSOC)) {
                $escalesParMois[] = $row;
            }
            
            return [
                'success' => true,
                'stats' => [
                    'en_cours' => $enCours,
                    'planifiees' => $planifiees,
                    'terminees' => $terminees,
                    'par_mois' => $escalesParMois
                ]
            ];
        } catch(PDOException $e) {
            return [
                'success' => false,
                'message' => "Erreur lors de la récupération des statistiques: " . $e->getMessage()
            ];
        }
    }
}

// Traitement de la requête
// Instantiation de la base de données
$database = new Database();
$db = $database->getConnection();

// Instantiation de l'objet contrôleur
$controller = new EscaleController($db);

// Point d'entrée de l'API
$request_method = $_SERVER["REQUEST_METHOD"];

switch($request_method) {
    case 'GET':
        // Obtenir une escale spécifique ou toutes les escales
        if (isset($_GET['num'])) {
            $num = $_GET['num'];
            echo json_encode($controller->getByNum($num));
        } else if (isset($_GET['stats'])) {
            echo json_encode($controller->getStats());
        } else {
            // Récupérer les paramètres de filtre
            $filters = [];
            
            if (isset($_GET['search'])) $filters['search'] = $_GET['search'];
            if (isset($_GET['etat'])) $filters['etat'] = $_GET['etat'];
            if (isset($_GET['period'])) $filters['period'] = $_GET['period'];
            if (isset($_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
            if (isset($_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];
            
            echo json_encode($controller->getAll($filters));
        }
        break;
    
    case 'POST':
        // Créer une nouvelle escale
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($controller->create($data));
        break;
    
    case 'PUT':
        // Mettre à jour une escale existante
        if (isset($_GET['num'])) {
            $num = $_GET['num'];
            $data = json_decode(file_get_contents("php://input"), true);
            echo json_encode($controller->update($num, $data));
        } else {
            echo json_encode([
                'success' => false,
                'message' => "Numéro d'escale non spécifié"
            ]);
        }
        break;
    
    case 'DELETE':
        // Supprimer une escale
        if (isset($_GET['num'])) {
            $num = $_GET['num'];
            echo json_encode($controller->delete($num));
        } else {
            echo json_encode([
                'success' => false,
                'message' => "Numéro d'escale non spécifié"
            ]);
        }
        break;
    
    default:
        // Méthode non autorisée
        header("HTTP/1.0 405 Method Not Allowed");
        echo json_encode([
            'success' => false,
            'message' => "Méthode non autorisée"
        ]);
        break;
}
?>