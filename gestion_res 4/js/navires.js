// Module et contrôleur AngularJS
var app = angular.module('gestionApp', []);

app.controller('NaviresController', function($scope, $http, $timeout) {
    // Configuration
    const apiUrl = 'api/navires.php';
    $scope.itemsPerPage = 10;
    $scope.currentPage = 1;
    $scope.totalPages = 1;
    $scope.Math = window.Math; // Pour les calculs dans la vue
    
    // Données
    $scope.navires = [];
    $scope.filteredNavires = [];
    $scope.displayedNavires = [];
    $scope.selectedNavire = null;
    $scope.selectedNavireEscales = [];
    $scope.loading = true;
    $scope.loadingEscales = false;
    $scope.alerts = [];
    
    // Formulaires
    $scope.newNavire = {};
    $scope.editNavire = {};
    $scope.viewNavire = {};
    $scope.deleteNavire = {};
    
    // Filtres de recherche
    $scope.search = {
        id: '',
        nom: '',
        matricule: '',
        global: ''
    };
    
    // Statistiques
    $scope.stats = {
        total: 0,
        active: 0,
        lastAdded: '-',
        lastAddedDate: '-'
    };
    
    // Initialisation
    $scope.init = function() {
        $scope.loadNaviresData();
    };
    
    // Charger les données des navires
    $scope.loadNaviresData = function() {
        $scope.loading = true;
        
        $http.get(apiUrl)
            .then(function(response) {
                var data = response.data;
                if (data.success === false) {
                    $scope.showAlert(data.message, 'danger');
                    $scope.loadDemoData();
                    return;
                }
                
                if (data.records && Array.isArray(data.records)) {
                    $scope.navires = data.records;
                    $scope.filteredNavires = [...$scope.navires];
                    $scope.updateDisplayedData();
                    $scope.updateStats($scope.navires);
                }
                
                $scope.loading = false;
            })
            .catch(function(error) {
                console.error('Erreur:', error);
                $scope.showAlert('Erreur de connexion au serveur. Mode démo activé.', 'warning');
                $scope.loadDemoData();
                $scope.loading = false;
            });
    };
    
    // Charger les escales d'un navire
    $scope.loadNavireEscales = function(navireId) {
        $scope.loadingEscales = true;
        
        $http.get('api/escales.php?navire=' + navireId)
            .then(function(response) {
                var data = response.data;
                if (data.success === false) {
                    $scope.showAlert(data.message, 'danger');
                    $scope.selectedNavireEscales = [];} else if (data.records && Array.isArray(data.records)) {
                        $scope.selectedNavireEscales = data.records;
                    }
                    
                    $scope.loadingEscales = false;
                })
                .catch(function(error) {
                    console.error('Erreur lors du chargement des escales:', error);
                    $scope.showAlert('Erreur lors du chargement des escales. Données de démo affichées.', 'warning');
                    $scope.loadDemoEscales();
                    $scope.loadingEscales = false;
                });
        };
        
        // Déterminer le statut d'une escale
        $scope.getEscaleStatus = function(escale) {
            const now = new Date();
            const dateAccostage = new Date(escale.DATE_accostage);
            const dateSortie = new Date(escale.DATE_sortie);
            
            if (now < dateAccostage) {
                return 'Planifiée';
            } else if (now >= dateAccostage && now <= dateSortie) {
                return 'En cours';
            } else {
                return 'Terminée';
            }
        };
        
        // Sauvegarder un nouveau navire
        $scope.saveNavire = function() {
            // Vérifier si le formulaire est valide
            if (!$scope.newNavire.nom || !$scope.newNavire.matricule) {
                $scope.showAlert('Veuillez remplir tous les champs obligatoires.', 'danger');
                return;
            }
            
            var navireData = {
                nom: $scope.newNavire.nom,
                matricule: $scope.newNavire.matricule
            };
            
            $http({
                method: 'POST',
                url: apiUrl,
                data: navireData,
                headers: {'Content-Type': 'application/json'}
            }).then(function(response) {
                var data = response.data;
                if (data.success === false) {
                    $scope.showAlert(data.message, 'danger');
                    return;
                }
                
                // Fermer le modal
                $scope.closeModal('addNavireModal');
                
                // Réinitialiser le formulaire
                $scope.newNavire = {};
                
                // Recharger les données
                $scope.loadNaviresData();
                
                // Afficher un message de succès
                $scope.showAlert(`Le navire ${navireData.nom} a été ajouté avec succès.`, 'success');
            }).catch(function(error) {
                console.error('Erreur lors de la création:', error);
                $scope.showAlert('Erreur lors de la création. Mode démo activé.', 'warning');
                
                // Simulation en mode démo
                $scope.simulateCreateNavire(navireData);
            });
        };
        
        // Modifier un navire
        $scope.editNavire = function(navire) {
            // Copier l'objet pour éviter de modifier directement les données
            $scope.editNavire = angular.copy(navire);
            
            // Ouvrir le modal
            $scope.openModal('editNavireModal');
        };
        
        // Mettre à jour un navire
        $scope.updateNavire = function() {
            // Vérifier si le formulaire est valide
            if (!$scope.editNavire.NOM_navire || !$scope.editNavire.MATRICULE_navire) {
                $scope.showAlert('Veuillez remplir tous les champs obligatoires.', 'danger');
                return;
            }
            
            var navireData = {
                nom: $scope.editNavire.NOM_navire,
                matricule: $scope.editNavire.MATRICULE_navire
            };
            
            $http({
                method: 'PUT',
                url: apiUrl + '?id=' + $scope.editNavire.ID_navire,
                data: navireData,
                headers: {'Content-Type': 'application/json'}
            }).then(function(response) {
                var data = response.data;
                if (data.success === false) {
                    $scope.showAlert(data.message, 'danger');
                    return;
                }
                
                // Fermer le modal
                $scope.closeModal('editNavireModal');
                
                // Recharger les données
                $scope.loadNaviresData();
                
                // Afficher un message de succès
                $scope.showAlert(`Le navire ${navireData.nom} a été mis à jour avec succès.`, 'success');
            }).catch(function(error) {
                console.error('Erreur lors de la mise à jour:', error);
                $scope.showAlert('Erreur lors de la mise à jour. Mode démo activé.', 'warning');
                
                // Simulation en mode démo
                $scope.simulateUpdateNavire($scope.editNavire.ID_navire, navireData);
            });
        };
        
        // Afficher les détails d'un navire
        $scope.viewNavire = function(navire) {
            $scope.viewNavire = angular.copy(navire);
            $scope.openModal('viewNavireModal');
        };
        
        // Préparer la suppression d'un navire
        $scope.confirmDeleteNavire = function(navire) {
            $scope.deleteNavire = angular.copy(navire);
            $scope.openModal('deleteNavireModal');
        };
        
        // Supprimer un navire
        $scope.deleteNavire = function() {
            $http({
                method: 'DELETE',
                url: apiUrl + '?id=' + $scope.deleteNavire.ID_navire
            }).then(function(response) {
                var data = response.data;
                if (data.success === false) {
                    $scope.showAlert(data.message, 'danger');
                    return;
                }
                
                // Fermer le modal
                $scope.closeModal('deleteNavireModal');
                
                // Recharger les données
                $scope.loadNaviresData();
                
                // Afficher un message de succès
                $scope.showAlert(`Le navire ${$scope.deleteNavire.NOM_navire} a été supprimé avec succès.`, 'success');
            }).catch(function(error) {
                console.error('Erreur lors de la suppression:', error);
                $scope.showAlert('Erreur lors de la suppression. Mode démo activé.', 'warning');
                
                // Simulation en mode démo
                $scope.simulateDeleteNavire($scope.deleteNavire.ID_navire);
            });
        };
        
        // Sélectionner un navire pour afficher ses escales
        $scope.selectNavire = function(navire) {
            $scope.selectedNavire = navire;
            $scope.loadNavireEscales(navire.ID_navire);
        };
        
        // Ouvrir le formulaire d'édition depuis la vue détaillée
        $scope.openEditFromView = function() {
            // Fermer le modal de visualisation
            $scope.closeModal('viewNavireModal');
            
            // Attendre que le modal soit fermé avant d'ouvrir le suivant
            $timeout(function() {
                // Ouvrir le modal d'édition avec les mêmes données
                $scope.editNavire($scope.viewNavire);
            }, 500);
        };
        
        // Ajouter une escale à un navire
        $scope.addEscale = function(navire) {
            // Rediriger vers la page d'escales avec le navire présélectionné
            // Cette fonctionnalité serait implémentée dans une application complète
            $scope.showAlert("La fonctionnalité d'ajout d'escale sera implémentée dans la page Escales.", 'info');
        };
        
        // Voir les détails d'une escale
        $scope.viewEscale = function(escale) {
            // Rediriger vers la page d'escales avec l'escale sélectionnée
            // Cette fonctionnalité serait implémentée dans une application complète
            $scope.showAlert("La fonctionnalité de vue détaillée d'escale sera implémentée dans la page Escales.", 'info');
        };
        
        // Filtrer les données
        $scope.filterData = function() {
            const id = ($scope.search.id || '').trim().toLowerCase();
            const nom = ($scope.search.nom || '').trim().toLowerCase();
            const matricule = ($scope.search.matricule || '').trim().toLowerCase();
            const global = ($scope.search.global || '').trim().toLowerCase();
            
            // Filtrer les données
            $scope.filteredNavires = $scope.navires.filter(function(navire) {
                // Si tous les champs de filtre sont vides, retourner tous les enregistrements
                if (!id && !nom && !matricule && !global) {
                    return true;
                }
                
                // Filtrer par ID
                if (id && !navire.ID_navire.toLowerCase().includes(id)) {
                    return false;
                }
                
                // Filtrer par nom
                if (nom && !navire.NOM_navire.toLowerCase().includes(nom)) {
                    return false;
                }
                
                // Filtrer par matricule
                if (matricule && !navire.MATRICULE_navire.toLowerCase().includes(matricule)) {
                    return false;
                }
                
                // Filtrer par recherche globale
                if (global) {
                    return (
                        navire.ID_navire.toLowerCase().includes(global) ||
                        navire.NOM_navire.toLowerCase().includes(global) ||
                        navire.MATRICULE_navire.toLowerCase().includes(global)
                    );
                }
                
                return true;
            });
            
            // Réinitialiser la pagination
            $scope.currentPage = 1;
            $scope.updateDisplayedData();
        };
        
        // Réinitialiser les filtres
        $scope.resetFilters = function() {
            $scope.search = {
                id: '',
                nom: '',
                matricule: '',
                global: ''
            };
            
            $scope.filteredNavires = [...$scope.navires];
            $scope.currentPage = 1;
            $scope.updateDisplayedData();
        };
        
        // Mettre à jour les données affichées en fonction de la pagination
        $scope.updateDisplayedData = function() {
            const startIndex = ($scope.currentPage - 1) * $scope.itemsPerPage;
            const endIndex = startIndex + $scope.itemsPerPage;
            
            $scope.displayedNavires = $scope.filteredNavires.slice(startIndex, endIndex);
            $scope.totalPages = Math.ceil($scope.filteredNavires.length / $scope.itemsPerPage);
        };
        
        // Changer de page
        $scope.setPage = function(page) {
            if (page < 1 || page > $scope.totalPages) {
                return;
            }
            
            $scope.currentPage = page;
            $scope.updateDisplayedData();
        };
        
        // Obtenir la liste des pages pour la pagination
        $scope.getPages = function() {
            const pages = [];
            for (let i = 1; i <= $scope.totalPages; i++) {
                pages.push(i);
            }
            return pages;
        };
        
        // Exporter les données au format CSV
        $scope.exportData = function() {
            // Création des données CSV
            let csvContent = 'data:text/csv;charset=utf-8,';
            csvContent += 'ID,Nom,Matricule,Status\n';
            
            $scope.filteredNavires.forEach(function(navire) {
                const status = navire.status || 'Inactif';
                const row = [
                    navire.ID_navire,
                    navire.NOM_navire,
                    navire.MATRICULE_navire,
                    status
                ].map(val => `"${val}"`).join(',');
                csvContent += row + '\n';
            });
            
            // Création du lien de téléchargement
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'navires_' + new Date().toISOString().slice(0,10) + '.csv');
            document.body.appendChild(link);
            
            // Déclenchement du téléchargement
            link.click();
            document.body.removeChild(link);
            
            $scope.showAlert('Export terminé avec succès.', 'success');
        };
        
        // Imprimer les données
        $scope.printData = function() {
            // Création d'une fenêtre d'impression
            const printWindow = window.open('', '_blank');
            
            // Contenu HTML à imprimer
            const html = `
                <!DOCTYPE html>
                <html lang="fr">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Liste des Navires - Impression</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { text-align: center; margin-bottom: 20px; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        tr:nth-child(even) { background-color: #f9f9f9; }
                        .footer { margin-top: 20px; text-align: center; font-size: 12px; color: #777; }
                        .status { padding: 3px 8px; border-radius: 4px; font-size: 12px; color: white; }
                        .active { background-color: #28a745; }
                        .inactive { background-color: #6c757d; }
                    </style>
                </head>
                <body>
                    <h1>Liste des Navires</h1>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Matricule</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            // Ajouter chaque ligne des navires
            $scope.filteredNavires.forEach(function(navire) {
                const statusClass = navire.status === 'En escale' ? 'active' : 'inactive';
                html += `
                    <tr>
                        <td>${navire.ID_navire}</td>
                        <td>${navire.NOM_navire}</td>
                        <td>${navire.MATRICULE_navire}</td>
                        <td><span class="status ${statusClass}">${navire.status || 'Inactif'}</span></td>
                    </tr>
                `;
            });
            
            // Fermer le tableau et ajouter le pied de page
            html += `
                        </tbody>
                    </table>
                    <div class="footer">
                        <p>Imprimé le ${new Date().toLocaleDateString()} à ${new Date().toLocaleTimeString()}</p>
                        <p>Total: ${$scope.filteredNavires.length} navires</p>
                    </div>
                </body>
                </html>
            `;
            
            // Écrire le contenu HTML dans la nouvelle fenêtre
            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();
            
            // Déclencher l'impression après le chargement de la page
            printWindow.onload = function() {
                printWindow.print();
            };
        };
        
        // Mettre à jour les statistiques
        $scope.updateStats = function(data) {
            // Nombre total de navires
            $scope.stats.total = data.length;
            
            // Nombre de navires actifs (en escale)
            $scope.stats.active = data.filter(navire => navire.status === 'En escale').length;
            
            // Dernier ajout
            if (data.length > 0) {
                // En prod, on utiliserait le tri par date d'ajout
                // Pour le démo, prenons le premier élément
                const lastAdded = data[0];
                $scope.stats.lastAdded = lastAdded.NOM_navire;
                $scope.stats.lastAddedDate = new Date().toLocaleDateString();
            }
        };
        
        // Charger des données de démonstration
        $scope.loadDemoData = function() {
            $scope.navires = [
                { ID_navire: 'NAV-001', NOM_navire: 'Maersk Shanghai', MATRICULE_navire: 'MS123456', status: 'En escale' },
                { ID_navire: 'NAV-002', NOM_navire: 'CMA CGM Marco Polo', MATRICULE_navire: 'CGMP789012', status: 'En escale' },
                { ID_navire: 'NAV-003', NOM_navire: 'MSC Gülsün', MATRICULE_navire: 'MSC345678', status: 'Inactif' },
                { ID_navire: 'NAV-004', NOM_navire: 'COSCO Shipping Universe', MATRICULE_navire: 'COSCO901234', status: 'Inactif' },
                { ID_navire: 'NAV-005', NOM_navire: 'Evergreen Triton', MATRICULE_navire: 'ET567890', status: 'Inactif' },
                { ID_navire: 'NAV-006', NOM_navire: 'HMM Oslo', MATRICULE_navire: 'HMMO123456', status: 'Inactif' },
                { ID_navire: 'NAV-007', NOM_navire: 'MOL Triumph', MATRICULE_navire: 'MOLT789012', status: 'Inactif' },
                { ID_navire: 'NAV-008', NOM_navire: 'ONE Apus', MATRICULE_navire: 'ONEA345678', status: 'Inactif' },
                { ID_navire: 'NAV-009', NOM_navire: 'NYK Swan', MATRICULE_navire: 'NYKS901234', status: 'Inactif' },
                { ID_navire: 'NAV-010', NOM_navire: 'Yang Ming Marine', MATRICULE_navire: 'YMM567890', status: 'Inactif' }
            ];
            
            $scope.filteredNavires = [...$scope.navires];
            $scope.updateDisplayedData();
            $scope.updateStats($scope.navires);
        };
        
        // Charger des escales de démonstration
        $scope.loadDemoEscales = function() {
            const now = new Date();
            const yesterday = new Date(now);
            yesterday.setDate(yesterday.getDate() - 1);
            
            const tomorrow = new Date(now);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            const nextWeek = new Date(now);
            nextWeek.setDate(nextWeek.getDate() + 7);
            
            const lastWeek = new Date(now);
            lastWeek.setDate(lastWeek.getDate() - 7);
            
            const twoWeeksAgo = new Date(now);
            twoWeeksAgo.setDate(twoWeeksAgo.getDate() - 14);
            
            $scope.selectedNavireEscales = [
                { 
                    NUM_escale: 'ESC-001', 
                    DATE_accostage: yesterday.toISOString(), 
                    DATE_sortie: tomorrow.toISOString()
                },
                { 
                    NUM_escale: 'ESC-002', 
                    DATE_accostage: tomorrow.toISOString(), 
                    DATE_sortie: nextWeek.toISOString()
                },
                { 
                    NUM_escale: 'ESC-003', 
                    DATE_accostage: twoWeeksAgo.toISOString(), 
                    DATE_sortie: lastWeek.toISOString()
                }
            ];
        };
        
        // Simuler l'ajout d'un navire (mode démo)
        $scope.simulateCreateNavire = function(data) {
            // Générer un ID fictif
            const newId = 'NAV-' + String($scope.navires.length + 1).padStart(3, '0');
            
            // Créer le nouvel objet navire
            const newNavire = {
                ID_navire: newId,
                NOM_navire: data.nom,
                MATRICULE_navire: data.matricule,
                status: 'Inactif'
            };
            
            // Ajouter au tableau de données
            $scope.navires.unshift(newNavire);
            $scope.filteredNavires = [...$scope.navires];
            
            // Fermer le modal
            $scope.closeModal('addNavireModal');
            
            // Réinitialiser le formulaire
            $scope.newNavire = {};
            
            // Mettre à jour l'affichage
            $scope.updateDisplayedData();
            $scope.updateStats($scope.navires);
            
            // Afficher un message de succès
            $scope.showAlert(`Le navire ${data.nom} a été ajouté avec succès (Mode démo).`, 'success');
        };
        
        // Simuler la mise à jour d'un navire (mode démo)
        $scope.simulateUpdateNavire = function(id, data) {
            // Trouver l'index du navire dans le tableau
            const index = $scope.navires.findIndex(n => n.ID_navire === id);
            if (index !== -1) {
                // Mettre à jour les données
                $scope.navires[index].NOM_navire = data.nom;
                $scope.navires[index].MATRICULE_navire = data.matricule;
                
                // Mettre à jour filteredNavires également
                $scope.filteredNavires = [...$scope.navires];
                
                // Fermer le modal
                $scope.closeModal('editNavireModal');
                
                // Mettre à jour l'affichage
                $scope.updateDisplayedData();
                $scope.updateStats($scope.navires);
                
                // Afficher un message de succès
                $scope.showAlert(`Le navire ${data.nom} a été mis à jour avec succès (Mode démo).`, 'success');
            } else {
                $scope.showAlert('Navire non trouvé.', 'danger');
            }
        };
        
        // Simuler la suppression d'un navire (mode démo)
        $scope.simulateDeleteNavire = function(id) {
            // Trouver l'index du navire dans le tableau
            const index = $scope.navires.findIndex(n => n.ID_navire === id);
            if (index !== -1) {
                // Récupérer les informations pour le message
                const navire = $scope.navires[index];
                const nom = navire.NOM_navire;
                
                // Supprimer du tableau
                $scope.navires.splice(index, 1);
                
                // Mettre à jour filteredNavires
                $scope.filteredNavires = [...$scope.navires];
                
                // Fermer le modal
                $scope.closeModal('deleteNavireModal');
                
                // Mettre à jour l'affichage
                $scope.updateDisplayedData();
                $scope.updateStats($scope.navires);
                
                // Afficher un message de succès
                $scope.showAlert(`Le navire ${nom} a été supprimé avec succès (Mode démo).`, 'success');
            } else {
                $scope.showAlert('Navire non trouvé.', 'danger');
            }
        };
        
        // Afficher une alerte
        $scope.showAlert = function(message, type) {
            $scope.alerts.push({
                type: type,
                message: message
            });
            
            // Auto-dismiss après 5 secondes
            $timeout(function() {
                if ($scope.alerts.length > 0) {
                    $scope.alerts.shift();
                }
            }, 5000);
        };
        
        // Fermer une alerte
        $scope.closeAlert = function(index) {
            $scope.alerts.splice(index, 1);
        };
        
        // Ouvrir un modal Bootstrap
        $scope.openModal = function(id) {
            const modalElement = document.getElementById(id);
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        };
        
        // Fermer un modal Bootstrap
        $scope.closeModal = function(id) {
            const modalElement = document.getElementById(id);
            if (modalElement) {
                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        };
        
        // Initialiser le contrôleur
        $scope.init();
    });