<?php
require_once 'config/database.php';
require_once 'config/cors.php';

header('Content-Type: application/json');

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Erreur de connexion à la base de données');
    }
    
    switch ($action) {
        case 'getStats':
            getDashboardStats($db);
            break;
            
        default:
            sendError('Action non reconnue', 400);
    }
    
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function getDashboardStats($db) {
    try {
        // Statistiques générales
        $stats = [
            'navires' => getCount($db, 'navire'),
            'conteneurs' => getCount($db, 'conteneure'),
            'operations' => getCount($db, 'operation'),
            'personnel' => getCount($db, 'personnel')
        ];
        
        // Activités récentes (simulation)
        $recentActivities = [
            [
                'time' => '14:30',
                'navire' => 'MSC MEDITERRANEAN',
                'operation' => 'Déchargement',
                'statut' => 'En cours',
                'statutClass' => 'warning'
            ],
            [
                'time' => '13:45',
                'navire' => 'MAERSK ESSEX',
                'operation' => 'Chargement',
                'statut' => 'Terminé',
                'statutClass' => 'success'
            ],
            [
                'time' => '12:20',
                'navire' => 'CMA CGM MARCO POLO',
                'operation' => 'Inspection',
                'statut' => 'En attente',
                'statutClass' => 'info'
            ],
            [
                'time' => '11:15',
                'navire' => 'EVER GIVEN',
                'operation' => 'Accostage',
                'statut' => 'Terminé',
                'statutClass' => 'success'
            ]
        ];
        
        // Alertes système (simulation)
        $alerts = [
            [
                'type' => 'warning',
                'icon' => 'exclamation-triangle',
                'title' => 'Maintenance programmée',
                'message' => 'Grue 3 en maintenance dans 2h',
                'time' => 'Il y a 15 min'
            ],
            [
                'type' => 'info',
                'icon' => 'info-circle',
                'title' => 'Nouveau navire',
                'message' => 'MSC SPLENDIDA prévu à 16h00',
                'time' => 'Il y a 30 min'
            ],
            [
                'type' => 'success',
                'icon' => 'check-circle',
                'title' => 'Opération terminée',
                'message' => 'Déchargement MAERSK ESSEX complété',
                'time' => 'Il y a 1h'
            ]
        ];
        
        // Performance portuaire (simulation)
        $performance = [
            'capaciteAccueil' => 78,
            'efficaciteOperationnelle' => 92,
            'utilisationGrues' => 85,
            'tempsAttenteMoyen' => 45
        ];
        
        sendResponse([
            'success' => true,
            'stats' => $stats,
            'recentActivities' => $recentActivities,
            'alerts' => $alerts,
            'performance' => $performance
        ]);
        
    } catch (Exception $e) {
        sendError('Erreur lors de la récupération des statistiques: ' . $e->getMessage(), 500);
    }
}

function getCount($db, $table) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table");
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)$result['count'];
    } catch (Exception $e) {
        return 0;
    }
}

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function sendError($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit();
}
?>
