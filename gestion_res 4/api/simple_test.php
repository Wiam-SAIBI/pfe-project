<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Définir la classe Database directement pour tester
class Database {
    private $host = "localhost";
    private $db_name = "gestion_res";
    private $username = "root";
    private $password = "";
    private $port = "3309";
    public $conn;
    
    public function getHost() { return $this->host; }
    public function getPort() { return $this->port; }
    public function getDbName() { return $this->db_name; }
    public function getUsername() { return $this->username; }
    
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
            echo "<p style='color:red'>Erreur: " . $e->getMessage() . "</p>";
            return null;
        }
    }
}

echo "<h2>Test de connexion à la base de données</h2>";

// Tentative de connexion
$database = new Database();
echo "<p>Tentative de connexion à <strong>{$database->getDbName()}</strong> sur <strong>{$database->getHost()}:{$database->getPort()}</strong> en tant que <strong>{$database->getUsername()}</strong></p>";

$db = $database->getConnection();

if ($db) {
    echo "<p style='color:green'>✅ Connexion réussie à la base de données</p>";
    
    // Tester une requête simple
    try {
        $query = "SELECT 1 as test";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p style='color:green'>✅ Test de requête réussi: " . $row['test'] . "</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red'>❌ Erreur lors de l'exécution de la requête de test: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>❌ Échec de la connexion à la base de données</p>";
}
?>