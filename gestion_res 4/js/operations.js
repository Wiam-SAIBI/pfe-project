// Module AngularJS pour la gestion des opérations
angular.module('gestionApp', [])
.controller('OperationsController', ['$scope', '$http', function($scope, $http) {
    
    // Configuration de l'API
    const API_BASE_URL = 'api/';
    
    // Variables d'initialisation
    $scope.operations = [];
    $scope.escales = [];
    $scope.shifts = [];
    $scope.equipes = [];
    $scope.engins = [];
    $scope.filteredOperations = [];
    $scope.displayedOperations = [];
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
        terminees: 0,
        enAttente: 0
    };
    
    // Objets pour les modals
    $scope.newOperation = {};
    $scope.editOperation = {};
    $scope.viewOperation = {};
    $scope.deleteOperation = {};
    
    // Types d'opération prédéfinis
    $scope.typesOperation = [
        'Chargement',
        'Déchargement',
        'Transbordement',
        'Stockage',
        'Manutention',
        'Inspection',
        'Nettoyage',
        'Maintenance'
    ];
    
    // Statuts d'opération
    $scope.statutsOperation = [
        'En attente',
        'En cours',
        'Terminée',
        'Suspendue',
        'Annulée'
    ];
    
    // Filtres de recherche
    $scope.search = {
        id: '',
        type: '',
        status: '',
        escale: '',
        equipe: '',
        global: ''
    };
    
    // Initialisation
    $scope.init = function() {
        console.log('Initialisation du contrôleur Opérations...');
        $scope.resetNewOperation();
        $scope.loadOperations();
        $scope.loadEscales();
        $scope.loadShifts();
        $scope.loadEquipes();
        $scope.loadEngins();
    };
    
    // Charger les opérations
    $scope.loadOperations = function() {
        $scope.loading = true;
        console.log('Chargement des opérations...');
        
        $http.get(API_BASE_URL + 'operations.php')
            .then(function(response) {
                console.log('Réponse opérations:', response.data);
                if (response.data && response.data.success) {
                    $scope.operations = response.data.records || [];
                    $scope.filteredOperations = [...$scope.operations];
                    $scope.calculateStats();
                    $scope.updatePagination();
                    console.log('Opérations chargées:', $scope.operations.length);
                } else {
                    console.error('Erreur API opérations:', response.data);
                    $scope.operations = [];
                    $scope.filteredOperations = [];
                    $scope.showAlert('danger', 'Erreur lors du chargement des opérations');
                }
            })
            .catch(function(error) {
                console.error('Erreur connexion opérations:', error);
                $scope.operations = [];
                $scope.filteredOperations = [];
                $scope.showAlert('danger', 'Erreur de connexion API opérations');
            })
            .finally(function() {
                $scope.loading = false;
            });
    };
    
    // Charger les escales
    $scope.loadEscales = function() {
        return $http.get(API_BASE_URL + 'escales.php')
            .then(function(response) {
                if (response.data && response.data.success) {
                    $scope.escales = response.data.records || [];
                    return $scope.escales;
                } else {
                    $scope.escales = [];
                    return [];
                }
            })
            .catch(function(error) {
                console.error('Erreur connexion escales:', error);
                $scope.escales = [];
                return [];
            });
    };
    
    // Charger les shifts
    $scope.loadShifts = function() {
        return $http.get(API_BASE_URL + 'shifts.php')
            .then(function(response) {
                if (response.data && response.data.success) {
                    $scope.shifts = response.data.records || [];
                    return $scope.shifts;
                } else {
                    $scope.shifts = [];
                    return [];
                }
            })
            .catch(function(error) {
                console.error('Erreur connexion shifts:', error);
                $scope.shifts = [];
                return [];
            });
    };
    
    // Charger les équipes
    $scope.loadEquipes = function() {
        return $http.get(API_BASE_URL + 'equipes.php')
            .then(function(response) {
                if (response.data && response.data.success) {
                    $scope.equipes = response.data.records || [];
                    return $scope.equipes;
                } else {
                    $scope.equipes = [];
                    return [];
                }
            })
            .catch(function(error) {
                console.error('Erreur connexion équipes:', error);
                $scope.equipes = [];
                return [];
            });
    };
    
    // Charger les engins
    $scope.loadEngins = function() {
        return $http.get(API_BASE_URL + 'engins.php')
            .then(function(response) {
                if (response.data && response.data.success) {
                    $scope.engins = response.data.records || [];
                    return $scope.engins;
                } else {
                    $scope.engins = [];
                    return [];
                }
            })
            .catch(function(error) {
                console.error('Erreur connexion engins:', error);
                $scope.engins = [];
                return [];
            });
    };
    
    // Calculer les statistiques
    $scope.calculateStats = function() {
        $scope.stats.total = $scope.operations.length;
        $scope.stats.enCours = 0;
        $scope.stats.terminees = 0;
        $scope.stats.enAttente = 0;
        
        $scope.operations.forEach(function(operation) {
            switch (operation.status) {
                case 'En cours':
                    $scope.stats.enCours++;
                    break;
                case 'Terminée':
                    $scope.stats.terminees++;
                    break;
                case 'En attente':
                    $scope.stats.enAttente++;
                    break;
            }
        });
    };
    
    // Filtrer les données
    $scope.filterData = function() {
        $scope.filteredOperations = $scope.operations.filter(function(operation) {
            let match = true;
            
            if ($scope.search.id) {
                match = match && operation.ID_operation.toLowerCase().includes($scope.search.id.toLowerCase());
            }
            
            if ($scope.search.type) {
                match = match && operation.TYPE_operation === $scope.search.type;
            }
            
            if ($scope.search.status) {
                match = match && operation.status === $scope.search.status;
            }
            
            if ($scope.search.escale) {
                match = match && operation.ID_escale === $scope.search.escale;
            }
            
            if ($scope.search.equipe) {
                match = match && operation.ID_equipe === $scope.search.equipe;
            }
            
            if ($scope.search.global) {
                const global = $scope.search.global.toLowerCase();
                match = match && (
                    operation.ID_operation.toLowerCase().includes(global) ||
                    operation.TYPE_operation.toLowerCase().includes(global) ||
                    (operation.NOM_navire && operation.NOM_navire.toLowerCase().includes(global)) ||
                    (operation.NOM_equipe && operation.NOM_equipe.toLowerCase().includes(global))
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
            type: '',
            status: '',
            escale: '',
            equipe: '',
            global: ''
        };
        $scope.filteredOperations = [...$scope.operations];
        $scope.currentPage = 1;
        $scope.updatePagination();
    };
    
    // Mise à jour de la pagination
    $scope.updatePagination = function() {
        $scope.totalPages = Math.ceil($scope.filteredOperations.length / $scope.itemsPerPage);
        const start = ($scope.currentPage - 1) * $scope.itemsPerPage;
        const end = start + $scope.itemsPerPage;
        $scope.displayedOperations = $scope.filteredOperations.slice(start, end);
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
        if ($scope.newOperation.escale && $scope.escales && $scope.escales.length > 0) {
            const selectedEscale = $scope.escales.find(e => e.NUM_escale === $scope.newOperation.escale);
            if (selectedEscale) {
                $scope.newOperation.nomNavire = selectedEscale.NOM_navire;
            }
        } else {
            $scope.newOperation.nomNavire = '';
        }
    };
    
    // Changement d'escale (édition)
    $scope.onEditEscaleChange = function() {
        const selectedEscale = $scope.escales.find(e => e.NUM_escale === $scope.editOperation.ID_escale);
        if (selectedEscale) {
            $scope.editOperation.NOM_navire = selectedEscale.NOM_navire;
        }
    };
    
    // Ajouter une opération
    $scope.saveOperation = function() {
        if (!$scope.newOperation.type || !$scope.newOperation.escale || !$scope.newOperation.equipe) {
            $scope.showAlert('warning', 'Veuillez remplir tous les champs obligatoires');
            return;
        }
        
        const data = {
            type_operation: $scope.newOperation.type,
            id_shift: $scope.newOperation.shift || null,
            id_escale: $scope.newOperation.escale,
            id_conteneure: $scope.newOperation.conteneurs || null,
            id_engin: $scope.newOperation.engins || null,
            id_equipe: $scope.newOperation.equipe,
            date_debut: $scope.newOperation.dateDebut || null,
            date_fin: $scope.newOperation.dateFin || null,
            status: $scope.newOperation.status || 'En attente'
        };
        
        $http.post(API_BASE_URL + 'operations.php', data)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('success', 'Opération ajoutée avec succès');
                    $scope.loadOperations();
                    $scope.resetNewOperation();
                    bootstrap.Modal.getInstance(document.getElementById('addOperationModal')).hide();
                } else {
                    $scope.showAlert('danger', response.data.message || 'Erreur lors de l\'ajout');
                }
            })
            .catch(function(error) {
                console.error('Erreur:', error);
                $scope.showAlert('danger', 'Erreur de connexion');
            });
    };
    
    // Modifier une opération
    $scope.editOperation = function(operation) {
        $scope.editOperation = angular.copy(operation);
        
        // Formatter les dates pour l'input datetime-local
        if ($scope.editOperation.DATE_debut) {
            $scope.editOperation.DATE_debut = $scope.formatDateTimeLocal($scope.editOperation.DATE_debut);
        }
        if ($scope.editOperation.DATE_fin) {
            $scope.editOperation.DATE_fin = $scope.formatDateTimeLocal($scope.editOperation.DATE_fin);
        }
        
        const modal = new bootstrap.Modal(document.getElementById('editOperationModal'));
        modal.show();
    };
    
    // Mettre à jour une opération
    $scope.updateOperation = function() {
        const data = {
            type_operation: $scope.editOperation.TYPE_operation,
            id_shift: $scope.editOperation.ID_shift,
            id_escale: $scope.editOperation.ID_escale,
            id_conteneure: $scope.editOperation.ID_conteneure,
            id_engin: $scope.editOperation.ID_engin,
            id_equipe: $scope.editOperation.ID_equipe,
            date_debut: $scope.editOperation.DATE_debut,
            date_fin: $scope.editOperation.DATE_fin,
            status: $scope.editOperation.status
        };
        
        $http.put(API_BASE_URL + 'operations.php?id=' + $scope.editOperation.ID_operation, data)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('success', 'Opération modifiée avec succès');
                    $scope.loadOperations();
                    bootstrap.Modal.getInstance(document.getElementById('editOperationModal')).hide();
                } else {
                    $scope.showAlert('danger', response.data.message || 'Erreur lors de la modification');
                }
            })
            .catch(function(error) {
                console.error('Erreur:', error);
                $scope.showAlert('danger', 'Erreur de connexion');
            });
    };
    
    // Voir une opération
    $scope.viewOperation = function(operation) {
        $scope.viewOperation = angular.copy(operation);
        $scope.viewOperation.duree = $scope.calculateOperationDuration(operation);
        const modal = new bootstrap.Modal(document.getElementById('viewOperationModal'));
        modal.show();
    };
    
    // Voir détails d'une opération (redirection vers page de détail)
    $scope.viewOperationDetail = function(operation) {
        window.location.href = 'operation-detail.html?id=' + operation.ID_operation;
    };
    
    // Confirmer suppression
    $scope.confirmDeleteOperation = function(operation) {
        $scope.deleteOperation = angular.copy(operation);
        const modal = new bootstrap.Modal(document.getElementById('deleteOperationModal'));
        modal.show();
    };
    
    // Supprimer une opération
    $scope.deleteOperation = function() {
        $http.delete(API_BASE_URL + 'operations.php?id=' + $scope.deleteOperation.ID_operation)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('success', 'Opération supprimée avec succès');
                    $scope.loadOperations();
                    bootstrap.Modal.getInstance(document.getElementById('deleteOperationModal')).hide();
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
        bootstrap.Modal.getInstance(document.getElementById('viewOperationModal')).hide();
        setTimeout(function() {
            $scope.editOperation($scope.viewOperation);
        }, 300);
    };
    
    // Réinitialiser nouvelle opération
    $scope.resetNewOperation = function() {
        $scope.newOperation = {
            type: '',
            shift: '',
            escale: '',
            conteneurs: '',
            engins: '',
            equipe: '',
            dateDebut: '',
            dateFin: '',
            status: 'En attente',
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
    
    // Calculer la durée d'une opération
    $scope.calculateOperationDuration = function(operation) {
        if (!operation.DATE_debut || !operation.DATE_fin) return '-';
        
        const debut = new Date(operation.DATE_debut);
        const fin = new Date(operation.DATE_fin);
        const diffMs = fin - debut;
        const diffMinutes = Math.round(diffMs / (1000 * 60));
        
        const hours = Math.floor(diffMinutes / 60);
        const mins = diffMinutes % 60;
        
        if (hours > 0) {
            return hours + 'h ' + (mins > 0 ? mins + 'min' : '');
        } else {
            return mins + 'min';
        }
    };
    
    // Obtenir la classe CSS du badge de statut
    $scope.getStatusBadgeClass = function(operation) {
        if (!operation || !operation.status) return 'bg-secondary';
        
        switch (operation.status) {
            case 'En cours': return 'bg-primary';
            case 'Terminée': return 'bg-success';
            case 'En attente': return 'bg-warning';
            case 'Suspendue': return 'bg-info';
            case 'Annulée': return 'bg-danger';
            default: return 'bg-secondary';
        }
    };
    
    // Obtenir l'icône selon le type d'opération
    $scope.getOperationIcon = function(type) {
        switch (type) {
            case 'Chargement': return 'fas fa-upload';
            case 'Déchargement': return 'fas fa-download';
            case 'Transbordement': return 'fas fa-exchange-alt';
            case 'Stockage': return 'fas fa-warehouse';
            case 'Manutention': return 'fas fa-hands-helping';
            case 'Inspection': return 'fas fa-search';
            case 'Nettoyage': return 'fas fa-broom';
            case 'Maintenance': return 'fas fa-tools';
            default: return 'fas fa-cogs';
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
            + "ID,Type,Escale,Navire,Equipe,Statut,Date Debut,Date Fin,Arrets\n"
            + $scope.filteredOperations.map(function(operation) {
                return [
                    operation.ID_operation,
                    operation.TYPE_operation,
                    operation.ID_escale,
                    operation.NOM_navire || 'Navire inconnu',
                    operation.NOM_equipe || 'Équipe inconnue',
                    operation.status,
                    $scope.formatDate(operation.DATE_debut),
                    $scope.formatDate(operation.DATE_fin),
                    operation.NOMBRE_ARRETS || 0
                ].join(',');
            }).join('\n');
            
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "operations_" + new Date().toISOString().slice(0, 10) + ".csv");
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