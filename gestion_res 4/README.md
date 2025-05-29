gestion_res/
│
├── 📄 index.html                 # Tableau de bord principal
├── 📄 escales.html              # Gestion des escales
├── 📄 conteneurs.html           # Gestion des conteneurs
├── 📄 arrets.html               # Gestion des arrêts
├── 📄 navires.html              # Gestion des navires
├── 📄 personnel.html            # Gestion du personnel
├── 📄 operations.html           # Gestion des opérations
├── 📄 equipes.html              # Gestion des équipes
├── 📄 sous-traitants.html       # Gestion des sous-traitants
├── 📄 shifts.html               # Gestion des shifts
├── 📄 engins.html               # Gestion des engins
├── 📄 login.html                # Page de connexion
│
├── 📁 css/
│   └── 📄 style.css             # Styles CSS (conservé)
│
├── 📁 js/
│   ├── 📄 app.js                # Module AngularJS principal
│   ├── 📄 common.js             # Fonctions communes
│   ├── 📄 auth.js               # Authentification
│   ├── 📄 dashboard.js          # Tableau de bord
│   ├── 📄 escales.js            # Contrôleur escales
│   ├── 📄 conteneurs.js         # Contrôleur conteneurs
│   ├── 📄 arrets.js             # Contrôleur arrêts
│   ├── 📄 navires.js            # Contrôleur navires
│   ├── 📄 personnel.js          # Contrôleur personnel
│   ├── 📄 operations.js         # Contrôleur opérations
│   ├── 📄 equipes.js            # Contrôleur équipes
│   ├── 📄 sous-traitants.js     # Contrôleur sous-traitants
│   ├── 📄 shifts.js             # Contrôleur shifts
│   └── 📄 engins.js             # Contrôleur engins
│
└── 📁 api/
    ├── 📁 config/
    │   ├── 📄 database.php      # Configuration BDD (existant)
    │   └── 📄 cors.php          # Configuration CORS
    │
    ├── 📄 escales.php           # API Escales
    ├── 📄 conteneurs.php        # API Conteneurs
    ├── 📄 arrets.php            # API Arrêts
    ├── 📄 navires.php           # API Navires
    ├── 📄 personnel.php         # API Personnel
    ├── 📄 operations.php        # API Opérations
    ├── 📄 equipes.php           # API Équipes
    ├── 📄 sous-traitants.php    # API Sous-traitants
    ├── 📄 shifts.php            # API Shifts
    ├── 📄 engins.php            # API Engins
    └── 📄 auth.php              # API Authentification



    Caractéristiques du design

Framework CSS: Bootstrap 5.3.0
Icônes: Font Awesome 6.4.0
JavaScript: AngularJS 1.8.2
Style: Interface moderne avec sidebar fixe
Couleurs: Thème professionnel (bleu, gris, blanc)
Responsive: Compatible mobile et desktop

⚙️ Fonctionnalités

✅ CRUD complet pour toutes les entités
✅ Authentification utilisateur
✅ Filtres et recherche avancée
✅ Pagination des données
✅ Statistiques en temps réel
✅ Export et impression
✅ Validation des formulaires
✅ Messages d'alerte
✅ Auto-génération des IDs (triggers)

🔗 Relations entre entités
NAVIRE (1) ←→ (n) ESCALE ←→ (n) OPERATION ←→ (n) ARRET
                    ↓              ↓
              CONTENEUR      EQUIPE ←→ PERSONNEL
                               ↓
                        SOUS-TRAITANT
                               ↓
                            SHIFT ←→ ENGIN


                            login.html → [Connexion réussie] → index.html → [Navigation] → autres pages
     ↑                                  ↓
     └── [Session expirée/Déconnexion] ──┘






     🔐 Différences entre USER et ADMIN
👑 ADMIN (Administrateur)
🎯 Accès COMPLET au système
Permissions spéciales :

✅ Gestion des utilisateurs (créer, modifier, supprimer)
✅ Accès page Users (users.html)
✅ Réinitialiser mots de passe d'autres utilisateurs
✅ Bloquer/débloquer des comptes
✅ Voir statistiques de tous les utilisateurs
✅ Export de données utilisateurs
✅ Configuration système

Navigation :
html<!-- L'admin voit ce menu supplémentaire -->
<li class="nav-item" ng-show="isAdmin()">
    <a class="nav-link" href="users.html">
        <i class="fas fa-user-cog"></i> Utilisateurs
    </a>
</li>
👤 USER (Utilisateur normal)
🎯 Accès LIMITÉ aux fonctions opérationnelles
Permissions limitées :

✅ Tableau de bord (lecture seule)
✅ Gestion navires (selon permissions)
✅ Gestion escales (selon permissions)
✅ Gestion opérations (selon permissions)
❌ PAS d'accès à Users (redirection automatique)
❌ PAS de gestion utilisateurs
❌ PAS de configuration système


📋 Tableau comparatif détaillé
Fonctionnalité👑 ADMIN👤 USERConnexion système✅✅Tableau de bord✅ Complet✅ LectureGestion utilisateurs✅ Totale❌ AucunePage users.html✅ Accès❌ RedirectionCréer utilisateurs✅❌Modifier utilisateurs✅❌Supprimer utilisateurs✅❌Réinitialiser MDP✅ Tous✅ Le sien seulementBloquer comptes✅❌Voir stats utilisateurs✅❌Gestion navires✅✅ (limitée)Gestion escales✅✅ (limitée)Gestion opérations✅✅ (limitée)Export données✅❌

🔍 Vérifications dans le code
Côté Frontend (JavaScript) :
javascript// Vérification admin pour accès page users
if (!AuthService.hasPermission('ADMIN')) {
    NotificationService.error('Accès refusé - Permissions administrateur requises');
    window.location.href = 'index.html';
    return;
}

// Menu conditionnel
$scope.isAdmin = function() {
    return $scope.currentUser && $scope.currentUser.role === 'ADMIN';
};
Côté Backend (PHP) :
php// Protection des endpoints admin
function requireAdmin() {
    $user = requireAuth();
    if($user['role'] !== 'ADMIN') {
        sendError('Permissions insuffisantes', 403);
    }
    return $user;
}

👥 Utilisateurs par défaut dans votre système
🔴 Administrateurs :
admin@marsamaroc.co.ma          (Super Admin)
supervisor@marsamaroc.co.ma     (Superviseur)
chef.quai@marsamaroc.co.ma      (Chef de quai)
🔵 Utilisateurs normaux :
operateur.port@marsamaroc.co.ma    (Opérateur)
gestionnaire@marsamaroc.co.ma      (Gestionnaire - Verrouillé)
controleur@marsamaroc.co.ma        (Contrôleur)

🎯 Cas d'usage typiques
👑 ADMIN utilise le système pour :

Créer des comptes pour nouveaux employés
Débloquer des comptes verrouillés
Réinitialiser mots de passe oubliés
Superviser l'activité des utilisateurs
Configurer les permissions système

👤 USER utilise le système pour :

Consulter le tableau de bord
Gérer les navires et escales
Enregistrer les opérations portuaires
Suivre les conteneurs
Gérer son équipe (selon permissions)


🔄 Comment changer un USER en ADMIN ?

Connectez-vous en tant qu'admin
Allez sur la page Users
Cliquez "Modifier" sur l'utilisateur
Changez le rôle de "Utilisateur" à "Administrateur"
Sauvegardez

L'utilisateur aura immédiatement accès aux fonctions admin lors de sa prochaine connexion !
