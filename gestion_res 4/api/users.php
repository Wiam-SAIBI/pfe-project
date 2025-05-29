<?php
/**
 * API Users Test - Version de test sans authentification
 * À utiliser uniquement pour les tests de développement
 */

require_once 'config/database.php';
require_once 'config/cors.php';

// Configuration des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Fonction pour envoyer une réponse JSON
 */
function sendResponse($data, $success = true, $message = '') {
    header('Content-Type: application/json; charset=utf-8');
    
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $data
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

function sendError($message, $code = 400) {
    http_response_code($code);
    sendResponse(null, false, $message);
}

// Connexion à la base de données
$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    sendError('Erreur de connexion à la base de données', 500);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($pdo);
            break;
        case 'POST':
            handlePost($pdo);
            break;
        case 'PUT':
            handlePut($pdo);
            break;
        case 'DELETE':
            handleDelete($pdo);
            break;
        default:
            sendError('Méthode HTTP non autorisée', 405);
    }
} catch (Exception $e) {
    error_log('Erreur API users test: ' . $e->getMessage());
    sendError('Erreur interne du serveur: ' . $e->getMessage(), 500);
}

function handleGet($pdo) {
    try {
        // Vérifier si c'est une requête pour un utilisateur spécifique
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("
                SELECT id, email, role, created_at, updated_at, last_login, 
                       failed_login_attempts, account_locked, account_locked_until
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                sendError('Utilisateur introuvable', 404);
            }
            
            $user['account_locked'] = (bool)$user['account_locked'];
            $user['failed_login_attempts'] = (int)$user['failed_login_attempts'];
            
            sendResponse($user, true, 'Utilisateur trouvé');
            return;
        }
        
        // Récupérer tous les utilisateurs
        $stmt = $pdo->prepare("
            SELECT id, email, role, created_at, updated_at, last_login, 
                   failed_login_attempts, account_locked, account_locked_until
            FROM users 
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formater les données
        foreach ($users as &$user) {
            $user['account_locked'] = (bool)$user['account_locked'];
            $user['failed_login_attempts'] = (int)$user['failed_login_attempts'];
        }
        
        sendResponse($users, true, 'Utilisateurs récupérés avec succès');
        
    } catch (Exception $e) {
        error_log('Erreur lors de la récupération des utilisateurs: ' . $e->getMessage());
        sendError('Erreur lors de la récupération des utilisateurs');
    }
}

function handlePost($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Données JSON invalides', 400);
    }
    
    // Vérifier s'il s'agit d'une action spéciale
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'toggle_lock':
                toggleUserLock($pdo, $input);
                break;
            case 'reset_password':
                resetUserPassword($pdo, $input);
                break;
            default:
                sendError('Action non reconnue', 400);
        }
        return;
    }
    
    // Création d'un nouvel utilisateur
    createUser($pdo, $input);
}

function createUser($pdo, $input) {
    // Validation
    if (!isset($input['email']) || !isset($input['password']) || !isset($input['role'])) {
        sendError('Email, mot de passe et rôle sont requis', 400);
    }
    
    $email = trim(strtolower($input['email']));
    $password = $input['password'];
    $role = strtoupper($input['role']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Format d\'email invalide', 400);
    }
    
    if (strlen($password) < 6) {
        sendError('Le mot de passe doit contenir au moins 6 caractères', 400);
    }
    
    if (!in_array($role, ['ADMIN', 'USER'])) {
        sendError('Rôle invalide', 400);
    }
    
    try {
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            sendError('Un utilisateur avec cet email existe déjà', 409);
        }
        
        // Générer un ID unique
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = $result['count'] + 1;
        $userId = 'USR-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        
        // Hasher le mot de passe
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insérer le nouvel utilisateur
        $stmt = $pdo->prepare("
            INSERT INTO users (id, email, password, role, created_at, updated_at) 
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$userId, $email, $hashedPassword, $role]);
        
        $newUser = [
            'id' => $userId,
            'email' => $email,
            'role' => $role,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        sendResponse($newUser, true, 'Utilisateur créé avec succès');
        
    } catch (Exception $e) {
        error_log('Erreur lors de la création de l\'utilisateur: ' . $e->getMessage());
        sendError('Erreur lors de la création de l\'utilisateur');
    }
}

