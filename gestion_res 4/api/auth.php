<?php
// api/auth.php - API pour l'authentification

require_once 'config/database.php';
require_once 'config/cors.php';

// Fonctions utilitaires
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

function generateToken($user) {
    // Simple token generation (à améliorer en production avec JWT)
    $payload = [
        'id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'exp' => time() + (24 * 60 * 60) // 24 heures
    ];
    return base64_encode(json_encode($payload));
}

function logSecurityEvent($email, $event, $ip, $userAgent) {
    error_log("SECURITY: {$event} for {$email} from {$ip} - {$userAgent}");
}

function isAccountLocked($user) {
    if (!$user['account_locked']) {
        return false;
    }
    
    // Vérifier si le verrouillage est expiré
    if ($user['account_locked_until'] && new DateTime($user['account_locked_until']) < new DateTime()) {
        return false;
    }
    
    return true;
}

function shouldLockAccount($failedAttempts) {
    return $failedAttempts >= 5;
}

function incrementFailedAttempts($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        
        // Vérifier si le compte doit être verrouillé
        $stmt = $pdo->prepare("SELECT failed_login_attempts FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        if ($result && shouldLockAccount($result['failed_login_attempts'])) {
            lockAccount($pdo, $userId);
        }
    } catch (Exception $e) {
        error_log('Erreur lors de l\'incrémentation des tentatives: ' . $e->getMessage());
    }
}

function lockAccount($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET account_locked = TRUE,
                account_locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log('Erreur lors du verrouillage du compte: ' . $e->getMessage());
    }
}

function resetFailedAttempts($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = 0,
                account_locked = FALSE,
                account_locked_until = NULL,
                last_login = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log('Erreur lors de la réinitialisation des tentatives: ' . $e->getMessage());
    }
}

function generateResetToken() {
    return bin2hex(random_bytes(32));
}

function sendResetEmail($email, $token) {
    // Simulation d'envoi d'email (à implémenter avec une vraie solution d'envoi)
    $resetLink = "http://localhost/gestion_res/reset_password.html?token=" . $token;
    
    // Log de l'email (en production, utiliser un service d'email)
    error_log("EMAIL DE RÉCUPÉRATION pour {$email}: {$resetLink}");
    
    // Retourner true pour simuler l'envoi réussi
    return true;
}

// Connexion à la base de données
$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    sendError('Erreur de connexion à la base de données', 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$input = json_decode(file_get_contents('php://input'), true);

// Récupérer l'IP et le User Agent pour la sécurité
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

try {
    switch ($action) {
        case 'login':
            handleLogin($pdo, $input, $clientIP, $userAgent);
            break;
        case 'logout':
            handleLogout();
            break;
        case 'forgot_password':
            handleForgotPassword($pdo, $input, $clientIP, $userAgent);
            break;
        case 'reset_password':
            handleResetPassword($pdo, $input, $clientIP, $userAgent);
            break;
        case 'change_password':
            handleChangePassword($pdo, $input, $clientIP, $userAgent);
            break;
        default:
            sendError('Action non reconnue', 400);
    }
} catch (Exception $e) {
    error_log('Erreur API auth: ' . $e->getMessage());
    sendError('Erreur interne du serveur', 500);
}

function handleLogin($pdo, $input, $clientIP, $userAgent) {
    if (!isset($input['email']) || !isset($input['password'])) {
        sendError('Email et mot de passe requis', 400);
    }
    
    $email = trim(strtolower($input['email']));
    $password = $input['password'];
    
    // Validation basique
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logSecurityEvent($email, 'INVALID_EMAIL_FORMAT', $clientIP, $userAgent);
        sendError('Format d\'email invalide', 400);
    }
    
    try {
        // Récupérer l'utilisateur
        $stmt = $pdo->prepare("
            SELECT id, email, password, role, failed_login_attempts, 
                   account_locked, account_locked_until, last_login
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            logSecurityEvent($email, 'USER_NOT_FOUND', $clientIP, $userAgent);
            sendError('Email ou mot de passe incorrect', 401);
        }
        
        // Vérifier si le compte est verrouillé
        if (isAccountLocked($user)) {
            logSecurityEvent($email, 'ACCOUNT_LOCKED_LOGIN_ATTEMPT', $clientIP, $userAgent);
            sendError('Compte temporairement verrouillé. Veuillez réessayer plus tard.', 423);
        }
        
        // Vérifier le mot de passe
        if (!password_verify($password, $user['password'])) {
            logSecurityEvent($email, 'INVALID_PASSWORD', $clientIP, $userAgent);
            incrementFailedAttempts($pdo, $user['id']);
            sendError('Email ou mot de passe incorrect', 401);
        }
        
        // Connexion réussie
        logSecurityEvent($email, 'LOGIN_SUCCESS', $clientIP, $userAgent);
        resetFailedAttempts($pdo, $user['id']);
        
        // Préparer les données utilisateur (sans le mot de passe)
        $userData = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'last_login' => $user['last_login']
        ];
        
        // Générer le token
        $token = generateToken($userData);
        
        sendResponse([
            'user' => $userData,
            'token' => $token
        ], true, 'Connexion réussie');
        
    } catch (Exception $e) {
        error_log('Erreur lors de la connexion: ' . $e->getMessage());
        sendError('Erreur lors de la connexion', 500);
    }
}

