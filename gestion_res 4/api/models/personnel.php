<?php
/**
 * Classe modèle pour la gestion du personnel
 */
class Personnel {
    // Connexion à la base de données et nom de la table
    private $conn;
    private $table_name = "personnel";
    
    // Propriétés
    public $ID_personnel;
    public $MATRICULE_personnel;
    public $NOM_personnel;
    public $PRENOM_personnel;
    public $FONCTION_personnel;
    public $CONTACT_personnel;
    public $ID_equipe;
    public $NOM_equipe;
    public $DATE_CREATION;
    
    /**
     * Constructeur avec $db pour la connexion à la base de données
     * @param PDO $db Connexion à la base de données
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Lire tous les enregistrements
     * @return PDOStatement Résultat de la requête
     */
    public function read() {
        // Requête pour lire tous les enregistrements
        $query = "SELECT p.*, e.NOM_equipe 
                FROM " . $this->table_name . " p 
                LEFT JOIN equipes e ON p.ID_equipe = e.ID_equipe 
                ORDER BY p.NOM_personnel ASC";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Exécuter la requête
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Lire un seul enregistrement
     * @return bool Succès de l'opération
     */
    public function readOne() {
        // Requête pour lire un seul enregistrement
        $query = "SELECT p.*, e.NOM_equipe 
                FROM " . $this->table_name . " p 
                LEFT JOIN equipes e ON p.ID_equipe = e.ID_equipe 
                WHERE p.ID_personnel = ? OR p.MATRICULE_personnel = ?
                LIMIT 0,1";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(1, $this->ID_personnel);
        $stmt->bindParam(2, $this->MATRICULE_personnel);
        
        // Exécuter la requête
        $stmt->execute();
        
        // Récupérer l'enregistrement
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Vérifier si un enregistrement a été trouvé
        if ($row) {
            // Assigner les valeurs aux propriétés de l'objet
            $this->ID_personnel = $row['ID_personnel'];
            $this->MATRICULE_personnel = $row['MATRICULE_personnel'];
            $this->NOM_personnel = $row['NOM_personnel'];
            $this->PRENOM_personnel = $row['PRENOM_personnel'];
            $this->FONCTION_personnel = $row['FONCTION_personnel'];
            $this->CONTACT_personnel = $row['CONTACT_personnel'];
            $this->ID_equipe = $row['ID_equipe'];
            $this->NOM_equipe = $row['NOM_equipe'] ?? null;
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Générer un matricule unique
     * @return string Matricule généré
     */
    private function generateMatricule() {
        // Définir le préfixe
        $prefix = "EMP";
        
        // Requête pour obtenir le dernier ID
        $query = "SELECT MAX(ID_personnel) as max_id FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $next_id = ($row['max_id'] ?? 0) + 1;
        
        // Formater le matricule
        return $prefix . str_pad($next_id, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Créer un nouvel enregistrement
     * @return bool Succès de l'opération
     */
    public function create() {
        // Générer le matricule
        $matricule = $this->generateMatricule();
        
        // Requête pour insérer un nouvel enregistrement
        $query = "INSERT INTO " . $this->table_name . "
                (MATRICULE_personnel, NOM_personnel, PRENOM_personnel, FONCTION_personnel, CONTACT_personnel, DATE_CREATION)
                VALUES
                (:matricule, :nom, :prenom, :fonction, :contact, NOW())";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyer les données
        $this->NOM_personnel = htmlspecialchars(strip_tags($this->NOM_personnel));
        $this->PRENOM_personnel = htmlspecialchars(strip_tags($this->PRENOM_personnel));
        $this->FONCTION_personnel = htmlspecialchars(strip_tags($this->FONCTION_personnel));
        $this->CONTACT_personnel = htmlspecialchars(strip_tags($this->CONTACT_personnel));
        
        // Liaison des valeurs
        $stmt->bindParam(":matricule", $matricule);
        $stmt->bindParam(":nom", $this->NOM_personnel);
        $stmt->bindParam(":prenom", $this->PRENOM_personnel);
        $stmt->bindParam(":fonction", $this->FONCTION_personnel);
        $stmt->bindParam(":contact", $this->CONTACT_personnel);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Récupérer l'ID généré
            $this->ID_personnel = $this->conn->lastInsertId();
            $this->MATRICULE_personnel = $matricule;
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Mettre à jour un enregistrement
     * @return bool Succès de l'opération
     */
    public function update() {
        // Requête pour mettre à jour un enregistrement
        $query = "UPDATE " . $this->table_name . "
                SET
                    NOM_personnel = :nom,
                    PRENOM_personnel = :prenom,
                    FONCTION_personnel = :fonction,
                    CONTACT_personnel = :contact
                WHERE ID_personnel = :id OR MATRICULE_personnel = :matricule";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyer les données
        $this->NOM_personnel = htmlspecialchars(strip_tags($this->NOM_personnel));
        $this->PRENOM_personnel = htmlspecialchars(strip_tags($this->PRENOM_personnel));
        $this->FONCTION_personnel = htmlspecialchars(strip_tags($this->FONCTION_personnel));
        $this->CONTACT_personnel = htmlspecialchars(strip_tags($this->CONTACT_personnel));
        $this->ID_personnel = htmlspecialchars(strip_tags($this->ID_personnel));
        $this->MATRICULE_personnel = htmlspecialchars(strip_tags($this->MATRICULE_personnel));
        
        // Liaison des valeurs
        $stmt->bindParam(":nom", $this->NOM_personnel);
        $stmt->bindParam(":prenom", $this->PRENOM_personnel);
        $stmt->bindParam(":fonction", $this->FONCTION_personnel);
        $stmt->bindParam(":contact", $this->CONTACT_personnel);
        $stmt->bindParam(":id", $this->ID_personnel);
        $stmt->bindParam(":matricule", $this->MATRICULE_personnel);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Supprimer un enregistrement
     * @return bool Succès de l'opération
     */
    public function delete() {
        // Requête pour supprimer un enregistrement
        $query = "DELETE FROM " . $this->table_name . " WHERE ID_personnel = ? OR MATRICULE_personnel = ?";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(1, $this->ID_personnel);
        $stmt->bindParam(2, $this->MATRICULE_personnel);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Rechercher des enregistrements
     * @param string $keywords Mots-clés à rechercher
     * @return PDOStatement Résultat de la requête
     */
    public function search($keywords) {
        // Requête pour rechercher des enregistrements
        $query = "SELECT p.*, e.NOM_equipe 
                FROM " . $this->table_name . " p 
                LEFT JOIN equipes e ON p.ID_equipe = e.ID_equipe 
                WHERE p.MATRICULE_personnel LIKE ? OR p.NOM_personnel LIKE ? OR p.PRENOM_personnel LIKE ? OR p.FONCTION_personnel LIKE ?
                ORDER BY p.NOM_personnel ASC";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyer et formater les mots-clés
        $keywords = htmlspecialchars(strip_tags($keywords));
        $keywords = "%{$keywords}%";
        
        // Liaison des paramètres
        $stmt->bindParam(1, $keywords);
        $stmt->bindParam(2, $keywords);
        $stmt->bindParam(3, $keywords);
        $stmt->bindParam(4, $keywords);
        
        // Exécuter la requête
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtenir le nombre total d'employés
     * @return int Nombre total d'employés
     */
    public function getCount() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'];
    }
    
    /**
     * Obtenir les statistiques par fonction
     * @return PDOStatement Résultat de la requête
     */
    public function getStatsByFunction() {
        $query = "SELECT FONCTION_personnel, COUNT(*) as count 
                FROM " . $this->table_name . " 
                GROUP BY FONCTION_personnel 
                ORDER BY count DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtenir le dernier membre du personnel ajouté
     * @return array|null Données du dernier membre ajouté ou null
     */
    public function getLastAdded() {
        $query = "SELECT * FROM " . $this->table_name . " 
                ORDER BY DATE_CREATION DESC, ID_personnel DESC 
                LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return array(
                "ID_personnel" => $row['ID_personnel'],
                "MATRICULE_personnel" => $row['MATRICULE_personnel'],
                "NOM_personnel" => $row['NOM_personnel'],
                "PRENOM_personnel" => $row['PRENOM_personnel'],
                "DATE_CREATION" => $row['DATE_CREATION']
            );
        }
        
        return null;
    }
    
    /**
     * Assigne un membre du personnel à une équipe
     * @param string $equipeId ID de l'équipe
     * @param string $role Rôle dans l'équipe (optionnel)
     * @return bool Succès de l'opération
     */
    public function assignToTeam($equipeId, $role = '') {
        // Si un nouveau rôle est spécifié, mettre à jour la fonction
        $updateRole = !empty($role) ? ", FONCTION_personnel = :role" : "";
        
        // Requête pour assigner un employé à une équipe
        $query = "UPDATE " . $this->table_name . "
                SET ID_equipe = :equipe" . $updateRole . "
                WHERE ID_personnel = :id OR MATRICULE_personnel = :matricule";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyer les données
        $equipeId = htmlspecialchars(strip_tags($equipeId));
        
        // Liaison des valeurs
        $stmt->bindParam(":equipe", $equipeId);
        $stmt->bindParam(":id", $this->ID_personnel);
        $stmt->bindParam(":matricule", $this->MATRICULE_personnel);
        
        if (!empty($role)) {
            $role = htmlspecialchars(strip_tags($role));
            $stmt->bindParam(":role", $role);
        }
        
        // Exécuter la requête
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
}
?>