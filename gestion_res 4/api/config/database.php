<?php
class Database {
    // Paramètres de connexion à la base de données
    private $host = "localhost";
    private $db_name = "gestion_res";
    private $username = "root";
    private $password = "";
    private $port = "3309"; // Assurez-vous que ce port est correct
    private $conn;
    
    public function getHost() { return $this->host; }
    public function getPort() { return $this->port; }
    public function getDbName() { return $this->db_name; }
    public function getUsername() { return $this->username; }
    
    /**
     * Méthode pour établir la connexion à la base de données
     * @return PDO|null Objet de connexion PDO ou null en cas d'erreur
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            return $this->conn;
        } catch (PDOException $e) {
            // Journaliser l'erreur
            error_log('Erreur de connexion à la base de données: ' . $e->getMessage());
            
            // Retourner null au lieu de quitter le script
            return null;
        }
    }
}
?>