function handlePut($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        sendError('ID utilisateur requis', 400);
    }
    
    updateUser($pdo, $input);
}

function updateUser($pdo, $input) {
    $userId = $input['id'];
    $email = trim(strtolower($input['email']));
    $role = strtoupper($input['role']);
    $resetFailedAttempts = isset($input['resetFailedAttempts']) ? $input['resetFailedAttempts'] : false;
    
    // Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Format d\'email invalide', 400);
    }
    
    if (!in_array($role, ['ADMIN', 'USER'])) {
        sendError('Rôle invalide', 400);
    }
    
    try {
        // Vérifier si l'utilisateur existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            sendError('Utilisateur introuvable', 404);
        }
        
        // Vérifier si l'email est déjà utilisé par un autre utilisateur
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            sendError('Un autre utilisateur utilise déjà cet email', 409);
        }
        
        // Construire la requête de mise à jour
        $updateFields = ['email = ?', 'role = ?', 'updated_at = NOW()'];
        $params = [$email, $role];
        
        if ($resetFailedAttempts) {
            $updateFields[] = 'failed_login_attempts = 0';
            $updateFields[] = 'account_locked = 0';
            $updateFields[] = 'account_locked_until = NULL';
        }
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $params[] = $userId;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        sendResponse(null, true, 'Utilisateur mis à jour avec succès');
        
    } catch (Exception $e) {
        error_log('Erreur lors de la mise à jour de l\'utilisateur: ' . $e->getMessage());
        sendError('Erreur lors de la mise à jour de l\'utilisateur');
    }
}

function handleDelete($pdo) {
    if (!isset($_GET['id'])) {
        sendError('ID utilisateur requis', 400);
    }
    
    $userId = $_GET['id'];
    
    try {
        // Vérifier si l'utilisateur existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            sendError('Utilisateur introuvable', 404);
        }
        
        // Supprimer l'utilisateur
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        sendResponse(null, true, 'Utilisateur supprimé avec succès');
        
    } catch (Exception $e) {
        error_log('Erreur lors de la suppression de l\'utilisateur: ' . $e->getMessage());
        sendError('Erreur lors de la suppression de l\'utilisateur');
    }
}

function toggleUserLock($pdo, $input) {
    if (!isset($input['id']) || !isset($input['action'])) {
        sendError('ID utilisateur et action requis', 400);
    }
    
    $userId = $input['id'];
    $action = $input['action'];
    
    try {
        if ($action === 'lock') {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET account_locked = 1, 
                    account_locked_until = DATE_ADD(NOW(), INTERVAL 1 HOUR),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $message = 'Utilisateur bloqué avec succès';
        } else if ($action === 'unlock') {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET account_locked = 0, 
                    account_locked_until = NULL,
                    failed_login_attempts = 0,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $message = 'Utilisateur débloqué avec succès';
        } else {
            sendError('Action invalide', 400);
        }
        
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() === 0) {
            sendError('Utilisateur introuvable', 404);
        }
        
        sendResponse(null, true, $message);
        
    } catch (Exception $e) {
        error_log('Erreur lors du changement de statut: ' . $e->getMessage());
        sendError('Erreur lors du changement de statut');
    }
}

function resetUserPassword($pdo, $input) {
    if (!isset($input['id'])) {
        sendError('ID utilisateur requis', 400);
    }
    
    $userId = $input['id'];
    
    try {
        // Vérifier si l'utilisateur existe
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendError('Utilisateur introuvable', 404);
        }
        
        // Générer un mot de passe temporaire
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $tempPassword = '';
        for ($i = 0; $i < 12; $i++) {
            $tempPassword .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
        
        // Mettre à jour le mot de passe
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, 
                failed_login_attempts = 0,
                account_locked = 0,
                account_locked_until = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $userId]);
        
        sendResponse([
            'temp_password' => $tempPassword,
            'user_email' => $user['email']
        ], true, 'Mot de passe réinitialisé avec succès');
        
    } catch (Exception $e) {
        error_log('Erreur lors de la réinitialisation du mot de passe: ' . $e->getMessage());
        sendError('Erreur lors de la réinitialisation du mot de passe');
    }
}

echo "<!-- API Users Test - Version sans authentification pour les tests -->";
?>