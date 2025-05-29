<?php
// generate_passwords.php - Script pour générer des mots de passe hashés

echo "=== GÉNÉRATEUR DE MOTS DE PASSE HASHÉS POUR MARSA MAROC ===\n\n";

// Liste des mots de passe à hasher
$passwords = [
    'admin@marsamaroc.co.ma' => 'admin123',
    'user@marsamaroc.co.ma' => 'user123',
    'supervisor@marsamaroc.co.ma' => 'supervisor123',
    'operateur@marsamaroc.co.ma' => 'operateur123',
    'chef.quai@marsamaroc.co.ma' => 'chef123'
];

echo "Génération des hashes...\n\n";

foreach($passwords as $email => $plainPassword) {
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    
    echo "Email: $email\n";
    echo "Mot de passe: $plainPassword\n";
    echo "Hash: $hashedPassword\n";
    echo "Vérification: " . (password_verify($plainPassword, $hashedPassword) ? "✓ OK" : "✗ ERREUR") . "\n";
    echo str_repeat("-", 80) . "\n\n";
}

echo "=== REQUÊTES SQL PRÊTES À UTILISER ===\n\n";

$userId = 1;
foreach($passwords as $email => $plainPassword) {
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    $userIdFormatted = 'USR-' . str_pad($userId, 3, '0', STR_PAD_LEFT);
    $role = (strpos($email, 'admin') !== false || strpos($email, 'supervisor') !== false || strpos($email, 'chef') !== false) ? 'ADMIN' : 'USER';
    
    echo "INSERT INTO users (id, email, password, role, created_at, updated_at, failed_login_attempts, account_locked) VALUES\n";
    echo "('$userIdFormatted', '$email', '$hashedPassword', '$role', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0, FALSE);\n\n";
    
    $userId++;
}

echo "=== REQUÊTES DE MISE À JOUR ===\n\n";

foreach($passwords as $email => $plainPassword) {
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    echo "UPDATE users SET password = '$hashedPassword' WHERE email = '$email';\n";
}

echo "\n=== INFORMATIONS DE CONNEXION ===\n\n";

foreach($passwords as $email => $plainPassword) {
    $role = (strpos($email, 'admin') !== false || strpos($email, 'supervisor') !== false || strpos($email, 'chef') !== false) ? 'ADMIN' : 'USER';
    echo "Email: $email\n";
    echo "Mot de passe: $plainPassword\n";
    echo "Rôle: $role\n";
    echo "---\n";
}

echo "\n=== SCRIPT DE VÉRIFICATION ===\n\n";
echo "Pour vérifier que les mots de passe fonctionnent :\n\n";

echo "<?php\n";
echo "// Test de vérification des mots de passe\n";
echo "require_once 'api/config/database.php';\n\n";

echo "\$database = new Database();\n";
echo "\$conn = \$database->getConnection();\n\n";

foreach($passwords as $email => $plainPassword) {
    echo "// Test pour $email\n";
    echo "\$stmt = \$conn->prepare('SELECT password FROM users WHERE email = ?');\n";
    echo "\$stmt->execute(['$email']);\n";
    echo "\$user = \$stmt->fetch();\n";
    echo "if (\$user && password_verify('$plainPassword', \$user['password'])) {\n";
    echo "    echo '$email: ✓ Mot de passe correct\\n';\n";
    echo "} else {\n";
    echo "    echo '$email: ✗ Erreur de mot de passe\\n';\n";
    echo "}\n\n";
}

echo "?>\n";

?>

===== EXÉCUTION DU SCRIPT =====

Pour utiliser ce script :

1. Sauvegardez ce code dans un fichier generate_passwords.php
2. Exécutez-le en ligne de commande : php generate_passwords.php
3. Ou ouvrez-le dans votre navigateur si vous avez un serveur web

Les mots de passe générés seront différents à chaque exécution pour plus de sécurité.