// Contrôleur pour la gestion des utilisateurs
angular.module('gestionApp', []).controller('UsersController', function($scope, $http) {
    // Variables d'initialisation
    $scope.users = [];
    $scope.filteredUsers = [];
    $scope.displayedUsers = [];
    $scope.currentPage = 1;
    $scope.itemsPerPage = 10;
    $scope.totalPages = 0;
    $scope.loading = true;
    $scope.loadingEscales = false;
    $scope.alerts = [];
    
    // Objets pour les modals
    $scope.newUser = {};
    $scope.editUser = {};
    $scope.viewUser = {};
    $scope.deleteUser = {};
    $scope.resetPasswordUser = {};
    $scope.newTempPassword = '';
    
    // Variables de recherche
    $scope.search = {
        id: '',
        email: '',
        role: '',
        status: '',
        global: ''
    };
    
    // Statistiques
    $scope.stats = {
        total: 0,
        admins: 0,
        users: 0,
        locked: 0
    };
    
    // Variables pour l'affichage des mots de passe
    $scope.showPassword = false;
    
    // Utilisateur actuel pour vérifier les permissions
    $scope.currentUser = null;

    // Initialisation
    $scope.init = function() {
        console.log('Initialisation du contrôleur Users');
        $scope.checkAdminPermissions();
        $scope.loadUsers();
        $scope.setupLogout();
    };

    // Vérifier les permissions administrateur
    $scope.checkAdminPermissions = function() {
        const userInfo = localStorage.getItem('userInfo');
        if (userInfo) {
            $scope.currentUser = JSON.parse(userInfo);
            if ($scope.currentUser.role !== 'ADMIN') {
                $scope.showAlert('Accès refusé - Permissions administrateur requises', 'danger');
                setTimeout(() => {
                    window.location.href = 'index.html';
                }, 2000);
                return false;
            }
        } else {
            window.location.href = 'login.html';
            return false;
        }
        return true;
    };

    // Vérifier si l'utilisateur est admin
    $scope.isAdmin = function() {
        return $scope.currentUser && $scope.currentUser.role === 'ADMIN';
    };

    // Charger tous les utilisateurs
    $scope.loadUsers = function() {
        $scope.loading = true;
        $http.get('api/users.php')
            .then(function(response) {
                if (response.data.success) {
                    $scope.users = response.data.data;
                    $scope.updateStats();
                    $scope.filterData();
                    console.log('Utilisateurs chargés:', $scope.users.length);
                } else {
                    $scope.showAlert('Erreur lors du chargement des utilisateurs: ' + response.data.message, 'danger');
                }
            })
            .catch(function(error) {
                console.error('Erreur lors du chargement:', error);
                $scope.showAlert('Erreur de connexion au serveur', 'danger');
            })
            .finally(function() {
                $scope.loading = false;
            });
    };

    // Mettre à jour les statistiques
    $scope.updateStats = function() {
        $scope.stats.total = $scope.users.length;
        $scope.stats.admins = $scope.users.filter(u => u.role === 'ADMIN').length;
        $scope.stats.users = $scope.users.filter(u => u.role === 'USER').length;
        $scope.stats.locked = $scope.users.filter(u => u.account_locked).length;
    };

    // Filtrer les données
    $scope.filterData = function() {
        $scope.filteredUsers = $scope.users.filter(function(user) {
            const matchId = !$scope.search.id || user.id.toLowerCase().includes($scope.search.id.toLowerCase());
            const matchEmail = !$scope.search.email || user.email.toLowerCase().includes($scope.search.email.toLowerCase());
            const matchRole = !$scope.search.role || user.role === $scope.search.role;
            const matchStatus = !$scope.search.status || 
                ($scope.search.status === 'active' && !user.account_locked) ||
                ($scope.search.status === 'locked' && user.account_locked);
            const matchGlobal = !$scope.search.global || 
                user.id.toLowerCase().includes($scope.search.global.toLowerCase()) ||
                user.email.toLowerCase().includes($scope.search.global.toLowerCase()) ||
                user.role.toLowerCase().includes($scope.search.global.toLowerCase());
            
            return matchId && matchEmail && matchRole && matchStatus && matchGlobal;
        });
        
        $scope.currentPage = 1;
        $scope.updatePagination();
    };

    // Réinitialiser les filtres
    $scope.resetFilters = function() {
        $scope.search = {
            id: '',
            email: '',
            role: '',
            status: '',
            global: ''
        };
        $scope.filterData();
    };

    // Gestion de la pagination
    $scope.updatePagination = function() {
        $scope.totalPages = Math.ceil($scope.filteredUsers.length / $scope.itemsPerPage);
        const startIndex = ($scope.currentPage - 1) * $scope.itemsPerPage;
        const endIndex = startIndex + $scope.itemsPerPage;
        $scope.displayedUsers = $scope.filteredUsers.slice(startIndex, endIndex);
    };

    $scope.setPage = function(page) {
        if (page >= 1 && page <= $scope.totalPages) {
            $scope.currentPage = page;
            $scope.updatePagination();
        }
    };

    $scope.getPages = function() {
        const pages = [];
        const maxPages = 5;
        let startPage = Math.max(1, $scope.currentPage - Math.floor(maxPages / 2));
        let endPage = Math.min($scope.totalPages, startPage + maxPages - 1);
        
        if (endPage - startPage < maxPages - 1) {
            startPage = Math.max(1, endPage - maxPages + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            pages.push(i);
        }
        return pages;
    };

    // Ajouter un nouvel utilisateur
    $scope.saveUser = function() {
        if (!$scope.newUser.email || !$scope.newUser.password || !$scope.newUser.role) {
            $scope.showAlert('Veuillez remplir tous les champs obligatoires', 'warning');
            return;
        }

        if ($scope.newUser.password !== $scope.newUser.confirmPassword) {
            $scope.showAlert('Les mots de passe ne correspondent pas', 'warning');
            return;
        }

        const userData = {
            email: $scope.newUser.email,
            password: $scope.newUser.password,
            role: $scope.newUser.role
        };

        $http.post('api/users.php', userData)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('Utilisateur ajouté avec succès', 'success');
                    $scope.newUser = {};
                    $scope.loadUsers();
                    $('#addUserModal').modal('hide');
                } else {
                    $scope.showAlert('Erreur lors de l\'ajout: ' + response.data.message, 'danger');
                }
            })
            .catch(function(error) {
                console.error('Erreur lors de l\'ajout:', error);
                $scope.showAlert('Erreur de connexion au serveur', 'danger');
            });
    };

    // Modifier un utilisateur
    $scope.editUser = function(user) {
        $scope.editUser = angular.copy(user);
        $scope.editUser.resetFailedAttempts = false;
        $('#editUserModal').modal('show');
    };

    $scope.updateUser = function() {
        if (!$scope.editUser.email || !$scope.editUser.role) {
            $scope.showAlert('Veuillez remplir tous les champs obligatoires', 'warning');
            return;
        }

        const updateData = {
            id: $scope.editUser.id,
            email: $scope.editUser.email,
            role: $scope.editUser.role,
            resetFailedAttempts: $scope.editUser.resetFailedAttempts || false
        };

        $http.put('api/users.php', updateData)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('Utilisateur modifié avec succès', 'success');
                    $scope.loadUsers();
                    $('#editUserModal').modal('hide');
                } else {
                    $scope.showAlert('Erreur lors de la modification: ' + response.data.message, 'danger');
                }
            })
            .catch(function(error) {
                console.error('Erreur lors de la modification:', error);
                $scope.showAlert('Erreur de connexion au serveur', 'danger');
            });
    };

    // Voir les détails d'un utilisateur
    $scope.viewUser = function(user) {
        $scope.viewUser = angular.copy(user);
        $('#viewUserModal').modal('show');
    };

    $scope.openEditFromView = function() {
        $('#viewUserModal').modal('hide');
        setTimeout(() => {
            $scope.editUser($scope.viewUser);
        }, 300);
    };

    // Supprimer un utilisateur
    $scope.confirmDeleteUser = function(user) {
        if (user.id === $scope.currentUser.id) {
            $scope.showAlert('Vous ne pouvez pas supprimer votre propre compte', 'warning');
            return;
        }
        $scope.deleteUser = angular.copy(user);
        $('#deleteUserModal').modal('show');
    };

    $scope.deleteUser = function() {
        $http.delete('api/users.php?id=' + $scope.deleteUser.id)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('Utilisateur supprimé avec succès', 'success');
                    $scope.loadUsers();
                    $('#deleteUserModal').modal('hide');
                } else {
                    $scope.showAlert('Erreur lors de la suppression: ' + response.data.message, 'danger');
                }
            })
            .catch(function(error) {
                console.error('Erreur lors de la suppression:', error);
                $scope.showAlert('Erreur de connexion au serveur', 'danger');
            });
    };

    // Bloquer/Débloquer un utilisateur
    $scope.toggleUserLock = function(user) {
        if (user.id === $scope.currentUser.id) {
            $scope.showAlert('Vous ne pouvez pas modifier votre propre statut', 'warning');
            return;
        }

        const lockData = {
            id: user.id,
            action: user.account_locked ? 'unlock' : 'lock'
        };

        $http.post('api/users.php?action=toggle_lock', lockData)
            .then(function(response) {
                if (response.data.success) {
                    const action = user.account_locked ? 'débloqué' : 'bloqué';
                    $scope.showAlert(`Utilisateur ${action} avec succès`, 'success');
                    $scope.loadUsers();
                } else {
                    $scope.showAlert('Erreur lors de l\'opération: ' + response.data.message, 'danger');
                }
            })
            .catch(function(error) {
                console.error('Erreur lors de l\'opération:', error);
                $scope.showAlert('Erreur de connexion au serveur', 'danger');
            });
    };

    // Réinitialiser le mot de passe
    $scope.resetPassword = function(user) {
        $scope.resetPasswordUser = angular.copy(user);
        $('#resetPasswordModal').modal('show');
    };

    $scope.confirmResetPassword = function() {
        const resetData = {
            id: $scope.resetPasswordUser.id,
            action: 'reset_password'
        };

        $http.post('api/users.php?action=reset_password', resetData)
            .then(function(response) {
                if (response.data.success) {
                    $scope.newTempPassword = response.data.temp_password;
                    $('#resetPasswordModal').modal('hide');
                    setTimeout(() => {
                        $('#newPasswordModal').modal('show');
                    }, 300);
                } else {
                    $scope.showAlert('Erreur lors de la réinitialisation: ' + response.data.message, 'danger');
                }
            })
            .catch(function(error) {
                console.error('Erreur lors de la réinitialisation:', error);
                $scope.showAlert('Erreur de connexion au serveur', 'danger');
            });
    };

    // Copier dans le presse-papiers
    $scope.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(function() {
            $scope.showAlert('Mot de passe copié dans le presse-papiers', 'info');
        }, function(err) {
            console.error('Erreur lors de la copie:', err);
            $scope.showAlert('Impossible de copier dans le presse-papiers', 'warning');
        });
    };

    // Basculer la visibilité du mot de passe
    $scope.togglePasswordVisibility = function(fieldId) {
        $scope.showPassword = !$scope.showPassword;
        const field = document.getElementById(fieldId);
        field.type = $scope.showPassword ? 'text' : 'password';
    };

    // Export des données
    $scope.exportData = function() {
        const csvContent = $scope.generateCSV();
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'utilisateurs_' + new Date().toISOString().split('T')[0] + '.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    $scope.generateCSV = function() {
        const headers = ['ID', 'Email', 'Rôle', 'Statut', 'Créé le', 'Dernière connexion', 'Tentatives échouées'];
        const rows = $scope.filteredUsers.map(user => [
            user.id,
            user.email,
            user.role === 'ADMIN' ? 'Administrateur' : 'Utilisateur',
            user.account_locked ? 'Bloqué' : 'Actif',
            new Date(user.created_at).toLocaleDateString('fr-FR'),
            user.last_login ? new Date(user.last_login).toLocaleDateString('fr-FR') : 'Jamais',
            user.failed_login_attempts
        ]);
        
        return [headers, ...rows].map(row => row.join(',')).join('\n');
    };

    // Impression des données
    $scope.printData = function() {
        const printContent = $scope.generatePrintContent();
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.print();
    };

    $scope.generatePrintContent = function() {
        let content = `
            <html>
            <head>
                <title>Liste des Utilisateurs</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { color: #333; text-align: center; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; font-weight: bold; }
                    .badge { padding: 2px 6px; border-radius: 3px; font-size: 12px; }
                    .badge-admin { background-color: #dc3545; color: white; }
                    .badge-user { background-color: #17a2b8; color: white; }
                    .badge-active { background-color: #28a745; color: white; }
                    .badge-locked { background-color: #dc3545; color: white; }
                </style>
            </head>
            <body>
                <h1>Liste des Utilisateurs</h1>
                <p>Généré le: ${new Date().toLocaleDateString('fr-FR')} à ${new Date().toLocaleTimeString('fr-FR')}</p>
                <p>Total: ${$scope.filteredUsers.length} utilisateurs</p>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Créé le</th>
                            <th>Dernière connexion</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        $scope.filteredUsers.forEach(user => {
            const roleClass = user.role === 'ADMIN' ? 'badge-admin' : 'badge-user';
            const statusClass = user.account_locked ? 'badge-locked' : 'badge-active';
            
            content += `
                <tr>
                    <td>${user.id}</td>
                    <td>${user.email}</td>
                    <td><span class="badge ${roleClass}">${user.role === 'ADMIN' ? 'Administrateur' : 'Utilisateur'}</span></td>
                    <td><span class="badge ${statusClass}">${user.account_locked ? 'Bloqué' : 'Actif'}</span></td>
                    <td>${new Date(user.created_at).toLocaleDateString('fr-FR')}</td>
                    <td>${user.last_login ? new Date(user.last_login).toLocaleDateString('fr-FR') : 'Jamais'}</td>
                </tr>
            `;
        });
        
        content += `
                    </tbody>
                </table>
            </body>
            </html>
        `;
        
        return content;
    };

    // Gestion des alertes
    $scope.showAlert = function(message, type) {
        const alert = {
            message: message,
            type: type || 'info'
        };
        $scope.alerts.push(alert);
        
        // Auto-fermeture après 5 secondes
        setTimeout(() => {
            const index = $scope.alerts.indexOf(alert);
            if (index > -1) {
                $scope.closeAlert(index);
                $scope.$apply();
            }
        }, 5000);
    };

    $scope.closeAlert = function(index) {
        $scope.alerts.splice(index, 1);
    };

    // Configuration de la déconnexion
    $scope.setupLogout = function() {
        document.getElementById('logoutButton').addEventListener('click', function() {
            localStorage.removeItem('userInfo');
            localStorage.removeItem('authToken');
            window.location.href = 'login.html';
        });
    };

    // Initialisation au chargement de la page
    $scope.init();
});