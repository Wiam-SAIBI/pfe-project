// Contrôleur pour le tableau de bord
angular.module('dashboardApp', []).controller('DashboardController', function($scope, $http, $interval) {
    // Variables d'initialisation
    $scope.currentUser = null;
    $scope.currentDateTime = new Date();
    $scope.loading = true;
    $scope.loadingActivities = false;
    $scope.refreshing = false;
    $scope.notifications = [];
    $scope.lastUpdate = new Date();
    
    // Statistiques
    $scope.stats = {
        navires_en_escale: 0,
        nouveaux_navires: 0,
        conteneurs_traites: 0,
        pourcentage_conteneurs: 0,
        operations_actives: 0,
        operations_en_cours: 0,
        personnel_actif: 0,
        equipes_actives: 0
    };
    
    // Données pour les graphiques
    $scope.chartData = {
        labels: [],
        datasets: []
    };
    
    // Activités récentes
    $scope.activities = [];
    
    // Alertes
    $scope.alerts = [];

    // Chart instance
    $scope.chart = null;

    // Initialisation
    $scope.init = function() {
        console.log('Initialisation du tableau de bord');
        $scope.getCurrentUser();
        $scope.loadDashboardData();
        $scope.setupRealTimeUpdates();
        $scope.setupLogout();
        setTimeout(function() {
            $scope.initChart();
        }, 1000);
    };

    // Récupérer l'utilisateur actuel
    $scope.getCurrentUser = function() {
        $scope.currentUser = AuthService.getCurrentUser();
        if (!$scope.currentUser) {
            AuthService.redirectToLogin();
            return;
        }
    };

    // Vérifier si l'utilisateur est admin
    $scope.isAdmin = function() {
        return $scope.currentUser && $scope.currentUser.role === 'ADMIN';
    };

    // Charger les données du tableau de bord
    $scope.loadDashboardData = function() {
        $scope.loading = true;
        
        // Charger les statistiques
        $scope.loadStatistics();
        
        // Charger les activités récentes
        $scope.loadRecentActivities();
        
        // Charger les alertes
        $scope.loadAlerts();
        
        // Simuler la fin du chargement
        setTimeout(function() {
            $scope.loading = false;
            $scope.$apply();
        }, 1000);
    };

    // Charger les statistiques
    $scope.loadStatistics = function() {
        // Simulation de données (remplacer par de vraies API)
        $scope.stats = {
            navires_en_escale: 12,
            nouveaux_navires: 3,
            conteneurs_traites: 847,
            pourcentage_conteneurs: 78,
            operations_actives: 23,
            operations_en_cours: 8,
            personnel_actif: 156,
            equipes_actives: 12
        };
        
        // En production, utiliser une vraie API :
        /*
        $http.get('api/dashboard/statistics.php')
            .then(function(response) {
                if (response.data && response.data.success) {
                    $scope.stats = response.data.data;
                }
            })
            .catch(function(error) {
                console.error('Erreur lors du chargement des statistiques:', error);
                $scope.handleError(error, 'chargement des statistiques');
            });
        */
    };

    // Charger les activités récentes
    $scope.loadRecentActivities = function() {
        $scope.loadingActivities = true;
        
        // Simulation de données
        $scope.activities = [
            {
                heure: '14:30',
                navire: 'MSC CRISTINA',
                matricule: 'MSC-2024-001',
                operation: 'Déchargement',
                statut: 'En cours'
            },
            {
                heure: '13:45',
                navire: 'MAERSK EDINBURGH',
                matricule: 'MAE-2024-087',
                operation: 'Chargement',
                statut: 'Terminé'
            },
            {
                heure: '12:20',
                navire: 'CMA CGM MOZART',
                matricule: 'CMA-2024-156',
                operation: 'Accostage',
                statut: 'En cours'
            },
            {
                heure: '11:15',
                navire: 'HAPAG LLOYD BERLIN',
                matricule: 'HAP-2024-092',
                operation: 'Inspection',
                statut: 'Terminé'
            },
            {
                heure: '10:30',
                navire: 'EVERGREEN HARMONY',
                matricule: 'EVE-2024-203',
                operation: 'Maintenance',
                statut: 'En attente'
            },
            {
                heure: '09:45',
                navire: 'COSCO SHANGHAI',
                matricule: 'COS-2024-134',
                operation: 'Déchargement',
                statut: 'Terminé'
            },
            {
                heure: '08:30',
                navire: 'MSC MEDITERRANEAN',
                matricule: 'MSC-2024-078',
                operation: 'Chargement',
                statut: 'En cours'
            }
        ];
        
        $scope.loadingActivities = false;
        $scope.lastUpdate = new Date();
        
        // Simuler un délai réseau
        setTimeout(function() {
            $scope.$apply();
        }, 500);
        
        // En production, utiliser une vraie API :
        /*
        $http.get('api/dashboard/activities.php')
            .then(function(response) {
                if (response.data && response.data.success) {
                    $scope.activities = response.data.data;
                    $scope.lastUpdate = new Date();
                }
            })
            .catch(function(error) {
                console.error('Erreur lors du chargement des activités:', error);
                $scope.handleError(error, 'chargement des activités');
            })
            .finally(function() {
                $scope.loadingActivities = false;
            });
        */
    };

    // Charger les alertes
    $scope.loadAlerts = function() {
        // Simulation de données
        $scope.alerts = [
            {
                title: 'Retard signalé',
                message: 'Le navire MSC MARINA a un retard de 2h',
                time: '15:00',
                priority: 'high'
            },
            {
                title: 'Maintenance programmée',
                message: 'Maintenance programmée sur la grue 3',
                time: '14:45',
                priority: 'medium'
            },
            {
                title: 'Nouveau navire en approche',
                message: 'ETA 16:00 - MAERSK SEALAND',
                time: '14:30',
                priority: 'low'
            },
            {
                title: 'Opération terminée',
                message: 'Déchargement terminé pour EVERGREEN HARMONY',
                time: '13:15',
                priority: 'low'
            }
        ];
        
        // En production, utiliser une vraie API :
        /*
        $http.get('api/dashboard/alerts.php')
            .then(function(response) {
                if (response.data && response.data.success) {
                    $scope.alerts = response.data.data;
                }
            })
            .catch(function(error) {
                console.error('Erreur lors du chargement des alertes:', error);
                $scope.handleError(error, 'chargement des alertes');
            });
        */
    };

    // Actualiser les activités
    $scope.refreshActivities = function() {
        $scope.refreshing = true;
        setTimeout(function() {
            $scope.loadRecentActivities();
            $scope.loadStatistics();
            $scope.refreshing = false;
            $scope.showNotification('Données actualisées avec succès', 'success');
            $scope.$apply();
        }, 1000);
    };

    // Initialiser le graphique
    $scope.initChart = function() {
        const ctx = document.getElementById('operationsChart');
        if (!ctx) {
            console.warn('Canvas operationsChart non trouvé');
            return;
        }

        const chartData = {
            labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            datasets: [
                {
                    label: 'Opérations',
                    data: [45, 52, 38, 67, 73, 42, 28],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    fill: true
                },
                {
                    label: 'Conteneurs',
                    data: [120, 135, 98, 156, 189, 102, 78],
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1,
                    fill: true
                }
            ]
        };

        $scope.chart = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                },
                elements: {
                    point: {
                        radius: 4,
                        hoverRadius: 6
                    }
                }
            }
        });
    };

    // Changer la période du graphique
    $scope.setChartPeriod = function(period) {
        // Mettre à jour les boutons actifs
        document.querySelectorAll('.btn-group .btn').forEach(function(btn) {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');
        
        // Mettre à jour les données du graphique
        if ($scope.chart) {
            let newData;
            if (period === 'week') {
                newData = {
                    labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                    datasets: [
                        {
                            label: 'Opérations',
                            data: [45, 52, 38, 67, 73, 42, 28],
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'Conteneurs',
                            data: [120, 135, 98, 156, 189, 102, 78],
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            tension: 0.1,
                            fill: true
                        }
                    ]
                };
            } else if (period === 'month') {
                newData = {
                    labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
                    datasets: [
                        {
                            label: 'Opérations',
                            data: [320, 289, 345, 412],
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'Conteneurs',
                            data: [890, 756, 923, 1145],
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            tension: 0.1,
                            fill: true
                        }
                    ]
                };
            }
            
            $scope.chart.data = newData;
            $scope.chart.update('active');
        }
        
        console.log('Période sélectionnée:', period);
    };

    // Actions sur les activités
    $scope.viewActivity = function(activity) {
        $scope.showNotification('Affichage des détails de l\'activité pour ' + activity.navire, 'info');
        // Rediriger vers la page de détails ou afficher un modal
        console.log('Voir activité:', activity);
    };

    $scope.editActivity = function(activity) {
        $scope.showNotification('Édition de l\'activité pour ' + activity.navire, 'info');
        // Rediriger vers la page d'édition
        console.log('Éditer activité:', activity);
    };

    // Gestion des notifications
    $scope.showNotification = function(message, type) {
        const notification = {
            message: message,
            type: type || 'info'
        };
        $scope.notifications.push(notification);
        
        // Auto-fermeture après 4 secondes
        setTimeout(function() {
            const index = $scope.notifications.indexOf(notification);
            if (index > -1) {
                $scope.closeNotification(index);
                $scope.$apply();
            }
        }, 4000);
    };

    $scope.closeNotification = function(index) {
        $scope.notifications.splice(index, 1);
    };

    // Mise à jour en temps réel
    $scope.setupRealTimeUpdates = function() {
        // Mettre à jour l'heure toutes les secondes
        $interval(function() {
            $scope.currentDateTime = new Date();
        }, 1000);

        // Actualiser les données toutes les 60 secondes
        $interval(function() {
            $scope.loadStatistics();
            $scope.loadRecentActivities();
            $scope.loadAlerts();
        }, 60000);

        // Simuler des notifications aléatoires
        $interval(function() {
            if (Math.random() > 0.85) { // 15% de chance
                const randomNotifications = [
                    { message: 'Nouveau navire détecté en approche du port', type: 'info' },
                    { message: 'Opération de chargement terminée avec succès', type: 'success' },
                    { message: 'Alerte météo - Vents forts prévus cet après-midi', type: 'warning' },
                    { message: 'Maintenance préventive programmée demain matin', type: 'info' },
                    { message: 'Quota journalier de conteneurs atteint', type: 'success' }
                ];
                const randomNotif = randomNotifications[Math.floor(Math.random() * randomNotifications.length)];
                $scope.showNotification(randomNotif.message, randomNotif.type);
            }
        }, 90000); // Toutes les 1.5 minutes
    };

    // Configuration de la déconnexion
    $scope.setupLogout = function() {
        const logoutBtn = document.getElementById('logoutButton');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function() {
                if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
                    AuthService.logout();
                }
            });
        }
    };

    // Fonctions utilitaires pour le formatage
    $scope.formatNumber = function(number) {
        if (typeof number !== 'number') return number;
        return number.toLocaleString('fr-FR');
    };

    $scope.getStatusClass = function(status) {
        const statusMap = {
            'En cours': 'bg-primary',
            'Terminé': 'bg-success',
            'En attente': 'bg-warning',
            'Arrêté': 'bg-danger',
            'Planifié': 'bg-info'
        };
        return statusMap[status] || 'bg-secondary';
    };

    $scope.getPriorityClass = function(priority) {
        const priorityMap = {
            'high': 'bg-danger',
            'medium': 'bg-warning',
            'low': 'bg-info'
        };
        return priorityMap[priority] || 'bg-secondary';
    };

    // Gestion des erreurs
    $scope.handleError = function(error, context) {
        console.error('Erreur dans ' + context + ':', error);
        $scope.showNotification('Erreur lors du ' + context, 'danger');
    };

    // Fonctions d'exportation
    $scope.exportStatistics = function() {
        try {
            const csvContent = $scope.generateStatisticsCSV();
            $scope.downloadCSV(csvContent, 'statistiques_' + new Date().toISOString().split('T')[0] + '.csv');
            $scope.showNotification('Statistiques exportées avec succès', 'success');
        } catch (error) {
            console.error('Erreur lors de l\'export:', error);
            $scope.showNotification('Erreur lors de l\'export des statistiques', 'danger');
        }
    };

    $scope.generateStatisticsCSV = function() {
        const headers = ['Métrique', 'Valeur', 'Date'];
        const rows = [
            ['Navires en escale', $scope.stats.navires_en_escale, new Date().toLocaleDateString('fr-FR')],
            ['Nouveaux navires', $scope.stats.nouveaux_navires, new Date().toLocaleDateString('fr-FR')],
            ['Conteneurs traités', $scope.stats.conteneurs_traites, new Date().toLocaleDateString('fr-FR')],
            ['Opérations actives', $scope.stats.operations_actives, new Date().toLocaleDateString('fr-FR')],
            ['Personnel actif', $scope.stats.personnel_actif, new Date().toLocaleDateString('fr-FR')]
        ];
        
        return [headers].concat(rows).map(function(row) {
            return row.join(',');
        }).join('\n');
    };

    $scope.downloadCSV = function(content, filename) {
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    // Fonctions de recherche rapide
    $scope.quickSearch = function(query) {
        if (!query || query.length < 2) return;
        
        $scope.showNotification('Recherche de "' + query + '"...', 'info');
        
        // En production, faire un appel API
        /*
        $http.get('api/search.php?q=' + encodeURIComponent(query))
            .then(function(response) {
                // Traiter les résultats
            })
            .catch(function(error) {
                $scope.handleError(error, 'recherche');
            });
        */
    };

    // Gestion des raccourcis clavier
    $scope.setupKeyboardShortcuts = function() {
        document.addEventListener('keydown', function(event) {
            // Ctrl+R pour actualiser
            if (event.ctrlKey && event.key === 'r') {
                event.preventDefault();
                $scope.refreshActivities();
                $scope.$apply();
            }
            
            // Ctrl+E pour exporter
            if (event.ctrlKey && event.key === 'e') {
                event.preventDefault();
                $scope.exportStatistics();
                $scope.$apply();
            }
            
            // Échap pour fermer toutes les notifications
            if (event.key === 'Escape') {
                $scope.notifications = [];
                $scope.$apply();
            }
        });
    };

    // Détection de l'inactivité
    $scope.setupInactivityDetection = function() {
        let inactivityTimer;
        const inactivityDelay = 30 * 60 * 1000; // 30 minutes

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(function() {
                $scope.showNotification('Session bientôt expirée due à l\'inactivité', 'warning');
                setTimeout(function() {
                    if (confirm('Votre session va expirer. Voulez-vous continuer ?')) {
                        resetInactivityTimer();
                    } else {
                        AuthService.logout();
                    }
                }, 60000); // 1 minute de grâce
            }, inactivityDelay);
        }

        // Événements qui réinitialisent le timer
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        events.forEach(function(event) {
            document.addEventListener(event, resetInactivityTimer, true);
        });

        // Initialiser le timer
        resetInactivityTimer();
    };

    // Gestion de la visibilité de la page
    $scope.setupVisibilityChange = function() {
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                console.log('Page cachée - réduction des mises à jour');
            } else {
                console.log('Page visible - reprise des mises à jour');
                $scope.loadDashboardData();
            }
        });
    };

    // Initialisation complète
    angular.element(document).ready(function() {
        // Vérifier l'authentification avant tout
        if (!window.AuthService || !AuthService.isAuthenticated()) {
            console.warn('AuthService non disponible ou utilisateur non authentifié');
            window.location.href = 'login.html';
            return;
        }

        // Initialiser le tableau de bord
        $scope.init();
        
        // Configurer les fonctionnalités avancées
        $scope.setupKeyboardShortcuts();
        $scope.setupInactivityDetection();
        $scope.setupVisibilityChange();
        
        // Message de bienvenue
        setTimeout(function() {
            if ($scope.currentUser) {
                $scope.showNotification('Bienvenue ' + $scope.currentUser.email + ' !', 'success');
                $scope.$apply();
            }
        }, 2000);
    });

    // Nettoyage lors de la destruction du contrôleur
    $scope.$on('$destroy', function() {
        console.log('Nettoyage du contrôleur dashboard');
        if ($scope.chart) {
            $scope.chart.destroy();
        }
    });
});