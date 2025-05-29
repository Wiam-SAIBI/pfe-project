<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// En-têtes requis
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Gérer la méthode OPTIONS (pré-vérification CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit;
}

// Inclure la connexion à la base de données
require_once 'config/database.php';

// Créer une connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Vérifier si la connexion a réussi
if (!$db) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données.'
    ]);
    exit;
}

try {
    // Requête pour récupérer tous les navires
    $query = "SELECT ID_navire, NOM_navire, MATRICULE_navire FROM navire ORDER BY NOM_navire ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $num = $stmt->rowCount();
    
    if ($num > 0) {
        // Tableau pour les données - exactement comme l'API escales
        $navires_arr = array();
        $navires_arr["records"] = array();
        $navires_arr["count"] = $num;
        
        // Récupérer les résultats
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $navire_item = array(
                "ID_navire" => $row['ID_navire'],
                "NOM_navire" => $row['NOM_navire'],
                "MATRICULE_navire" => $row['MATRICULE_navire']
            );
            
            array_push($navires_arr["records"], $navire_item);
        }
        
        // Réponse - succès - exactement comme l'API escales
        http_response_code(200);
        echo json_encode(array_merge(
            ["success" => true, "message" => 'Navires récupérés avec succès'],
            $navires_arr
        ));
    } else {
        // Aucun navire trouvé
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Aucun navire trouvé.',
            'records' => [],
            'count' => 0
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des navires: ' . $e->getMessage()
    ]);
}
?>