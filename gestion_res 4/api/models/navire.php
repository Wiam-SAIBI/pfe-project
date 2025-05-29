<?php
class Navire {
    // Connexion à la base de données
    private $conn;
    private $table_name = "navires";
    
    // Propriétés de l'objet
    public $id;
    public $nom;
    public $matricule;
    public $statut;
    public $derniere_escale;
    public $prochaine_escale;
    
    // Constructeur avec $db pour la connexion à la base de données
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Lire tous les navires
    public function read($searchTerm = '', $status = 'all', $dateRange = 'all') {
        // Requête SQL de base
        $query = "SELECT 
                    n.id, 
                    n.nom, 
                    n.matricule, 
                    n.statut,
                    e1.date_arrivee as derniere_escale,
                    e2.date_arrivee as prochaine_escale
                FROM 
                    " . $this->table_name . " n
                LEFT JOIN 
                    escales e1 ON n.id = e1.navire_id AND e1.date_arrivee <= NOW()
                LEFT JOIN 
                    escales e2 ON n.id = e2.navire_id AND e2.date_arrivee > NOW()
                WHERE 1";
        
        // Ajouter les conditions de recherche si nécessaire
        if (!empty($searchTerm)) {
            $query .= " AND (n.nom LIKE :searchTerm OR n.matricule LIKE :searchTerm)";
        }
        
        // Filtrer par statut si nécessaire
        if ($status != 'all') {
            $query .= " AND n.statut = :status";
        }
        
        // Filtrer par plage de dates si nécessaire
        if ($dateRange != 'all') {
            switch ($dateRange) {
                case 'today':
                    $query .= " AND (DATE(e1.date_arrivee) = CURDATE() OR DATE(e2.date_arrivee) = CURDATE())";
                    break;
                case 'this_week':
                    $query .= " AND (YEARWEEK(e1.date_arrivee, 1) = YEARWEEK(CURDATE(), 1) OR YEARWEEK(e2.date_arrivee, 1) = YEARWEEK(CURDATE(), 1))";
                    break;
                case 'this_month':
                    $query .= " AND (YEAR(e1.date_arrivee) = YEAR(CURDATE()) AND MONTH(e1.date_arrivee) = MONTH(CURDATE()) 
                                OR YEAR(e2.date_arrivee) = YEAR(CURDATE()) AND MONTH(e2.date_arrivee) = MONTH(CURDATE()))";
                    break;
            }
        }
        
        // Regrouper et ordonner
        $query .= " GROUP BY n.id ORDER BY CASE 
                        WHEN n.statut = 'En escale' THEN 1 
                        WHEN n.statut = 'Attendu' THEN 2 
                        ELSE 3 
                    END, 
                    n.nom ASC";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Lier les paramètres si nécessaire
        if (!empty($searchTerm)) {
            $searchParam = "%" . $searchTerm . "%";
            $stmt->bindParam(":searchTerm", $searchParam);
        }
        
        if ($status != 'all') {
            $stmt->bindParam(":status", $status);
        }
        
        // Exécuter la requête
        $stmt->execute();
        
        return $stmt;
    }
    
    // Lire un seul navire
    public function readOne() {
        // Requête pour lire un seul enregistrement
        $query = "SELECT 
                    n.id, 
                    n.nom, 
                    n.matricule, 
                    n.statut,
                    e1.date_arrivee as derniere_escale,
                    e2.date_arrivee as prochaine_escale
                FROM 
                    " . $this->table_name . " n
                LEFT JOIN 
                    escales e1 ON n.id = e1.navire_id AND e1.date_arrivee <= NOW()
                LEFT JOIN 
                    escales e2 ON n.id = e2.navire_id AND e2.date_arrivee > NOW()
                WHERE 
                    n.id = ?
                LIMIT 0,1";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Lier l'ID
        $stmt->bindParam(1, $this->id);
        
        // Exécuter la requête
        $stmt->execute();
        
        // Récupérer l'enregistrement
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Définir les valeurs
            $this->id = $row["id"];
            $this->nom = $row["nom"];
            $this->matricule = $row["matricule"];
            $this->statut = $row["statut"];
            $this->derniere_escale = $row["derniere_escale"];
            $this->prochaine_escale = $row["prochaine_escale"];
            
            return $row;
        }
        
        return null;
    }
    
    // Créer un navire
    public function create() {
        // Générer un nouvel ID
        $nextId = $this->getNextId();
        $this->id = generateFormattedId("NAV", $nextId);
        
        // Requête d'insertion
        $query = "INSERT INTO " . $this->table_name . " 
                (id, nom, matricule, statut) 
                VALUES
                (:id, :nom, :matricule, :statut)";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyer les données
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->matricule = htmlspecialchars(strip_tags($this->matricule));
        $this->statut = htmlspecialchars(strip_tags($this->statut));
        
        // Lier les données
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":matricule", $this->matricule);
        $stmt->bindParam(":statut", $this->statut);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Journaliser l'action
            logAction('CREATE', 'navires', $this->id, "Création du navire {$this->nom}");
            return true;
        }
        
        // Journaliser l'erreur
        logError("Erreur lors de la création du navire : " . $stmt->errorInfo()[2]);
        return false;
    }
    
    // Mettre à jour un navire
    public function update() {
        // Requête de mise à jour
        $query = "UPDATE " . $this->table_name . " 
                SET
                    nom = :nom,
                    matricule = :matricule,
                    statut = :statut
                WHERE
                    id = :id";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyer les données
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->matricule = htmlspecialchars(strip_tags($this->matricule));
        $this->statut = htmlspecialchars(strip_tags($this->statut));
        
        // Lier les données
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":matricule", $this->matricule);
        $stmt->bindParam(":statut", $this->statut);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Journaliser l'action
            logAction('UPDATE', 'navires', $this->id, "Mise à jour du navire {$this->nom}");
            return true;
        }
        
        // Journaliser l'erreur
        logError("Erreur lors de la mise à jour du navire : " . $stmt->errorInfo()[2]);
        return false;
    }
    
    // Supprimer un navire
    public function delete() {
        // Récupérer les infos du navire avant suppression pour le log
        $this->readOne();
        
        // Requête de suppression
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyer l'ID
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Lier l'ID
        $stmt->bindParam(1, $this->id);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Journaliser l'action
            logAction('DELETE', 'navires', $this->id, "Suppression du navire {$this->nom}");
            return true;
        }
        
        // Journaliser l'erreur
        logError("Erreur lors de la suppression du navire : " . $stmt->errorInfo()[2]);
        return false;
    }
    
    // Obtenir le prochain ID numérique
    private function getNextId() {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['count'] + 1;
    }
}

// Fonction pour journaliser les actions
function logAction($action, $table, $record_id, $description) {
    // Dans une implémentation réelle, vous inséreriez cette information dans une table d'audit
    // Pour l'instant, nous utilisons simplement la fonction de journalisation des erreurs
    logError("$action sur la table $table, enregistrement #$record_id : $description", 'INFO');
}
?>