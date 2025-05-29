<?php
// Paramètres de base de l'application
define('APP_NAME', 'Gestion Portuaire');
define('APP_VERSION', '1.0.0');

// Paramètres de l'API
define('API_URL', 'http://localhost:3309/gestion_res/api');
define('ITEMS_PER_PAGE', 10);

// Paramètres de sécurité
define('JWT_SECRET_KEY', 'votre_cle_secrete_pour_jwt');
define('JWT_EXPIRATION', 3600); // 1 heure en secondes

// Paramètres de gestion d'erreur
define('DISPLAY_ERROR_DETAILS', true); // En production, mettre à false

// Paramètres du serveur SMTP pour les emails
define('SMTP_HOST', '');
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('EMAIL_FROM', 'no-reply@marsamaroc.co.ma');
define('EMAIL_FROM_NAME', 'Gestion Portuaire - Marsa Maroc');

// Paths
define('ROOT_PATH', dirname(__DIR__) . '/');
define('LOGS_PATH', ROOT_PATH . 'logs/');
define('UPLOADS_PATH', ROOT_PATH . '../uploads/');

// Fuseaux horaires
date_default_timezone_set('Africa/Casablanca');

// Fonction pour générer un identifiant unique formaté
function generateFormattedId($prefix, $number) {
    return $prefix . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);
}

// Fonction pour journaliser les erreurs
function logError($message, $type = 'ERROR') {
    $date = date('Y-m-d H:i:s');
    $logMessage = "[$date] [$type] $message" . PHP_EOL;
    
    $logFile = LOGS_PATH . date('Y-m-d') . '.log';
    error_log($logMessage, 3, $logFile);
}

// Fonction pour nettoyer les données d'entrée
function sanitizeInput($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitizeInput($value);
        }
        return $data;
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>