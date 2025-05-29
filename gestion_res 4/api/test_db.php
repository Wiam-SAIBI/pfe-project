<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Utiliser un chemin relatif direct pour l'inclusion
include_once 'config/database.php';

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
        
        // Tester si la table personnel existe
        try {
            $query = "SHOW TABLES LIKE 'personnel'";
            $stmt = $db->prepare($query);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                echo "<p style='color:green'>✅ La table 'personnel' existe</p>";
                
                // Tester une requête sur la table personnel
                $query = "SELECT COUNT(*) as count FROM personnel";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<p style='color:green'>✅ La table 'personnel' contient " . $row['count'] . " enregistrements</p>";
            } else {
                echo "<p style='color:red'>❌ La table 'personnel' n'existe pas</p>";
                echo "<p>Vous devez créer la table 'personnel'. Voici un exemple de commande SQL :</p>";
                echo "<pre style='background-color: #f8f9fa; padding: 10px; border-radius: 5px;'>
CREATE TABLE `personnel` (
  `ID_personnel` int(11) NOT NULL AUTO_INCREMENT,
  `MATRICULE_personnel` varchar(50) NOT NULL,
  `NOM_personnel` varchar(100) NOT NULL,
  `PRENOM_personnel` varchar(100) NOT NULL,
  `FONCTION_personnel` varchar(100) NOT NULL,
  `CONTACT_personnel` varchar(100) DEFAULT NULL,
  `ID_equipe` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID_personnel`),
  UNIQUE KEY `MATRICULE_personnel` (`MATRICULE_personnel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                </pre>";
            }
        } catch (PDOException $e) {
            echo "<p style='color:red'>❌ Erreur lors de la vérification de la table 'personnel': " . $e->getMessage() . "</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color:red'>❌ Erreur lors de l'exécution de la requête de test: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>❌ Échec de la connexion à la base de données</p>";
    echo "<p>Vérifiez les points suivants :</p>";
    echo "<ul>";
    echo "<li>Le serveur MySQL est-il en cours d'exécution sur le port 3309 ?</li>";
    echo "<li>Les identifiants (username='root', password='') sont-ils corrects ?</li>";
    echo "<li>La base de données 'gestion_res' existe-t-elle ?</li>";
    echo "</ul>";
    echo "<p>Vous pouvez créer la base de données avec cette commande SQL :</p>";
    echo "<pre style='background-color: #f8f9fa; padding: 10px; border-radius: 5px;'>CREATE DATABASE gestion_res CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;</pre>";
}
?>