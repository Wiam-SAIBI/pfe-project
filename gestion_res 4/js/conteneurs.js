// Module AngularJS pour la gestion des conteneurs
angular.module('gestionApp', [])
.controller('ConteneursController', ['$scope', '$http', function($scope, $http) {
    
    // Configuration de l'API
    const API_BASE_URL = 'api/';
    
    // Variables d'initialisation
    $scope.conteneurs = [];
    $scope.navires = [];
    $scope.operations = [];
    $scope.filteredConteneurs = [];
    $scope.displayedConteneurs = [];
    $scope.loading = true;
    $scope.alerts = [];
    
    // Pagination
    $scope.currentPage = 1;
    $scope.itemsPerPage = 10;
    $scope.totalPages = 1;
    
    // Statistiques
    $scope.stats = {
        total: 0,
        avecNavire: 0,
        sansNavire: 0,
        typesUniques: 0
    };
    
    // Objets pour les modals
    $scope.newConteneur = {};
    $scope.editConteneur = {};
    $scope.viewConteneur = {};
    $scope.deleteConteneur = {};
    
    // Types de conteneurs prédéfinis
    $scope.typesConteneur = [
        '20FT',
        '40FT',
        '40HC',
        '45FT',
        'REEFER 20FT',
        'REEFER 40FT',
        'TANK 20FT',
        'TANK 40FT',
        'FLAT RACK',
        'OPEN TOP'
    ];
    
    // Filtres de recherche
    $scope.search = {
        id: '',
        nom: '',
        type: '',
        navire: '',
        global: ''
    };
    
    // Initialisation
    $scope.init = function() {
        console.log('Initialisation du contrôleur Conteneurs...');
        $scope.resetNewConteneur();
        $scope.loadConteneurs();
        $scope.loadNavires();
        $scope.loadOperations();
    };
    
    // Charger les conteneurs
    $scope.loadConteneurs = function() {
        $scope.loading = true;
        console.log('Chargement des conteneurs...');
        
        $http.get(API_BASE_URL + 'conteneurs.php')
            .then(function(response) {
                console.log('Réponse conteneurs:', response.data);
                if (response.data && response.data.success) {
                    $scope.conteneurs = response.data.records || [];
                    $scope.filteredConteneurs = [...$scope.conteneurs];
                    $scope.calculateStats();
                    $scope.updatePagination();
                    console.log('Conteneurs chargés:', $scope.conteneurs.length);
                } else {
                    console.error('Erreur API conteneurs:', response.data);
                    $scope.conteneurs = [];
                    $scope.filteredConteneurs = [];
                    $scope.showAlert('danger', 'Erreur lors du chargement des conteneurs');
                }
            })
            .catch(function(error) {
                console.error('Erreur connexion conteneurs:', error);
                $scope.conteneurs = [];
                $scope.filteredConteneurs = [];
                $scope.showAlert('danger', 'Erreur de connexion API conteneurs');
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
        $scope.stats.total = $scope.conteneurs.length;
        $scope.stats.avecNavire = 0;
        $scope.stats.sansNavire = 0;
        
        let typesUniques = new Set();
        
        $scope.conteneurs.forEach(function(conteneur) {
            // Conteneurs avec/sans navire
            if (conteneur.ID_navire && conteneur.ID_navire !== null) {
                $scope.stats.avecNavire++;
            } else {
                $scope.stats.sansNavire++;
            }
            
            // Types uniques
            if (conteneur.TYPE_conteneure) {
                typesUniques.add(conteneur.TYPE_conteneure);
            }
        });
        
        $scope.stats.typesUniques = typesUniques.size;
    };
    
    // Filtrer les données
    $scope.filterData = function() {
        $scope.filteredConteneurs = $scope.conteneurs.filter(function(conteneur) {
            let match = true;
            
            if ($scope.search.id) {
                match = match && conteneur.ID_conteneure.toLowerCase().includes($scope.search.id.toLowerCase());
            }
            
            if ($scope.search.nom) {
                match = match && conteneur.NOM_conteneure.toLowerCase().includes($scope.search.nom.toLowerCase());
            }
            
            if ($scope.search.type) {
                match = match && conteneur.TYPE_conteneure === $scope.search.type;
            }
            
            if ($scope.search.navire) {
                match = match && conteneur.ID_navire === $scope.search.navire;
            }
            
            if ($scope.search.global) {
                const global = $scope.search.global.toLowerCase();
                match = match && (
                    conteneur.ID_conteneure.toLowerCase().includes(global) ||
                    conteneur.NOM_conteneure.toLowerCase().includes(global) ||
                    conteneur.TYPE_conteneure.toLowerCase().includes(global) ||
                    (conteneur.NOM_navire && conteneur.NOM_navire.toLowerCase().includes(global))
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
            nom: '',
            type: '',
            navire: '',
            global: ''
        };
        $scope.filteredConteneurs = [...$scope.conteneurs];
        $scope.currentPage = 1;
        $scope.updatePagination();
    };
    
    // Mise à jour de la pagination
    $scope.updatePagination = function() {
        $scope.totalPages = Math.ceil($scope.filteredConteneurs.length / $scope.itemsPerPage);
        const start = ($scope.currentPage - 1) * $scope.itemsPerPage;
        const end = start + $scope.itemsPerPage;
        $scope.displayedConteneurs = $scope.filteredConteneurs.slice(start, end);
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
        console.log('Navire sélectionné:', $scope.newConteneur.navire);
        console.log('Liste des navires:', $scope.navires);
        
        if ($scope.newConteneur.navire && $scope.navires && $scope.navires.length > 0) {
            const selectedNavire = $scope.navires.find(n => n.ID_navire === $scope.newConteneur.navire);
            console.log('Navire trouvé:', selectedNavire);
            
            if (selectedNavire) {
                $scope.newConteneur.nomNavire = selectedNavire.NOM_navire;
                console.log('Nom navire défini:', $scope.newConteneur.nomNavire);
            }
        } else {
            $scope.newConteneur.nomNavire = '';
        }
    };
    
    // Changement de navire (édition)
    $scope.onEditNavireChange = function() {
        const selectedNavire = $scope.navires.find(n => n.ID_navire === $scope.editConteneur.ID_navire);
        if (selectedNavire) {
            $scope.editConteneur.NOM_navire = selectedNavire.NOM_navire;
        }
    };
    
    // Ajouter un conteneur
    $scope.saveConteneur = function() {
        if (!$scope.newConteneur.nom || !$scope.newConteneur.type) {
            $scope.showAlert('warning', 'Veuillez remplir tous les champs obligatoires');
            return;
        }
        
        const data = {
            nom_conteneure: $scope.newConteneur.nom,
            type_conteneure: $scope.newConteneur.type,
            id_type: $scope.newConteneur.idType || null,
            id_navire: $scope.newConteneur.navire || null
        };
        
        $http.post(API_BASE_URL + 'conteneurs.php', data)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('success', 'Conteneur ajouté avec succès');
                    $scope.loadConteneurs();
                    $scope.resetNewConteneur();
                    bootstrap.Modal.getInstance(document.getElementById('addConteneurModal')).hide();
                } else {
                    $scope.showAlert('danger', response.data.message || 'Erreur lors de l\'ajout');
                }
            })
            .catch(function(error) {
                console.error('Erreur:', error);
                $scope.showAlert('danger', 'Erreur de connexion');
            });
    };
    
    // Modifier un conteneur
    $scope.editConteneur = function(conteneur) {
        $scope.editConteneur = angular.copy(conteneur);
        
        const modal = new bootstrap.Modal(document.getElementById('editConteneurModal'));
        modal.show();
    };
    
    // Mettre à jour un conteneur
    $scope.updateConteneur = function() {
        const data = {
            nom_conteneure: $scope.editConteneur.NOM_conteneure,
            type_conteneure: $scope.editConteneur.TYPE_conteneure,
            id_type: $scope.editConteneur.ID_type || null,
            id_navire: $scope.editConteneur.ID_navire || null
        };
        
        $http.put(API_BASE_URL + 'conteneurs.php?id=' + $scope.editConteneur.ID_conteneure, data)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('success', 'Conteneur modifié avec succès');
                    $scope.loadConteneurs();
                    bootstrap.Modal.getInstance(document.getElementById('editConteneurModal')).hide();
                } else {
                    $scope.showAlert('danger', response.data.message || 'Erreur lors de la modification');
                }
            })
            .catch(function(error) {
                console.error('Erreur:', error);
                $scope.showAlert('danger', 'Erreur de connexion');
            });
    };
    
    // Voir un conteneur
    $scope.viewConteneur = function(conteneur) {
        $scope.viewConteneur = angular.copy(conteneur);
        const modal = new bootstrap.Modal(document.getElementById('viewConteneurModal'));
        modal.show();
    };
    
    // Confirmer suppression
    $scope.confirmDeleteConteneur = function(conteneur) {
        $scope.deleteConteneur = angular.copy(conteneur);
        const modal = new bootstrap.Modal(document.getElementById('deleteConteneurModal'));
        modal.show();
    };
    
    // Supprimer un conteneur
    $scope.deleteConteneur = function() {
        $http.delete(API_BASE_URL + 'conteneurs.php?id=' + $scope.deleteConteneur.ID_conteneure)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('success', 'Conteneur supprimé avec succès');
                    $scope.loadConteneurs();
                    bootstrap.Modal.getInstance(document.getElementById('deleteConteneurModal')).hide();
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
        bootstrap.Modal.getInstance(document.getElementById('viewConteneurModal')).hide();
        setTimeout(function() {
            $scope.editConteneur($scope.viewConteneur);
        }, 300);
    };
    
    // Réinitialiser nouveau conteneur
    $scope.resetNewConteneur = function() {
        $scope.newConteneur = {
            nom: '',
            type: '',
            idType: '',
            navire: '',
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
    
    // Obtenir le statut du conteneur
    $scope.getStatusText = function(conteneur) {
        if (conteneur.ID_navire && conteneur.ID_navire !== null) {
            return 'Assigné';
        } else {
            return 'Libre';
        }
    };
    
    // Obtenir la classe CSS du badge de statut
    $scope.getStatusBadgeClass = function(conteneur) {
        const status = $scope.getStatusText(conteneur);
        switch (status) {
            case 'Assigné': return 'bg-success';
            case 'Libre': return 'bg-warning';
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
            + "ID,Nom,Type,ID Type,Navire,Date Ajout,Derniere Operation,Statut\n"
            + $scope.filteredConteneurs.map(function(conteneur) {
                return [
                    conteneur.ID_conteneure,
                    conteneur.NOM_conteneure,
                    conteneur.TYPE_conteneure,
                    conteneur.ID_type || '',
                    conteneur.NOM_navire || 'Aucun navire',
                    $scope.formatDate(conteneur.DATE_AJOUT),
                    conteneur.TYPE_operation || 'Aucune opération',
                    $scope.getStatusText(conteneur)
                ].join(',');
            }).join('\n');
            
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "conteneurs_" + new Date().toISOString().slice(0, 10) + ".csv");
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