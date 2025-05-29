// Contrôleur pour la page de connexion
angular.module('loginApp', []).controller('LoginController', function($scope, $http) {
    // Variables d'initialisation
    $scope.loginData = {
        email: '',
        password: '',
        rememberMe: false
    };
    
    $scope.forgotPassword = {
        email: ''
    };
    
    $scope.loading = false;
    $scope.sendingReset = false;
    $scope.showPassword = false;
    $scope.alerts = [];

    // Initialisation
    $scope.init = function() {
        console.log('Initialisation de la page de connexion');
        $scope.checkExistingSession();
        $scope.loadRememberedCredentials();
    };

    // Vérifier s'il y a déjà une session active
    $scope.checkExistingSession = function() {
        const userInfo = localStorage.getItem('userInfo');
        const authToken = localStorage.getItem('authToken');
        
        if (userInfo && authToken) {
            // Rediriger vers le tableau de bord si déjà connecté
            window.location.href = 'index.html';
        }
    };

    // Charger les identifiants mémorisés
    $scope.loadRememberedCredentials = function() {
        const rememberedEmail = localStorage.getItem('rememberedEmail');
        if (rememberedEmail) {
            $scope.loginData.email = rememberedEmail;
            $scope.loginData.rememberMe = true;
        }
    };

    // Fonction de connexion
    $scope.login = function() {
        if ($scope.detectBruteForce()) {
            return;
        }

        if ($scope.loginForm.$invalid) {
            $scope.showAlert('Veuillez remplir tous les champs correctement', 'warning');
            return;
        }

        $scope.loading = true;
        $scope.clearAlerts();
        $scope.animateLogin();

        const loginData = {
            email: $scope.loginData.email.trim(),
            password: $scope.loginData.password
        };

        $http.post('api/auth.php?action=login', loginData)
            .then(function(response) {
                if (response.data.success) {
                    // Effacer les tentatives échouées
                    $scope.clearFailedAttempts();
                    
                    // Stocker les informations de l'utilisateur
                    const userInfo = response.data.data.user;
                    const token = response.data.data.token;
                    
                    localStorage.setItem('userInfo', JSON.stringify(userInfo));
                    localStorage.setItem('authToken', token);
                    
                    // Mémoriser l'email si demandé
                    if ($scope.loginData.rememberMe) {
                        localStorage.setItem('rememberedEmail', $scope.loginData.email);
                    } else {
                        localStorage.removeItem('rememberedEmail');
                    }
                    
                    $scope.showAlert('Connexion réussie ! Redirection en cours...', 'success');
                    
                    // Redirection après un court délai
                    setTimeout(function() {
                        window.location.href = 'index.html';
                    }, 1500);
                    
                } else {
                    $scope.recordFailedAttempt();
                    $scope.showAlert(response.data.message || 'Erreur de connexion', 'danger');
                }
            })
            .catch(function(error) {
                console.error('Erreur de connexion:', error);
                $scope.recordFailedAttempt();
                
                let errorMessage = 'Erreur de connexion au serveur';
                if (error.data && error.data.message) {
                    errorMessage = error.data.message;
                } else if (error.status === 401) {
                    errorMessage = 'Email ou mot de passe incorrect';
                } else if (error.status === 423) {
                    errorMessage = 'Compte temporairement verrouillé. Veuillez réessayer plus tard.';
                }
                
                $scope.showAlert(errorMessage, 'danger');
            })
            .finally(function() {
                $scope.loading = false;
            });
    };

    // Remplir automatiquement avec un compte de démonstration
    $scope.fillDemoAccount = function(email, password) {
        $scope.loginData.email = email;
        $scope.loginData.password = password;
        $scope.showAlert('Compte de démonstration sélectionné', 'info');
        
        // Auto-focus sur le bouton de connexion
        setTimeout(function() {
            const loginButton = document.querySelector('.btn-login');
            if (loginButton) {
                loginButton.focus();
            }
        }, 100);
    };

    // Basculer la visibilité du mot de passe
    $scope.togglePasswordVisibility = function() {
        $scope.showPassword = !$scope.showPassword;
        const passwordField = document.getElementById('password');
        passwordField.type = $scope.showPassword ? 'text' : 'password';
    };

    // Afficher le modal de mot de passe oublié
    $scope.showForgotPassword = function() {
        const modal = new bootstrap.Modal(document.getElementById('forgotPasswordModal'));
        modal.show();
    };

    // Envoyer l'email de récupération
    $scope.sendResetEmail = function() {
        if ($scope.forgotForm.$invalid) {
            $scope.showAlert('Veuillez entrer une adresse email valide', 'warning');
            return;
        }

        $scope.sendingReset = true;

        const resetData = {
            email: $scope.forgotPassword.email.trim()
        };

        $http.post('api/auth.php?action=forgot_password', resetData)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('Instructions de récupération envoyées par email', 'success');
                    $('#forgotPasswordModal').modal('hide');
                    $scope.forgotPassword.email = '';
                } else {
                    $scope.showAlert(response.data.message || 'Erreur lors de l\'envoi', 'danger');
                }
            })
            .catch(function(error) {
                console.error('Erreur lors de l\'envoi:', error);
                $scope.showAlert('Erreur de connexion au serveur', 'danger');
            })
            .finally(function() {
                $scope.sendingReset = false;
            });
    };

    // Gestion des alertes
    $scope.showAlert = function(message, type) {
        const alert = {
            message: message,
            type: type || 'info'
        };
        $scope.alerts.push(alert);
        
        // Auto-fermeture après 5 secondes pour les messages de succès et d'info
        if (type === 'success' || type === 'info') {
            setTimeout(function() {
                const index = $scope.alerts.indexOf(alert);
                if (index > -1) {
                    $scope.closeAlert(index);
                    $scope.$apply();
                }
            }, 5000);
        }
    };

    $scope.closeAlert = function(index) {
        $scope.alerts.splice(index, 1);
    };

    $scope.clearAlerts = function() {
        $scope.alerts = [];
    };

    // Gestion des événements clavier
    $scope.setupKeyboardEvents = function() {
        document.addEventListener('keypress', function(event) {
            if (event.key === 'Enter' && !$scope.loading) {
                // Si le focus est sur un champ du formulaire, soumettre
                const activeElement = document.activeElement;
                if (activeElement && (activeElement.id === 'email' || activeElement.id === 'password')) {
                    $scope.login();
                    $scope.$apply();
                }
            }
        });
    };

    // Validation en temps réel
    $scope.validateEmail = function() {
        if ($scope.loginForm.email && $scope.loginForm.email.$invalid && $scope.loginForm.email.$dirty) {
            if ($scope.loginForm.email.$error.required) {
                return 'L\'adresse email est requise';
            }
            if ($scope.loginForm.email.$error.email) {
                return 'Format d\'email invalide';
            }
        }
        return '';
    };

    $scope.validatePassword = function() {
        if ($scope.loginForm.password && $scope.loginForm.password.$invalid && $scope.loginForm.password.$dirty) {
            if ($scope.loginForm.password.$error.required) {
                return 'Le mot de passe est requis';
            }
        }
        return '';
    };

    // Animation et effets visuels
    $scope.animateLogin = function() {
        const container = document.querySelector('.login-container');
        if (container) {
            container.style.transform = 'scale(0.95)';
            container.style.transition = 'transform 0.1s ease';
            setTimeout(function() {
                container.style.transform = 'scale(1)';
            }, 100);
        }
    };

    // Détection des tentatives de piratage
    $scope.detectBruteForce = function() {
        const attempts = parseInt(localStorage.getItem('loginAttempts') || '0');
        const lastAttempt = parseInt(localStorage.getItem('lastAttempt') || '0');
        const now = Date.now();
        
        // Réinitialiser après 15 minutes
        if (now - lastAttempt > 15 * 60 * 1000) {
            localStorage.setItem('loginAttempts', '0');
            return false;
        }
        
        // Bloquer après 5 tentatives
        if (attempts >= 5) {
            const remainingTime = Math.ceil((15 * 60 * 1000 - (now - lastAttempt)) / 60000);
            $scope.showAlert('Trop de tentatives de connexion. Réessayez dans ' + remainingTime + ' minutes.', 'danger');
            return true;
        }
        
        return false;
    };

    $scope.recordFailedAttempt = function() {
        const attempts = parseInt(localStorage.getItem('loginAttempts') || '0') + 1;
        localStorage.setItem('loginAttempts', attempts.toString());
        localStorage.setItem('lastAttempt', Date.now().toString());
    };

    $scope.clearFailedAttempts = function() {
        localStorage.removeItem('loginAttempts');
        localStorage.removeItem('lastAttempt');
    };

    // Initialisation au chargement de la page
    angular.element(document).ready(function() {
        $scope.init();
        $scope.setupKeyboardEvents();
        
        // Focus automatique sur le premier champ
        setTimeout(function() {
            const emailField = document.getElementById('email');
            if (emailField && !$scope.loginData.email) {
                emailField.focus();
            } else if ($scope.loginData.email) {
                const passwordField = document.getElementById('password');
                if (passwordField) {
                    passwordField.focus();
                }
            }
        }, 500);
    });
});