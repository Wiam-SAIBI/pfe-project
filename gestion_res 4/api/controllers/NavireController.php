<?php
class NavireController {
    // Connexion à la base de données et table
    private $conn;
    private $table_name = "navires";

    // Constructeur avec $db pour la connexion à la base de données
    public function __construct($db) {
        $this->conn = $db;
    }

    // Obtenir tous les navires
    public function getAll() {
        // Récupérer les paramètres de filtre
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $status = isset($_GET['status']) && $_GET['status'] != 'all' ? $_GET['status'] : null;
        $dateRange = isset($_GET['dateRange']) ? $_GET['dateRange'] : 'all';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = ITEMS_PER_PAGE;
        $offset = ($page - 1) * $perPage;
        
        // Compter le nombre total d'enregistrements (pour la pagination)
        $countQuery = $this->buildQuery(true, $search, $status, $dateRange);
        $countStmt = $this->conn->prepare($countQuery);
        $this->bindFilterParams($countStmt, $search, $status, $dateRange);
        $countStmt->execute();
        $totalItems = (int)$countStmt->fetchColumn();
        
        // Récupérer les données des navires
        $query = $this->buildQuery(false, $search, $status, $dateRange);
        $query .= " LIMIT :offset, :perPage";
        
        $stmt = $this->conn->prepare($query);
        $this->bindFilterParams($stmt, $search, $status, $dateRange);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        
        // Préparer la réponse
        $navires = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $navires[] = $this->formatNavireData($row);
        }
        
