<?php
// api/session.php - Vérification de session

require_once 'config/database.php';
require_once 'config/cors.php';

function sendResponse($data, $success = true, $message = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

function sendError($message, $code = 400) {
    http_response_code($code);
    sendResponse(null, false, $message);
}

// Récupérer le token depuis les headers
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : 
              (isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null);

if (!$authHeader) {
    sendError('Token d\'authentification manquant', 401);
}

// Extraire le token
$token = str_replace('Bearer ', '', $authHeader);

try {
    // Décoder le token (simple base64 - à améliorer en production avec JWT)
    $tokenData = json_decode(base64_decode($token), true);
    
    if (!$tokenData || !isset($tokenData['id']) || !isset($tokenData['exp'])) {
        sendError('Token invalide', 401);
    }
    
    // Vérifier l'expiration
    if ($tokenData['exp'] < time()) {
        sendError('Token expiré', 401);
    }
    
    // Connexion à la base de données
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        sendError('Erreur de connexion à la base de données', 500);
    }
    
    // Vérifier que l'utilisateur existe toujours
    $stmt = $pdo->prepare("
        SELECT id, email, role, account_locked 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$tokenData['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        sendError('Utilisateur introuvable', 401);
    }
    
    if ($user['account_locked']) {
        sendError('Compte verrouillé', 423);
    }
    
    // Session valide
    sendResponse([
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ], true, 'Session valide');
    
} catch (Exception $e) {
    error_log('Erreur vérification session: ' . $e->getMessage());
    sendError('Erreur lors de la vérification de session', 500);
}
?>