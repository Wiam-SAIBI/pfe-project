<?php
/**
 * Contrôleur d'authentification pour la gestion des utilisateurs
 */
class AuthController {
    private $conn;
    private $table_name = "utilisateurs";
    
    /**
     * Constructeur
     * @param PDO $db Connexion à la base de données
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Vérifie si un utilisateur est authentifié
     * @return bool True si l'utilisateur est authentifié, false sinon
     */
    public function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Authentifie un utilisateur
     * @param string $username Nom d'utilisateur
     * @param string $password Mot de passe
     * @return bool|array Données de l'utilisateur en cas de succès, false sinon
     */
    public function login($username, $password) {
        // Vérifier si les champs sont remplis
        if (empty($username) || empty($password)) {
            return false;
        }
        
        // Préparer la requête
        $query = "SELECT id_utilisateur, nom_utilisateur, mot_de_passe, role 
                  FROM " . $this->table_name . " 
                  WHERE nom_utilisateur = :username 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        
        // Nettoyer les données
        $username = htmlspecialchars(strip_tags($username));
        
        // Lier les paramètres
        $stmt->bindParam(':username', $username);
        
        // Exécuter la requête
        $stmt->execute();
        
        // Vérifier si l'utilisateur existe
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Vérifier le mot de passe
            if (password_verify($password, $row['mot_de_passe'])) {
                // Créer une session
                $_SESSION['user_id'] = $row['id_utilisateur'];
                $_SESSION['username'] = $row['nom_utilisateur'];
                $_SESSION['role'] = $row['role'];
                
                return [
                    'id' => $row['id_utilisateur'],
                    'username' => $row['nom_utilisateur'],
                    'role' => $row['role']
                ];
            }
        }
        
        return false;
    }
    
    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        // Détruire toutes les variables de session
        $_SESSION = [];
        
        // Détruire la session
        session_destroy();
        
        return true;
    }
    
    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     * @param string $role Rôle à vérifier
     * @return bool True si l'utilisateur a le rôle, false sinon
     */
    public function hasRole($role) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        return $_SESSION['role'] === $role;
    }
}
?>