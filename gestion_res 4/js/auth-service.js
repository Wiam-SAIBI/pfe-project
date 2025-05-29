// js/auth-service.js - Service d'authentification global

// Service d'authentification global
window.AuthService = {
    // Vérifier si l'utilisateur est connecté
    isAuthenticated: function() {
        const userInfo = localStorage.getItem('userInfo');
        const authToken = localStorage.getItem('authToken');
        return userInfo && authToken;
    },

    // Obtenir les informations de l'utilisateur actuel
    getCurrentUser: function() {
        const userInfo = localStorage.getItem('userInfo');
        return userInfo ? JSON.parse(userInfo) : null;
    },

    // Obtenir le token d'authentification
    getAuthToken: function() {
        return localStorage.getItem('authToken');
    },

    // Vérifier les permissions
    hasPermission: function(permission) {
        const user = this.getCurrentUser();
        if (!user) return false;
        
        switch (permission) {
            case 'ADMIN':
                return user.role === 'ADMIN';
            case 'USER':
                return user.role === 'USER' || user.role === 'ADMIN';
            default:
                return false;
        }
    },

    // Vérifier la session côté serveur
    validateSession: function() {
        return new Promise((resolve, reject) => {
            const token = this.getAuthToken();
            if (!token) {
                reject('Pas de token');
                return;
            }

            // Faire un appel AJAX pour vérifier la session
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'api/session.php', true);
            xhr.setRequestHeader('Authorization', 'Bearer ' + token);
            xhr.setRequestHeader('Content-Type', 'application/json');

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                resolve(response.data.user);
                            } else {
                                reject(response.message);
                            }
                        } catch (e) {
                            reject('Erreur de parsing');
                        }
                    } else {
                        reject('Session invalide');
                    }
                }
            };

            xhr.send();
        });
    },

    // Déconnecter l'utilisateur
    logout: function() {
        localStorage.removeItem('userInfo');
        localStorage.removeItem('authToken');
        localStorage.removeItem('rememberedEmail');
        window.location.href = 'login.html';
    },

    // Rediriger vers la page de connexion
    redirectToLogin: function() {
        // Sauvegarder la page actuelle pour redirection après connexion
        localStorage.setItem('redirectAfterLogin', window.location.pathname);
        window.location.href = 'login.html';
    },

    // Initialiser la vérification d'authentification pour une page
    requireAuth: function(requiredPermission = null) {
        // Vérification basique côté client
        if (!this.isAuthenticated()) {
            this.showSessionExpiredDialog();
            return false;
        }

        // Vérifier les permissions si spécifiées
        if (requiredPermission && !this.hasPermission(requiredPermission)) {
            this.showAccessDeniedDialog();
            return false;
        }

        // Vérification côté serveur (asynchrone)
        this.validateSession()
            .then(user => {
                // Session valide - continuer
                console.log('Session valide pour:', user.email);
            })
            .catch(error => {
                console.error('Session invalide:', error);
                this.showSessionExpiredDialog();
            });

        return true;
    },

    // Afficher le dialogue de session expirée
    showSessionExpiredDialog: function() {
        // Créer un modal Bootstrap simple
        const modalHTML = `
            <div class="modal fade" id="sessionExpiredModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-warning">
                            <h5 class="modal-title">Session expirée</h5>
                        </div>
                        <div class="modal-body">
                            <p>Votre session a expiré. Vous allez être redirigé vers la page de connexion.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" onclick="AuthService.redirectToLogin()">OK</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Ajouter le modal au DOM s'il n'existe pas déjà
        if (!document.getElementById('sessionExpiredModal')) {
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }

        // Afficher le modal
        const modal = new bootstrap.Modal(document.getElementById('sessionExpiredModal'));
        modal.show();

        // Redirection automatique après 3 secondes
        setTimeout(() => {
            this.redirectToLogin();
        }, 3000);
    },

    // Afficher le dialogue d'accès refusé
    showAccessDeniedDialog: function() {
        alert('Accès refusé. Vous n\'avez pas les permissions nécessaires.');
        window.location.href = 'index.html';
    }
};

// Auto-initialisation pour les pages qui nécessitent une authentification
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier si la page actuelle nécessite une authentification
    const currentPage = window.location.pathname;
    
    // Pages qui ne nécessitent pas d'authentification
    const publicPages = ['/login.html', '/reset-password.html'];
    
    // Si ce n'est pas une page publique, vérifier l'authentification
    if (!publicPages.some(page => currentPage.includes(page))) {
        // Vérification différée pour laisser le temps à la page de se charger
        setTimeout(() => {
            if (currentPage.includes('users.html')) {
                // Page utilisateurs - nécessite des droits admin
                AuthService.requireAuth('ADMIN');
            } else {
                // Autres pages - authentification de base
                AuthService.requireAuth();
            }
        }, 500);
    }
});