        // Retourner la réponse finale avec la pagination
        return [
            'records' => $navires,
            'pagination' => [
                'totalItems' => $totalItems,
                'currentPage' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($totalItems / $perPage)
            ]
        ];
    }
    
    // Obtenir un navire spécifique
    public function getOne($id) {
        // Requête principale pour les informations du navire
        $query = "SELECT 
                    n.id, 
                    n.nom, 
                    n.matricule, 
                    n.statut,
                    n.date_creation,
                    n.date_modification
                FROM 
                    " . $this->table_name . " n
                WHERE 
                    n.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $navire = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$navire) {
            return null;
        }
        
        // Requête pour obtenir les escales associées
        $escalesQuery = "SELECT 
                            e.id,
                            e.date_arrivee,
                            e.date_depart,
                            e.quai,
                            e.statut
                        FROM 
                            escales e
                        WHERE 
                            e.navire_id = :navire_id
                        ORDER BY 
                            e.date_arrivee DESC";
        
        $escalesStmt = $this->conn->prepare($escalesQuery);
        $escalesStmt->bindParam(':navire_id', $id);
        $escalesStmt->execute();
        
        $escales = [];
        while ($escale = $escalesStmt->fetch(PDO::FETCH_ASSOC)) {
            $escales[] = $escale;
        }
        
        // Requête pour obtenir les activités récentes
        $activitiesQuery = "SELECT 
                            a.id,
                            a.date,
                            a.type,
                            a.details
                        FROM 
                            activities a
                        WHERE 
                            a.navire_id = :navire_id
                        ORDER BY 
                            a.date DESC
                        LIMIT 10";
        
        $activitiesStmt = $this->conn->prepare($activitiesQuery);
        $activitiesStmt->bindParam(':navire_id', $id);
        $activitiesStmt->execute();
        
        $activities = [];
        while ($activity = $activitiesStmt->fetch(PDO::FETCH_ASSOC)) {
            $activities[] = $activity;
        }
        
        // Ajouter les escales et activités au navire
        $navire['escales'] = $escales;
        $navire['activities'] = $activities;
        
        return $navire;
    }
    
    // Créer un nouveau navire
    public function create() {
        $data = json_decode(file_get_contents("php://input"));
        
        if (!isset($data->nom) || !isset($data->matricule)) {
            return ['error' => 'Les données sont incomplètes'];
        }
        
        // Vérifier si le matricule existe déjà
        $checkQuery = "SELECT id FROM " . $this->table_name . " WHERE matricule = :matricule";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(':matricule', $data->matricule);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            return ['error' => 'Un navire avec ce matricule existe déjà'];
        }
        
        // Générer un ID unique
        $nextId = $this->getNextId();
        $id = generateFormattedId("NAV", $nextId);
        
        // Préparer la requête d'insertion
        $query = "INSERT INTO " . $this->table_name . " 
                (id, nom, matricule, statut) 
                VALUES 
                (:id, :nom, :matricule, :statut)";
        
        $stmt = $this->conn->prepare($query);
        
        // Nettoyer et lier les données
        $nom = sanitizeInput($data->nom);
        $matricule = sanitizeInput($data->matricule);
        $statut = isset($data->statut) ? sanitizeInput($data->statut) : 'Attendu';
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':statut', $statut);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Journaliser l'action
            $this->logAction($id, 'Création', "Création du navire $nom");
            
            return [
                'id' => $id,
                'message' => 'Navire créé avec succès'
            ];
        }
        
        return ['error' => 'Impossible de créer le navire'];
    }
    
    // Mettre à jour un navire
    public function update($id) {
        $data = json_decode(file_get_contents("php://input"));
        
        if (!isset($data->nom) || !isset($data->matricule) || !isset($data->statut)) {
            return ['error' => 'Les données sont incomplètes'];
        }
        
        // Vérifier que le navire existe
        $checkQuery = "SELECT id, nom FROM " . $this->table_name . " WHERE id = :id";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() == 0) {
            return ['error' => 'Navire non trouvé'];
        }
        
        $oldNavire = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Vérifier si le matricule existe déjà (pour un autre navire)
        $matriculeQuery = "SELECT id FROM " . $this->table_name . " WHERE matricule = :matricule AND id != :id";
        $matriculeStmt = $this->conn->prepare($matriculeQuery);
        $matriculeStmt->bindParam(':matricule', $data->matricule);
        $matriculeStmt->bindParam(':id', $id);
        $matriculeStmt->execute();
        
        if ($matriculeStmt->rowCount() > 0) {
            return ['error' => 'Un autre navire avec ce matricule existe déjà'];
        }
        
        // Préparer la requête de mise à jour
        $query = "UPDATE " . $this->table_name . " 
                SET 
                    nom = :nom, 
                    matricule = :matricule,
                    statut = :statut,
                    date_modification = NOW()
                WHERE 
                    id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        // Nettoyer et lier les données
        $nom = sanitizeInput($data->nom);
        $matricule = sanitizeInput($data->matricule);
        $statut = sanitizeInput($data->statut);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':statut', $statut);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Journaliser l'action
            $this->logAction($id, 'Modification', "Modification du navire {$oldNavire['nom']} -> $nom");
            
            return [
                'message' => 'Navire mis à jour avec succès'
            ];
        }
        
        return ['error' => 'Impossible de mettre à jour le navire'];
    }
    
    // Supprimer un navire
    public function delete($id) {
        // Vérifier que le navire existe
        $checkQuery = "SELECT id, nom FROM " . $this->table_name . " WHERE id = :id";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() == 0) {
            return ['error' => 'Navire non trouvé'];
        }
        
        $navire = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Préparer la requête de suppression
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Journaliser l'action
            $this->logAction($id, 'Suppression', "Suppression du navire {$navire['nom']}");
            
            return [
                'message' => 'Navire supprimé avec succès'
            ];
        }
        
        return ['error' => 'Impossible de supprimer le navire'];
    }
    
    // Obtenir les statistiques des navires
    public function getStats() {
        $query = "SELECT 
                    statut,
                    COUNT(*) as count
                FROM 
                    " . $this->table_name . "
                GROUP BY 
                    statut";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $stats = [
            'En escale' => 0,
            'Attendu' => 0,
            'Parti' => 0
        ];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['statut']] = (int)$row['count'];
        }
        
        return [
            'labels' => array_keys($stats),
            'data' => array_values($stats)
        ];
    }
    
    // Obtenir les activités récentes des navires
    public function getRecentActivities($limit = 5) {
        $query = "SELECT 
                    a.id,
                    a.date,
                    a.type,
                    n.nom as navire_nom,
                    a.details
                FROM 
                    activities a
                LEFT JOIN 
                    navires n ON a.navire_id = n.id
                WHERE 
                    a.navire_id IS NOT NULL
                ORDER BY 
                    a.date DESC
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $activities = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activities[] = [
                'id' => $row['id'],
                'date' => $row['date'],
                'type' => $row['type'],
                'navire' => $row['navire_nom'],
                'details' => $row['details']
            ];
        }
        
        return ['records' => $activities];
    }
    
    // Méthodes utilitaires privées
    
    // Construire la requête avec les filtres
    private function buildQuery($isCount = false, $search = '', $status = null, $dateRange = 'all') {
        if ($isCount) {
            $select = "SELECT COUNT(DISTINCT n.id) as total";
        } else {
            $select = "SELECT 
                        n.id, 
                        n.nom, 
                        n.matricule, 
                        n.statut,
                        (SELECT MAX(e1.date_arrivee) FROM escales e1 WHERE e1.navire_id = n.id AND e1.date_arrivee <= NOW()) as derniere_escale,
                        (SELECT MIN(e2.date_arrivee) FROM escales e2 WHERE e2.navire_id = n.id AND e2.date_arrivee > NOW()) as prochaine_escale";
        }
        
        $query = "$select
                FROM 
                    " . $this->table_name . " n
                LEFT JOIN 
                    escales e ON n.id = e.navire_id
                WHERE 1=1";
        
        // Ajouter les conditions de recherche
        if (!empty($search)) {
            $query .= " AND (n.nom LIKE :search OR n.matricule LIKE :search)";
        }
        
        // Filtrer par statut
        if ($status !== null) {
            $query .= " AND n.statut = :status";
        }
        
        // Filtrer par période
        if ($dateRange != 'all') {
            switch ($dateRange) {
                case 'today':
                    $query .= " AND (DATE(e.date_arrivee) = CURRENT_DATE OR DATE(e.date_depart) = CURRENT_DATE)";
                    break;
                case 'this_week':
                    $query .= " AND (
                        YEARWEEK(e.date_arrivee, 1) = YEARWEEK(CURRENT_DATE, 1) OR 
                        YEARWEEK(e.date_depart, 1) = YEARWEEK(CURRENT_DATE, 1)
                    )";
                    break;
                case 'this_month':
                    $query .= " AND (
                        (YEAR(e.date_arrivee) = YEAR(CURRENT_DATE) AND MONTH(e.date_arrivee) = MONTH(CURRENT_DATE)) OR 
                        (YEAR(e.date_depart) = YEAR(CURRENT_DATE) AND MONTH(e.date_depart) = MONTH(CURRENT_DATE))
                    )";
                    break;
            }
        }
        
        if (!$isCount) {
            $query .= " GROUP BY n.id, n.nom, n.matricule, n.statut";
            $query .= " ORDER BY 
                        CASE 
                            WHEN n.statut = 'En escale' THEN 1 
                            WHEN n.statut = 'Attendu' THEN 2 
                            ELSE 3 
                        END, 
                        n.nom ASC";
        }
        
        return $query;
    }
    
    // Lier les paramètres de filtre
    private function bindFilterParams($stmt, $search = '', $status = null, $dateRange = 'all') {
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        
        if ($status !== null) {
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        }
    }
    
    // Formater les données du navire
    private function formatNavireData($row) {
        $derniereEscale = $row['derniere_escale'] ? $row['derniere_escale'] : null;
        $prochaineEscale = $row['prochaine_escale'] ? $row['prochaine_escale'] : null;
        
        return [
            'id' => $row['id'],
            'nom' => $row['nom'],
            'matricule' => $row['matricule'],
            'statut' => $row['statut'],
            'derniereEscale' => $derniereEscale,
            'prochaineEscale' => $prochaineEscale
        ];
    }
    
    // Obtenir le prochain ID numérique
    private function getNextId() {
        $query = "SELECT COUNT(*) + 1 as next_id FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['next_id'];
    }
    
    // Journaliser une action
    private function logAction($navireId, $type, $details) {
        $query = "INSERT INTO activities (date, type, navire_id, details) 
                  VALUES (NOW(), :type, :navire_id, :details)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':navire_id', $navireId);
        $stmt->bindParam(':details', $details);
        
        $stmt->execute();
    }
}
?>