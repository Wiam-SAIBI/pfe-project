<?php
// api/equipes.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestion des requêtes OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config/database.php';

class EquipeAPI {
    private $conn;
    private $table_name = "equipe";
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        if (!$this->conn) {
            $this->sendError('Erreur de connexion à la base de données', 500);
        }
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        try {
            switch ($method) {
                case 'GET':
                    if ($action === 'personnel') {
                        $this->getEquipePersonnel();
                    } elseif ($action === 'soustraitants') {
                        $this->getEquipeSoustraitants();
                    } else {
                        $this->getAllEquipes();
                    }
                    break;
                case 'POST':
                    if ($action === 'add_personnel') {
                        $this->addPersonnelToEquipe();
                    } elseif ($action === 'remove_personnel') {
                        $this->removePersonnelFromEquipe();
                    } elseif ($action === 'add_soustraitant') {
                        $this->addSoustraitantToEquipe();
                    } elseif ($action === 'remove_soustraitant') {
                        $this->removeSoustraitantFromEquipe();
                    } else {
                        $this->createEquipe();
                    }
                    break;
                case 'PUT':
                    $this->updateEquipe();
                    break;
                case 'DELETE':
                    $this->deleteEquipe();
                    break;
                default:
                    $this->sendError('Méthode non autorisée', 405);
            }
        } catch (Exception $e) {
            $this->sendError('Erreur serveur: ' . $e->getMessage(), 500);
        }
    }
    
    private function getAllEquipes() {
        $query = "SELECT 
                    e.ID_equipe,
                    e.NOM_equipe,
                    COUNT(DISTINCT ep.personnel_ID_personnel) as personnel_count,
                    COUNT(DISTINCT es.soustraiteure_ID_soustraiteure) as soustraitants_count
                  FROM " . $this->table_name . " e
                  LEFT JOIN equipe_has_personnel ep ON e.ID_equipe = ep.equipe_ID_equipe
                  LEFT JOIN equipe_has_soustraiteure es ON e.ID_equipe = es.equipe_ID_equipe
                  GROUP BY e.ID_equipe, e.NOM_equipe
                  ORDER BY e.ID_equipe";
        
        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute()) {
            $equipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->sendSuccess($equipes);
        } else {
            $this->sendError('Erreur lors de la récupération des équipes');
        }
    }
    
    private function getEquipePersonnel() {
        if (!isset($_GET['id'])) {
            $this->sendError('ID équipe manquant', 400);
        }
        
        $equipe_id = $_GET['id'];
        
        $query = "SELECT p.ID_personnel, p.MATRICULE_personnel, p.NOM_personnel, 
                         p.PRENOM_personnel, p.FONCTION_personnel, p.CONTACT_personnel
                  FROM personnel p
                  INNER JOIN equipe_has_personnel ep ON p.ID_personnel = ep.personnel_ID_personnel 
                      AND p.MATRICULE_personnel = ep.personnel_MATRICULE_personnel
                  WHERE ep.equipe_ID_equipe = ?
                  ORDER BY p.NOM_personnel, p.PRENOM_personnel";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $equipe_id);
        
        if ($stmt->execute()) {
            $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->sendSuccess($personnel);
        } else {
            $this->sendError('Erreur lors de la récupération du personnel');
        }
    }
    
    private function getEquipeSoustraitants() {
        if (!isset($_GET['id'])) {
            $this->sendError('ID équipe manquant', 400);
        }
        
        $equipe_id = $_GET['id'];
        
        $query = "SELECT s.ID_soustraiteure, s.MATRICULE_soustraiteure, s.NOM_soustraiteure, 
                         s.PRENOM_soustraiteure, s.FONCTION_soustraiteure, s.CONTACT_soustraiteure,
                         s.ENTREPRISE_soustraiteure
                  FROM soustraiteure s
                  INNER JOIN equipe_has_soustraiteure es ON s.ID_soustraiteure = es.soustraiteure_ID_soustraiteure 
                      AND s.MATRICULE_soustraiteure = es.soustraiteure_MATRICULE_soustraiteure
                  WHERE es.equipe_ID_equipe = ?
                  ORDER BY s.NOM_soustraiteure, s.PRENOM_soustraiteure";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $equipe_id);
        
        if ($stmt->execute()) {
            $soustraitants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->sendSuccess($soustraitants);
        } else {
            $this->sendError('Erreur lors de la récupération des sous-traitants');
        }
    }
    
    private function createEquipe() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['NOM_equipe'])) {
            $this->sendError('Le nom de l\'équipe est requis', 400);
        }
        
        // Vérifier l'unicité du nom
        $check_query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE NOM_equipe = ?";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(1, $data['NOM_equipe']);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            $this->sendError('Une équipe avec ce nom existe déjà', 400);
        }
        
        $query = "INSERT INTO " . $this->table_name . " (NOM_equipe) VALUES (?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $data['NOM_equipe']);
        
        if ($stmt->execute()) {
            // Récupérer l'ID généré automatiquement par le trigger
            $last_id_query = "SELECT ID_equipe FROM " . $this->table_name . " WHERE NOM_equipe = ? ORDER BY ID_equipe DESC LIMIT 1";
            $last_id_stmt = $this->conn->prepare($last_id_query);
            $last_id_stmt->bindParam(1, $data['NOM_equipe']);
            $last_id_stmt->execute();
            $result = $last_id_stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->sendSuccess([
                'ID_equipe' => $result['ID_equipe'],
                'NOM_equipe' => $data['NOM_equipe']
            ], 'Équipe créée avec succès');
        } else {
            $this->sendError('Erreur lors de la création de l\'équipe');
        }
    }
    
    private function updateEquipe() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['ID_equipe']) || empty($data['NOM_equipe'])) {
            $this->sendError('ID et nom de l\'équipe requis', 400);
        }
        
        // Vérifier l'unicité du nom (exclure l'équipe actuelle)
        $check_query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE NOM_equipe = ? AND ID_equipe != ?";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(1, $data['NOM_equipe']);
        $check_stmt->bindParam(2, $data['ID_equipe']);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            $this->sendError('Une équipe avec ce nom existe déjà', 400);
        }
        
        $query = "UPDATE " . $this->table_name . " SET NOM_equipe = ? WHERE ID_equipe = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $data['NOM_equipe']);
        $stmt->bindParam(2, $data['ID_equipe']);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                $this->sendSuccess($data, 'Équipe modifiée avec succès');
            } else {
                $this->sendError('Équipe non trouvée', 404);
            }
        } else {
            $this->sendError('Erreur lors de la modification de l\'équipe');
        }
    }
    
    private function deleteEquipe() {
        if (!isset($_GET['id'])) {
            $this->sendError('ID équipe manquant', 400);
        }
        
        $equipe_id = $_GET['id'];
        
        // Vérifier si l'équipe existe
        $check_query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE ID_equipe = ?";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(1, $equipe_id);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() == 0) {
            $this->sendError('Équipe non trouvée', 404);
        }
        
        // Vérifier si l'équipe est utilisée dans des opérations
        $operations_check = "SELECT COUNT(*) FROM operation WHERE ID_equipe = ?";
        $operations_stmt = $this->conn->prepare($operations_check);
        $operations_stmt->bindParam(1, $equipe_id);
        $operations_stmt->execute();
        
        if ($operations_stmt->fetchColumn() > 0) {
            $this->sendError('Impossible de supprimer cette équipe car elle est utilisée dans des opérations', 400);
        }
        
        $query = "DELETE FROM " . $this->table_name . " WHERE ID_equipe = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $equipe_id);
        
        if ($stmt->execute()) {
            $this->sendSuccess(null, 'Équipe supprimée avec succès');
        } else {
            $this->sendError('Erreur lors de la suppression de l\'équipe');
        }
    }
    
    private function addPersonnelToEquipe() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['equipe_id']) || empty($data['personnel_id']) || empty($data['personnel_matricule'])) {
            $this->sendError('Données manquantes (equipe_id, personnel_id, personnel_matricule)', 400);
        }
        
        // Vérifier si la relation existe déjà
        $check_query = "SELECT COUNT(*) FROM equipe_has_personnel 
                       WHERE equipe_ID_equipe = ? AND personnel_ID_personnel = ? AND personnel_MATRICULE_personnel = ?";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(1, $data['equipe_id']);
        $check_stmt->bindParam(2, $data['personnel_id']);
        $check_stmt->bindParam(3, $data['personnel_matricule']);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            $this->sendError('Ce personnel est déjà assigné à cette équipe', 400);
        }
        
        $query = "INSERT INTO equipe_has_personnel (equipe_ID_equipe, personnel_ID_personnel, personnel_MATRICULE_personnel) 
                  VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $data['equipe_id']);
        $stmt->bindParam(2, $data['personnel_id']);
        $stmt->bindParam(3, $data['personnel_matricule']);
        
        if ($stmt->execute()) {
            $this->sendSuccess(null, 'Personnel ajouté à l\'équipe avec succès');
        } else {
            $this->sendError('Erreur lors de l\'ajout du personnel à l\'équipe');
        }
    }
    
    private function removePersonnelFromEquipe() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['equipe_id']) || empty($data['personnel_id']) || empty($data['personnel_matricule'])) {
            $this->sendError('Données manquantes (equipe_id, personnel_id, personnel_matricule)', 400);
        }
        
        $query = "DELETE FROM equipe_has_personnel 
                  WHERE equipe_ID_equipe = ? AND personnel_ID_personnel = ? AND personnel_MATRICULE_personnel = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $data['equipe_id']);
        $stmt->bindParam(2, $data['personnel_id']);
        $stmt->bindParam(3, $data['personnel_matricule']);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                $this->sendSuccess(null, 'Personnel retiré de l\'équipe avec succès');
            } else {
                $this->sendError('Relation personnel-équipe non trouvée', 404);
            }
        } else {
            $this->sendError('Erreur lors du retrait du personnel de l\'équipe');
        }
    }
    
    private function addSoustraitantToEquipe() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['equipe_id']) || empty($data['soustraitant_id']) || empty($data['soustraitant_matricule'])) {
            $this->sendError('Données manquantes (equipe_id, soustraitant_id, soustraitant_matricule)', 400);
        }
        
        // Vérifier si la relation existe déjà
        $check_query = "SELECT COUNT(*) FROM equipe_has_soustraiteure 
                       WHERE equipe_ID_equipe = ? AND soustraiteure_ID_soustraiteure = ? AND soustraiteure_MATRICULE_soustraiteure = ?";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(1, $data['equipe_id']);
        $check_stmt->bindParam(2, $data['soustraitant_id']);
        $check_stmt->bindParam(3, $data['soustraitant_matricule']);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            $this->sendError('Ce sous-traitant est déjà assigné à cette équipe', 400);
        }
        
        $query = "INSERT INTO equipe_has_soustraiteure (equipe_ID_equipe, soustraiteure_ID_soustraiteure, soustraiteure_MATRICULE_soustraiteure) 
                  VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $data['equipe_id']);
        $stmt->bindParam(2, $data['soustraitant_id']);
        $stmt->bindParam(3, $data['soustraitant_matricule']);
        
        if ($stmt->execute()) {
            $this->sendSuccess(null, 'Sous-traitant ajouté à l\'équipe avec succès');
        } else {
            $this->sendError('Erreur lors de l\'ajout du sous-traitant à l\'équipe');
        }
    }
    
    private function removeSoustraitantFromEquipe() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['equipe_id']) || empty($data['soustraitant_id']) || empty($data['soustraitant_matricule'])) {
            $this->sendError('Données manquantes (equipe_id, soustraitant_id, soustraitant_matricule)', 400);
        }
        
        $query = "DELETE FROM equipe_has_soustraiteure 
                  WHERE equipe_ID_equipe = ? AND soustraiteure_ID_soustraiteure = ? AND soustraiteure_MATRICULE_soustraiteure = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $data['equipe_id']);
        $stmt->bindParam(2, $data['soustraitant_id']);
        $stmt->bindParam(3, $data['soustraitant_matricule']);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                $this->sendSuccess(null, 'Sous-traitant retiré de l\'équipe avec succès');
            } else {
                $this->sendError('Relation sous-traitant-équipe non trouvée', 404);
            }
        } else {
            $this->sendError('Erreur lors du retrait du sous-traitant de l\'équipe');
        }
    }
    
    private function sendSuccess($data = null, $message = 'Opération réussie') {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'records' => $data
        ]);
        exit;
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit;
    }
}

// Initialiser et gérer la requête
$api = new EquipeAPI();
$api->handleRequest();
?>