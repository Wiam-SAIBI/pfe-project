<?php
// Démarrer la session
session_start();

// Inclure les fichiers nécessaires
require_once 'config/database.php';
require_once 'controllers/AuthController.php';

// Instancier la base de données
$database = new Database();
$conn = $database->getConnection();

// Instancier l'authentification
$auth = new AuthController($conn);

// Vérifier l'authentification
$isLoggedIn = $auth->isAuthenticated();

// Gérer la connexion
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $result = $auth->login($username, $password);
    if (!$result) {
        $loginError = "Nom d'utilisateur ou mot de passe incorrect.";
    } else {
        // Rediriger vers la page d'accueil après connexion
        header('Location: index.php');
        exit;
    }
}

// Gérer la déconnexion
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: index.php');
    exit;
}

// Déterminer la page à afficher
$page = 'dashboard'; // Page par défaut
if (isset($_GET['page']) && file_exists('pages/' . $_GET['page'] . '.php')) {
    $page = $_GET['page'];
}

// Vérifier les autorisations - exemple simple
if (!$isLoggedIn && $page !== 'login') {
    $page = 'login';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Portuaire - Marsa Terminal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php if ($isLoggedIn): ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Barre latérale -->
            <div class="col-md-2 col-lg-2 sidebar">
                <div class="sidebar-sticky">
                    <div class="logo-container text-center">
                        <h4>Marsa Terminal</h4>
                        <h6 class="text-white-50">Gestion Portuaire</h6>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page === 'dashboard') ? 'active' : ''; ?>" href="index.php">
                                <i class="fas fa-tachometer-alt"></i> Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page === 'navires') ? 'active' : ''; ?>" href="index.php?page=navires">
                                <i class="fas fa-ship"></i> Navires
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page === 'escales') ? 'active' : ''; ?>" href="index.php?page=escales">
                                <i class="fas fa-anchor"></i> Escales
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page === 'operations') ? 'active' : ''; ?>" href="index.php?page=operations">
                                <i class="fas fa-cogs"></i> Opérations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page === 'personnel') ? 'active' : ''; ?>" href="index.php?page=personnel">
                                <i class="fas fa-user-tie"></i> Personnel
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page === 'equipements') ? 'active' : ''; ?>" href="index.php?page=equipements">
                                <i class="fas fa-tools"></i> Équipements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page === 'maintenance') ? 'active' : ''; ?>" href="index.php?page=maintenance">
                                <i class="fas fa-wrench"></i> Maintenance
                            </a>
                        </li>
                    </ul>
                    
                    <div class="mt-4 d-grid px-3">
                        <a href="index.php?logout=1" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt"></i> Déconnexion
                        </a>
                    </div>
                </div>
            </div>

            <!-- Contenu principal -->
            <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4 py-4">
                <?php include_once 'pages/' . $page . '.php'; ?>
            </main>
        </div>
    </div>
    <?php else: ?>
        <?php include_once 'pages/login.php'; ?>
    <?php endif; ?>

    <!-- Alerte Container -->
    <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1100;"></div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <?php if ($page === 'personnel'): ?>
    <script src="js/personnel.js"></script>
    <?php endif; ?>
    
</body>
</html>