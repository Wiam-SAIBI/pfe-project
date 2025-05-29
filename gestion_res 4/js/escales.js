// Module AngularJS pour la gestion des escales
angular.module('gestionApp', [])
.controller('EscalesController', ['$scope', '$http', function($scope, $http) {
    
    // Configuration de l'API
    const API_BASE_URL = 'api/';
    
    // Variables d'initialisation
    $scope.escales = [];
    $scope.navires = [];
    $scope.filteredEscales = [];
    $scope.displayedEscales = [];
    $scope.loading = true;
    $scope.alerts = [];
    
    // Pagination
    $scope.currentPage = 1;
    $scope.itemsPerPage = 10;
    $scope.totalPages = 1;
    
    // Statistiques
    $scope.stats = {
        total: 0,
        enCours: 0,
        today: 0,
        dureeMoyenne: 0
    };
    
    // Objets pour les modals
    $scope.newEscale = {};
    $scope.editEscale = {};
    $scope.viewEscale = {};
    $scope.deleteEscale = {};
    
    // Filtres de recherche
    $scope.search = {
        numero: '',
        navire: '',
        dateDebut: '',
        global: ''
    };
    
    // Initialisation
    $scope.init = function() {
        console.log('Initialisation du contrôleur Escales...');
        $scope.resetNewEscale();
        $scope.loadEscales();
        $scope.loadNavires();
    };
    
    // Charger les escales
    $scope.loadEscales = function() {
        $scope.loading = true;
        console.log('Chargement des escales...');
        
        $http.get(API_BASE_URL + 'escales.php')
            .then(function(response) {
                console.log('Réponse escales:', response.data);
                if (response.data && response.data.success) {
                    $scope.escales = response.data.records || [];
                    $scope.filteredEscales = [...$scope.escales];
                    $scope.calculateStats();
                    $scope.updatePagination();
                    console.log('Escales chargées:', $scope.escales.length);
                } else {
                    console.error('Erreur API escales:', response.data);
                    $scope.escales = [];
                    $scope.filteredEscales = [];
                    $scope.showAlert('danger', 'Erreur lors du chargement des escales');
                }
            })
            .catch(function(error) {
                console.error('Erreur connexion escales:', error);
                $scope.escales = [];
                $scope.filteredEscales = [];
                $scope.showAlert('danger', 'Erreur de connexion API escales');
            })
            .finally(function() {
                $scope.loading = false;
            });
    };
    
    // Charger les navires
    $scope.loadNavires = function() {
        console.log('Chargement des navires...');
        
        return $http.get(API_BASE_URL + 'navires.php')
            .then(function(response) {
                console.log('Réponse navires:', response.data);
                if (response.data && response.data.success) {
                    $scope.navires = response.data.records || [];
                    console.log('Navires chargés:', $scope.navires.length);
                    return $scope.navires;
                } else {
                    console.error('Erreur API navires:', response.data);
                    $scope.navires = [];
                    $scope.showAlert('danger', 'Erreur lors du chargement des navires');
                    return [];
                }
            })
            .catch(function(error) {
                console.error('Erreur connexion navires:', error);
                $scope.navires = [];
                $scope.showAlert('danger', 'Erreur de connexion API navires');
                return [];
            });
    };
    
    // Calculer les statistiques
    $scope.calculateStats = function() {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        
        $scope.stats.total = $scope.escales.length;
        $scope.stats.enCours = 0;
        $scope.stats.today = 0;
        
        let totalDuration = 0;
        let completedCount = 0;
        
        $scope.escales.forEach(function(escale) {
            const dateAccostage = new Date(escale.DATE_accostage);
            const dateSortie = new Date(escale.DATE_sortie);
            
            // Escales en cours
            if (dateAccostage <= now && dateSortie >= now) {
                $scope.stats.enCours++;
            }
            
            // Escales aujourd'hui
            if (dateAccostage >= today) {
                $scope.stats.today++;
            }
            
            // Durée moyenne
            if (dateSortie < now) {
                const duration = (dateSortie - dateAccostage) / (1000 * 60 * 60); // en heures
                totalDuration += duration;
                completedCount++;
            }
        });
        
        $scope.stats.dureeMoyenne = completedCount > 0 ? Math.round(totalDuration / completedCount) : 0;
    };
    
    // Filtrer les données
    $scope.filterData = function() {
        $scope.filteredEscales = $scope.escales.filter(function(escale) {
            let match = true;
            
            if ($scope.search.numero) {
                match = match && escale.NUM_escale.toLowerCase().includes($scope.search.numero.toLowerCase());
            }
            
            if ($scope.search.navire) {
                match = match && escale.MATRICULE_navire === $scope.search.navire;
            }
            
            if ($scope.search.dateDebut) {
                const searchDate = new Date($scope.search.dateDebut);
                const escaleDate = new Date(escale.DATE_accostage);
                match = match && escaleDate >= searchDate;
            }
            
            if ($scope.search.global) {
                const global = $scope.search.global.toLowerCase();
                match = match && (
                    escale.NUM_escale.toLowerCase().includes(global) ||
                    escale.NOM_navire.toLowerCase().includes(global) ||
                    escale.MATRICULE_navire.toLowerCase().includes(global)
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
            numero: '',
            navire: '',
            dateDebut: '',
            global: ''
        };
        $scope.filteredEscales = [...$scope.escales];
        $scope.currentPage = 1;
        $scope.updatePagination();
    };
    
    // Mise à jour de la pagination
    $scope.updatePagination = function() {
        $scope.totalPages = Math.ceil($scope.filteredEscales.length / $scope.itemsPerPage);
        const start = ($scope.currentPage - 1) * $scope.itemsPerPage;
        const end = start + $scope.itemsPerPage;
        $scope.displayedEscales = $scope.filteredEscales.slice(start, end);
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
    
    // Changement de navire (nouveau)
    $scope.onNavireChange = function() {
        console.log('Navire sélectionné:', $scope.newEscale.navire);
        console.log('Liste des navires:', $scope.navires);
        
        if ($scope.newEscale.navire && $scope.navires && $scope.navires.length > 0) {
            const selectedNavire = $scope.navires.find(n => n.MATRICULE_navire === $scope.newEscale.navire);
            console.log('Navire trouvé:', selectedNavire);
            
            if (selectedNavire) {
                $scope.newEscale.nomNavire = selectedNavire.NOM_navire;
                console.log('Nom navire défini:', $scope.newEscale.nomNavire);
            } else {
                console.log('Aucun navire trouvé pour matricule:', $scope.newEscale.navire);
                // Essayer de trouver par nom
                const navireByName = $scope.navires.find(n => n.NOM_navire === $scope.newEscale.navire);
                if (navireByName) {
                    $scope.newEscale.navire = navireByName.MATRICULE_navire;
                    $scope.newEscale.nomNavire = navireByName.NOM_navire;
                    console.log('Navire trouvé par nom, matricule corrigé:', $scope.newEscale.navire);
                }
            }
        } else {
            $scope.newEscale.nomNavire = '';
        }
    };
    
    // Changement de navire (édition)
    $scope.onEditNavireChange = function() {
        const selectedNavire = $scope.navires.find(n => n.MATRICULE_navire === $scope.editEscale.MATRICULE_navire);
        if (selectedNavire) {
            $scope.editEscale.NOM_navire = selectedNavire.NOM_navire;
        }
    };
    
    // Ajouter une escale
    $scope.saveEscale = function() {
        if (!$scope.newEscale.navire || !$scope.newEscale.dateAccostage || !$scope.newEscale.dateSortie) {
            $scope.showAlert('warning', 'Veuillez remplir tous les champs obligatoires');
            return;
        }
        
        // Validation des dates
        const dateAccostage = new Date($scope.newEscale.dateAccostage);
        const dateSortie = new Date($scope.newEscale.dateSortie);
        
        if (dateSortie <= dateAccostage) {
            $scope.showAlert('warning', 'La date de sortie doit être postérieure à la date d\'accostage');
            return;
        }
        
        const data = {
            matricule_navire: $scope.newEscale.navire,
            nom_navire: $scope.newEscale.nomNavire,
            date_accostage: $scope.newEscale.dateAccostage,
            date_sortie: $scope.newEscale.dateSortie
        };
        
        $http.post(API_BASE_URL + 'escales.php', data)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('success', 'Escale ajoutée avec succès');
                    $scope.loadEscales();
                    $scope.resetNewEscale();
                    bootstrap.Modal.getInstance(document.getElementById('addEscaleModal')).hide();
                } else {
                    $scope.showAlert('danger', response.data.message || 'Erreur lors de l\'ajout');
                }
            })
            .catch(function(error) {
                console.error('Erreur:', error);
                $scope.showAlert('danger', 'Erreur de connexion');
            });
    };
    
    // Modifier une escale
    $scope.editEscale = function(escale) {
        $scope.editEscale = angular.copy(escale);
        
        // Formatter les dates pour l'input datetime-local
        if ($scope.editEscale.DATE_accostage) {
            $scope.editEscale.DATE_accostage = $scope.formatDateTimeLocal($scope.editEscale.DATE_accostage);
        }
        if ($scope.editEscale.DATE_sortie) {
            $scope.editEscale.DATE_sortie = $scope.formatDateTimeLocal($scope.editEscale.DATE_sortie);
        }
        
        const modal = new bootstrap.Modal(document.getElementById('editEscaleModal'));
        modal.show();
    };
    
    // Mettre à jour une escale
    $scope.updateEscale = function() {
        const data = {
            num_escale: $scope.editEscale.NUM_escale,
            matricule_navire: $scope.editEscale.MATRICULE_navire,
            nom_navire: $scope.editEscale.NOM_navire,
            date_accostage: $scope.editEscale.DATE_accostage,
            date_sortie: $scope.editEscale.DATE_sortie
        };
        
        $http.put(API_BASE_URL + 'escales.php?id=' + $scope.editEscale.NUM_escale, data)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('success', 'Escale modifiée avec succès');
                    $scope.loadEscales();
                    bootstrap.Modal.getInstance(document.getElementById('editEscaleModal')).hide();
                } else {
                    $scope.showAlert('danger', response.data.message || 'Erreur lors de la modification');
                }
            })
            .catch(function(error) {
                console.error('Erreur:', error);
                $scope.showAlert('danger', 'Erreur de connexion');
            });
    };
    
    // Voir une escale
    $scope.viewEscale = function(escale) {
        $scope.viewEscale = angular.copy(escale);
        const modal = new bootstrap.Modal(document.getElementById('viewEscaleModal'));
        modal.show();
    };
    
    // Confirmer suppression
    $scope.confirmDeleteEscale = function(escale) {
        $scope.deleteEscale = angular.copy(escale);
        const modal = new bootstrap.Modal(document.getElementById('deleteEscaleModal'));
        modal.show();
    };
    
    // Supprimer une escale
    $scope.deleteEscale = function() {
        $http.delete(API_BASE_URL + 'escales.php?id=' + $scope.deleteEscale.NUM_escale)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('success', 'Escale supprimée avec succès');
                    $scope.loadEscales();
                    bootstrap.Modal.getInstance(document.getElementById('deleteEscaleModal')).hide();
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
        bootstrap.Modal.getInstance(document.getElementById('viewEscaleModal')).hide();
        setTimeout(function() {
            $scope.editEscale($scope.viewEscale);
        }, 300);
    };
    
    // Réinitialiser nouvelle escale
    $scope.resetNewEscale = function() {
        $scope.newEscale = {
            navire: '',
            nomNavire: '',
            dateAccostage: '',
            dateSortie: ''
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
    
    // Calculer la durée
    $scope.calculateDuration = function(dateAccostage, dateSortie) {
        if (!dateAccostage || !dateSortie) return '-';
        
        const start = new Date(dateAccostage);
        const end = new Date(dateSortie);
        const diffMs = end - start;
        const diffHours = Math.round(diffMs / (1000 * 60 * 60));
        
        if (diffHours < 24) {
            return diffHours + 'h';
        } else {
            const days = Math.floor(diffHours / 24);
            const hours = diffHours % 24;
            return days + 'j ' + hours + 'h';
        }
    };
    
    // Obtenir le statut de l'escale
    $scope.getStatusText = function(escale) {
        const now = new Date();
        const dateAccostage = new Date(escale.DATE_accostage);
        const dateSortie = new Date(escale.DATE_sortie);
        
        if (now < dateAccostage) {
            return 'Programmée';
        } else if (now >= dateAccostage && now <= dateSortie) {
            return 'En cours';
        } else {
            return 'Terminée';
        }
    };
    
    // Obtenir la classe CSS du badge de statut
    $scope.getStatusBadgeClass = function(escale) {
        const status = $scope.getStatusText(escale);
        switch (status) {
            case 'Programmée': return 'bg-warning';
            case 'En cours': return 'bg-success';
            case 'Terminée': return 'bg-secondary';
            default: return 'bg-secondary';
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
            $scope.closeAlert(0);
            $scope.$apply();
        }, 5000);
    };
    
    // Fermer une alerte
    $scope.closeAlert = function(index) {
        $scope.alerts.splice(index, 1);
    };
    
    // Exporter les données
    $scope.exportData = function() {
        const csvContent = "data:text/csv;charset=utf-8," 
            + "Numéro,Navire,Matricule,Date Accostage,Date Sortie,Statut\n"
            + $scope.filteredEscales.map(function(escale) {
                return [
                    escale.NUM_escale,
                    escale.NOM_navire,
                    escale.MATRICULE_navire,
                    $scope.formatDate(escale.DATE_accostage),
                    $scope.formatDate(escale.DATE_sortie),
                    $scope.getStatusText(escale)
                ].join(',');
            }).join('\n');
            
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "escales_" + new Date().toISOString().slice(0, 10) + ".csv");
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