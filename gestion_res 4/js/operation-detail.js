// js/operation-detail.js
// Contrôleur AngularJS pour la page de détail d'opération avec intégration BDD

// Application AngularJS
var app = angular.module('gestionPortuaireApp', []);

// Contrôleur principal pour le détail d'opération
app.controller('OperationDetailController', ['$scope', '$http', function($scope, $http) {
    
    // ===== VARIABLES PRINCIPALES =====
    $scope.operation = {};
    $scope.personnel = [];
    $scope.conteneurs = [];
    $scope.equipements = [];
    $scope.arrets = [];
    $scope.loading = true;
    
    // Données disponibles pour ajout
    $scope.availablePersonnel = [];
    $scope.availableSoustraitants = [];
    $scope.availableConteneurs = [];
    $scope.availableEquipements = [];
    
    // Formulaires
    $scope.editForm = {};
    $scope.personnelForm = { selected: null };
    $scope.soustraitantForm = { selected: null };
    $scope.conteneurForm = { selected: null };
    $scope.equipementForm = { selected: null };
    $scope.arretForm = {};

    // Statistiques
    $scope.statistics = {
        personnel: 0,
        conteneurs: 0,
        equipements: 0,
        arrets: 0
    };

    // Alertes
    $scope.alert = {
        show: false,
        type: 'info',
        message: ''
    };

    // Configuration API
    var API_BASE_URL = 'api/operation_detail.php';
    var operationId = null;

    // ===== INITIALISATION =====
    $scope.init = function() {
        console.log('🚀 Initialisation du contrôleur operation detail');
        
        // Récupérer l'ID de l'opération depuis l'URL
        const urlParams = new URLSearchParams(window.location.search);
        operationId = urlParams.get('id');
        
        console.log('📋 ID Opération:', operationId);
        
        if (!operationId) {
            $scope.showAlert('ID opération manquant dans l\'URL', 'danger');
            setTimeout(() => { window.location.href = 'operations.html'; }, 2000);
            return;
        }
        
        // Charger toutes les données nécessaires
        $scope.loadAllData();
    };

    // ===== CHARGEMENT DES DONNÉES =====
    
    // Charger toutes les données en parallèle
    $scope.loadAllData = function() {
        console.log('📊 Chargement complet des données...');
        $scope.loading = true;
        
        const promises = [
            $scope.loadOperationDetails(),
            $scope.loadOperationPersonnel(),
            $scope.loadOperationConteneurs(),
            $scope.loadOperationEquipements(),
            $scope.loadOperationArrets(),
            $scope.loadAvailableData()
        ];
        
        Promise.all(promises)
            .then(() => {
                console.log('✅ Toutes les données chargées');
                $scope.updateStatistics();
                $scope.loading = false;
                $scope.$apply();
            })
            .catch(error => {
                console.error('❌ Erreur lors du chargement:', error);
                $scope.showAlert('Erreur lors du chargement des données', 'danger');
                $scope.loading = false;
                $scope.$apply();
            });
    };
    
    // Charger les détails de l'opération
    $scope.loadOperationDetails = function() {
        return $http.get(API_BASE_URL + '?action=operation&id=' + operationId)
            .then(function(response) {
                if (response.data.success) {
                    $scope.operation = response.data.data;
                    console.log('✅ Opération chargée:', $scope.operation);
                } else {
                    throw new Error('Erreur API: ' + response.data.message);
                }
            })
            .catch(function(error) {
                console.warn('⚠️ Erreur chargement opération, données test utilisées');
                // Données de test en cas d'erreur API
                $scope.operation = {
                    ID_operation: operationId,
                    TYPE_operation: 'Chargement',
                    ID_escale: 'ESC-001',
                    NOM_navire: 'MSC MARINA',
                    NOM_equipe: 'Équipe Alpha',
                    NOM_shift: 'Shift Matin',
                    status: 'En cours',
                    DATE_debut: new Date().toISOString().slice(0, 16),
                    DATE_fin: new Date(Date.now() + 8 * 60 * 60 * 1000).toISOString().slice(0, 16)
                };
            });
    };

    // Charger le personnel de l'opération
    $scope.loadOperationPersonnel = function() {
        return $http.get(API_BASE_URL + '?action=personnel&id=' + operationId)
            .then(function(response) {
                if (response.data.success) {
                    $scope.personnel = response.data.data;
                    console.log('✅ Personnel chargé:', $scope.personnel.length, 'membres');
                } else {
                    throw new Error('Erreur API personnel');
                }
            })
            .catch(function(error) {
                console.warn('⚠️ Erreur chargement personnel');
                $scope.personnel = [];
            });
    };

    // Charger les conteneurs de l'opération
    $scope.loadOperationConteneurs = function() {
        return $http.get(API_BASE_URL + '?action=conteneurs&id=' + operationId)
            .then(function(response) {
                if (response.data.success) {
                    $scope.conteneurs = response.data.data;
                    console.log('✅ Conteneurs chargés:', $scope.conteneurs.length);
                } else {
                    throw new Error('Erreur API conteneurs');
                }
            })
            .catch(function(error) {
                console.warn('⚠️ Erreur chargement conteneurs');
                $scope.conteneurs = [];
            });
    };

    // Charger les équipements de l'opération
    $scope.loadOperationEquipements = function() {
        return $http.get(API_BASE_URL + '?action=equipements&id=' + operationId)
            .then(function(response) {
                if (response.data.success) {
                    $scope.equipements = response.data.data;
                    console.log('✅ Équipements chargés:', $scope.equipements.length);
                } else {
                    throw new Error('Erreur API équipements');
                }
            })
            .catch(function(error) {
                console.warn('⚠️ Erreur chargement équipements');
                $scope.equipements = [];
            });
    };

    // Charger les arrêts de l'opération
    $scope.loadOperationArrets = function() {
        return $http.get(API_BASE_URL + '?action=arrets&id=' + operationId)
            .then(function(response) {
                if (response.data.success) {
                    $scope.arrets = response.data.data;
                    console.log('✅ Arrêts chargés:', $scope.arrets.length);
                } else {
                    throw new Error('Erreur API arrêts');
                }
            })
            .catch(function(error) {
                console.warn('⚠️ Erreur chargement arrêts');
                $scope.arrets = [];
            });
    };

    // Charger les données disponibles pour les ajouts
    $scope.loadAvailableData = function() {
        const promises = [
            // Personnel disponible
            $http.get(API_BASE_URL + '?action=available_personnel')
                .then(response => {
                    if (response.data.success) {
                        $scope.availablePersonnel = response.data.data;
                        console.log('✅ Personnel disponible:', $scope.availablePersonnel.length);
                    }
                })
                .catch(() => {
                    console.warn('⚠️ Erreur personnel disponible');
                    $scope.availablePersonnel = [];
                }),
            
            // Sous-traitants disponibles
            $http.get(API_BASE_URL + '?action=available_soustraitants')
                .then(response => {
                    if (response.data.success) {
                        $scope.availableSoustraitants = response.data.data;
                        console.log('✅ Sous-traitants disponibles:', $scope.availableSoustraitants.length);
                    }
                })
                .catch(() => {
                    console.warn('⚠️ Erreur sous-traitants disponibles');
                    $scope.availableSoustraitants = [];
                }),
            
            // Conteneurs disponibles
            $http.get(API_BASE_URL + '?action=available_conteneurs')
                .then(response => {
                    if (response.data.success) {
                        $scope.availableConteneurs = response.data.data;
                        console.log('✅ Conteneurs disponibles:', $scope.availableConteneurs.length);
                    }
                })
                .catch(() => {
                    console.warn('⚠️ Erreur conteneurs disponibles');
                    $scope.availableConteneurs = [];
                }),
            
            // Équipements disponibles
            $http.get(API_BASE_URL + '?action=available_equipements')
                .then(response => {
                    if (response.data.success) {
                        $scope.availableEquipements = response.data.data;
                        console.log('✅ Équipements disponibles:', $scope.availableEquipements.length);
                    }
                })
                .catch(() => {
                    console.warn('⚠️ Erreur équipements disponibles');
                    $scope.availableEquipements = [];
                })
        ];
        
        return Promise.all(promises);
    };

    // ===== UTILITAIRES DE FORMATAGE =====
    
    $scope.formatDateTime = function(dateTime) {
        if (!dateTime) return null;
        const date = new Date(dateTime);
        return date.toLocaleString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    $scope.formatDuration = function(minutes) {
        if (!minutes) return '0h 0m';
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return hours + 'h ' + mins + 'm';
    };

    $scope.calculateDuration = function() {
        if (!$scope.operation.DATE_debut || !$scope.operation.DATE_fin) return null;
        const debut = new Date($scope.operation.DATE_debut);
        const fin = new Date($scope.operation.DATE_fin);
        const diffMinutes = Math.round((fin - debut) / 60000);
        return $scope.formatDuration(diffMinutes);
    };

    $scope.getStatusBadgeClass = function() {
        if (!$scope.operation.status) return 'bg-secondary';
        switch ($scope.operation.status.toLowerCase()) {
            case 'en cours': return 'bg-primary';
            case 'terminée': return 'bg-success';
            case 'en attente': return 'bg-warning';
            case 'suspendue': return 'bg-info';
            case 'annulée': return 'bg-danger';
            default: return 'bg-secondary';
        }
    };

    $scope.getOperationIcon = function(type) {
        switch (type) {
            case 'Chargement': return 'fas fa-upload';
            case 'Déchargement': return 'fas fa-download';
            case 'Transbordement': return 'fas fa-exchange-alt';
            case 'Stockage': return 'fas fa-warehouse';
            case 'Manutention': return 'fas fa-hands-helping';
            case 'Inspection': return 'fas fa-search';
            default: return 'fas fa-cogs';
        }
    };

    // Mettre à jour les statistiques
    $scope.updateStatistics = function() {
        $scope.statistics = {
            personnel: $scope.personnel.length,
            conteneurs: $scope.conteneurs.length,
            equipements: $scope.equipements.length,
            arrets: $scope.arrets.length
        };
    };

    // ===== GESTION DES MODALS =====
    
    $scope.showEditModal = function() {
        $scope.editForm = angular.copy($scope.operation);
        if ($scope.editForm.DATE_debut) {
            $scope.editForm.DATE_debut = new Date($scope.editForm.DATE_debut).toISOString().slice(0, 16);
        }
        if ($scope.editForm.DATE_fin) {
            $scope.editForm.DATE_fin = new Date($scope.editForm.DATE_fin).toISOString().slice(0, 16);
        }
        const modal = new bootstrap.Modal(document.getElementById('editOperationModal'));
        modal.show();
    };

    $scope.showAddPersonnelModal = function() {
        console.log('📋 Ouverture modal personnel');
        $scope.personnelForm.selected = null;
        $scope.soustraitantForm.selected = null;
        const modal = new bootstrap.Modal(document.getElementById('addPersonnelModal'));
        modal.show();
    };

    $scope.showAddConteneurModal = function() {
        $scope.conteneurForm.selected = null;
        const modal = new bootstrap.Modal(document.getElementById('addConteneurModal'));
        modal.show();
    };

    $scope.showAddEquipementModal = function() {
        $scope.equipementForm.selected = null;
        const modal = new bootstrap.Modal(document.getElementById('addEquipementModal'));
        modal.show();
    };

    $scope.showAddArretModal = function() {
        $scope.arretForm = {
            MOTIF_arret: '',
            DATE_DEBUT_arret: new Date().toISOString().slice(0, 16),
            DATE_FIN_arret: '',
            DURE_arret: 0
        };
        const modal = new bootstrap.Modal(document.getElementById('addArretModal'));
        modal.show();
    };

    // ===== ACTIONS CRUD =====
    
    // Mettre à jour l'opération
    $scope.updateOperation = function() {
        console.log('💾 Mise à jour de l\'opération:', $scope.editForm);
        
        $http.put(API_BASE_URL + '?action=update_operation', $scope.editForm)
            .then(function(response) {
                if (response.data.success) {
                    $scope.operation = angular.copy($scope.editForm);
                    $scope.showAlert('Opération mise à jour avec succès', 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editOperationModal'));
                    if (modal) modal.hide();
                } else {
                    $scope.showAlert('Erreur lors de la mise à jour: ' + response.data.message, 'danger');
                }
            })
            .catch(function(error) {
                console.error('Erreur mise à jour:', error);
                $scope.showAlert('Erreur de connexion lors de la mise à jour', 'danger');
            });
    };

    // Ajouter du personnel (gestion via équipes - nécessite logique supplémentaire)
    $scope.addPersonnel = function() {
        console.log('🏃 Ajout de personnel...');
        
        let selectedPerson = null;
        
        if ($scope.personnelForm.selected) {
            selectedPerson = {
                matricule: $scope.personnelForm.selected.MATRICULE_personnel,
                nom: $scope.personnelForm.selected.NOM_personnel,
                prenom: $scope.personnelForm.selected.PRENOM_personnel,
                fonction: $scope.personnelForm.selected.FONCTION_personnel,
                contact: $scope.personnelForm.selected.CONTACT_personnel || '-',
                type: 'Personnel'
            };
        } else if ($scope.soustraitantForm.selected) {
            selectedPerson = {
                matricule: $scope.soustraitantForm.selected.MATRICULE_soustraiteure,
                nom: $scope.soustraitantForm.selected.NOM_soustraiteure,
                prenom: $scope.soustraitantForm.selected.PRENOM_soustraiteure,
                fonction: $scope.soustraitantForm.selected.FONCTION_soustraiteure,
                contact: $scope.soustraitantForm.selected.CONTACT_soustraiteure || '-',
                type: 'Sous-traitant'
            };
        }
        
        if (selectedPerson) {
            // Vérifier doublon
            const alreadyAssigned = $scope.personnel.some(p => p.matricule === selectedPerson.matricule);
            if (alreadyAssigned) {
                $scope.showAlert('Cette personne est déjà assignée à l\'opération', 'warning');
                return;
            }
            
            // Pour l'instant, ajout local (nécessite logique équipe côté serveur)
            $scope.personnel.push(selectedPerson);
            $scope.updateStatistics();
            
            $scope.personnelForm.selected = null;
            $scope.soustraitantForm.selected = null;
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('addPersonnelModal'));
            if (modal) modal.hide();
            
            $scope.showAlert('Personnel ajouté avec succès (local)', 'success');
            console.log('✅ Personnel ajouté:', selectedPerson);
        } else {
            $scope.showAlert('Veuillez sélectionner un personnel ou un sous-traitant', 'warning');
        }
    };

    // Ajouter un conteneur
    $scope.addConteneur = function() {
        if (!$scope.conteneurForm.selected) {
            $scope.showAlert('Veuillez sélectionner un conteneur', 'warning');
            return;
        }
        
        const alreadyAssigned = $scope.conteneurs.some(c => c.ID_conteneure === $scope.conteneurForm.selected.ID_conteneure);
        if (alreadyAssigned) {
            $scope.showAlert('Ce conteneur est déjà assigné à l\'opération', 'warning');
            return;
        }
        
        const data = {
            ID_operation: operationId,
            ID_conteneure: $scope.conteneurForm.selected.ID_conteneure
        };
        
        $http.post(API_BASE_URL + '?action=add_conteneur', data)
            .then(function(response) {
                if (response.data.success) {
                    const newConteneur = angular.copy($scope.conteneurForm.selected);
                    newConteneur.DATE_AJOUT = new Date().toISOString();
                    $scope.conteneurs.push(newConteneur);
                    $scope.updateStatistics();
                    $scope.conteneurForm.selected = null;
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addConteneurModal'));
                    if (modal) modal.hide();
                    
                    $scope.showAlert('Conteneur ajouté avec succès', 'success');
                } else {
                    $scope.showAlert('Erreur lors de l\'ajout: ' + response.data.message, 'danger');
                }
            })
            .catch(function(error) {
                console.error('Erreur ajout conteneur:', error);
                $scope.showAlert('Erreur de connexion lors de l\'ajout', 'danger');
            });
    };

    // Ajouter un équipement
    $scope.addEquipement = function() {
        if (!$scope.equipementForm.selected) {
            $scope.showAlert('Veuillez sélectionner un équipement', 'warning');
            return;
        }
        
        const alreadyAssigned = $scope.equipements.some(e => e.ID_engin === $scope.equipementForm.selected.ID_engin);
        if (alreadyAssigned) {
            $scope.showAlert('Cet équipement est déjà assigné à l\'opération', 'warning');
            return;
        }
        
        const data = {
            ID_operation: operationId,
            ID_engin: $scope.equipementForm.selected.ID_engin
        };
        
        $http.post(API_BASE_URL + '?action=add_equipement', data)
            .then(function(response) {
                if (response.data.success) {
                    $scope.equipements.push(angular.copy($scope.equipementForm.selected));
                    $scope.updateStatistics();
                    $scope.equipementForm.selected = null;
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addEquipementModal'));
                    if (modal) modal.hide();
                    
                    $scope.showAlert('Équipement ajouté avec succès', 'success');
                } else {
                    $scope.showAlert('Erreur lors de l\'ajout: ' + response.data.message, 'danger');
                }
            })
            .catch(function(error) {
                console.error('Erreur ajout équipement:', error);
                $scope.showAlert('Erreur de connexion lors de l\'ajout', 'danger');
            });
    };

    // Ajouter un arrêt
    $scope.addArret = function() {
        if (!$scope.arretForm.MOTIF_arret || !$scope.arretForm.DATE_DEBUT_arret) {
            $scope.showAlert('Veuillez remplir tous les champs obligatoires', 'warning');
            return;
        }

        // Calculer la durée automatiquement
        if ($scope.arretForm.DATE_DEBUT_arret && $scope.arretForm.DATE_FIN_arret) {
            const debut = new Date($scope.arretForm.DATE_DEBUT_arret);
            const fin = new Date($scope.arretForm.DATE_FIN_arret);
            const dureeMinutes = Math.round((fin - debut) / 60000);
            $scope.arretForm.DURE_arret = dureeMinutes;
        }

        const data = {
            ID_operation: operationId,
            MOTIF_arret: $scope.arretForm.MOTIF_arret,
            DATE_DEBUT_arret: $scope.arretForm.DATE_DEBUT_arret,
            DATE_FIN_arret: $scope.arretForm.DATE_FIN_arret || null,
            DURE_arret: $scope.arretForm.DURE_arret || 0
        };

        $http.post(API_BASE_URL + '?action=add_arret', data)
            .then(function(response) {
                if (response.data.success) {
                    const newArret = {
                        ID_arret: 'AR-' + Date.now().toString().slice(-3),
                        ...data
                    };
                    $scope.arrets.push(newArret);
                    $scope.updateStatistics();
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addArretModal'));
                    if (modal) modal.hide();
                    
                    $scope.showAlert('Arrêt déclaré avec succès', 'success');
                } else {
                    $scope.showAlert('Erreur lors de l\'ajout: ' + response.data.message, 'danger');
                }
            })
            .catch(function(error) {
                console.error('Erreur ajout arrêt:', error);
                $scope.showAlert('Erreur de connexion lors de l\'ajout', 'danger');
            });
    };

    // ===== ACTIONS DE SUPPRESSION =====
    
    $scope.removePersonnel = function(person) {
        if (confirm('Retirer ' + person.prenom + ' ' + person.nom + ' de l\'opération ?')) {
            // Pour l'instant suppression locale (nécessite logique équipe)
            $scope.personnel = $scope.personnel.filter(p => p.matricule !== person.matricule);
            $scope.updateStatistics();
            $scope.showAlert('Personnel retiré de l\'opération (local)', 'success');
        }
    };

    $scope.removeConteneur = function(conteneur) {
        if (confirm('Retirer le conteneur ' + conteneur.ID_conteneure + ' de l\'opération ?')) {
            $http.delete(API_BASE_URL + '?action=remove_conteneur&id=' + conteneur.ID_conteneure + '&operation=' + operationId)
                .then(function(response) {
                    if (response.data.success) {
                        $scope.conteneurs = $scope.conteneurs.filter(c => c.ID_conteneure !== conteneur.ID_conteneure);
                        $scope.updateStatistics();
                        $scope.showAlert('Conteneur retiré de l\'opération', 'success');
                    } else {
                        $scope.showAlert('Erreur lors du retrait: ' + response.data.message, 'danger');
                    }
                })
                .catch(function(error) {
                    console.error('Erreur retrait conteneur:', error);
                    $scope.showAlert('Erreur de connexion lors du retrait', 'danger');
                });
        }
    };

    $scope.removeEquipement = function(equipement) {
        if (confirm('Retirer l\'équipement ' + equipement.NOM_engin + ' de l\'opération ?')) {
            $http.delete(API_BASE_URL + '?action=remove_equipement&id=' + equipement.ID_engin + '&operation=' + operationId)
                .then(function(response) {
                    if (response.data.success) {
                        $scope.equipements = $scope.equipements.filter(e => e.ID_engin !== equipement.ID_engin);
                        $scope.updateStatistics();
                        $scope.showAlert('Équipement retiré de l\'opération', 'success');
                    } else {
                        $scope.showAlert('Erreur lors du retrait: ' + response.data.message, 'danger');
                    }
                })
                .catch(function(error) {
                    console.error('Erreur retrait équipement:', error);
                    $scope.showAlert('Erreur de connexion lors du retrait', 'danger');
                });
        }
    };

    $scope.removeArret = function(arret) {
        if (confirm('Supprimer cet arrêt ?')) {
            $http.delete(API_BASE_URL + '?action=remove_arret&id=' + arret.ID_arret)
                .then(function(response) {
                    if (response.data.success) {
                        $scope.arrets = $scope.arrets.filter(a => a.ID_arret !== arret.ID_arret);
                        $scope.updateStatistics();
                        $scope.showAlert('Arrêt supprimé', 'success');
                    } else {
                        $scope.showAlert('Erreur lors de la suppression: ' + response.data.message, 'danger');
                    }
                })
                .catch(function(error) {
                    console.error('Erreur suppression arrêt:', error);
                    $scope.showAlert('Erreur de connexion lors de la suppression', 'danger');
                });
        }
    };

    // ===== EXPORT ET UTILITAIRES =====
    
    $scope.exportData = function() {
        const data = {
            operation: $scope.operation,
            personnel: $scope.personnel,
            conteneurs: $scope.conteneurs,
            equipements: $scope.equipements,
            arrets: $scope.arrets,
            statistics: $scope.statistics,
            export_date: new Date().toISOString()
        };
        
        const jsonData = JSON.stringify(data, null, 2);
        const blob = new Blob([jsonData], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        
        const link = document.createElement('a');
        link.href = url;
        link.download = 'operation_' + $scope.operation.ID_operation + '_' + new Date().toISOString().slice(0, 10) + '.json';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
        $scope.showAlert('Données exportées avec succès', 'success');
    };

    // Actualiser les données
    $scope.refreshData = function() {
        $scope.loadAllData();
        $scope.showAlert('Données actualisées', 'success');
    };

    // ===== SYSTÈME D'ALERTES =====
    
    $scope.showAlert = function(message, type) {
        $scope.alert = {
            show: true,
            type: type || 'info',
            message: message
        };
        
        setTimeout(function() {
            $scope.hideAlert();
            if (!$scope.$$phase) {
                $scope.$apply();
            }
        }, 5000);
    };

    $scope.hideAlert = function() {
        $scope.alert.show = false;
    };

    // Déconnexion
    $scope.logout = function() {
        if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
            localStorage.removeItem('authToken');
            localStorage.removeItem('userInfo');
            window.location.href = 'login.html';
        }
    };

    // ===== INITIALISATION AUTOMATIQUE =====
    $scope.init();
}]);