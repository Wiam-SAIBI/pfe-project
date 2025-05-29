// Module et contrôleur AngularJS
var app = angular.module('gestionApp', []);

app.controller('EnginsController', function($scope, $http, $timeout) {
    // Configuration
    const apiUrl = 'api/engins.php';
    $scope.itemsPerPage = 10;
    $scope.currentPage = 1;
    $scope.totalPages = 1;
    $scope.Math = window.Math; // Pour les calculs dans la vue
    
    // Données
    $scope.engins = [];
    $scope.filteredEngins = [];
    $scope.displayedEngins = [];
    $scope.selectedEngin = null;
    $scope.enginOperations = []; // Opérations utilisant l'engin sélectionné
    $scope.loading = true;
    $scope.loadingOperations = false;
    $scope.alerts = [];
    $scope.types = []; // Liste des types d'engins pour le filtre et les formulaires
    
    // Formulaires
    $scope.newEngin = {
        nom: '',
        type: ''
    };
    $scope.editEngin = {};
    $scope.viewEngin = {};
    $scope.deleteEngin = {};
    
    // Filtres de recherche
    $scope.search = {
        id: '',
        nom: '',
        type: '',
        global: ''
    };
    
    // Statistiques
    $scope.stats = {
        total: 0,
        types: 0,
        enActivite: 0,
        lastAdded: '-',
        lastAddedDate: '-'
    };
    
    // Initialisation
    $scope.init = function() {
        $scope.loadEnginsData();
    };
    
    // Charger les données des engins
    $scope.loadEnginsData = function() {
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
                    $scope.engins = data.records;
                    $scope.filteredEngins = [...$scope.engins];
                    $scope.updateDisplayedData();
                    $scope.extractTypes();
                    $scope.updateStats($scope.engins);
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
    
    // Extraire la liste des types d'engins pour le filtre
    $scope.extractTypes = function() {
        const uniqueTypes = new Set();
        
        $scope.engins.forEach(function(engin) {
            if (engin.TYPE_engin) {
                uniqueTypes.add(engin.TYPE_engin);
            }
        });
        
        $scope.types = Array.from(uniqueTypes).sort();
    };
    
    // Charger les opérations d'un engin
    $scope.loadEnginOperations = function(enginId) {
        $scope.loadingOperations = true;
        
        $http.get('api/engins.php?id=' + enginId + '&operations=1')
            .then(function(response) {
                var data = response.data;
                if (data.success === false) {
                    $scope.showAlert(data.message, 'danger');
                    $scope.enginOperations = [];
                } else if (data.records && Array.isArray(data.records)) {
                    $scope.enginOperations = data.records;
                }
                
                $scope.loadingOperations = false;
            })
            .catch(function(error) {
                console.error('Erreur lors du chargement des opérations:', error);
                $scope.showAlert('Erreur lors du chargement des opérations. Données de démo affichées.', 'warning');
                $scope.loadDemoOperations();
                $scope.loadingOperations = false;
            });
    };
    
    // Sauvegarder un nouvel engin
    $scope.saveEngin = function() {
        // Vérifier si le formulaire est valide
        if (!$scope.newEngin.nom || !$scope.newEngin.type) {
            $scope.showAlert('Veuillez remplir tous les champs obligatoires.', 'danger');
            return;
        }
        
        var enginData = {
            nom: $scope.newEngin.nom,
            type: $scope.newEngin.type
        };
        
        $http({
            method: 'POST',
            url: apiUrl,
            data: enginData,
            headers: {'Content-Type': 'application/json'}
        }).then(function(response) {
            var data = response.data;
            if (data.success === false) {
                $scope.showAlert(data.message, 'danger');
                return;
            }
            
            // Fermer le modal
            $scope.closeModal('addEnginModal');
            
            // Réinitialiser le formulaire
            $scope.newEngin = {
                nom: '',
                type: ''
            };
            
            // Recharger les données
            $scope.loadEnginsData();
            
            // Afficher un message de succès
            $scope.showAlert(`L'engin ${enginData.nom} a été ajouté avec succès.`, 'success');
        }).catch(function(error) {
            console.error('Erreur lors de la création:', error);
            $scope.showAlert('Erreur lors de la création. Mode démo activé.', 'warning');
            
            // Simulation en mode démo
            $scope.simulateCreateEngin(enginData);
        });
    };
    
    // Modifier un engin
    $scope.startEditEngin = function(engin) {
        // Copier l'objet pour éviter de modifier directement les données
        $scope.editEngin = angular.copy(engin);
        
        // Ouvrir le modal
        $scope.openModal('editEnginModal');
    };
    
    // Mettre à jour un engin
    $scope.updateEngin = function() {
        // Vérifier si le formulaire est valide
        if (!$scope.editEngin.NOM_engin || !$scope.editEngin.TYPE_engin) {
            $scope.showAlert('Veuillez remplir tous les champs obligatoires.', 'danger');
            return;
        }
        
        var enginData = {
            nom: $scope.editEngin.NOM_engin,
            type: $scope.editEngin.TYPE_engin
        };
        
        $http({
            method: 'PUT',
            url: apiUrl + '?id=' + $scope.editEngin.ID_engin,
            data: enginData,
            headers: {'Content-Type': 'application/json'}
        }).then(function(response) {
            var data = response.data;
            if (data.success === false) {
                $scope.showAlert(data.message, 'danger');
                return;
            }
            
            // Fermer le modal
            $scope.closeModal('editEnginModal');
            
            // Recharger les données
            $scope.loadEnginsData();
            
            // Afficher un message de succès
            $scope.showAlert(`L'engin ${enginData.nom} a été mis à jour avec succès.`, 'success');
        }).catch(function(error) {
            console.error('Erreur lors de la mise à jour:', error);
            $scope.showAlert('Erreur lors de la mise à jour. Mode démo activé.', 'warning');
            
            // Simulation en mode démo
            $scope.simulateUpdateEngin($scope.editEngin.ID_engin, enginData);
        });
    };
    
    // Afficher les détails d'un engin
    $scope.viewEnginDetails = function(engin) {
        $scope.viewEngin = angular.copy(engin);
        $scope.openModal('viewEnginModal');
    };
    
    // Préparer la suppression d'un engin
    $scope.confirmDeleteEngin = function(engin) {
        $scope.deleteEngin = angular.copy(engin);
        $scope.openModal('deleteEnginModal');
    };
    
    // Supprimer un engin
    $scope.deleteSelectedEngin = function() {
        $http({
            method: 'DELETE',
            url: apiUrl + '?id=' + $scope.deleteEngin.ID_engin
        }).then(function(response) {
            var data = response.data;
            if (data.success === false) {
                $scope.showAlert(data.message, 'danger');
                return;
            }
            
            // Fermer le modal
            $scope.closeModal('deleteEnginModal');
            
            // Recharger les données
            $scope.loadEnginsData();
            
            // Si l'engin supprimé était sélectionné, désélectionner
            if ($scope.selectedEngin && $scope.selectedEngin.ID_engin === $scope.deleteEngin.ID_engin) {
                $scope.selectedEngin = null;
                $scope.enginOperations = [];
            }
            
            // Afficher un message de succès
            $scope.showAlert(`L'engin ${$scope.deleteEngin.NOM_engin} a été supprimé avec succès.`, 'success');
        }).catch(function(error) {
            console.error('Erreur lors de la suppression:', error);
            $scope.showAlert('Erreur lors de la suppression. Mode démo activé.', 'warning');
            
            // Simulation en mode démo
            $scope.simulateDeleteEngin($scope.deleteEngin.ID_engin);
        });
    };
    
    // Sélectionner un engin pour afficher ses opérations
    $scope.selectEngin = function(engin) {
        $scope.selectedEngin = engin;
        $scope.loadEnginOperations(engin.ID_engin);
    };
    
    // Ouvrir le formulaire d'édition depuis la vue détaillée
    $scope.openEditFromView = function() {
        // Fermer le modal de visualisation
        $scope.closeModal('viewEnginModal');
        
        // Attendre que le modal soit fermé avant d'ouvrir le suivant
        $timeout(function() {
            // Ouvrir le modal d'édition avec les mêmes données
            $scope.startEditEngin($scope.viewEngin);
        }, 500);
    };
    
    // Filtrer les données
    $scope.filterData = function() {
        const id = ($scope.search.id || '').trim().toLowerCase();
        const nom = ($scope.search.nom || '').trim().toLowerCase();
        const type = $scope.search.type;
        const global = ($scope.search.global || '').trim().toLowerCase();
        
        // Filtrer les données
        $scope.filteredEngins = $scope.engins.filter(function(engin) {
            // Si tous les champs de filtre sont vides, retourner tous les enregistrements
            if (!id && !nom && !type && !global) {
                return true;
            }
            
            // Filtrer par ID
            if (id && !engin.ID_engin.toLowerCase().includes(id)) {
                return false;
            }
            
            // Filtrer par nom
            if (nom && !engin.NOM_engin.toLowerCase().includes(nom)) {
                return false;
            }
            
            // Filtrer par type
            if (type && engin.TYPE_engin !== type) {
                return false;
            }
            
            // Filtrer par recherche globale
            if (global) {
                return (
                    engin.ID_engin.toLowerCase().includes(global) ||
                    engin.NOM_engin.toLowerCase().includes(global) ||
                    engin.TYPE_engin.toLowerCase().includes(global)
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
            type: '',
            global: ''
        };
        
        $scope.filteredEngins = [...$scope.engins];
        $scope.currentPage = 1;
        $scope.updateDisplayedData();
    };
    
    // Mettre à jour les données affichées en fonction de la pagination
    $scope.updateDisplayedData = function() {
        const startIndex = ($scope.currentPage - 1) * $scope.itemsPerPage;
        const endIndex = startIndex + $scope.itemsPerPage;
        
        $scope.displayedEngins = $scope.filteredEngins.slice(startIndex, endIndex);
        $scope.totalPages = Math.ceil($scope.filteredEngins.length / $scope.itemsPerPage);
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
        csvContent += 'ID,Nom,Type,Statut\n';
        
        $scope.filteredEngins.forEach(function(engin) {
            const row = [
                engin.ID_engin,
                engin.NOM_engin,
                engin.TYPE_engin,
                engin.status
            ].map(val => `"${val}"`).join(',');
            csvContent += row + '\n';
        });
        
        // Création du lien de téléchargement
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', 'engins_' + new Date().toISOString().slice(0,10) + '.csv');
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
                <title>Liste des Engins - Impression</title>
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
                <h1>Liste des Engins</h1>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        // Ajouter chaque ligne des engins
        $scope.filteredEngins.forEach(function(engin) {
            const statusClass = engin.status === 'En service' ? 'active' : 'inactive';
            
            html += `
                <tr>
                    <td>${engin.ID_engin}</td>
                    <td>${engin.NOM_engin}</td>
                    <td>${engin.TYPE_engin}</td>
                    <td><span class="status ${statusClass}">${engin.status}</span></td>
                </tr>
            `;
        });
        
        // Fermer le tableau et ajouter le pied de page
        html += `
                    </tbody>
                </table>
                <div class="footer">
                    <p>Imprimé le ${new Date().toLocaleDateString()} à ${new Date().toLocaleTimeString()}</p>
                    <p>Total: ${$scope.filteredEngins.length} engins</p>
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
        // Nombre total d'engins
        $scope.stats.total = data.length;
        
        // Nombre de types différents
        const uniqueTypes = new Set();
        data.forEach(function(engin) {
            if (engin.TYPE_engin) {
                uniqueTypes.add(engin.TYPE_engin);
            }
        });
        $scope.stats.types = uniqueTypes.size;
        
        // Nombre d'engins en activité
        $scope.stats.enActivite = data.filter(engin => engin.status === 'En service').length;
        
        // Dernier engin ajouté
        if (data.length > 0) {
            // Pour le démo, prenons le premier engin
            const lastAdded = data[0];
            $scope.stats.lastAdded = lastAdded.NOM_engin;
            $scope.stats.lastAddedDate = new Date().toLocaleDateString();
        }
    };
    
    // Charger des données de démonstration
    $scope.loadDemoData = function() {
        $scope.engins = [
            { ID_engin: 'ENG-001', NOM_engin: 'Grue Portuaire 1', TYPE_engin: 'Grue', status: 'En service' },
            { ID_engin: 'ENG-002', NOM_engin: 'Tracteur de Terminal 1', TYPE_engin: 'Tracteur', status: 'Disponible' },
            { ID_engin: 'ENG-003', NOM_engin: 'Chariot Élévateur 1', TYPE_engin: 'Chariot Élévateur', status: 'Disponible' },
            { ID_engin: 'ENG-004', NOM_engin: 'Reachstacker 1', TYPE_engin: 'Reachstacker', status: 'En service' },
            { ID_engin: 'ENG-005', NOM_engin: 'Straddle Carrier 1', TYPE_engin: 'Straddle Carrier', status: 'Disponible' },
            { ID_engin: 'ENG-006', NOM_engin: 'Grue Portuaire 2', TYPE_engin: 'Grue', status: 'Disponible' },
            { ID_engin: 'ENG-007', NOM_engin: 'Tracteur de Terminal 2', TYPE_engin: 'Tracteur', status: 'En service' }
        ];
        
        $scope.filteredEngins = [...$scope.engins];
        $scope.updateDisplayedData();
        $scope.extractTypes();
        $scope.updateStats($scope.engins);
    };
    
    // Charger des opérations de démonstration
    $scope.loadDemoOperations = function() {
        const now = new Date();
        const yesterday = new Date(now);
        yesterday.setDate(yesterday.getDate() - 1);
        
        const tomorrow = new Date(now);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        $scope.enginOperations = [
            { 
                ID_operation: 'OP-001', 
                TYPE_operation: 'Chargement', 
                ID_escale: 'ESC-001',
                ID_equipe: 'EQ-001',
                DATE_debut: yesterday.toISOString(), 
                DATE_fin: tomorrow.toISOString(),
                status: 'En cours'
            },
            { 
                ID_operation: 'OP-002', 
                TYPE_operation: 'Déchargement', 
                ID_escale: 'ESC-002',
                ID_equipe: 'EQ-002',
                DATE_debut: tomorrow.toISOString(), 
                DATE_fin: new Date(tomorrow.getTime() + 86400000).toISOString(),
                status: 'Planifiée'
            }
        ];
    };
    
    // Simuler l'ajout d'un engin (mode démo)
    $scope.simulateCreateEngin = function(data) {
        // Générer un ID fictif
        const newId = 'ENG-' + String($scope.engins.length + 1).padStart(3, '0');
        
        // Créer le nouvel objet engin
        const newEngin = {
            ID_engin: newId,
            NOM_engin: data.nom,
            TYPE_engin: data.type,
            status: 'Disponible'
        };
        
        // Ajouter au tableau de données
        $scope.engins.unshift(newEngin);
        $scope.filteredEngins = [...$scope.engins];
        
        // Fermer le modal
        $scope.closeModal('addEnginModal');
        
        // Réinitialiser le formulaire
        $scope.newEngin = {
            nom: '',
            type: ''
        };
        
        // Mettre à jour l'affichage
        $scope.updateDisplayedData();
        $scope.extractTypes();
        $scope.updateStats($scope.engins);
        
        // Afficher un message de succès
        $scope.showAlert(`L'engin ${data.nom} a été ajouté avec succès (Mode démo).`, 'success');
    };
    
    // Simuler la mise à jour d'un engin (mode démo)
    $scope.simulateUpdateEngin = function(id, data) {
        // Trouver l'index de l'engin dans le tableau
        const index = $scope.engins.findIndex(e => e.ID_engin === id);
        if (index !== -1) {
            // Mettre à jour les données
            $scope.engins[index].NOM_engin = data.nom;
            $scope.engins[index].TYPE_engin = data.type;
            
            // Mettre à jour l'engin sélectionné si nécessaire
            if ($scope.selectedEngin && $scope.selectedEngin.ID_engin === id) {
                $scope.selectedEngin.NOM_engin = data.nom;
                $scope.selectedEngin.TYPE_engin = data.type;
            }
            
            // Mettre à jour filteredEngins également
            $scope.filteredEngins = [...$scope.engins];
            
            // Fermer le modal
            $scope.closeModal('editEnginModal');
            
            // Mettre à jour l'affichage
            $scope.updateDisplayedData();
            $scope.extractTypes();
            
            // Afficher un message de succès
            $scope.showAlert(`L'engin ${data.nom} a été mis à jour avec succès (Mode démo).`, 'success');
        } else {
            $scope.showAlert('Engin non trouvé.', 'danger');
        }
    };
    
    // Simuler la suppression d'un engin (mode démo)
    $scope.simulateDeleteEngin = function(id) {
        // Trouver l'index de l'engin dans le tableau
        const index = $scope.engins.findIndex(e => e.ID_engin === id);
        if (index !== -1) {
            // Récupérer les informations pour le message
            const engin = $scope.engins[index];
            
            // Supprimer du tableau
            $scope.engins.splice(index, 1);
            
            // Mettre à jour filteredEngins
            $scope.filteredEngins = [...$scope.engins];
            
            // Si l'engin supprimé était sélectionné, désélectionner
            if ($scope.selectedEngin && $scope.selectedEngin.ID_engin === id) {
                $scope.selectedEngin = null;
                $scope.enginOperations = [];
            }
            
            // Fermer le modal
            $scope.closeModal('deleteEnginModal');
            
            // Mettre à jour l'affichage
            $scope.updateDisplayedData();
            $scope.extractTypes();
            $scope.updateStats($scope.engins);
            
            // Afficher un message de succès
            $scope.showAlert(`L'engin ${engin.NOM_engin} a été supprimé avec succès (Mode démo).`, 'success');
        } else {
            $scope.showAlert('Engin non trouvé.', 'danger');
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