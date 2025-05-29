// Module AngularJS pour la gestion des arrêts
angular.module('gestionApp', [])
.controller('ArretsController', ['$scope', '$http', function($scope, $http) {
    
    // Configuration de l'API
    const API_BASE_URL = 'api/';
    
    // Variables d'initialisation
    $scope.arrets = [];
    $scope.escales = [];
    $scope.operations = [];
    $scope.filteredArrets = [];
    $scope.displayedArrets = [];
    $scope.loading = true;
    $scope.alerts = [];
    
    // Pagination
    $scope.currentPage = 1;
    $scope.itemsPerPage = 10;
    $scope.totalPages = 1;
    
    // Statistiques
    $scope.stats = {
        total: 0,
        today: 0,
        dureeMoyenne: 0,
        dureeTotale: 0
    };
    
    // Objets pour les modals
    $scope.newArret = {};
    $scope.editArret = {};
    $scope.viewArret = {};
    $scope.deleteArret = {};
    
    // Motifs d'arrêt prédéfinis
    $scope.motifsArret = [
        'Panne technique',
        'Maintenance préventive',
        'Conditions météorologiques',
        'Attente de conteneurs',
        'Problème de grue',
        'Manque de personnel',
        'Problème électrique',
        'Problème hydraulique',
        'Incident de sécurité',
        'Pause réglementaire',
        'Changement d\'équipe',
        'Attente d\'autorisation',
        'Problème navire',
        'Autre'
    ];
    
    // Filtres de recherche
    $scope.search = {
        id: '',
        motif: '',
        escale: '',
        operation: '',
        dateDebut: '',
        global: ''
    };
    
    // Initialisation
    $scope.init = function() {
        console.log('Initialisation du contrôleur Arrêts...');
        $scope.resetNewArret();
        $scope.loadArrets();
        $scope.loadEscales();
        $scope.loadOperations();
    };
    
    // Charger les arrêts
    $scope.loadArrets = function() {
        $scope.loading = true;
        console.log('Chargement des arrêts...');
        
        $http.get(API_BASE_URL + 'arrets.php')
            .then(function(response) {
                console.log('Réponse arrêts:', response.data);
                if (response.data && response.data.success) {
                    $scope.arrets = response.data.records || [];
                    $scope.filteredArrets = [...$scope.arrets];
                    $scope.calculateStats();
                    $scope.updatePagination();
                    console.log('Arrêts chargés:', $scope.arrets.length);
                } else {
                    console.error('Erreur API arrêts:', response.data);
                    $scope.arrets = [];
                    $scope.filteredArrets = [];
                    $scope.showAlert('danger', 'Erreur lors du chargement des arrêts');
                }
            })
            .catch(function(error) {
                console.error('Erreur connexion arrêts:', error);
                $scope.arrets = [];
                $scope.filteredArrets = [];
                $scope.showAlert('danger', 'Erreur de connexion API arrêts');
            })
            .finally(function() {
                $scope.loading = false;
            });
    };
    
    // Charger les escales
    $scope.loadEscales = function() {
        console.log('Chargement des escales...');
        
        return $http.get(API_BASE_URL + 'escales.php')
            .then(function(response) {
                console.log('Réponse escales:', response.data);
                if (response.data && response.data.success) {
                    $scope.escales = response.data.records || [];
                    console.log('Escales chargées:', $scope.escales.length);
                    return $scope.escales;
                } else {
                    console.error('Erreur API escales:', response.data);
                    $scope.escales = [];
                    $scope.showAlert('danger', 'Erreur lors du chargement des escales');
                    return [];
                }
            })
            .catch(function(error) {
                console.error('Erreur connexion escales:', error);
                $scope.escales = [];
                $scope.showAlert('danger', 'Erreur de connexion API escales');
                return [];
            });
    };
    
    // Charger les opérations
    $scope.loadOperations = function() {
        console.log('Chargement des opérations...');
        
        return $http.get(API_BASE_URL + 'operations.php')
            .then(function(response) {
                console.log('Réponse opérations:', response.data);
                if (response.data && response.data.success) {
                    $scope.operations = response.data.records || [];
                    console.log('Opérations chargées:', $scope.operations.length);
                    return $scope.operations;
                } else {
                    console.error('Erreur API opérations:', response.data);
                    $scope.operations = [];
                    return [];
                }
            })
            .catch(function(error) {
                console.error('Erreur connexion opérations:', error);
                $scope.operations = [];
                return [];
            });
    };
    
    // Calculer les statistiques
    $scope.calculateStats = function() {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        $scope.stats.total = $scope.arrets.length;
        $scope.stats.today = 0;
        $scope.stats.dureeTotale = 0;
        
        $scope.arrets.forEach(function(arret) {
            const dateDebut = new Date(arret.DATE_DEBUT_arret);
            
            // Arrêts aujourd'hui
            if (dateDebut >= today && dateDebut < tomorrow) {
                $scope.stats.today++;
            }
            
            // Durée totale
            $scope.stats.dureeTotale += parseInt(arret.DURE_arret) || 0;
        });
        
        // Durée moyenne en minutes
        $scope.stats.dureeMoyenne = $scope.stats.total > 0 ? Math.round($scope.stats.dureeTotale / $scope.stats.total) : 0;
    };
    
    // Filtrer les données
    $scope.filterData = function() {
        $scope.filteredArrets = $scope.arrets.filter(function(arret) {
            let match = true;
            
            if ($scope.search.id) {
                match = match && arret.ID_arret.toLowerCase().includes($scope.search.id.toLowerCase());
            }
            
            if ($scope.search.motif) {
                match = match && arret.MOTIF_arret.toLowerCase().includes($scope.search.motif.toLowerCase());
            }
            
            if ($scope.search.escale) {
                match = match && arret.NUM_escale === $scope.search.escale;
            }
            
            if ($scope.search.operation) {
                match = match && arret.ID_operation === $scope.search.operation;
            }
            
            if ($scope.search.dateDebut) {
                const searchDate = new Date($scope.search.dateDebut);
                const arretDate = new Date(arret.DATE_DEBUT_arret);
                match = match && arretDate >= searchDate;
            }
            
            if ($scope.search.global) {
                const global = $scope.search.global.toLowerCase();
                match = match && (
                    arret.ID_arret.toLowerCase().includes(global) ||
                    arret.MOTIF_arret.toLowerCase().includes(global) ||
                    arret.NUM_escale.toLowerCase().includes(global) ||
                    (arret.NOM_navire && arret.NOM_navire.toLowerCase().includes(global)) ||
                    (arret.TYPE_operation && arret.TYPE_operation.toLowerCase().includes(global))
                );
            }
            
            return match;
        });
        
        $scope.currentPage = 1;
        $scope.updatePagination();
    };
    
    // Réinitialiser les filtres
    $scope.resetFilters = function() {
        $scope.search = {
            id: '',
            motif: '',
            escale: '',
            operation: '',
            dateDebut: '',
            global: ''
        };
        $scope.filteredArrets = [...$scope.arrets];
        $scope.currentPage = 1;
        $scope.updatePagination();
    };
    
    // Mise à jour de la pagination
    $scope.updatePagination = function() {
        $scope.totalPages = Math.ceil($scope.filteredArrets.length / $scope.itemsPerPage);
        const start = ($scope.currentPage - 1) * $scope.itemsPerPage;
        const end = start + $scope.itemsPerPage;
        $scope.displayedArrets = $scope.filteredArrets.slice(start, end);
    };
    
    // Navigation pages
    $scope.setPage = function(page) {
        if (page >= 1 && page <= $scope.totalPages) {
            $scope.currentPage = page;
            $scope.updatePagination();
        }
    };
    
    // Générer les numéros de pages
    $scope.getPages = function() {
        const pages = [];
        const start = Math.max(1, $scope.currentPage - 2);
        const end = Math.min($scope.totalPages, $scope.currentPage + 2);
        
        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
        return pages;
    };
    
    // Changement d'escale (nouveau)
    $scope.onEscaleChange = function() {
        console.log('Escale sélectionnée:', $scope.newArret.escale);
        console.log('Liste des escales:', $scope.escales);
        
        if ($scope.newArret.escale && $scope.escales && $scope.escales.length > 0) {
            const selectedEscale = $scope.escales.find(e => e.NUM_escale === $scope.newArret.escale);
            console.log('Escale trouvée:', selectedEscale);
            
            if (selectedEscale) {
                $scope.newArret.nomNavire = selectedEscale.NOM_navire;
                console.log('Nom navire défini:', $scope.newArret.nomNavire);
            }
        } else {
            $scope.newArret.nomNavire = '';
        }
    };
    
    // Changement d'escale (édition)
    $scope.onEditEscaleChange = function() {
        const selectedEscale = $scope.escales.find(e => e.NUM_escale === $scope.editArret.NUM_escale);
        if (selectedEscale) {
            $scope.editArret.NOM_navire = selectedEscale.NOM_navire;
        }
    };
    
    // Calculer durée automatiquement
    $scope.calculateDuration = function() {
        if ($scope.newArret.dateDebut && $scope.newArret.dateFin) {
            const debut = new Date($scope.newArret.dateDebut);
            const fin = new Date($scope.newArret.dateFin);
            
            if (fin > debut) {
                const dureeMs = fin - debut;
                const dureeMinutes = Math.round(dureeMs / (1000 * 60));
                $scope.newArret.duree = dureeMinutes;
            } else {
                $scope.newArret.duree = 0;
            }
        }
    };
    
    // Calculer durée pour l'édition
    $scope.calculateEditDuration = function() {
        if ($scope.editArret.DATE_DEBUT_arret && $scope.editArret.DATE_FIN_arret) {
            const debut = new Date($scope.editArret.DATE_DEBUT_arret);
            const fin = new Date($scope.editArret.DATE_FIN_arret);
            
            if (fin > debut) {
                const dureeMs = fin - debut;
                const dureeMinutes = Math.round(dureeMs / (1000 * 60));
                $scope.editArret.DURE_arret = dureeMinutes;
            } else {
                $scope.editArret.DURE_arret = 0;
            }
        }
    };
    
    // Ajouter un arrêt
    $scope.saveArret = function() {
        if (!$scope.newArret.escale || !$scope.newArret.motif || 
            !$scope.newArret.duree || !$scope.newArret.dateDebut || !$scope.newArret.dateFin) {
            $scope.showAlert('warning', 'Veuillez remplir tous les champs obligatoires');
            return;
        }
        
        // Validation des dates
        const dateDebut = new Date($scope.newArret.dateDebut);
        const dateFin = new Date($scope.newArret.dateFin);
        
        if (dateFin <= dateDebut) {
            $scope.showAlert('warning', 'La date de fin doit être postérieure à la date de début');
            return;
        }
        
        const data = {
            num_escale: $scope.newArret.escale,
            id_operation: $scope.newArret.operation || null,
            motif_arret: $scope.newArret.motif,
            duree_arret: parseInt($scope.newArret.duree),
            date_debut: $scope.newArret.dateDebut,
            date_fin: $scope.newArret.dateFin
        };
        
        $http.post(API_BASE_URL + 'arrets.php', data)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('success', 'Arrêt ajouté avec succès');
                    $scope.loadArrets();
                    $scope.resetNewArret();
                    bootstrap.Modal.getInstance(document.getElementById('addArretModal')).hide();
                } else {
                    $scope.showAlert('danger', response.data.message || 'Erreur lors de l\'ajout');
                }
            })
            .catch(function(error) {
                console.error('Erreur:', error);
                $scope.showAlert('danger', 'Erreur de connexion');
            });
    };
    
    // Modifier un arrêt
    $scope.editArret = function(arret) {
        $scope.editArret = angular.copy(arret);
        
        // Formatter les dates pour l'input datetime-local
        if ($scope.editArret.DATE_DEBUT_arret) {
            $scope.editArret.DATE_DEBUT_arret = $scope.formatDateTimeLocal($scope.editArret.DATE_DEBUT_arret);
        }
        if ($scope.editArret.DATE_FIN_arret) {
            $scope.editArret.DATE_FIN_arret = $scope.formatDateTimeLocal($scope.editArret.DATE_FIN_arret);
        }
        
        const modal = new bootstrap.Modal(document.getElementById('editArretModal'));
        modal.show();
    };
    
    // Mettre à jour un arrêt
    $scope.updateArret = function() {
        const data = {
            id_operation: $scope.editArret.ID_operation || null,
            num_escale: $scope.editArret.NUM_escale,
            motif_arret: $scope.editArret.MOTIF_arret,
            duree_arret: parseInt($scope.editArret.DURE_arret),
            date_debut: $scope.editArret.DATE_DEBUT_arret,
            date_fin: $scope.editArret.DATE_FIN_arret
        };
        
        $http.put(API_BASE_URL + 'arrets.php?id=' + $scope.editArret.ID_arret, data)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('success', 'Arrêt modifié avec succès');
                    $scope.loadArrets();
                    bootstrap.Modal.getInstance(document.getElementById('editArretModal')).hide();
                } else {
                    $scope.showAlert('danger', response.data.message || 'Erreur lors de la modification');
                }
            })
            .catch(function(error) {
                console.error('Erreur:', error);
                $scope.showAlert('danger', 'Erreur de connexion');
            });
    };
    
    // Voir un arrêt
    $scope.viewArret = function(arret) {
        $scope.viewArret = angular.copy(arret);
        const modal = new bootstrap.Modal(document.getElementById('viewArretModal'));
        modal.show();
    };
    
    // Confirmer suppression
    $scope.confirmDeleteArret = function(arret) {
        $scope.deleteArret = angular.copy(arret);
        const modal = new bootstrap.Modal(document.getElementById('deleteArretModal'));
        modal.show();
    };
    
    // Supprimer un arrêt
    $scope.deleteArret = function() {
        $http.delete(API_BASE_URL + 'arrets.php?id=' + $scope.deleteArret.ID_arret)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('success', 'Arrêt supprimé avec succès');
                    $scope.loadArrets();
                    bootstrap.Modal.getInstance(document.getElementById('deleteArretModal')).hide();
                } else {
                    $scope.showAlert('danger', response.data.message || 'Erreur lors de la suppression');
                }
            })
            .catch(function(error) {
                console.error('Erreur:', error);
                $scope.showAlert('danger', 'Erreur de connexion');
            });
    };
    
    // Ouvrir modification depuis vue
    $scope.openEditFromView = function() {
        bootstrap.Modal.getInstance(document.getElementById('viewArretModal')).hide();
        setTimeout(function() {
            $scope.editArret($scope.viewArret);
        }, 300);
    };
    
    // Réinitialiser nouvel arrêt
    $scope.resetNewArret = function() {
        $scope.newArret = {
            escale: '',
            operation: '',
            motif: '',
            duree: '',
            dateDebut: '',
            dateFin: '',
            nomNavire: ''
        };
    };
    
    // Déconnexion
    $scope.logout = function() {
        localStorage.removeItem('authToken');
        localStorage.removeItem('userInfo');
        window.location.href = 'login.html';
    };
    
    // Utilitaires de formatage
    $scope.formatDate = function(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleString('fr-FR');
    };
    
    $scope.formatDateTimeLocal = function(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toISOString().slice(0, 16);
    };
    
    // Formater la durée
    $scope.formatDuration = function(minutes) {
        if (!minutes || minutes === 0) return '-';
        
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        
        if (hours > 0) {
            return hours + 'h ' + (mins > 0 ? mins + 'min' : '');
        } else {
            return mins + 'min';
        }
    };
    
    // Obtenir la classe CSS du badge de priorité selon la durée
    $scope.getDurationBadgeClass = function(minutes) {
        if (!minutes) return 'bg-secondary';
        
        if (minutes < 30) {
            return 'bg-success'; // Court arrêt
        } else if (minutes < 120) {
            return 'bg-warning'; // Arrêt moyen
        } else {
            return 'bg-danger'; // Long arrêt
        }
    };
    
    // Afficher une alerte
    $scope.showAlert = function(type, message) {
        $scope.alerts.push({
            type: type,
            message: message
        });
        
        // Auto-fermeture après 5 secondes
        setTimeout(function() {
            if ($scope.alerts.length > 0) {
                $scope.closeAlert(0);
                $scope.$apply();
            }
        }, 5000);
    };
    
    // Fermer une alerte
    $scope.closeAlert = function(index) {
        $scope.alerts.splice(index, 1);
    };
    
    // Exporter les données
    $scope.exportData = function() {
        const csvContent = "data:text/csv;charset=utf-8," 
            + "ID,Escale,Navire,Operation,Motif,Duree (min),Date Debut,Date Fin\n"
            + $scope.filteredArrets.map(function(arret) {
                return [
                    arret.ID_arret,
                    arret.NUM_escale,
                    arret.NOM_navire || 'Navire inconnu',
                    arret.TYPE_operation || 'Aucune opération',
                    arret.MOTIF_arret,
                    arret.DURE_arret,
                    $scope.formatDate(arret.DATE_DEBUT_arret),
                    $scope.formatDate(arret.DATE_FIN_arret)
                ].join(',');
            }).join('\n');
            
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "arrets_" + new Date().toISOString().slice(0, 10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        $scope.showAlert('success', 'Données exportées avec succès');
    };
    
    // Imprimer les données
    $scope.printData = function() {
        window.print();
    };
    
    // Initialiser le contrôleur
    $scope.init();
}]);