// Module et contrôleur AngularJS
var app = angular.module('gestionApp', []);

app.controller('PersonnelController', function($scope, $http, $timeout) {
    // Configuration
    const apiUrl = 'api/personnel.php';
    $scope.itemsPerPage = 10;
    $scope.currentPage = 1;
    $scope.totalPages = 1;
    
    // Données
    $scope.personnel = [];
    $scope.filteredPersonnel = [];
    $scope.displayedPersonnel = [];
    $scope.loading = true;
    $scope.alerts = [];
    $scope.Math = window.Math; // Pour les calculs dans la vue
    
    // Formulaires
    $scope.newPersonnel = {};
    $scope.editPersonnel = {};
    $scope.viewPersonnel = {};
    $scope.deletePersonnel = {};
    
    // Filtres de recherche
    $scope.search = {
        matricule: '',
        nom: '',
        fonction: '',
        global: ''
    };
    
    // Statistiques
    $scope.stats = {
        total: 0,
        mainFunction: '-',
        lastAdded: {
            name: '-',
            date: '-'
        }
    };
    
    // Initialisation
    $scope.init = function() {
        $scope.loadPersonnelData();
        $scope.loadStatsData();
    };
    
    // Charger les données du personnel
    $scope.loadPersonnelData = function() {
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
                    $scope.personnel = data.records;
                    $scope.filteredPersonnel = [...$scope.personnel];
                    $scope.updateDisplayedData();
                    $scope.updateStats($scope.personnel);
                    $scope.initFonctionChart($scope.personnel);
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
    
    // Charger les statistiques
    $scope.loadStatsData = function() {
        $http.get(apiUrl + '?action=stats')
            .then(function(response) {
                var data = response.data;
                if (data.success === false) {
                    console.error('Erreur de chargement des statistiques:', data.message);
                    return;
                }
                
                // Mettre à jour les stats
                $scope.stats.total = data.total || 0;
                
                if (data.by_function && data.by_function.length > 0) {
                    $scope.stats.mainFunction = data.by_function[0].FONCTION_personnel || '-';
                }
                
                if (data.last_added) {
                    $scope.stats.lastAdded.name = data.last_added.PRENOM_personnel + ' ' + data.last_added.NOM_personnel;
                    
                    if (data.last_added.DATE_CREATION) {
                        var date = new Date(data.last_added.DATE_CREATION);
                        $scope.stats.lastAdded.date = date.toLocaleDateString();
                    }
                }
            })
            .catch(function(error) {
                console.error('Erreur lors du chargement des statistiques:', error);
                // Calculer les stats à partir des données locales en cas d'erreur
                $scope.updateStats($scope.personnel);
            });
    };
    
    // Sauvegarder un nouveau membre du personnel
    $scope.savePersonnel = function() {
        // Vérifier si le formulaire est valide
        if (!$scope.newPersonnel.nom || !$scope.newPersonnel.prenom || !$scope.newPersonnel.fonction) {
            $scope.showAlert('Veuillez remplir tous les champs obligatoires.', 'danger');
            return;
        }
        
        var personnelData = {
            nom: $scope.newPersonnel.nom,
            prenom: $scope.newPersonnel.prenom,
            fonction: $scope.newPersonnel.fonction,
            contact: $scope.newPersonnel.contact || ''
        };
        
        $http({
            method: 'POST',
            url: apiUrl,
            data: personnelData,
            headers: {'Content-Type': 'application/json'}
        }).then(function(response) {
            var data = response.data;
            if (data.success === false) {
                $scope.showAlert(data.message, 'danger');
                return;
            }
            
            // Fermer le modal
            $scope.closeModal('addPersonnelModal');
            
            // Réinitialiser le formulaire
            $scope.newPersonnel = {};
            
            // Recharger les données
            $scope.loadPersonnelData();
            $scope.loadStatsData();
            
            // Afficher un message de succès
            $scope.showAlert(`Le membre du personnel ${personnelData.prenom} ${personnelData.nom} a été ajouté avec succès.`, 'success');
        }).catch(function(error) {
            console.error('Erreur lors de la création:', error);
            $scope.showAlert('Erreur lors de la création. Mode démo activé.', 'warning');
            
            // Simulation en mode démo
            $scope.simulateCreatePersonnel(personnelData);
        });
    };
    
    // Modifier un membre du personnel
    $scope.editPersonnel = function(personnel) {
        // Copier l'objet pour éviter de modifier directement les données
        $scope.editPersonnel = angular.copy(personnel);
        
        // Ouvrir le modal
        $scope.openModal('editPersonnelModal');
    };
    
    // Mettre à jour un membre du personnel
    $scope.updatePersonnel = function() {
        // Vérifier si le formulaire est valide
        if (!$scope.editPersonnel.NOM_personnel || !$scope.editPersonnel.PRENOM_personnel || !$scope.editPersonnel.FONCTION_personnel) {
            $scope.showAlert('Veuillez remplir tous les champs obligatoires.', 'danger');
            return;
        }
        
        var personnelData = {
            nom: $scope.editPersonnel.NOM_personnel,
            prenom: $scope.editPersonnel.PRENOM_personnel,
            fonction: $scope.editPersonnel.FONCTION_personnel,
            contact: $scope.editPersonnel.CONTACT_personnel || ''
        };
        
        $http({
            method: 'PUT',
            url: apiUrl + '?id=' + $scope.editPersonnel.ID_personnel,
            data: personnelData,
            headers: {'Content-Type': 'application/json'}
        }).then(function(response) {
            var data = response.data;
            if (data.success === false) {
                $scope.showAlert(data.message, 'danger');
                return;
            }
            
            // Fermer le modal
            $scope.closeModal('editPersonnelModal');
            
            // Recharger les données
            $scope.loadPersonnelData();
            
            // Afficher un message de succès
            $scope.showAlert(`Le membre du personnel ${personnelData.prenom} ${personnelData.nom} a été mis à jour avec succès.`, 'success');
        }).catch(function(error) {
            console.error('Erreur lors de la mise à jour:', error);
            $scope.showAlert('Erreur lors de la mise à jour. Mode démo activé.', 'warning');
            
            // Simulation en mode démo
            $scope.simulateUpdatePersonnel($scope.editPersonnel.ID_personnel, personnelData);
        });
    };
    
    // Afficher les détails d'un membre du personnel
    $scope.viewPersonnel = function(personnel) {
        $scope.viewPersonnel = angular.copy(personnel);
        $scope.openModal('viewPersonnelModal');
    };
    
    // Préparer la suppression d'un membre du personnel
    $scope.confirmDeletePersonnel = function(personnel) {
        $scope.deletePersonnel = angular.copy(personnel);
        $scope.openModal('deletePersonnelModal');
    };
    
    // Supprimer un membre du personnel
    $scope.deletePersonnel = function() {
        $http({
            method: 'DELETE',
            url: apiUrl + '?id=' + $scope.deletePersonnel.ID_personnel
        }).then(function(response) {
            var data = response.data;
            if (data.success === false) {
                $scope.showAlert(data.message, 'danger');
                return;
            }
            
            // Fermer le modal
            $scope.closeModal('deletePersonnelModal');
            
            // Recharger les données
            $scope.loadPersonnelData();
            $scope.loadStatsData();
            
            // Afficher un message de succès
            $scope.showAlert(`Le membre du personnel ${$scope.deletePersonnel.PRENOM_personnel} ${$scope.deletePersonnel.NOM_personnel} a été supprimé avec succès.`, 'success');
        }).catch(function(error) {
            console.error('Erreur lors de la suppression:', error);
            $scope.showAlert('Erreur lors de la suppression. Mode démo activé.', 'warning');
            
            // Simulation en mode démo
            $scope.simulateDeletePersonnel($scope.deletePersonnel.ID_personnel);
        });
    };
    
    // Ouvrir le formulaire d'édition depuis la vue détaillée
    $scope.openEditFromView = function() {
        // Fermer le modal de visualisation
        $scope.closeModal('viewPersonnelModal');
        
        // Attendre que le modal soit fermé avant d'ouvrir le suivant
        $timeout(function() {
            // Ouvrir le modal d'édition avec les mêmes données
            $scope.editPersonnel($scope.viewPersonnel);
        }, 500);
    };
    
    // Filtrer les données
    $scope.filterData = function() {
        const matricule = ($scope.search.matricule || '').trim().toLowerCase();
        const nom = ($scope.search.nom || '').trim().toLowerCase();
        const fonction = $scope.search.fonction;
        const global = ($scope.search.global || '').trim().toLowerCase();
        
        // Filtrer les données
        $scope.filteredPersonnel = $scope.personnel.filter(function(personnel) {
            // Si tous les champs de filtre sont vides, retourner tous les enregistrements
            if (!matricule && !nom && !fonction && !global) {
                return true;
            }
            
            // Filtrer par matricule
            if (matricule && !personnel.MATRICULE_personnel.toLowerCase().includes(matricule)) {
                return false;
            }
            
            // Filtrer par nom
            if (nom && !personnel.NOM_personnel.toLowerCase().includes(nom)) {
                return false;
            }
            
            // Filtrer par fonction
            if (fonction && personnel.FONCTION_personnel !== fonction) {
                return false;
            }
            
            // Filtrer par recherche globale
            if (global) {
                return (
                    personnel.MATRICULE_personnel.toLowerCase().includes(global) ||
                    personnel.NOM_personnel.toLowerCase().includes(global) ||
                    personnel.PRENOM_personnel.toLowerCase().includes(global) ||
                    personnel.FONCTION_personnel.toLowerCase().includes(global) ||
                    (personnel.CONTACT_personnel && personnel.CONTACT_personnel.toLowerCase().includes(global))
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
            matricule: '',
            nom: '',
            fonction: '',
            global: ''
        };
        
        $scope.filteredPersonnel = [...$scope.personnel];
        $scope.currentPage = 1;
        $scope.updateDisplayedData();
    };
    
    // Mettre à jour les données affichées en fonction de la pagination
    $scope.updateDisplayedData = function() {
        const startIndex = ($scope.currentPage - 1) * $scope.itemsPerPage;
        const endIndex = startIndex + $scope.itemsPerPage;
        
        $scope.displayedPersonnel = $scope.filteredPersonnel.slice(startIndex, endIndex);
        $scope.totalPages = Math.ceil($scope.filteredPersonnel.length / $scope.itemsPerPage);
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
        csvContent += 'Matricule,Nom,Prénom,Fonction,Contact\n';
        
        $scope.filteredPersonnel.forEach(function(personnel) {
            const row = [
                personnel.MATRICULE_personnel,
                personnel.NOM_personnel,
                personnel.PRENOM_personnel,
                personnel.FONCTION_personnel,
                personnel.CONTACT_personnel || ''
            ].map(val => `"${val}"`).join(',');
            csvContent += row + '\n';
        });
        
        // Création du lien de téléchargement
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', 'personnel_' + new Date().toISOString().slice(0,10) + '.csv');
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
                <title>Liste du Personnel - Impression</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { text-align: center; margin-bottom: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    tr:nth-child(even) { background-color: #f9f9f9; }
                    .footer { margin-top: 20px; text-align: center; font-size: 12px; color: #777; }
                </style>
            </head>
            <body>
                <h1>Liste du Personnel</h1>
                <table>
                    <thead>
                        <tr>
                            <th>Matricule</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Fonction</th>
                            <th>Contact</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        // Ajouter chaque ligne du personnel
        $scope.filteredPersonnel.forEach(function(personnel) {
            html += `
                <tr>
                    <td>${personnel.MATRICULE_personnel}</td>
                    <td>${personnel.NOM_personnel}</td>
                    <td>${personnel.PRENOM_personnel}</td>
                    <td>${personnel.FONCTION_personnel}</td>
                    <td>${personnel.CONTACT_personnel || '-'}</td>
                </tr>
            `;
        });
        
        // Fermer le tableau et ajouter le pied de page
        html += `
                    </tbody>
                </table>
                <div class="footer">
                    <p>Imprimé le ${new Date().toLocaleDateString()} à ${new Date().toLocaleTimeString()}</p>
                    <p>Total: ${$scope.filteredPersonnel.length} employés</p>
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
        // Nombre total de personnel
        $scope.stats.total = data.length;
        
        // Fonction principale (la plus représentée)
        const fonctionsCount = {};
        let maxCount = 0;
        let mainFunction = '';
        
        data.forEach(function(personnel) {
            const fonction = personnel.FONCTION_personnel;
            fonctionsCount[fonction] = (fonctionsCount[fonction] || 0) + 1;
            
            if (fonctionsCount[fonction] > maxCount) {
                maxCount = fonctionsCount[fonction];
                mainFunction = fonction;
            }
        });
        
        $scope.stats.mainFunction = mainFunction || '-';
        
        // Dernier ajout (on prend le premier élément pour la démo)
        if (data.length > 0) {
            const lastAdded = data[0];
            $scope.stats.lastAdded.name = `${lastAdded.PRENOM_personnel} ${lastAdded.NOM_personnel}`;
            $scope.stats.lastAdded.date = new Date().toLocaleDateString();
        }
    };
    
    // Initialiser le graphique de répartition par fonction
    $scope.initFonctionChart = function(data) {
        // Comptage des fonctions
        const fonctionsCount = {};
        data.forEach(function(personnel) {
            const fonction = personnel.FONCTION_personnel;
            fonctionsCount[fonction] = (fonctionsCount[fonction] || 0) + 1;
        });
        
        const labels = Object.keys(fonctionsCount);
        const counts = labels.map(fonction => fonctionsCount[fonction]);
        
        const chartCanvas = document.getElementById('fonctionChart');
        if (!chartCanvas) return;
        
        if (window.chartInstance) {
            window.chartInstance.destroy();
        }
        
        window.chartInstance = new Chart(chartCanvas, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                        '#5a5c69', '#858796', '#75daad', '#f8f9fa', '#6f42c1'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    };
    
    // Simuler l'ajout d'un membre du personnel (mode démo)
    $scope.simulateCreatePersonnel = function(data) {
        // Générer un ID et un matricule fictifs
        const newId = ($scope.personnel.length > 0 ? Math.max(...$scope.personnel.map(p => parseInt(p.ID_personnel))) : 0) + 1;
        const newMatricule = 'MARMA-' + String(newId).padStart(3, '0');
        
        // Créer le nouvel objet personnel
        const newPersonnel = {
            ID_personnel: newId.toString(),
            MATRICULE_personnel: newMatricule,
            NOM_personnel: data.nom,
            PRENOM_personnel: data.prenom,
            FONCTION_personnel: data.fonction,
            CONTACT_personnel: data.contact
        };
        
        // Ajouter au tableau de données
        $scope.personnel.unshift(newPersonnel);
        $scope.filteredPersonnel = [...$scope.personnel];
        
        // Fermer le modal
        $scope.closeModal('addPersonnelModal');
        
        // Réinitialiser le formulaire
        $scope.newPersonnel = {};
        
        // Mettre à jour l'affichage
        $scope.updateDisplayedData();
        $scope.updateStats($scope.personnel);
        $scope.initFonctionChart($scope.personnel);
        
        // Afficher un message de succès
        $scope.showAlert(`Le membre du personnel ${data.prenom} ${data.nom} a été ajouté avec succès (Mode démo).`, 'success');
    };
    
    // Simuler la mise à jour d'un membre du personnel (mode démo)
    $scope.simulateUpdatePersonnel = function(id, data) {
        // Trouver l'index du personnel dans le tableau
        const index = $scope.personnel.findIndex(p => p.ID_personnel === id);
        if (index !== -1) {
            // Mettre à jour les données
            $scope.personnel[index].NOM_personnel = data.nom;
            $scope.personnel[index].PRENOM_personnel = data.prenom;
            $scope.personnel[index].FONCTION_personnel = data.fonction;
            $scope.personnel[index].CONTACT_personnel = data.contact;
            
            // Mettre à jour filteredPersonnel également
            $scope.filteredPersonnel = [...$scope.personnel];
            
            // Fermer le modal
            $scope.closeModal('editPersonnelModal');
            
            // Mettre à jour l'affichage
            $scope.updateDisplayedData();
            $scope.updateStats($scope.personnel);
            $scope.initFonctionChart($scope.personnel);
            
            // Afficher un message de succès
            $scope.showAlert(`Le membre du personnel ${data.prenom} ${data.nom} a été mis à jour avec succès (Mode démo).`, 'success');
        } else {
            $scope.showAlert('Personnel non trouvé.', 'danger');
        }
    };
    
    // Simuler la suppression d'un membre du personnel (mode démo)
    $scope.simulateDeletePersonnel = function(id) {
        // Trouver l'index du personnel dans le tableau
        const index = $scope.personnel.findIndex(p => p.ID_personnel === id);
        if (index !== -1) {
            // Récupérer les informations pour le message
            const personnel = $scope.personnel[index];
            const nom = personnel.NOM_personnel;
            const prenom = personnel.PRENOM_personnel;
            
            // Supprimer du tableau
            $scope.personnel.splice(index, 1);
            
            // Mettre à jour filteredPersonnel
            $scope.filteredPersonnel = [...$scope.personnel];
            
            // Fermer le modal
            $scope.closeModal('deletePersonnelModal');
            
            // Mettre à jour l'affichage
            $scope.updateDisplayedData();
            $scope.updateStats($scope.personnel);
            $scope.initFonctionChart($scope.personnel);
            
            // Afficher un message de succès
            $scope.showAlert(`Le membre du personnel ${prenom} ${nom} a été supprimé avec succès (Mode démo).`, 'success');
        } else {
            $scope.showAlert('Personnel non trouvé.', 'danger');
        }
    };
    
    // Charger des données de démonstration
    $scope.loadDemoData = function() {
        $scope.personnel = [
            { ID_personnel: '1', MATRICULE_personnel: 'MARMA-001', NOM_personnel: 'Dupont', PRENOM_personnel: 'Jean', FONCTION_personnel: 'Chef d\'équipe', CONTACT_personnel: 'jean.dupont@example.com' },
            { ID_personnel: '2', MATRICULE_personnel: 'MARMA-002', NOM_personnel: 'Martin', PRENOM_personnel: 'Sophie', FONCTION_personnel: 'Grutier', CONTACT_personnel: 'sophie.martin@example.com' },
            { ID_personnel: '3', MATRICULE_personnel: 'MARMA-003', NOM_personnel: 'Dubois', PRENOM_personnel: 'Pierre', FONCTION_personnel: 'Conducteur', CONTACT_personnel: 'pierre.dubois@example.com' },
            { ID_personnel: '4', MATRICULE_personnel: 'MARMA-004', NOM_personnel: 'Robert', PRENOM_personnel: 'Marie', FONCTION_personnel: 'Manutentionnaire', CONTACT_personnel: 'marie.robert@example.com' },
            { ID_personnel: '5', MATRICULE_personnel: 'MARMA-005', NOM_personnel: 'Richard', PRENOM_personnel: 'Thomas', FONCTION_personnel: 'Technicien', CONTACT_personnel: 'thomas.richard@example.com' },
            { ID_personnel: '6', MATRICULE_personnel: 'MARMA-006', NOM_personnel: 'Petit', PRENOM_personnel: 'Lucie', FONCTION_personnel: 'Administratif', CONTACT_personnel: 'lucie.petit@example.com' },
            { ID_personnel: '7', MATRICULE_personnel: 'MARMA-007', NOM_personnel: 'Simon', PRENOM_personnel: 'David', FONCTION_personnel: 'Grutier', CONTACT_personnel: 'david.simon@example.com' },
            { ID_personnel: '8', MATRICULE_personnel: 'MARMA-008', NOM_personnel: 'Michel', PRENOM_personnel: 'Julie', FONCTION_personnel: 'Manutentionnaire', CONTACT_personnel: 'julie.michel@example.com' },
            { ID_personnel: '9', MATRICULE_personnel: 'MARMA-009', NOM_personnel: 'Leroy', PRENOM_personnel: 'Antoine', FONCTION_personnel: 'Conducteur', CONTACT_personnel: 'antoine.leroy@example.com' },
            { ID_personnel: '10', MATRICULE_personnel: 'MARMA-010', NOM_personnel: 'Roux', PRENOM_personnel: 'Emilie', FONCTION_personnel: 'Technicien', CONTACT_personnel: 'emilie.roux@example.com' },
            { ID_personnel: '11', MATRICULE_personnel: 'MARMA-011', NOM_personnel: 'Vincent', PRENOM_personnel: 'Nicolas', FONCTION_personnel: 'Chef d\'équipe', CONTACT_personnel: 'nicolas.vincent@example.com' },
            { ID_personnel: '12', MATRICULE_personnel: 'MARMA-012', NOM_personnel: 'Mercier', PRENOM_personnel: 'Stephanie', FONCTION_personnel: 'Administratif', CONTACT_personnel: 'stephanie.mercier@example.com' }
        ];
        
        $scope.filteredPersonnel = [...$scope.personnel];
        $scope.updateDisplayedData();
        $scope.updateStats($scope.personnel);
        $scope.initFonctionChart($scope.personnel);
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
    
    // Directive pour créer le graphique de répartition par fonction
    app.directive('chartFonction', function() {
        return {
            restrict: 'A',
            scope: {
                data: '=chartFonction'
            },
            link: function(scope, element) {
                let chartInstance = null;
                
                // Observer les changements dans les données
                scope.$watch('data', function(newData) {
                    if (newData && newData.length > 0) {
                        updateChart(newData);
                    }
                }, true);
                
                function updateChart(data) {
                    // Comptage des fonctions
                    const fonctionsCount = {};
                    data.forEach(function(personnel) {
                        const fonction = personnel.FONCTION_personnel;
                        fonctionsCount[fonction] = (fonctionsCount[fonction] || 0) + 1;
                    });
                    
                    const labels = Object.keys(fonctionsCount);
                    const counts = labels.map(fonction => fonctionsCount[fonction]);
                    
                    // Détruire le graphique existant si nécessaire
                    if (chartInstance) {
                        chartInstance.destroy();
                    }
                    
                    // Créer le nouveau graphique
                    const ctx = element[0].getContext('2d');
                    chartInstance = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: counts,
                                backgroundColor: [
                                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                                    '#5a5c69', '#858796', '#75daad', '#f8f9fa', '#6f42c1'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
                
                // Nettoyer lors de la destruction du scope
                scope.$on('$destroy', function() {
                    if (chartInstance) {
                        chartInstance.destroy();
                    }
                });
            }
        };
    });
    
    // Initialiser le contrôleur
    $scope.init();
});