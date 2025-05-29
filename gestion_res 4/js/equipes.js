// js/equipes.js
angular.module('gestionApp').controller('EquipeController', function($scope, $http, AuthService, NotificationService) {
    
    // Variables d'état
    $scope.equipes = [];
    $scope.currentEquipe = {};
    $scope.selectedEquipe = {};
    $scope.modalTitle = '';
    $scope.searchTerm = '';
    
    // Variables pour la gestion des membres
    $scope.equipePersonnel = [];
    $scope.equipeSoustraitants = [];
    $scope.availablePersonnel = [];
    $scope.availableSoustraitants = [];
    $scope.selectedPersonnel = null;
    $scope.selectedSoustraitant = null;
    
    // Variables de pagination
    $scope.currentPage = 1;
    $scope.itemsPerPage = 10;
    $scope.totalPages = 1;
    
    // Utilisateur actuel
    $scope.currentUser = AuthService.getCurrentUser();
    
    // Initialisation
    $scope.init = function() {
        if (!AuthService.isAuthenticated()) {
            window.location.href = 'login.html';
            return;
        }
        $scope.loadEquipes();
        $scope.loadPersonnel();
        $scope.loadSoustraitants();
    };
    
    // Charger toutes les équipes
    $scope.loadEquipes = function() {
        $http.get('api/equipes.php').then(
            function(response) {
                if (response.data.success) {
                    $scope.equipes = response.data.data;
                    $scope.updatePagination();
                } else {
                    NotificationService.error('Erreur lors du chargement des équipes');
                }
            },
            function(error) {
                console.error('Erreur:', error);
                NotificationService.error('Erreur de connexion au serveur');
            }
        );
    };
    
    // Charger le personnel disponible
    $scope.loadPersonnel = function() {
        $http.get('api/personnel.php').then(
            function(response) {
                if (response.data.success) {
                    $scope.availablePersonnel = response.data.data;
                }
            },
            function(error) {
                console.error('Erreur chargement personnel:', error);
            }
        );
    };
    
    // Charger les sous-traitants disponibles
    $scope.loadSoustraitants = function() {
        $http.get('api/sous-traitants.php').then(
            function(response) {
                if (response.data.success) {
                    $scope.availableSoustraitants = response.data.data;
                }
            },
            function(error) {
                console.error('Erreur chargement sous-traitants:', error);
            }
        );
    };
    
    // Ouvrir modal pour nouvelle équipe
    $scope.openEquipeModal = function() {
        $scope.currentEquipe = {};
        $scope.modalTitle = 'Nouvelle Équipe';
        const modal = new bootstrap.Modal(document.getElementById('equipeModal'));
        modal.show();
    };
    
    // Modifier une équipe
    $scope.editEquipe = function(equipe) {
        $scope.currentEquipe = angular.copy(equipe);
        $scope.modalTitle = 'Modifier Équipe';
        const modal = new bootstrap.Modal(document.getElementById('equipeModal'));
        modal.show();
    };
    
    // Sauvegarder équipe
    $scope.saveEquipe = function() {
        if (!$scope.currentEquipe.NOM_equipe) {
            NotificationService.error('Le nom de l\'équipe est requis');
            return;
        }
        
        const isEdit = $scope.currentEquipe.ID_equipe;
        const method = isEdit ? 'PUT' : 'POST';
        const url = 'api/equipes.php' + (isEdit ? '?id=' + $scope.currentEquipe.ID_equipe : '');
        
        $http({
            method: method,
            url: url,
            data: $scope.currentEquipe,
            headers: {'Content-Type': 'application/json'}
        }).then(
            function(response) {
                if (response.data.success) {
                    NotificationService.success(isEdit ? 'Équipe modifiée avec succès' : 'Équipe créée avec succès');
                    $scope.loadEquipes();
                    bootstrap.Modal.getInstance(document.getElementById('equipeModal')).hide();
                } else {
                    NotificationService.error(response.data.message || 'Erreur lors de la sauvegarde');
                }
            },
            function(error) {
                console.error('Erreur:', error);
                NotificationService.error('Erreur de connexion au serveur');
            }
        );
    };
    
    // Supprimer équipe
    $scope.deleteEquipe = function(equipe) {
        if (confirm('Êtes-vous sûr de vouloir supprimer cette équipe ?')) {
            $http.delete('api/equipes.php?id=' + equipe.ID_equipe).then(
                function(response) {
                    if (response.data.success) {
                        NotificationService.success('Équipe supprimée avec succès');
                        $scope.loadEquipes();
                    } else {
                        NotificationService.error(response.data.message || 'Erreur lors de la suppression');
                    }
                },
                function(error) {
                    console.error('Erreur:', error);
                    NotificationService.error('Erreur de connexion au serveur');
                }
            );
        }
    };
    
    // Voir détails équipe
    $scope.viewEquipeDetails = function(equipe) {
        $scope.selectedEquipe = angular.copy(equipe);
        const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
        modal.show();
    };
    
    // Gérer les membres d'une équipe
    $scope.manageMembers = function(equipe) {
        $scope.selectedEquipe = angular.copy(equipe);
        $scope.loadEquipeMembers(equipe.ID_equipe);
        const modal = new bootstrap.Modal(document.getElementById('membersModal'));
        modal.show();
    };
    
    // Charger les membres d'une équipe
    $scope.loadEquipeMembers = function(equipeId) {
        // Charger personnel de l'équipe
        $http.get('api/equipes.php?action=personnel&id=' + equipeId).then(
            function(response) {
                if (response.data.success) {
                    $scope.equipePersonnel = response.data.data;
                }
            }
        );
        
        // Charger sous-traitants de l'équipe
        $http.get('api/equipes.php?action=soustraitants&id=' + equipeId).then(
            function(response) {
                if (response.data.success) {
                    $scope.equipeSoustraitants = response.data.data;
                }
            }
        );
    };
    
    // Ajouter personnel à l'équipe
    $scope.addPersonnelToEquipe = function() {
        if (!$scope.selectedPersonnel) return;
        
        const data = {
            equipe_id: $scope.selectedEquipe.ID_equipe,
            personnel_id: $scope.selectedPersonnel.ID_personnel,
            personnel_matricule: $scope.selectedPersonnel.MATRICULE_personnel
        };
        
        $http.post('api/equipes.php?action=add_personnel', data).then(
            function(response) {
                if (response.data.success) {
                    NotificationService.success('Personnel ajouté à l\'équipe');
                    $scope.loadEquipeMembers($scope.selectedEquipe.ID_equipe);
                    $scope.selectedPersonnel = null;
                    $scope.loadEquipes(); // Recharger pour mettre à jour les compteurs
                } else {
                    NotificationService.error(response.data.message || 'Erreur lors de l\'ajout');
                }
            },
            function(error) {
                console.error('Erreur:', error);
                NotificationService.error('Erreur de connexion au serveur');
            }
        );
    };
    
    // Retirer personnel de l'équipe
    $scope.removePersonnelFromEquipe = function(personnel) {
        if (confirm('Retirer ce personnel de l\'équipe ?')) {
            const data = {
                equipe_id: $scope.selectedEquipe.ID_equipe,
                personnel_id: personnel.ID_personnel,
                personnel_matricule: personnel.MATRICULE_personnel
            };
            
            $http.post('api/equipes.php?action=remove_personnel', data).then(
                function(response) {
                    if (response.data.success) {
                        NotificationService.success('Personnel retiré de l\'équipe');
                        $scope.loadEquipeMembers($scope.selectedEquipe.ID_equipe);
                        $scope.loadEquipes(); // Recharger pour mettre à jour les compteurs
                    } else {
                        NotificationService.error(response.data.message || 'Erreur lors du retrait');
                    }
                },
                function(error) {
                    console.error('Erreur:', error);
                    NotificationService.error('Erreur de connexion au serveur');
                }
            );
        }
    };

    // Ajouter sous-traitant à l'équipe
    $scope.addSoustraitantToEquipe = function() {
        if (!$scope.selectedSoustraitant) return;
        
        const data = {
            equipe_id: $scope.selectedEquipe.ID_equipe,
            soustraitant_id: $scope.selectedSoustraitant.ID_soustraitant
        };
        
        $http.post('api/equipes.php?action=add_soustraitant', data).then(
            function(response) {
                if (response.data.success) {
                    NotificationService.success('Sous-traitant ajouté à l\'équipe');
                    $scope.loadEquipeMembers($scope.selectedEquipe.ID_equipe);
                    $scope.selectedSoustraitant = null;
                    $scope.loadEquipes(); // Recharger pour mettre à jour les compteurs
                } else {
                    NotificationService.error(response.data.message || 'Erreur lors de l\'ajout');
                }
            },
            function(error) {
                console.error('Erreur:', error);
                NotificationService.error('Erreur de connexion au serveur');
            }
        );
    };
    
    // Retirer sous-traitant de l'équipe
    $scope.removeSoustraitantFromEquipe = function(soustraitant) {
        if (confirm('Retirer ce sous-traitant de l\'équipe ?')) {
            const data = {
                equipe_id: $scope.selectedEquipe.ID_equipe,
                soustraitant_id: soustraitant.ID_soustraitant
            };
            
            $http.post('api/equipes.php?action=remove_soustraitant', data).then(
                function(response) {
                    if (response.data.success) {
                        NotificationService.success('Sous-traitant retiré de l\'équipe');
                        $scope.loadEquipeMembers($scope.selectedEquipe.ID_equipe);
                        $scope.loadEquipes(); // Recharger pour mettre à jour les compteurs
                    } else {
                        NotificationService.error(response.data.message || 'Erreur lors du retrait');
                    }
                },
                function(error) {
                    console.error('Erreur:', error);
                    NotificationService.error('Erreur de connexion au serveur');
                }
            );
        }
    };

    // Pagination et filtrage
    $scope.updatePagination = function() {
        const filteredEquipes = $scope.getFilteredEquipes();
        $scope.totalPages = Math.ceil(filteredEquipes.length / $scope.itemsPerPage);
        if ($scope.currentPage > $scope.totalPages) {
            $scope.currentPage = 1;
        }
    };

    $scope.getFilteredEquipes = function() {
        if (!$scope.searchTerm) {
            return $scope.equipes;
        }
        return $scope.equipes.filter(function(equipe) {
            return equipe.NOM_equipe.toLowerCase().includes($scope.searchTerm.toLowerCase()) ||
                   (equipe.DESCRIPTION_equipe && equipe.DESCRIPTION_equipe.toLowerCase().includes($scope.searchTerm.toLowerCase()));
        });
    };

    $scope.getPaginatedEquipes = function() {
        const filteredEquipes = $scope.getFilteredEquipes();
        const startIndex = ($scope.currentPage - 1) * $scope.itemsPerPage;
        return filteredEquipes.slice(startIndex, startIndex + $scope.itemsPerPage);
    };

    $scope.goToPage = function(page) {
        if (page >= 1 && page <= $scope.totalPages) {
            $scope.currentPage = page;
        }
    };

    // Watcher pour la recherche
    $scope.$watch('searchTerm', function() {
        $scope.updatePagination();
        $scope.currentPage = 1;
    });

    // Initialiser au chargement
    $scope.init();
});