function handleLogout() {
    // Simple déconnexion (le token sera supprimé côté client)
    sendResponse(null, true, 'Déconnexion réussie');
}

function handleForgotPassword($pdo, $input, $clientIP, $userAgent) {
    if (!isset($input['email'])) {
        sendError('Email requis', 400);
    }
    
    $email = trim(strtolower($input['email']));
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Format d\'email invalide', 400);
    }
    
    try {
        // Vérifier si l'utilisateur existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Générer un token de réinitialisation
            $resetToken = generateResetToken();
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Sauvegarder le token en base
            $stmt = $pdo->prepare("
                UPDATE users 
                SET reset_token = ?, 
                    reset_token_expiry = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$resetToken, $expiry, $user['id']]);
            
            // Envoyer l'email de réinitialisation
            if (sendResetEmail($email, $resetToken)) {
                logSecurityEvent($email, 'PASSWORD_RESET_REQUESTED', $clientIP, $userAgent);
            }
        }
        
        // Toujours retourner succès pour éviter l'énumération d'emails
        sendResponse(null, true, 'Si cet email existe, vous recevrez des instructions de récupération');
        
    } catch (Exception $e) {
        error_log('Erreur lors de la récupération du mot de passe: ' . $e->getMessage());
        sendError('Erreur lors de l\'envoi des instructions', 500);
    }
}

function handleResetPassword($pdo, $input, $clientIP, $userAgent) {
    if (!isset($input['token']) || !isset($input['password'])) {
        sendError('Token et nouveau mot de passe requis', 400);
    }
    
    $token = $input['token'];
    $newPassword = $input['password'];
    
    if (strlen($newPassword) < 6) {
        sendError('Le mot de passe doit contenir au moins 6 caractères', 400);
    }
    
    try {
        // Vérifier le token
        $stmt = $pdo->prepare("
            SELECT id, email 
            FROM users 
            WHERE reset_token = ? 
            AND reset_token_expiry > NOW()
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendError('Token invalide ou expiré', 400);
        }
        
        // Mettre à jour le mot de passe
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, 
                reset_token = NULL,
                reset_token_expiry = NULL,
                failed_login_attempts = 0,
                account_locked = FALSE,
                account_locked_until = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $user['id']]);
        
        logSecurityEvent($user['email'], 'PASSWORD_RESET_SUCCESS', $clientIP, $userAgent);
        sendResponse(null, true, 'Mot de passe réinitialisé avec succès');
        
    } catch (Exception $e) {
        error_log('Erreur lors de la réinitialisation du mot de passe: ' . $e->getMessage());
        sendError('Erreur lors de la réinitialisation', 500);
    }
}

function handleChangePassword($pdo, $input, $clientIP, $userAgent) {
    // Cette fonction nécessite une authentification
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? $headers['Authorization'] : null;
    
    if (!$token) {
        sendError('Authentification requise', 401);
    }
    
    // Décoder le token
    $tokenData = json_decode(base64_decode(str_replace('Bearer ', '', $token)), true);
    if (!$tokenData || !isset($tokenData['id'])) {
        sendError('Token invalide', 401);
    }
    
    if (!isset($input['current_password']) || !isset($input['new_password'])) {
        sendError('Mot de passe actuel et nouveau mot de passe requis', 400);
    }
    
    $userId = $tokenData['id'];
    $currentPassword = $input['current_password'];
    $newPassword = $input['new_password'];
    
    if (strlen($newPassword) < 6) {
        sendError('Le nouveau mot de passe doit contenir au moins 6 caractères', 400);
    }
    
    try {
        // Vérifier le mot de passe actuel
        $stmt = $pdo->prepare("SELECT email, password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            logSecurityEvent($user['email'] ?? 'unknown', 'INVALID_CURRENT_PASSWORD', $clientIP, $userAgent);
            sendError('Mot de passe actuel incorrect', 401);
        }
        
        // Mettre à jour le mot de passe
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, 
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $userId]);
        
        logSecurityEvent($user['email'], 'PASSWORD_CHANGED', $clientIP, $userAgent);
        sendResponse(null, true, 'Mot de passe modifié avec succès');
        
    } catch (Exception $e) {
        error_log('Erreur lors du changement de mot de passe: ' . $e->getMessage());
        sendError('Erreur lors du changement de mot de passe', 500);
    }
}
?>