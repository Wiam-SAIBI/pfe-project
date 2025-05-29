// Module AngularJS pour la gestion des shifts
angular.module('gestionApp', [])
.controller('ShiftsController', ['$scope', '$http', function($scope, $http) {
    
    // Configuration de l'API
    const API_BASE_URL = 'api/';
    
    // Variables d'initialisation
    $scope.shifts = [];
    $scope.filteredShifts = [];
    $scope.displayedShifts = [];
    $scope.loading = true;
    $scope.alerts = [];
    
    // Pagination
    $scope.currentPage = 1;
    $scope.itemsPerPage = 10;
    $scope.totalPages = 1;
    
    // Statistiques
    $scope.stats = {
        total: 0,
        matin: 0,
        apresmidi: 0,
        nuit: 0
    };
    
    // Objets pour les modals
    $scope.newShift = {
        nom: '',
        heureDebut: '',
        heureFin: ''
    };
    $scope.editShift = {};
    $scope.viewShift = {};
    $scope.deleteShift = {};
    
    // Shifts prédéfinis
    $scope.shiftsPredefinis = [
        { nom: 'Matin', debut: '06:00', fin: '14:00' },
        { nom: 'Après-midi', debut: '14:00', fin: '22:00' },
        { nom: 'Nuit', debut: '22:00', fin: '06:00' },
        { nom: 'Journée', debut: '08:00', fin: '17:00' },
        { nom: 'Service continu', debut: '00:00', fin: '23:59' }
    ];
    
    // Filtres de recherche
    $scope.search = {
        id: '',
        nom: '',
        periode: '',
        global: ''
    };
    
    // Initialisation
    $scope.init = function() {
        console.log('Initialisation du contrôleur Shifts...');
        $scope.loadShifts();
    };
    
    // Charger les shifts
    $scope.loadShifts = function() {
        $scope.loading = true;
        console.log('Chargement des shifts...');
        
        $http.get(API_BASE_URL + 'shifts.php')
            .then(function(response) {
                console.log('Réponse shifts:', response.data);
                if (response.data && response.data.success) {
                    $scope.shifts = response.data.records || [];
                    $scope.filteredShifts = [...$scope.shifts];
                    $scope.calculateStats();
                    $scope.updatePagination();
                    console.log('Shifts chargés:', $scope.shifts.length);
                } else {
                    console.error('Erreur API shifts:', response.data);
                    $scope.shifts = [];
                    $scope.filteredShifts = [];
                    $scope.showAlert('danger', 'Erreur lors du chargement des shifts');
                }
            })
            .catch(function(error) {
                console.error('Erreur connexion shifts:', error);
                $scope.shifts = [];
                $scope.filteredShifts = [];
                $scope.showAlert('danger', 'Erreur de connexion API shifts');
            })
            .finally(function() {
                $scope.loading = false;
            });
    };
    
    // Calculer les statistiques
    $scope.calculateStats = function() {
        $scope.stats.total = $scope.shifts.length;
        $scope.stats.matin = 0;
        $scope.stats.apresmidi = 0;
        $scope.stats.nuit = 0;
        
        $scope.shifts.forEach(function(shift) {
            const periode = $scope.getPeriodeShift(shift);
            switch (periode) {
                case 'Matin':
                    $scope.stats.matin++;
                    break;
                case 'Après-midi':
                    $scope.stats.apresmidi++;
                    break;
                case 'Nuit':
                    $scope.stats.nuit++;
                    break;
            }
        });
    };
    
    // Déterminer la période d'un shift
    $scope.getPeriodeShift = function(shift) {
        if (!shift || !shift.HEURE_debut) return 'Indéterminée';
        
        const heure = shift.HEURE_debut.split(':')[0];
        const heureNum = parseInt(heure);
        
        if (heureNum >= 5 && heureNum < 12) {
            return 'Matin';
        } else if (heureNum >= 12 && heureNum < 20) {
            return 'Après-midi';
        } else {
            return 'Nuit';
        }
    };
    
    // Filtrer les données
    $scope.filterData = function() {
        $scope.filteredShifts = $scope.shifts.filter(function(shift) {
            let match = true;
            
            if ($scope.search.id) {
                match = match && shift.ID_shift.toLowerCase().includes($scope.search.id.toLowerCase());
            }
            
            if ($scope.search.nom) {
                match = match && shift.NOM_shift.toLowerCase().includes($scope.search.nom.toLowerCase());
            }
            
            if ($scope.search.periode) {
                match = match && $scope.getPeriodeShift(shift) === $scope.search.periode;
            }
            
            if ($scope.search.global) {
                const global = $scope.search.global.toLowerCase();
                match = match && (
                    shift.ID_shift.toLowerCase().includes(global) ||
                    shift.NOM_shift.toLowerCase().includes(global) ||
                    (shift.HEURE_debut && shift.HEURE_debut.includes(global)) ||
                    (shift.HEURE_fin && shift.HEURE_fin.includes(global))
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
            periode: '',
            global: ''
        };
        $scope.filteredShifts = [...$scope.shifts];
        $scope.currentPage = 1;
        $scope.updatePagination();
    };
    
    // Mise à jour de la pagination
    $scope.updatePagination = function() {
        $scope.totalPages = Math.ceil($scope.filteredShifts.length / $scope.itemsPerPage);
        const start = ($scope.currentPage - 1) * $scope.itemsPerPage;
        const end = start + $scope.itemsPerPage;
        $scope.displayedShifts = $scope.filteredShifts.slice(start, end);
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
    
    // Appliquer un shift prédéfini
    $scope.applyShiftPredefini = function(shiftPredefini) {
        $scope.newShift.nom = shiftPredefini.nom;
        $scope.newShift.heureDebut = shiftPredefini.debut;
        $scope.newShift.heureFin = shiftPredefini.fin;
    };
    
    // Ajouter un shift
    $scope.saveShift = function() {
        if (!$scope.newShift.nom || !$scope.newShift.heureDebut || !$scope.newShift.heureFin) {
            $scope.showAlert('warning', 'Veuillez remplir tous les champs obligatoires');
            return;
        }
        
        // S'assurer que les heures sont au format string HH:MM
        let heureDebut = $scope.newShift.heureDebut;
        let heureFin = $scope.newShift.heureFin;
        
        // Si c'est un objet Date, extraire seulement l'heure
        if (typeof heureDebut === 'object' && heureDebut instanceof Date) {
            heureDebut = heureDebut.toTimeString().substring(0, 5);
        }
        if (typeof heureFin === 'object' && heureFin instanceof Date) {
            heureFin = heureFin.toTimeString().substring(0, 5);
        }
        
        // Valider le format HH:MM
        const timeRegex = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
        if (!timeRegex.test(heureDebut)) {
            $scope.showAlert('warning', 'Format d\'heure de début invalide. Utilisez HH:MM');
            return;
        }
        if (!timeRegex.test(heureFin)) {
            $scope.showAlert('warning', 'Format d\'heure de fin invalide. Utilisez HH:MM');
            return;
        }
        
        const data = {
            nom_shift: $scope.newShift.nom.trim(),
            heure_debut: heureDebut,
            heure_fin: heureFin
        };
        
        console.log('Données envoyées (corrigées):', data);
        
        $http.post(API_BASE_URL + 'shifts.php', data)
            .then(function(response) {
                console.log('Réponse API:', response.data);
                if (response.data.success) {
                    $scope.showAlert('success', 'Shift ajouté avec succès');
                    $scope.loadShifts();
                    $scope.resetNewShift();
                    bootstrap.Modal.getInstance(document.getElementById('addShiftModal')).hide();
                } else {
                    $scope.showAlert('danger', response.data.message || 'Erreur lors de l\'ajout');
                }
            })
            .catch(function(error) {
                console.error('Erreur complète:', error);
                if (error.data && error.data.message) {
                    $scope.showAlert('danger', 'Erreur: ' + error.data.message);
                } else {
                    $scope.showAlert('danger', 'Erreur de connexion: ' + (error.statusText || 'Erreur inconnue'));
                }
            });
    };
    
    // Modifier un shift
    $scope.editShift = function(shift) {
        $scope.editShift = angular.copy(shift);
        
        // Formatter les heures pour l'input time
        if ($scope.editShift.HEURE_debut) {
            $scope.editShift.HEURE_debut = $scope.editShift.HEURE_debut.substring(0, 5);
        }
        if ($scope.editShift.HEURE_fin) {
            $scope.editShift.HEURE_fin = $scope.editShift.HEURE_fin.substring(0, 5);
        }
        
        const modal = new bootstrap.Modal(document.getElementById('editShiftModal'));
        modal.show();
    };
    
    // Mettre à jour un shift
    $scope.updateShift = function() {
        if (!$scope.editShift.NOM_shift || !$scope.editShift.HEURE_debut || !$scope.editShift.HEURE_fin) {
            $scope.showAlert('warning', 'Veuillez remplir tous les champs obligatoires');
            return;
        }
        
        // S'assurer que les heures sont au format string HH:MM
        let heureDebut = $scope.editShift.HEURE_debut;
        let heureFin = $scope.editShift.HEURE_fin;
        
        // Si c'est un objet Date, extraire seulement l'heure
        if (typeof heureDebut === 'object' && heureDebut instanceof Date) {
            heureDebut = heureDebut.toTimeString().substring(0, 5);
        }
        if (typeof heureFin === 'object' && heureFin instanceof Date) {
            heureFin = heureFin.toTimeString().substring(0, 5);
        }
        
        // Valider le format HH:MM
        const timeRegex = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
        if (!timeRegex.test(heureDebut)) {
            $scope.showAlert('warning', 'Format d\'heure de début invalide. Utilisez HH:MM');
            return;
        }
        if (!timeRegex.test(heureFin)) {
            $scope.showAlert('warning', 'Format d\'heure de fin invalide. Utilisez HH:MM');
            return;
        }
        
        const data = {
            nom_shift: $scope.editShift.NOM_shift.trim(),
            heure_debut: heureDebut,
            heure_fin: heureFin
        };
        
        console.log('Données de mise à jour (corrigées):', data);
        
        $http.put(API_BASE_URL + 'shifts.php?id=' + $scope.editShift.ID_shift, data)
            .then(function(response) {
                console.log('Réponse mise à jour:', response.data);
                if (response.data.success) {
                    $scope.showAlert('success', 'Shift modifié avec succès');
                    $scope.loadShifts();
                    bootstrap.Modal.getInstance(document.getElementById('editShiftModal')).hide();
                } else {
                    $scope.showAlert('danger', response.data.message || 'Erreur lors de la modification');
                }
            })
            .catch(function(error) {
                console.error('Erreur de mise à jour:', error);
                if (error.data && error.data.message) {
                    $scope.showAlert('danger', 'Erreur: ' + error.data.message);
                } else {
                    $scope.showAlert('danger', 'Erreur de connexion: ' + (error.statusText || 'Erreur inconnue'));
                }
            });
    };
    
    // Voir un shift
    $scope.viewShift = function(shift) {
        $scope.viewShift = angular.copy(shift);
        $scope.viewShift.duree = $scope.calculateShiftDuration(shift);
        $scope.viewShift.periode = $scope.getPeriodeShift(shift);
        const modal = new bootstrap.Modal(document.getElementById('viewShiftModal'));
        modal.show();
    };
    
    // Confirmer suppression
    $scope.confirmDeleteShift = function(shift) {
        $scope.deleteShift = angular.copy(shift);
        const modal = new bootstrap.Modal(document.getElementById('deleteShiftModal'));
        modal.show();
    };
    
    // Supprimer un shift
    $scope.deleteShift = function() {
        $http.delete(API_BASE_URL + 'shifts.php?id=' + $scope.deleteShift.ID_shift)
            .then(function(response) {
                if (response.data.success) {
                    $scope.showAlert('success', 'Shift supprimé avec succès');
                    $scope.loadShifts();
                    bootstrap.Modal.getInstance(document.getElementById('deleteShiftModal')).hide();
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
        bootstrap.Modal.getInstance(document.getElementById('viewShiftModal')).hide();
        setTimeout(function() {
            $scope.editShift($scope.viewShift);
        }, 300);
    };
    
    // Réinitialiser nouveau shift
    $scope.resetNewShift = function() {
        $scope.newShift = {
            nom: '',
            heureDebut: '',
            heureFin: ''
        };
    };
    
    // Déconnexion
    $scope.logout = function() {
        localStorage.removeItem('authToken');
        localStorage.removeItem('userInfo');
        window.location.href = 'login.html';
    };
    
    // Utilitaires de formatage
    $scope.formatTime = function(timeString) {
        if (!timeString) return '-';
        return timeString.substring(0, 5);
    };
    
    // Calculer la durée d'un shift
    $scope.calculateShiftDuration = function(shift) {
        if (!shift || !shift.HEURE_debut || !shift.HEURE_fin) return '-';
        
        const debut = shift.HEURE_debut.split(':');
        const fin = shift.HEURE_fin.split(':');
        
        const debutMinutes = parseInt(debut[0]) * 60 + parseInt(debut[1]);
        const finMinutes = parseInt(fin[0]) * 60 + parseInt(fin[1]);
        
        let duree;
        if (finMinutes <= debutMinutes) {
            duree = (24 * 60 - debutMinutes) + finMinutes;
        } else {
            duree = finMinutes - debutMinutes;
        }
        
        const hours = Math.floor(duree / 60);
        const mins = duree % 60;
        
        if (hours > 0) {
            return hours + 'h ' + (mins > 0 ? mins + 'min' : '');
        } else {
            return mins + 'min';
        }
    };
    
    // Obtenir la classe CSS du badge de période
    $scope.getPeriodeBadgeClass = function(shift) {
        const periode = $scope.getPeriodeShift(shift);
        switch (periode) {
            case 'Matin': return 'bg-warning';
            case 'Après-midi': return 'bg-info';
            case 'Nuit': return 'bg-dark';
            default: return 'bg-secondary';
        }
    };
    
    // Vérifier si c'est un shift de nuit
    $scope.isShiftDeNuit = function(shift) {
        if (!shift || !shift.HEURE_debut || !shift.HEURE_fin) return false;
        
        const debut = shift.HEURE_debut.split(':');
        const fin = shift.HEURE_fin.split(':');
        
        const debutMinutes = parseInt(debut[0]) * 60 + parseInt(debut[1]);
        const finMinutes = parseInt(fin[0]) * 60 + parseInt(fin[1]);
        
        return finMinutes <= debutMinutes;
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
            + "ID,Nom,Heure Debut,Heure Fin,Duree,Periode,Operations\n"
            + $scope.filteredShifts.map(function(shift) {
                return [
                    shift.ID_shift,
                    shift.NOM_shift,
                    $scope.formatTime(shift.HEURE_debut),
                    $scope.formatTime(shift.HEURE_fin),
                    $scope.calculateShiftDuration(shift),
                    $scope.getPeriodeShift(shift),
                    shift.NOMBRE_OPERATIONS || 0
                ].join(',');
            }).join('\n');
            
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "shifts_" + new Date().toISOString().slice(0, 10) + ".csv");
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