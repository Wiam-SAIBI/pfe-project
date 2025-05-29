<?php
/**
 * API pour la gestion des détails d'opération
 * Fichier: api/operation_detail.php
 * Intégration complète avec la base de données gestion_res
 */

// Headers CORS et Content-Type
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Gestion des requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Inclusion de la configuration de base de données
require_once 'config/database.php';

class OperationDetailAPI {
    private $conn;
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        
        if (!$this->conn) {
            $this->sendError('Erreur de connexion à la base de données', 500);
        }
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';
        
        try {
            switch ($method) {
                case 'GET':
                    $this->handleGet($action);
                    break;
                case 'POST':
                    $this->handlePost($action);
                    break;
                case 'PUT':
                    $this->handlePut($action);
                    break;
                case 'DELETE':
                    $this->handleDelete($action);
                    break;
                default:
                    $this->sendError('Méthode non autorisée', 405);
            }
        } catch (Exception $e) {
            $this->sendError('Erreur serveur: ' . $e->getMessage(), 500);
        }
    }

    private function handleGet($action) {
        switch ($action) {
            case 'operation':
                $this->getOperationDetails();
                break;
            case 'personnel':
                $this->getOperationPersonnel();
                break;
            case 'conteneurs':
                $this->getOperationConteneurs();
                break;
            case 'equipements':
                $this->getOperationEquipements();
                break;
            case 'arrets':
                $this->getOperationArrets();
                break;
            case 'available_personnel':
                $this->getAvailablePersonnel();
                break;
            case 'available_soustraitants':
                $this->getAvailableSoustraitants();
                break;
            case 'available_conteneurs':
                $this->getAvailableConteneurs();
                break;
            case 'available_equipements':
                $this->getAvailableEquipements();
                break;
            default:
                $this->sendError('Action non reconnue', 400);
        }
    }

    private function handlePost($action) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        switch ($action) {
            case 'add_conteneur':
                $this->addConteneurToOperation($input);
                break;
            case 'add_equipement':
                $this->addEquipementToOperation($input);
                break;
            case 'add_arret':
                $this->addArretToOperation($input);
                break;
            case 'add_personnel':
                $this->addPersonnelToOperation($input);
                break;
            default:
                $this->sendError('Action non reconnue', 400);
        }
    }

    private function handlePut($action) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        switch ($action) {
            case 'update_operation':
                $this->updateOperation($input);
                break;
            default:
                $this->sendError('Action non reconnue', 400);
        }
    }

    private function handleDelete($action) {
        switch ($action) {
            case 'remove_conteneur':
                $this->removeConteneurFromOperation();
                break;
            case 'remove_equipement':
                $this->removeEquipementFromOperation();
                break;
            case 'remove_arret':
                $this->removeArret();
                break;
            case 'remove_personnel':
                $this->removePersonnelFromOperation();
                break;
            default:
                $this->sendError('Action non reconnue', 400);
        }
    }

    // ===== MÉTHODES GET =====

    // Récupérer les détails de l'opération
    private function getOperationDetails() {
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            $this->sendError('ID opération requis', 400);
        }

        $query = "
            SELECT 
                o.ID_operation,
                o.TYPE_operation,
                o.DATE_debut,
                o.DATE_fin,
                o.status,
                o.ID_escale,
                o.ID_shift,
                o.ID_equipe,
                o.ID_conteneure,
                o.ID_engin,
                eq.NOM_equipe,
                s.NOM_shift,
                s.HEURE_debut,
                s.HEURE_fin,
                esc.NOM_navire,
                esc.MATRICULE_navire,
                esc.DATE_accostage,
                esc.DATE_sortie
            FROM operation o
            LEFT JOIN equipe eq ON o.ID_equipe = eq.ID_equipe
            LEFT JOIN shift s ON o.ID_shift = s.ID_shift
            LEFT JOIN escale esc ON o.ID_escale = esc.NUM_escale
            WHERE o.ID_operation = :id
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $operation = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->sendSuccess($operation);
        } else {
            $this->sendError('Opération non trouvée', 404);
        }
    }

    // Récupérer le personnel de l'opération
    private function getOperationPersonnel() {
        $operationId = $_GET['id'] ?? '';
        
        if (empty($operationId)) {
            $this->sendError('ID opération requis', 400);
        }

        // Récupérer l'ID_equipe de l'opération
        $queryEquipe = "SELECT ID_equipe FROM operation WHERE ID_operation = :id";
        $stmtEquipe = $this->conn->prepare($queryEquipe);
        $stmtEquipe->bindParam(':id', $operationId);
        $stmtEquipe->execute();
        
        if ($stmtEquipe->rowCount() == 0) {
            $this->sendError('Opération non trouvée', 404);
        }
        
        $equipeId = $stmtEquipe->fetch(PDO::FETCH_ASSOC)['ID_equipe'];
        
        if (empty($equipeId)) {
            $this->sendSuccess([]);
            return;
        }
        
        // Personnel interne
        $queryPersonnel = "
            SELECT 
                p.MATRICULE_personnel as matricule,
                p.NOM_personnel as nom,
                p.PRENOM_personnel as prenom,
                p.FONCTION_personnel as fonction,
                p.CONTACT_personnel as contact,
                'Personnel' as type
            FROM equipe_has_personnel ehp
            JOIN personnel p ON ehp.personnel_MATRICULE_personnel = p.MATRICULE_personnel
            WHERE ehp.equipe_ID_equipe = :equipe_id
        ";
        
        $stmtPersonnel = $this->conn->prepare($queryPersonnel);
        $stmtPersonnel->bindParam(':equipe_id', $equipeId);
        $stmtPersonnel->execute();
        $personnel = $stmtPersonnel->fetchAll(PDO::FETCH_ASSOC);
        
        // Sous-traitants
        $querySoustraitants = "
            SELECT 
                s.MATRICULE_soustraiteure as matricule,
                s.NOM_soustraiteure as nom,
                s.PRENOM_soustraiteure as prenom,
                s.FONCTION_soustraiteure as fonction,
                s.CONTACT_soustraiteure as contact,
                s.ENTREPRISE_soustraiteure as entreprise,
                'Sous-traitant' as type
            FROM equipe_has_soustraiteure ehs
            JOIN soustraiteure s ON ehs.soustraiteure_MATRICULE_soustraiteure = s.MATRICULE_soustraiteure
            WHERE ehs.equipe_ID_equipe = :equipe_id
        ";
        
        $stmtSoustraitants = $this->conn->prepare($querySoustraitants);
        $stmtSoustraitants->bindParam(':equipe_id', $equipeId);
        $stmtSoustraitants->execute();
        $soustraitants = $stmtSoustraitants->fetchAll(PDO::FETCH_ASSOC);
        
        // Fusionner les résultats
        $allPersonnel = array_merge($personnel, $soustraitants);
        
        $this->sendSuccess($allPersonnel);
    }

    // Récupérer les conteneurs de l'opération
    private function getOperationConteneurs() {
        $operationId = $_GET['id'] ?? '';
        
        if (empty($operationId)) {
            $this->sendError('ID opération requis', 400);
        }

        // Méthode 1: Via le champ ID_conteneure de l'opération
        $queryOperation = "SELECT ID_conteneure FROM operation WHERE ID_operation = :id";
        $stmtOperation = $this->conn->prepare($queryOperation);
        $stmtOperation->bindParam(':id', $operationId);
        $stmtOperation->execute();
        
        if ($stmtOperation->rowCount() == 0) {
            $this->sendError('Opération non trouvée', 404);
        }
        
        $result = $stmtOperation->fetch(PDO::FETCH_ASSOC);
        $conteneurIds = $result['ID_conteneure'];
        
        if (empty($conteneurIds)) {
            // Méthode 2: Via le champ DERNIERE_OPERATION des conteneurs
            $query = "
                SELECT 
                    c.ID_conteneure,
                    c.NOM_conteneure,
                    c.TYPE_conteneure,
                    c.DATE_AJOUT,
                    c.ID_navire,
                    n.NOM_navire
                FROM conteneure c
                LEFT JOIN navire n ON c.ID_navire = n.ID_navire
                WHERE c.DERNIERE_OPERATION = :operation_id
                ORDER BY c.DATE_AJOUT DESC
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':operation_id', $operationId);
            $stmt->execute();
            $conteneurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Traiter les IDs multiples (séparés par virgules)
            $idsArray = array_map('trim', explode(',', $conteneurIds));
            $placeholders = str_repeat('?,', count($idsArray) - 1) . '?';

            $query = "
                SELECT 
                    c.ID_conteneure,
                    c.NOM_conteneure,
                    c.TYPE_conteneure,
                    c.DATE_AJOUT,
                    c.ID_navire,
                    n.NOM_navire
                FROM conteneure c
                LEFT JOIN navire n ON c.ID_navire = n.ID_navire
                WHERE c.ID_conteneure IN ($placeholders)
                ORDER BY c.DATE_AJOUT DESC
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute($idsArray);
            $conteneurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->sendSuccess($conteneurs);
    }

    // Récupérer les équipements de l'opération
    private function getOperationEquipements() {
        $operationId = $_GET['id'] ?? '';
        
        if (empty($operationId)) {
            $this->sendError('ID opération requis', 400);
        }

        // Récupérer les IDs des engins depuis le champ ID_engin de l'opération
        $queryOperation = "SELECT ID_engin FROM operation WHERE ID_operation = :id";
        $stmtOperation = $this->conn->prepare($queryOperation);
        $stmtOperation->bindParam(':id', $operationId);
        $stmtOperation->execute();
        
        if ($stmtOperation->rowCount() == 0) {
            $this->sendError('Opération non trouvée', 404);
        }
        
        $result = $stmtOperation->fetch(PDO::FETCH_ASSOC);
        $enginIds = $result['ID_engin'];
        
        if (empty($enginIds)) {
            $this->sendSuccess([]);
            return;
        }

        // Traiter les IDs multiples (séparés par virgules)
        $idsArray = array_map('trim', explode(',', $enginIds));
        $placeholders = str_repeat('?,', count($idsArray) - 1) . '?';

        $query = "
            SELECT 
                ID_engin,
                NOM_engin,
                TYPE_engin
            FROM engin
            WHERE ID_engin IN ($placeholders)
            ORDER BY NOM_engin
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($idsArray);

        $equipements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->sendSuccess($equipements);
    }

    // Récupérer les arrêts de l'opération
    private function getOperationArrets() {
        $operationId = $_GET['id'] ?? '';
        
        if (empty($operationId)) {
            $this->sendError('ID opération requis', 400);
        }

        $query = "
            SELECT 
                ID_arret,
                MOTIF_arret,
                DATE_DEBUT_arret,
                DATE_FIN_arret,
                DURE_arret
            FROM arret
            WHERE ID_operation = :operation_id
            ORDER BY DATE_DEBUT_arret DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':operation_id', $operationId);
        $stmt->execute();

        $arrets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->sendSuccess($arrets);
    }

    // Récupérer le personnel disponible (non assigné à l'équipe courante)
    private function getAvailablePersonnel() {
        $operationId = $_GET['operation_id'] ?? '';
        $equipeId = '';
        
        if (!empty($operationId)) {
            // Récupérer l'équipe de l'opération pour exclure son personnel
            $queryEquipe = "SELECT ID_equipe FROM operation WHERE ID_operation = :id";
            $stmtEquipe = $this->conn->prepare($queryEquipe);
            $stmtEquipe->bindParam(':id', $operationId);
            $stmtEquipe->execute();
            if ($stmtEquipe->rowCount() > 0) {
                $equipeId = $stmtEquipe->fetch(PDO::FETCH_ASSOC)['ID_equipe'];
            }
        }

        $query = "
            SELECT 
                ID_personnel,
                MATRICULE_personnel,
                NOM_personnel,
                PRENOM_personnel,
                FONCTION_personnel,
                CONTACT_personnel
            FROM personnel p
        ";
        
        $params = [];
        
        if (!empty($equipeId)) {
            $query .= "
                WHERE p.MATRICULE_personnel NOT IN (
                    SELECT personnel_MATRICULE_personnel 
                    FROM equipe_has_personnel 
                    WHERE equipe_ID_equipe = :equipe_id
                )
            ";
            $params['equipe_id'] = $equipeId;
        }
        
        $query .= " ORDER BY p.NOM_personnel, p.PRENOM_personnel";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindParam(':' . $key, $value);
        }
        $stmt->execute();

        $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->sendSuccess($personnel);
    }

    // Récupérer les sous-traitants disponibles
    private function getAvailableSoustraitants() {
        $operationId = $_GET['operation_id'] ?? '';
        $equipeId = '';
        
        if (!empty($operationId)) {
            // Récupérer l'équipe de l'opération pour exclure ses sous-traitants
            $queryEquipe = "SELECT ID_equipe FROM operation WHERE ID_operation = :id";
            $stmtEquipe = $this->conn->prepare($queryEquipe);
            $stmtEquipe->bindParam(':id', $operationId);
            $stmtEquipe->execute();
            if ($stmtEquipe->rowCount() > 0) {
                $equipeId = $stmtEquipe->fetch(PDO::FETCH_ASSOC)['ID_equipe'];
            }
        }

        $query = "
            SELECT 
                ID_soustraiteure,
                MATRICULE_soustraiteure,
                NOM_soustraiteure,
                PRENOM_soustraiteure,
                FONCTION_soustraiteure,
                CONTACT_soustraiteure,
                ENTREPRISE_soustraiteure
            FROM soustraiteure s
        ";
        
        $params = [];
        
        if (!empty($equipeId)) {
            $query .= "
                WHERE s.MATRICULE_soustraiteure NOT IN (
                    SELECT soustraiteure_MATRICULE_soustraiteure 
                    FROM equipe_has_soustraiteure 
                    WHERE equipe_ID_equipe = :equipe_id
                )
            ";
            $params['equipe_id'] = $equipeId;
        }
        
        $query .= " ORDER BY s.NOM_soustraiteure, s.PRENOM_soustraiteure";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindParam(':' . $key, $value);
        }
        $stmt->execute();

        $soustraitants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->sendSuccess($soustraitants);
    }

    // Récupérer les conteneurs disponibles (non assignés à une opération)
    private function getAvailableConteneurs() {
        $query = "
            SELECT 
                c.ID_conteneure,
                c.NOM_conteneure,
                c.TYPE_conteneure,
                c.ID_navire,
                n.NOM_navire
            FROM conteneure c
            LEFT JOIN navire n ON c.ID_navire = n.ID_navire
            WHERE (c.DERNIERE_OPERATION IS NULL OR c.DERNIERE_OPERATION = '')
            ORDER BY c.NOM_conteneure
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $conteneurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->sendSuccess($conteneurs);
    }

    // Récupérer les équipements disponibles
    private function getAvailableEquipements() {
        $query = "
            SELECT 
                ID_engin,
                NOM_engin,
                TYPE_engin
            FROM engin
            ORDER BY TYPE_engin, NOM_engin
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $equipements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->sendSuccess($equipements);
    }

    // ===== MÉTHODES POST =====

    // Mettre à jour une opération
    private function updateOperation($data) {
        $requiredFields = ['ID_operation', 'TYPE_operation', 'DATE_debut', 'DATE_fin', 'status'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $this->sendError("Champ requis manquant: $field", 400);
            }
        }

        $query = "
            UPDATE operation 
            SET TYPE_operation = :type_operation,
                DATE_debut = :date_debut,
                DATE_fin = :date_fin,
                status = :status
            WHERE ID_operation = :id_operation
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':type_operation', $data['TYPE_operation']);
        $stmt->bindParam(':date_debut', $data['DATE_debut']);
        $stmt->bindParam(':date_fin', $data['DATE_fin']);
        $stmt->bindParam(':status', $data['status']);
        $stmt->bindParam(':id_operation', $data['ID_operation']);

        if ($stmt->execute()) {
            $this->sendSuccess(['message' => 'Opération mise à jour avec succès']);
        } else {
            $this->sendError('Erreur lors de la mise à jour', 500);
        }
    }

    // Ajouter un conteneur à l'opération
    private function addConteneurToOperation($data) {
        if (!isset($data['ID_operation']) || !isset($data['ID_conteneure'])) {
            $this->sendError('ID opération et ID conteneur requis', 400);
        }

        try {
            $this->conn->beginTransaction();

            // Méthode 1: Mettre à jour le conteneur
            $query1 = "
                UPDATE conteneure 
                SET DERNIERE_OPERATION = :operation_id 
                WHERE ID_conteneure = :conteneur_id
            ";

            $stmt1 = $this->conn->prepare($query1);
            $stmt1->bindParam(':operation_id', $data['ID_operation']);
            $stmt1->bindParam(':conteneur_id', $data['ID_conteneure']);
            $stmt1->execute();

            // Méthode 2: Ajouter à la liste des conteneurs de l'opération
            $queryGet = "SELECT ID_conteneure FROM operation WHERE ID_operation = :operation_id";
            $stmtGet = $this->conn->prepare($queryGet);
            $stmtGet->bindParam(':operation_id', $data['ID_operation']);
            $stmtGet->execute();
            
            $result = $stmtGet->fetch(PDO::FETCH_ASSOC);
            $currentConteneurs = $result['ID_conteneure'] ?? '';
            
            // Ajouter le nouveau conteneur à la liste
            $newConteneurs = empty($currentConteneurs) ? $data['ID_conteneure'] : $currentConteneurs . ',' . $data['ID_conteneure'];

            $queryUpdate = "
                UPDATE operation 
                SET ID_conteneure = :conteneurs 
                WHERE ID_operation = :operation_id
            ";

            $stmtUpdate = $this->conn->prepare($queryUpdate);
            $stmtUpdate->bindParam(':conteneurs', $newConteneurs);
            $stmtUpdate->bindParam(':operation_id', $data['ID_operation']);
            $stmtUpdate->execute();

            $this->conn->commit();
            $this->sendSuccess(['message' => 'Conteneur ajouté à l\'opération']);

        } catch (Exception $e) {
            $this->conn->rollback();
            $this->sendError('Erreur lors de l\'ajout du conteneur: ' . $e->getMessage(), 500);
        }
    }

    // Ajouter un équipement à l'opération
    private function addEquipementToOperation($data) {
        if (!isset($data['ID_operation']) || !isset($data['ID_engin'])) {
            $this->sendError('ID opération et ID engin requis', 400);
        }

        // Récupérer les engins actuels
        $queryGet = "SELECT ID_engin FROM operation WHERE ID_operation = :operation_id";
        $stmtGet = $this->conn->prepare($queryGet);
        $stmtGet->bindParam(':operation_id', $data['ID_operation']);
        $stmtGet->execute();
        
        if ($stmtGet->rowCount() == 0) {
            $this->sendError('Opération non trouvée', 404);
        }
        
        $result = $stmtGet->fetch(PDO::FETCH_ASSOC);
        $currentEngins = $result['ID_engin'] ?? '';
        
        // Vérifier si l'engin n'est pas déjà assigné
        if (!empty($currentEngins)) {
            $enginsArray = array_map('trim', explode(',', $currentEngins));
            if (in_array($data['ID_engin'], $enginsArray)) {
                $this->sendError('Cet équipement est déjà assigné à l\'opération', 400);
            }
        }
        
        // Ajouter le nouvel engin
        $newEngins = empty($currentEngins) ? $data['ID_engin'] : $currentEngins . ',' . $data['ID_engin'];

        $queryUpdate = "
            UPDATE operation 
            SET ID_engin = :engins 
            WHERE ID_operation = :operation_id
        ";

        $stmtUpdate = $this->conn->prepare($queryUpdate);
        $stmtUpdate->bindParam(':engins', $newEngins);
        $stmtUpdate->bindParam(':operation_id', $data['ID_operation']);

        if ($stmtUpdate->execute()) {
            $this->sendSuccess(['message' => 'Équipement ajouté à l\'opération']);
        } else {
            $this->sendError('Erreur lors de l\'ajout de l\'équipement', 500);
        }
    }

    // Ajouter un arrêt à l'opération
    private function addArretToOperation($data) {
        $requiredFields = ['ID_operation', 'MOTIF_arret', 'DATE_DEBUT_arret'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->sendError("Champ requis manquant: $field", 400);
            }
        }

        // Récupérer le NUM_escale de l'opération
        $queryEscale = "SELECT ID_escale FROM operation WHERE ID_operation = :operation_id";
        $stmtEscale = $this->conn->prepare($queryEscale);
        $stmtEscale->bindParam(':operation_id', $data['ID_operation']);
        $stmtEscale->execute();
        
        if ($stmtEscale->rowCount() == 0) {
            $this->sendError('Opération non trouvée', 404);
        }
        
        $escaleId = $stmtEscale->fetch(PDO::FETCH_ASSOC)['ID_escale'];

        $query = "
            INSERT INTO arret (ID_operation, NUM_escale, MOTIF_arret, DATE_DEBUT_arret, DATE_FIN_arret, DURE_arret)
            VALUES (:operation_id, :escale_id, :motif, :date_debut, :date_fin, :duree)
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':operation_id', $data['ID_operation']);
        $stmt->bindParam(':escale_id', $escaleId);
        $stmt->bindParam(':motif', $data['MOTIF_arret']);
        $stmt->bindParam(':date_debut', $data['DATE_DEBUT_arret']);
        $stmt->bindParam(':date_fin', $data['DATE_FIN_arret'] ?? null);
        $stmt->bindParam(':duree', $data['DURE_arret'] ?? 0);

        if ($stmt->execute()) {
            $this->sendSuccess(['message' => 'Arrêt ajouté avec succès']);
        } else {
            $this->sendError('Erreur lors de l\'ajout de l\'arrêt', 500);
        }
    }

    // Ajouter du personnel à l'opération (via équipe)
    private function addPersonnelToOperation($data) {
        if (!isset($data['ID_operation']) || (!isset($data['ID_personnel']) && !isset($data['ID_soustraiteure']))) {
            $this->sendError('ID opération et ID personnel/sous-traiteur requis', 400);
        }

        // Récupérer l'équipe de l'opération
        $queryEquipe = "SELECT ID_equipe FROM operation WHERE ID_operation = :operation_id";
        $stmtEquipe = $this->conn->prepare($queryEquipe);
        $stmtEquipe->bindParam(':operation_id', $data['ID_operation']);
        $stmtEquipe->execute();
        
        if ($stmtEquipe->rowCount() == 0) {
            $this->sendError('Opération non trouvée', 404);
        }
        
        $equipeId = $stmtEquipe->fetch(PDO::FETCH_ASSOC)['ID_equipe'];

        if (empty($equipeId)) {
            $this->sendError('Aucune équipe assignée à cette opération', 400);
        }

        try {
            if (isset($data['ID_personnel'])) {
                // Ajouter personnel interne
                $queryPersonnel = "SELECT MATRICULE_personnel FROM personnel WHERE ID_personnel = :id";
                $stmtPersonnel = $this->conn->prepare($queryPersonnel);
                $stmtPersonnel->bindParam(':id', $data['ID_personnel']);
                $stmtPersonnel->execute();
                
                if ($stmtPersonnel->rowCount() == 0) {
                    $this->sendError('Personnel non trouvé', 404);
                }
                
                $matricule = $stmtPersonnel->fetch(PDO::FETCH_ASSOC)['MATRICULE_personnel'];
                
                $queryInsert = "
                    INSERT INTO equipe_has_personnel (equipe_ID_equipe, personnel_ID_personnel, personnel_MATRICULE_personnel)
                    VALUES (:equipe_id, :personnel_id, :matricule)
                ";
                
                $stmtInsert = $this->conn->prepare($queryInsert);
                $stmtInsert->bindParam(':equipe_id', $equipeId);
                $stmtInsert->bindParam(':personnel_id', $data['ID_personnel']);
                $stmtInsert->bindParam(':matricule', $matricule);
                $stmtInsert->execute();
                
            } else {
                // Ajouter sous-traitant
                $querySoustraitant = "SELECT MATRICULE_soustraiteure FROM soustraiteure WHERE ID_soustraiteure = :id";
                $stmtSoustraitant = $this->conn->prepare($querySoustraitant);
                $stmtSoustraitant->bindParam(':id', $data['ID_soustraiteure']);
                $stmtSoustraitant->execute();
                
                if ($stmtSoustraitant->rowCount() == 0) {
                    $this->sendError('Sous-traitant non trouvé', 404);
                }
                
                $matricule = $stmtSoustraitant->fetch(PDO::FETCH_ASSOC)['MATRICULE_soustraiteure'];
                
                $queryInsert = "
                    INSERT INTO equipe_has_soustraiteure (equipe_ID_equipe, soustraiteure_ID_soustraiteure, soustraiteure_MATRICULE_soustraiteure)
                    VALUES (:equipe_id, :soustraiteure_id, :matricule)
                ";
                
                $stmtInsert = $this->conn->prepare($queryInsert);
                $stmtInsert->bindParam(':equipe_id', $equipeId);
                $stmtInsert->bindParam(':soustraiteure_id', $data['ID_soustraiteure']);
                $stmtInsert->bindParam(':matricule', $matricule);
                $stmtInsert->execute();
            }
            
            $this->sendSuccess(['message' => 'Personnel ajouté à l\'équipe avec succès']);
            
        } catch (Exception $e) {
            $this->sendError('Erreur lors de l\'ajout du personnel: ' . $e->getMessage(), 500);
        }
    }

    // ===== MÉTHODES DELETE =====

    // Retirer un conteneur de l'opération
    private function removeConteneurFromOperation() {
        $conteneurId = $_GET['id'] ?? '';
        $operationId = $_GET['operation'] ?? '';
        
        if (empty($conteneurId) || empty($operationId)) {
            $this->sendError('ID conteneur et ID opération requis', 400);
        }

        try {
            $this->conn->beginTransaction();

            // Méthode 1: Mettre à jour le conteneur
            $query1 = "
                UPDATE conteneure 
                SET DERNIERE_OPERATION = NULL 
                WHERE ID_conteneure = :conteneur_id AND DERNIERE_OPERATION = :operation_id
            ";

            $stmt1 = $this->conn->prepare($query1);
            $stmt1->bindParam(':conteneur_id', $conteneurId);
            $stmt1->bindParam(':operation_id', $operationId);
            $stmt1->execute();

            // Méthode 2: Retirer de la liste des conteneurs de l'opération
            $queryGet = "SELECT ID_conteneure FROM operation WHERE ID_operation = :operation_id";
            $stmtGet = $this->conn->prepare($queryGet);
            $stmtGet->bindParam(':operation_id', $operationId);
            $stmtGet->execute();
            
            $result = $stmtGet->fetch(PDO::FETCH_ASSOC);
            $currentConteneurs = $result['ID_conteneure'] ?? '';
            
            if (!empty($currentConteneurs)) {
                // Retirer le conteneur de la liste
                $conteneursArray = array_map('trim', explode(',', $currentConteneurs));
                $conteneursArray = array_filter($conteneursArray, function($id) use ($conteneurId) {
                    return $id !== $conteneurId;
                });
                $newConteneurs = implode(',', $conteneursArray);

                $queryUpdate = "
                    UPDATE operation 
                    SET ID_conteneure = :conteneurs 
                    WHERE ID_operation = :operation_id
                ";

                $stmtUpdate = $this->conn->prepare($queryUpdate);
                $stmtUpdate->bindParam(':conteneurs', $newConteneurs);
                $stmtUpdate->bindParam(':operation_id', $operationId);
                $stmtUpdate->execute();
            }

            $this->conn->commit();
            $this->sendSuccess(['message' => 'Conteneur retiré de l\'opération']);

        } catch (Exception $e) {
            $this->conn->rollback();
            $this->sendError('Erreur lors du retrait du conteneur: ' . $e->getMessage(), 500);
        }
    }

    // Retirer un équipement de l'opération
    private function removeEquipementFromOperation() {
        $enginId = $_GET['id'] ?? '';
        $operationId = $_GET['operation'] ?? '';
        
        if (empty($enginId) || empty($operationId)) {
            $this->sendError('ID engin et ID opération requis', 400);
        }

        // Récupérer les engins actuels
        $queryGet = "SELECT ID_engin FROM operation WHERE ID_operation = :operation_id";
        $stmtGet = $this->conn->prepare($queryGet);
        $stmtGet->bindParam(':operation_id', $operationId);
        $stmtGet->execute();
        
        if ($stmtGet->rowCount() == 0) {
            $this->sendError('Opération non trouvée', 404);
        }
        
        $result = $stmtGet->fetch(PDO::FETCH_ASSOC);
        $currentEngins = $result['ID_engin'] ?? '';
        
        // Retirer l'engin de la liste
        $enginsArray = array_map('trim', explode(',', $currentEngins));
        $enginsArray = array_filter($enginsArray, function($id) use ($enginId) {
            return $id !== $enginId;
        });
        $newEngins = implode(',', $enginsArray);

        $queryUpdate = "
            UPDATE operation 
            SET ID_engin = :engins 
            WHERE ID_operation = :operation_id
        ";

        $stmtUpdate = $this->conn->prepare($queryUpdate);
        $stmtUpdate->bindParam(':engins', $newEngins);
        $stmtUpdate->bindParam(':operation_id', $operationId);

        if ($stmtUpdate->execute()) {
            $this->sendSuccess(['message' => 'Équipement retiré de l\'opération']);
        } else {
            $this->sendError('Erreur lors du retrait de l\'équipement', 500);
        }
    }

    // Supprimer un arrêt
    private function removeArret() {
        $arretId = $_GET['id'] ?? '';
        
        if (empty($arretId)) {
            $this->sendError('ID arrêt requis', 400);
        }

        $query = "DELETE FROM arret WHERE ID_arret = :arret_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':arret_id', $arretId);

        if ($stmt->execute()) {
            $this->sendSuccess(['message' => 'Arrêt supprimé avec succès']);
        } else {
            $this->sendError('Erreur lors de la suppression de l\'arrêt', 500);
        }
    }

    // Retirer du personnel de l'opération (via équipe)
    private function removePersonnelFromOperation() {
        $matricule = $_GET['matricule'] ?? '';
        $operationId = $_GET['operation'] ?? '';
        $type = $_GET['type'] ?? 'personnel'; // 'personnel' ou 'soustraitant'
        
        if (empty($matricule) || empty($operationId)) {
            $this->sendError('Matricule et ID opération requis', 400);
        }

        // Récupérer l'équipe de l'opération
        $queryEquipe = "SELECT ID_equipe FROM operation WHERE ID_operation = :operation_id";
        $stmtEquipe = $this->conn->prepare($queryEquipe);
        $stmtEquipe->bindParam(':operation_id', $operationId);
        $stmtEquipe->execute();
        
        if ($stmtEquipe->rowCount() == 0) {
            $this->sendError('Opération non trouvée', 404);
        }
        
        $equipeId = $stmtEquipe->fetch(PDO::FETCH_ASSOC)['ID_equipe'];

        if (empty($equipeId)) {
            $this->sendError('Aucune équipe assignée à cette opération', 400);
        }

        try {
            if ($type === 'personnel') {
                $query = "
                    DELETE FROM equipe_has_personnel 
                    WHERE equipe_ID_equipe = :equipe_id AND personnel_MATRICULE_personnel = :matricule
                ";
            } else {
                $query = "
                    DELETE FROM equipe_has_soustraiteure 
                    WHERE equipe_ID_equipe = :equipe_id AND soustraiteure_MATRICULE_soustraiteure = :matricule
                ";
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':equipe_id', $equipeId);
            $stmt->bindParam(':matricule', $matricule);
            
            if ($stmt->execute()) {
                $this->sendSuccess(['message' => 'Personnel retiré de l\'équipe avec succès']);
            } else {
                $this->sendError('Erreur lors du retrait du personnel', 500);
            }
            
        } catch (Exception $e) {
            $this->sendError('Erreur lors du retrait du personnel: ' . $e->getMessage(), 500);
        }
    }

    // ===== MÉTHODES UTILITAIRES =====

    // Envoyer une réponse de succès
    private function sendSuccess($data = null, $message = 'Succès') {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Envoyer une réponse d'erreur
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => $code,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Valider les données d'entrée
    private function validateInput($data, $requiredFields) {
        $errors = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Champ requis manquant ou vide: $field";
            }
        }
        
        if (!empty($errors)) {
            $this->sendError(implode(', ', $errors), 400);
        }
        
        return true;
    }

    // Logger les actions (optionnel)
    private function logAction($action, $operationId, $details = []) {
        // Implémentation optionnelle pour tracer les actions
        error_log(sprintf(
            "[OPERATION_DETAIL] %s - Action: %s, Operation: %s, Details: %s",
            date('Y-m-d H:i:s'),
            $action,
            $operationId,
            json_encode($details)
        ));
    }
}

// ===== TRAITEMENT DE LA REQUÊTE =====
try {
    // Validation des paramètres de base
    if (!isset($_GET['action'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Paramètre action requis',
            'error_code' => 400
        ]);
        exit();
    }

    $api = new OperationDetailAPI();
    $api->handleRequest();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur interne',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
?>