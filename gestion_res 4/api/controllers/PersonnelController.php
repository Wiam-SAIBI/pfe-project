<?php
class PersonnelController {
    private $db;
    private $personnel;
    
    public function __construct($db) {
        $this->db = $db;
        $this->personnel = new Personnel($db);
    }
    
    // Récupérer tous les membres du personnel
    public function getAll() {
        // Récupérer les données
        $stmt = $this->personnel->read();
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            // Tableau de personnel
            $personnel_arr = array();
            $personnel_arr["records"] = array();
            $personnel_arr["count"] = $num;
            
            // Récupérer le contenu du tableau
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $personnel_item = array(
                    "ID_personnel" => $ID_personnel,
                    "MATRICULE_personnel" => $MATRICULE_personnel,
                    "NOM_personnel" => $NOM_personnel,
                    "PRENOM_personnel" => $PRENOM_personnel,
                    "FONCTION_personnel" => $FONCTION_personnel,
                    "CONTACT_personnel" => $CONTACT_personnel
                );
                
                array_push($personnel_arr["records"], $personnel_item);
            }
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode($personnel_arr);
        } else {
            // Pas de personnel trouvé
            http_response_code(404);
            echo json_encode(array("message" => "Aucun membre du personnel trouvé."));
        }
    }
    
    // Récupérer un seul membre du personnel
    public function getOne($id) {
        // Définir l'ID à lire
        $this->personnel->ID_personnel = is_numeric($id) ? $id : null;
        $this->personnel->MATRICULE_personnel = !is_numeric($id) ? $id : null;
        
        // Lire les détails du membre
        if ($this->personnel->readOne()) {
            // Créer un tableau
            $personnel_arr = array(
                "ID_personnel" => $this->personnel->ID_personnel,
                "MATRICULE_personnel" => $this->personnel->MATRICULE_personnel,
                "NOM_personnel" => $this->personnel->NOM_personnel,
                "PRENOM_personnel" => $this->personnel->PRENOM_personnel,
                "FONCTION_personnel" => $this->personnel->FONCTION_personnel,
                "CONTACT_personnel" => $this->personnel->CONTACT_personnel
            );
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode($personnel_arr);
        } else {
            // Pas trouvé
            http_response_code(404);
            echo json_encode(array("message" => "Membre du personnel non trouvé."));
        }
    }
    
    // Créer un membre du personnel
    public function create() {
        // Récupérer les données POST
        $data = json_decode(file_get_contents("php://input"));
        
        // Vérifier que les données ne sont pas vides
        if (
            !empty($data->nom) &&
            !empty($data->prenom) &&
            !empty($data->fonction)
        ) {
            // Définir les valeurs
            $this->personnel->NOM_personnel = $data->nom;
            $this->personnel->PRENOM_personnel = $data->prenom;
            $this->personnel->FONCTION_personnel = $data->fonction;
            $this->personnel->CONTACT_personnel = $data->contact ?? null;
            
            // Création du membre
            if ($this->personnel->create()) {
                // Réponse - succès
                http_response_code(201);
                echo json_encode(array(
                    "message" => "Membre du personnel créé avec succès.",
                    "id" => $this->personnel->ID_personnel,
                    "matricule" => $this->personnel->MATRICULE_personnel
                ));
            } else {
                // Erreur
                http_response_code(503);
                echo json_encode(array("message" => "Impossible de créer le membre du personnel."));
            }
        } else {
            // Données incomplètes
            http_response_code(400);
            echo json_encode(array("message" => "Impossible de créer le membre du personnel. Données incomplètes."));
        }
    }
    
    // Mettre à jour un membre du personnel
    public function update($id) {
        // Récupérer les données PUT
        $data = json_decode(file_get_contents("php://input"));
        
        // Définir l'ID à mettre à jour
        $this->personnel->ID_personnel = is_numeric($id) ? $id : null;
        $this->personnel->MATRICULE_personnel = !is_numeric($id) ? $id : null;
        
        // Vérifier si le membre existe
        if (!$this->personnel->readOne()) {
            http_response_code(404);
            echo json_encode(array("message" => "Membre du personnel non trouvé."));
            return;
        }
        
        // Mettre à jour uniquement les champs qui sont fournis
        if (isset($data->nom)) $this->personnel->NOM_personnel = $data->nom;
        if (isset($data->prenom)) $this->personnel->PRENOM_personnel = $data->prenom;
        if (isset($data->fonction)) $this->personnel->FONCTION_personnel = $data->fonction;
        if (isset($data->contact)) $this->personnel->CONTACT_personnel = $data->contact;
        
        // Mise à jour du membre
        if ($this->personnel->update()) {
            // Réponse - succès
            http_response_code(200);
            echo json_encode(array("message" => "Membre du personnel mis à jour avec succès."));
        } else {
            // Erreur
            http_response_code(503);
            echo json_encode(array("message" => "Impossible de mettre à jour le membre du personnel."));
        }
    }
    
    // Supprimer un membre du personnel
    public function delete($id) {
        // Définir l'ID à supprimer
        $this->personnel->ID_personnel = is_numeric($id) ? $id : null;
        $this->personnel->MATRICULE_personnel = !is_numeric($id) ? $id : null;
        
        // Vérifier si le membre existe
        if (!$this->personnel->readOne()) {
            http_response_code(404);
            echo json_encode(array("message" => "Membre du personnel non trouvé."));
            return;
        }
        
        // Suppression du membre
        if ($this->personnel->delete()) {
            // Réponse - succès
            http_response_code(200);
            echo json_encode(array("message" => "Membre du personnel supprimé avec succès."));
        } else {
            // Erreur
            http_response_code(503);
            echo json_encode(array("message" => "Impossible de supprimer le membre du personnel."));
        }
    }
    
    // Rechercher des membres du personnel
    public function search() {
        // Récupérer les paramètres de recherche
        $keywords = isset($_GET['q']) ? $_GET['q'] : '';
        
        // Recherche
        $stmt = $this->personnel->search($keywords);
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            // Tableau de personnel
            $personnel_arr = array();
            $personnel_arr["records"] = array();
            $personnel_arr["count"] = $num;
            
            // Récupérer le contenu du tableau
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $personnel_item = array(
                    "ID_personnel" => $ID_personnel,
                    "MATRICULE_personnel" => $MATRICULE_personnel,
                    "NOM_personnel" => $NOM_personnel,
                    "PRENOM_personnel" => $PRENOM_personnel,
                    "FONCTION_personnel" => $FONCTION_personnel,
                    "CONTACT_personnel" => $CONTACT_personnel
                );
                
                array_push($personnel_arr["records"], $personnel_item);
            }
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode($personnel_arr);
        } else {
            // Pas de personnel trouvé
            http_response_code(404);
            echo json_encode(array("message" => "Aucun membre du personnel trouvé avec ces critères."));
        }
    }
    
    // Obtenir les statistiques du personnel
    public function getStats() {
        $response = array();
        
        // Nombre total d'employés
        $response["total"] = $this->personnel->getCount();
        
        // Stats par fonction
        $stmt = $this->personnel->getStatsByFunction();
        $response["byFunction"] = array();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $function_item = array(
                "fonction" => $row['FONCTION_personnel'],
                "count" => $row['count']
            );
            
            array_push($response["byFunction"], $function_item);
        }
        
        // Réponse - succès
        http_response_code(200);
        echo json_encode($response);
    }
}
?>