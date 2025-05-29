// Module et contrôleur AngularJS
var app = angular.module('gestionApp', []);

app.controller('SoustraitantsController', function($scope, $http, $timeout) {
    // Configuration
    const apiUrl = 'api/soustraiteurs.php';
    $scope.itemsPerPage = 10;
    $scope.currentPage = 1;
    $scope.totalPages = 1;
    $scope.Math = window.Math; // Pour les calculs dans la vue
    
    // Données
    $scope.soustraitants = [];
    $scope.filteredSoustraitants = [];
    $scope.displayedSoustraitants = [];
    $scope.selectedSoustraitant = null;
    $scope.equipesSoustraitant = []; // Équipes du sous-traitant sélectionné
    $scope.loading = true;
    $scope.loadingEquipes = false;
    $scope.alerts = [];
    $scope.entreprises = []; // Liste des entreprises pour le filtre et les formulaires
    
    // Formulaires
    $scope.newSoustraitant = {
        nom: '',
        prenom: '',
        fonction: '',
        contact: '',
        entreprise: ''
    };
    $scope.editSoustraitant = {};
    $scope.viewSoustraitant = {};
    $scope.deleteSoustraitant = {};
    
    // Filtres de recherche
    $scope.search = {
        matricule: '',
        nom: '',
        fonction: '',
        entreprise: '',
        global: ''
    };
    
    // Statistiques
    $scope.stats = {
        total: 0,
        entreprises: 0,
        enActivite: 0,
        lastAdded: '-',
        lastAddedDate: '-'
    };
    
    // Initialisation
    $scope.init = function() {
        $scope.loadSoustraitantsData();
    };
    
    // Charger les données des sous-traitants
    $scope.loadSoustraitantsData = function() {
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
                    $scope.soustraitants = data.records;
                    $scope.filteredSoustraitants = [...$scope.soustraitants];
                    $scope.updateDisplayedData();
                    $scope.extractEntreprises();
                    $scope.updateStats($scope.soustraitants);
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
    
    // Extraire la liste des entreprises pour le filtre
    $scope.extractEntreprises = function() {
        const uniqueEntreprises = new Set();
        
        $scope.soustraitants.forEach(function(soustraitant) {
            if (soustraitant.ENTREPRISE_soustraiteure) {
                uniqueEntreprises.add(soustraitant.ENTREPRISE_soustraiteure);
            }
        });
        
        $scope.entreprises = Array.from(uniqueEntreprises).sort();
    };
    
    // Charger les équipes d'un sous-traitant
    $scope.loadSoustraitantEquipes = function(soustraitantId) {
        $scope.loadingEquipes = true;
        
        $http.get('api/soustraiteurs.php?id=' + soustraitantId + '&equipes=1')
            .then(function(response) {
                var data = response.data;
                if (data.success === false) {
                    $scope.showAlert(data.message, 'danger');
                    $scope.equipesSoustraitant = [];
                } else if (data.records && Array.isArray(data.records)) {
                    $scope.equipesSoustraitant = data.records;
                }
                
                $scope.loadingEquipes = false;
            })
            .catch(function(error) {
                console.error('Erreur lors du chargement des équipes:', error);
                $scope.showAlert('Erreur lors du chargement des équipes. Données de démo affichées.', 'warning');
                $scope.loadDemoEquipes();
                $scope.loadingEquipes = false;
            });
    };
    
    // Sauvegarder un nouveau sous-traitant
    $scope.saveSoustraitant = function() {
        // Vérifier si le formulaire est valide
        if (!$scope.newSoustraitant.nom || !$scope.newSoustraitant.prenom || !$scope.newSoustraitant.fonction) {
            $scope.showAlert('Veuillez remplir tous les champs obligatoires.', 'danger');
            return;
        }
        
        var soustraitantData = {
            nom: $scope.newSoustraitant.nom,
            prenom: $scope.newSoustraitant.prenom,
            fonction: $scope.newSoustraitant.fonction,
            contact: $scope.newSoustraitant.contact || null,
            entreprise: $scope.newSoustraitant.entreprise || null
        };
        
        $http({
            method: 'POST',
            url: apiUrl,
            data: soustraitantData,
            headers: {'Content-Type': 'application/json'}
        }).then(function(response) {
            var data = response.data;
            if (data.success === false) {
                $scope.showAlert(data.message, 'danger');
                return;
            }
            
            // Fermer le modal
            $scope.closeModal('addSoustraitantModal');
            
            // Réinitialiser le formulaire
            $scope.newSoustraitant = {
                nom: '',
                prenom: '',
                fonction: '',
                contact: '',
                entreprise: ''
            };
            
            // Recharger les données
            $scope.loadSoustraitantsData();
            
            // Afficher un message de succès
            $scope.showAlert(`Le sous-traitant ${soustraitantData.prenom} ${soustraitantData.nom} a été ajouté avec succès.`, 'success');
        }).catch(function(error) {
            console.error('Erreur lors de la création:', error);
            $scope.showAlert('Erreur lors de la création. Mode démo activé.', 'warning');
            
            // Simulation en mode démo
            $scope.simulateCreateSoustraitant(soustraitantData);
        });
    };
    
    // Modifier un sous-traitant
    $scope.startEditSoustraitant = function(soustraitant) {
        // Copier l'objet pour éviter de modifier directement les données
        $scope.editSoustraitant = angular.copy(soustraitant);
        
        // Ouvrir le modal
        $scope.openModal('editSoustraitantModal');
    };
    
    // Mettre à jour un sous-traitant
    $scope.updateSoustraitant = function() {
        // Vérifier si le formulaire est valide
        if (!$scope.editSoustraitant.NOM_soustraiteure || !$scope.editSoustraitant.PRENOM_soustraiteure || !$scope.editSoustraitant.FONCTION_soustraiteure) {
            $scope.showAlert('Veuillez remplir tous les champs obligatoires.', 'danger');
            return;
        }
        
        var soustraitantData = {
            nom: $scope.editSoustraitant.NOM_soustraiteure,
            prenom: $scope.editSoustraitant.PRENOM_soustraiteure,
            fonction: $scope.editSoustraitant.FONCTION_soustraiteure,
            contact: $scope.editSoustraitant.CONTACT_soustraiteure || null,
            entreprise: $scope.editSoustraitant.ENTREPRISE_soustraiteure || null
        };
        
        $http({
            method: 'PUT',
            url: apiUrl + '?id=' + $scope.editSoustraitant.ID_soustraiteure,
            data: soustraitantData,
            headers: {'Content-Type': 'application/json'}
        }).then(function(response) {
            var data = response.data;
            if (data.success === false) {
                $scope.showAlert(data.message, 'danger');
                return;
            }
            
            // Fermer le modal
            $scope.closeModal('editSoustraitantModal');
            
            // Recharger les données
            $scope.loadSoustraitantsData();
            
            // Afficher un message de succès
            $scope.showAlert(`Le sous-traitant ${soustraitantData.prenom} ${soustraitantData.nom} a été mis à jour avec succès.`, 'success');
        }).catch(function(error) {
            console.error('Erreur lors de la mise à jour:', error);
            $scope.showAlert('Erreur lors de la mise à jour. Mode démo activé.', 'warning');
            
            // Simulation en mode démo
            $scope.simulateUpdateSoustraitant($scope.editSoustraitant.ID_soustraiteure, soustraitantData);
        });
    };
    
    // Afficher les détails d'un sous-traitant
    $scope.viewSoustraitantDetails = function(soustraitant) {
        $scope.viewSoustraitant = angular.copy(soustraitant);
        $scope.openModal('viewSoustraitantModal');
    };
    
    // Préparer la suppression d'un sous-traitant
    $scope.confirmDeleteSoustraitant = function(soustraitant) {
        $scope.deleteSoustraitant = angular.copy(soustraitant);
        $scope.openModal('deleteSoustraitantModal');
    };
    
    // Supprimer un sous-traitant
    $scope.deleteSelectedSoustraitant = function() {
        $http({
            method: 'DELETE',
            url: apiUrl + '?id=' + $scope.deleteSoustraitant.ID_soustraiteure
        }).then(function(response) {
            var data = response.data;
            if (data.success === false) {
                $scope.showAlert(data.message, 'danger');
                return;
            }
            
            // Fermer le modal
            $scope.closeModal('deleteSoustraitantModal');
            
            // Recharger les données
            $scope.loadSoustraitantsData();
            
            // Si le sous-traitant supprimé était sélectionné, désélectionner
            if ($scope.selectedSoustraitant && $scope.selectedSoustraitant.ID_soustraiteure === $scope.deleteSoustraitant.ID_soustraiteure) {
                $scope.selectedSoustraitant = null;
                $scope.equipesSoustraitant = [];
            }
            
            // Afficher un message de succès
            $scope.showAlert(`Le sous-traitant ${$scope.deleteSoustraitant.PRENOM_soustraiteure} ${$scope.deleteSoustraitant.NOM_soustraiteure} a été supprimé avec succès.`, 'success');
        }).catch(function(error) {
            console.error('Erreur lors de la suppression:', error);
            $scope.showAlert('Erreur lors de la suppression. Mode démo activé.', 'warning');
            
            // Simulation en mode démo
            $scope.simulateDeleteSoustraitant($scope.deleteSoustraitant.ID_soustraiteure);
        });
    };
    
    // Sélectionner un sous-traitant pour afficher ses équipes
    $scope.selectSoustraitant = function(soustraitant) {
        $scope.selectedSoustraitant = soustraitant;
        $scope.loadSoustraitantEquipes(soustraitant.ID_soustraiteure);
    };
    
    // Ouvrir le formulaire d'édition depuis la vue détaillée
    $scope.openEditFromView = function() {
        // Fermer le modal de visualisation
        $scope.closeModal('viewSoustraitantModal');
        
        // Attendre que le modal soit fermé avant d'ouvrir le suivant
        $timeout(function() {
            // Ouvrir le modal d'édition avec les mêmes données
            $scope.startEditSoustraitant($scope.viewSoustraitant);
        }, 500);
    };
    
    // Filtrer les données
    $scope.filterData = function() {
        const matricule = ($scope.search.matricule || '').trim().toLowerCase();
        const nom = ($scope.search.nom || '').trim().toLowerCase();
        const fonction = ($scope.search.fonction || '').trim().toLowerCase();
        const entreprise = $scope.search.entreprise;
        const global = ($scope.search.global || '').trim().toLowerCase();
        
        // Filtrer les données
        $scope.filteredSoustraitants = $scope.soustraitants.filter(function(soustraitant) {
            // Si tous les champs de filtre sont vides, retourner tous les enregistrements
            if (!matricule && !nom && !fonction && !entreprise && !global) {
                return true;
            }
            
            // Filtrer par matricule
            if (matricule && !soustraitant.MATRICULE_soustraiteure.toLowerCase().includes(matricule)) {
                return false;
            }
            
            // Filtrer par nom/prénom
            if (nom && !(
                soustraitant.NOM_soustraiteure.toLowerCase().includes(nom) || 
                soustraitant.PRENOM_soustraiteure.toLowerCase().includes(nom)
            )) {
                return false;
            }
            
            // Filtrer par fonction
            if (fonction && !soustraitant.FONCTION_soustraiteure.toLowerCase().includes(fonction)) {
                return false;
            }
            
            // Filtrer par entreprise
            if (entreprise && soustraitant.ENTREPRISE_soustraiteure !== entreprise) {
                return false;
            }
            
            // Filtrer par recherche globale
            if (global) {
                return (
                    soustraitant.MATRICULE_soustraiteure.toLowerCase().includes(global) ||
                    soustraitant.NOM_soustraiteure.toLowerCase().includes(global) ||
                    soustraitant.PRENOM_soustraiteure.toLowerCase().includes(global) ||
                    soustraitant.FONCTION_soustraiteure.toLowerCase().includes(global) ||
                    (soustraitant.ENTREPRISE_soustraiteure && soustraitant.ENTREPRISE_soustraiteure.toLowerCase().includes(global)) ||
                    (soustraitant.CONTACT_soustraiteure && soustraitant.CONTACT_soustraiteure.toLowerCase().includes(global))
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
            entreprise: '',
            global: ''
        };
        
        $scope.filteredSoustraitants = [...$scope.soustraitants];
        $scope.currentPage = 1;
        $scope.updateDisplayedData();
    };
    
    // Mettre à jour les données affichées en fonction de la pagination
    $scope.updateDisplayedData = function() {
        const startIndex = ($scope.currentPage - 1) * $scope.itemsPerPage;
        const endIndex = startIndex + $scope.itemsPerPage;
        
        $scope.displayedSoustraitants = $scope.filteredSoustraitants.slice(startIndex, endIndex);
        $scope.totalPages = Math.ceil($scope.filteredSoustraitants.length / $scope.itemsPerPage);
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
        csvContent += 'Matricule,Nom,Prénom,Fonction,Entreprise,Contact\n';
        
        $scope.filteredSoustraitants.forEach(function(soustraitant) {
            const row = [
                soustraitant.MATRICULE_soustraiteure,
                soustraitant.NOM_soustraiteure,
                soustraitant.PRENOM_soustraiteure,
                soustraitant.FONCTION_soustraiteure,
                soustraitant.ENTREPRISE_soustraiteure || '',
                soustraitant.CONTACT_soustraiteure || ''
            ].map(val => `"${val}"`).join(',');
            csvContent += row + '\n';
        });
        
        // Création du lien de téléchargement
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', 'soustraitants_' + new Date().toISOString().slice(0,10) + '.csv');
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
                <title>Liste des Sous-traitants - Impression</title>
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
                <h1>Liste des Sous-traitants</h1>
                <table>
                    <thead>
                        <tr>
                            <th>Matricule</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Fonction</th>
                            <th>Entreprise</th>
                            <th>Contact</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        // Ajouter chaque ligne des sous-traitants
        $scope.filteredSoustraitants.forEach(function(soustraitant) {
            html += `
                <tr>
                    <td>${soustraitant.MATRICULE_soustraiteure}</td>
                    <td>${soustraitant.NOM_soustraiteure}</td>
                    <td>${soustraitant.PRENOM_soustraiteure}</td>
                    <td>${soustraitant.FONCTION_soustraiteure}</td>
                    <td>${soustraitant.ENTREPRISE_soustraiteure || ''}</td>
                    <td>${soustraitant.CONTACT_soustraiteure || ''}</td>
                </tr>
            `;
        });
        
        // Fermer le tableau et ajouter le pied de page
        html += `
                    </tbody>
                </table>
                <div class="footer">
                    <p>Imprimé le ${new Date().toLocaleDateString()} à ${new Date().toLocaleTimeString()}</p>
                    <p>Total: ${$scope.filteredSoustraitants.length} sous-traitants</p>
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
        // Nombre total de sous-traitants
        $scope.stats.total = data.length;
        
        // Nombre d'entreprises uniques
        const uniqueEntreprises = new Set();
        data.forEach(function(soustraitant) {
            if (soustraitant.ENTREPRISE_soustraiteure) {
                uniqueEntreprises.add(soustraitant.ENTREPRISE_soustraiteure);
            }
        });
        $scope.stats.entreprises = uniqueEntreprises.size;
        
        // Nombre de sous-traitants en activité (présents dans au moins une équipe)
        // Pour le démo, on simule une valeur
        $scope.stats.enActivite = Math.floor(data.length * 0.7);
        
        // Dernier sous-traitant ajouté
        if (data.length > 0) {
            // Pour le démo, prenons le premier sous-traitant
            const lastAdded = data[0];
            $scope.stats.lastAdded = `${lastAdded.PRENOM_soustraiteure} ${lastAdded.NOM_soustraiteure}`;
            $scope.stats.lastAddedDate = new Date().toLocaleDateString();
        }
    };
    
    // Charger des données de démonstration
    $scope.loadDemoData = function() {
        $scope.soustraitants = [
            { ID_soustraiteure: 1, MATRICULE_soustraiteure: 'SUSTR-001', NOM_soustraiteure: 'Leroy', PRENOM_soustraiteure: 'Philippe', FONCTION_soustraiteure: 'Grutier', CONTACT_soustraiteure: '0601020304', ENTREPRISE_soustraiteure: 'GruTech' },
            { ID_soustraiteure: 2, MATRICULE_soustraiteure: 'SUSTR-002', NOM_soustraiteure: 'Moreau', PRENOM_soustraiteure: 'Isabelle', FONCTION_soustraiteure: 'Mécanicien', CONTACT_soustraiteure: '0602030405', ENTREPRISE_soustraiteure: 'MécaPro' },
            { ID_soustraiteure: 3, MATRICULE_soustraiteure: 'SUSTR-003', NOM_soustraiteure: 'Fournier', PRENOM_soustraiteure: 'Laurent', FONCTION_soustraiteure: 'Électricien', CONTACT_soustraiteure: '0603040506', ENTREPRISE_soustraiteure: 'ÉlecService' },
            { ID_soustraiteure: 4, MATRICULE_soustraiteure: 'SUSTR-004', NOM_soustraiteure: 'Dubois', PRENOM_soustraiteure: 'Marie', FONCTION_soustraiteure: 'Soudeur', CONTACT_soustraiteure: '0604050607', ENTREPRISE_soustraiteure: 'SoudurePlus' },
            { ID_soustraiteure: 5, MATRICULE_soustraiteure: 'SUSTR-005', NOM_soustraiteure: 'Mercier', PRENOM_soustraiteure: 'Jean', FONCTION_soustraiteure: 'Plombier', CONTACT_soustraiteure: '0605060708', ENTREPRISE_soustraiteure: 'PlomberieExpress' },
            { ID_soustraiteure: 6, MATRICULE_soustraiteure: 'SUSTR-006', NOM_soustraiteure: 'Lambert', PRENOM_soustraiteure: 'Sophie', FONCTION_soustraiteure: 'Ingénieur', CONTACT_soustraiteure: '0606070809', ENTREPRISE_soustraiteure: 'IngéConsult' },
            { ID_soustraiteure: 7, MATRICULE_soustraiteure: 'SUSTR-007', NOM_soustraiteure: 'Bonnet', PRENOM_soustraiteure: 'Paul', FONCTION_soustraiteure: 'Grutier', CONTACT_soustraiteure: '0607080910', ENTREPRISE_soustraiteure: 'GruTech' }
        ];
        
        $scope.filteredSoustraitants = [...$scope.soustraitants];
        $scope.updateDisplayedData();
        $scope.extractEntreprises();
        $scope.updateStats($scope.soustraitants);
    };
    
    // Charger des équipes de démonstration pour un sous-traitant
    $scope.loadDemoEquipes = function() {
        $scope.equipesSoustraitant = [
            { ID_equipe: 'EQ-001', NOM_equipe: 'Équipe Chargement A' },
            { ID_equipe: 'EQ-003', NOM_equipe: 'Équipe Maintenance' }
        ];
    };
    
    // Simuler l'ajout d'un sous-traitant (mode démo)
    $scope.simulateCreateSoustraitant = function(data) {
        // Générer un ID fictif
        const newId = $scope.soustraitants.length + 1;
        const newMatricule = 'SUSTR-' + String(newId).padStart(3, '0');
        
        // Créer le nouvel objet sous-traitant
        const newSoustraitant = {
            ID_soustraiteure: newId,
            MATRICULE_soustraiteure: newMatricule,
            NOM_soustraiteure: data.nom,
            PRENOM_soustraiteure: data.prenom,
            FONCTION_soustraiteure: data.fonction,
            CONTACT_soustraiteure: data.contact,
            ENTREPRISE_soustraiteure: data.entreprise
        };
        
        // Ajouter au tableau de données
        $scope.soustraitants.unshift(newSoustraitant);
        $scope.filteredSoustraitants = [...$scope.soustraitants];
        
        // Fermer le modal
        $scope.closeModal('addSoustraitantModal');
        
        // Réinitialiser le formulaire
        $scope.newSoustraitant = {
            nom: '',
            prenom: '',
            fonction: '',
            contact: '',
            entreprise: ''
        };
        
        // Mettre à jour l'affichage
        $scope.updateDisplayedData();
        $scope.extractEntreprises();
        $scope.updateStats($scope.soustraitants);
        
        // Afficher un message de succès
        $scope.showAlert(`Le sous-traitant ${data.prenom} ${data.nom} a été ajouté avec succès (Mode démo).`, 'success');
    };
    
    // Simuler la mise à jour d'un sous-traitant (mode démo)
    $scope.simulateUpdateSoustraitant = function(id, data) {
        // Trouver l'index du sous-traitant dans le tableau
        const index = $scope.soustraitants.findIndex(s => s.ID_soustraiteure === id);
        if (index !== -1) {
            // Mettre à jour les données
            $scope.soustraitants[index].NOM_soustraiteure = data.nom;
            $scope.soustraitants[index].PRENOM_soustraiteure = data.prenom;
            $scope.soustraitants[index].FONCTION_soustraiteure = data.fonction;
            $scope.soustraitants[index].CONTACT_soustraiteure = data.contact;
            $scope.soustraitants[index].ENTREPRISE_soustraiteure = data.entreprise;
            
            // Mettre à jour le sous-traitant sélectionné si nécessaire
            if ($scope.selectedSoustraitant && $scope.selectedSoustraitant.ID_soustraiteure === id) {
                $scope.selectedSoustraitant = { ...$scope.soustraitants[index] };
            }
            
            // Mettre à jour filteredSoustraitants également
            $scope.filteredSoustraitants = [...$scope.soustraitants];
            
            // Fermer le modal
            $scope.closeModal('editSoustraitantModal');
            
            // Mettre à jour l'affichage
            $scope.updateDisplayedData();
            $scope.extractEntreprises();
            
            // Afficher un message de succès
            $scope.showAlert(`Le sous-traitant ${data.prenom} ${data.nom} a été mis à jour avec succès (Mode démo).`, 'success');
        } else {
            $scope.showAlert('Sous-traitant non trouvé.', 'danger');
        }
    };
    
    // Simuler la suppression d'un sous-traitant (mode démo)
    $scope.simulateDeleteSoustraitant = function(id) {
        // Trouver l'index du sous-traitant dans le tableau
        const index = $scope.soustraitants.findIndex(s => s.ID_soustraiteure === id);
        if (index !== -1) {
            // Récupérer les informations pour le message
            const soustraitant = $scope.soustraitants[index];
            
            // Supprimer du tableau
            $scope.soustraitants.splice(index, 1);
            
            // Mettre à jour filteredSoustraitants
            $scope.filteredSoustraitants = [...$scope.soustraitants];
            
            // Si le sous-traitant supprimé était sélectionné, désélectionner
            if ($scope.selectedSoustraitant && $scope.selectedSoustraitant.ID_soustraiteure === id) {
                $scope.selectedSoustraitant = null;
                $scope.equipesSoustraitant = [];
            }
            
            // Fermer le modal
            $scope.closeModal('deleteSoustraitantModal');
            
            // Mettre à jour l'affichage
            $scope.updateDisplayedData();
            $scope.extractEntreprises();
            $scope.updateStats($scope.soustraitants);
            
            // Afficher un message de succès
            $scope.showAlert(`Le sous-traitant ${soustraitant.PRENOM_soustraiteure} ${soustraitant.NOM_soustraiteure} a été supprimé avec succès (Mode démo).`, 'success');
        } else {
            $scope.showAlert('Sous-traitant non trouvé.', 'danger');
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