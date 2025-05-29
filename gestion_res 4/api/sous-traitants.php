<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// En-têtes requis
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Gérer la méthode OPTIONS (pré-vérification CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Inclure la connexion à la base de données
// require_once 'config/database.php';

// Pour la démonstration, utiliser des données statiques
function getDemoDatabase() {
    return [
        [
            'ID_soustraiteure' => 1,
            'MATRICULE_soustraiteure' => 'SUSTR-001',
            'NOM_soustraiteure' => 'Garcia',
            'PRENOM_soustraiteure' => 'Carlos',
            'FONCTION_soustraiteure' => 'Opérateur',
            'CONTACT_soustraiteure' => '06.55.44.33.22',
            'ENTREPRISE_soustraiteure' => 'TransCargo SARL'
        ],
        [
            'ID_soustraiteure' => 2,
            'MATRICULE_soustraiteure' => 'SUSTR-002',
            'NOM_soustraiteure' => 'Silva',
            'PRENOM_soustraiteure' => 'Maria',
            'FONCTION_soustraiteure' => 'Conducteur',
            'CONTACT_soustraiteure' => 'maria.silva@logicargo.com',
            'ENTREPRISE_soustraiteure' => 'LogiCargo SA'
        ],
        [
            'ID_soustraiteure' => 3,
            'MATRICULE_soustraiteure' => 'SUSTR-003',
            'NOM_soustraiteure' => 'Chen',
            'PRENOM_soustraiteure' => 'Wei',
            'FONCTION_soustraiteure' => 'Technicien',
            'CONTACT_soustraiteure' => '06.77.88.99.00',
            'ENTREPRISE_soustraiteure' => 'Asia Maritime'
        ]
    ];
}

// Créer une connexion à la base de données (simulation)
try {
    // $database = new Database();
    // $db = $database->getConnection();
    $db = null; // Simulation - pas de vraie connexion
} catch (Exception $e) {
    $db = null;
}

// Vérifier si la connexion a réussi
if (!$db) {
    // Mode démo - pas d'erreur, juste utiliser les données statiques
    $demo_mode = true;
} else {
    $demo_mode = false;
}

// Déterminer la méthode HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Récupérer l'ID en paramètre si présent
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$equipes = isset($_GET['equipes']) && $_GET['equipes'] == 1;

switch ($method) {
    case 'GET':
        if ($id && $equipes) {
            getEquipesSoustraitant($db, $id);
        } elseif ($id) {
            getOneSoustraitant($db, $id);
        } else {
            getAllSoustraitants($db);
        }
        break;
    
    case 'POST':
        addSoustraitant($db);
        break;
    
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID sous-traitant manquant.'
            ]);
            exit;
        }
        updateSoustraitant($db, $id);
        break;
    
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID sous-traitant manquant.'
            ]);
            exit;
        }
        deleteSoustraitant($db, $id);
        break;
    
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Méthode non autorisée.'
        ]);
        break;
}

/**
 * Récupérer tous les sous-traitants
 */
function getAllSoustraitants($db) {
    try {
        if (!$db) {
            // Mode démo
            $soustraitants = getDemoDatabase();
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Sous-traitants récupérés avec succès (mode démo)',
                'records' => $soustraitants,
                'count' => count($soustraitants)
            ]);
            return;
        }

        // Requête pour récupérer tous les sous-traitants
        $query = "
            SELECT * FROM soustraiteure 
            ORDER BY NOM_soustraiteure, PRENOM_soustraiteure ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            // Tableau pour les données
            $soustraitants_arr = array();
            $soustraitants_arr["records"] = array();
            $soustraitants_arr["count"] = $num;
            
            // Récupérer les résultats
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $soustraitant_item = array(
                    "ID_soustraiteure" => $ID_soustraiteure,
                    "MATRICULE_soustraiteure" => $MATRICULE_soustraiteure,
                    "NOM_soustraiteure" => $NOM_soustraiteure,
                    "PRENOM_soustraiteure" => $PRENOM_soustraiteure,
                    "FONCTION_soustraiteure" => $FONCTION_soustraiteure,
                    "CONTACT_soustraiteure" => $CONTACT_soustraiteure,
                    "ENTREPRISE_soustraiteure" => $ENTREPRISE_soustraiteure
                );
                
                array_push($soustraitants_arr["records"], $soustraitant_item);
            }
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode(array_merge(
                ["success" => true, "message" => 'Sous-traitants récupérés avec succès'],
                $soustraitants_arr
            ));
            return;
        } else {
            // Aucun sous-traitant trouvé
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Aucun sous-traitant trouvé.',
                'records' => [],
                'count' => 0
            ]);
            return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
        ]);
    }
}

/**
 * Récupérer un sous-traitant par ID
 */
function getOneSoustraitant($db, $id) {
    try {
        if (!$db) {
            // Mode démo
            $soustraitants = getDemoDatabase();
            $soustraitant = null;
            
            foreach ($soustraitants as $s) {
                if ($s['ID_soustraiteure'] == $id) {
                    $soustraitant = $s;
                    break;
                }
            }
            
            if ($soustraitant) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Sous-traitant trouvé (mode démo)',
                    'record' => $soustraitant
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Sous-traitant non trouvé.'
                ]);
            }
            return;
        }

        // Requête pour lire un seul enregistrement
        $query = "
            SELECT * FROM soustraiteure 
            WHERE ID_soustraiteure = :id
            LIMIT 0,1
        ";
        
        $stmt = $db->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':id', $id);
        
        // Exécuter la requête
        $stmt->execute();
        
        // Récupérer l'enregistrement
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Créer un tableau
            $soustraitant_arr = array(
                "ID_soustraiteure" => $row['ID_soustraiteure'],
                "MATRICULE_soustraiteure" => $row['MATRICULE_soustraiteure'],
                "NOM_soustraiteure" => $row['NOM_soustraiteure'],
                "PRENOM_soustraiteure" => $row['PRENOM_soustraiteure'],
                "FONCTION_soustraiteure" => $row['FONCTION_soustraiteure'],
                "CONTACT_soustraiteure" => $row['CONTACT_soustraiteure'],
                "ENTREPRISE_soustraiteure" => $row['ENTREPRISE_soustraiteure']
            );
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Sous-traitant trouvé',
                'record' => $soustraitant_arr
            ]);
            return;
        } else {
            // Sous-traitant non trouvé
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Sous-traitant non trouvé.'
            ]);
            return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
        ]);
    }
}

/**
 * Récupérer les équipes d'un sous-traitant
 */
function getEquipesSoustraitant($db, $id) {
    try {
        if (!$db) {
            // Mode démo - équipes factices
            $demo_equipes = [
                ['ID_equipe' => 1, 'NOM_equipe' => 'Équipe Alpha'],
                ['ID_equipe' => 2, 'NOM_equipe' => 'Équipe Beta']
            ];
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Équipes du sous-traitant récupérées avec succès (mode démo)',
                'records' => $demo_equipes,
                'count' => count($demo_equipes)
            ]);
            return;
        }

        // Vérifier si le sous-traitant existe
        $checkQuery = "SELECT COUNT(*) as count FROM soustraiteure WHERE ID_soustraiteure = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Sous-traitant non trouvé.'
            ]);
            return;
        }
        
        // Requête pour récupérer les équipes du sous-traitant
        $query = "
            SELECT e.* 
            FROM equipe e
            JOIN equipe_has_soustraiteure ehs ON e.ID_equipe = ehs.equipe_ID_equipe
            WHERE ehs.soustraiteure_ID_soustraiteure = :id
            ORDER BY e.NOM_equipe ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            // Tableau pour les données
            $equipes_arr = array();
            $equipes_arr["records"] = array();
            $equipes_arr["count"] = $num;
            
            // Récupérer les résultats
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                
                $equipe_item = array(
                    "ID_equipe" => $ID_equipe,
                    "NOM_equipe" => $NOM_equipe
                );
                
                array_push($equipes_arr["records"], $equipe_item);
            }
            
            // Réponse - succès
            http_response_code(200);
            echo json_encode(array_merge(
                ["success" => true, "message" => 'Équipes du sous-traitant récupérées avec succès'],
                $equipes_arr
            ));
            return;
        } else {
            // Aucune équipe trouvée
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Ce sous-traitant n\'est affecté à aucune équipe.',
                'records' => [],
                'count' => 0
            ]);
            return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
        ]);
    }
}

/**
 * Ajouter un nouveau sous-traitant
 */
function addSoustraitant($db) {
    // Récupérer les données JSON envoyées
    $input = file_get_contents("php://input");
    $data = json_decode($input);
    
    // Vérifier si les données JSON sont valides
    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données JSON invalides.'
        ]);
        return;
    }
    
    // Vérifier si les données nécessaires sont fournies
    if (!isset($data->nom) || empty(trim($data->nom)) || 
        !isset($data->prenom) || empty(trim($data->prenom)) || 
        !isset($data->fonction) || empty(trim($data->fonction))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données incomplètes. Nom, prénom et fonction sont requis.'
        ]);
        return;
    }

    if (!$db) {
        // Mode démo - simulation de création
        $newId = rand(100, 999);
        $matricule = 'SUSTR-' . str_pad($newId, 3, '0', STR_PAD_LEFT);
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Sous-traitant créé avec succès (mode démo).',
            'id' => $newId,
            'matricule' => $matricule
        ]);
        return;
    }
    
    try {
        // Requête pour insérer un nouveau sous-traitant
        $query = "
            INSERT INTO soustraiteure (
                NOM_soustraiteure, 
                PRENOM_soustraiteure, 
                FONCTION_soustraiteure, 
                CONTACT_soustraiteure, 
                ENTREPRISE_soustraiteure
            ) VALUES (
                :nom, 
                :prenom, 
                :fonction, 
                :contact, 
                :entreprise
            )
        ";
        $stmt = $db->prepare($query);
        
        // Nettoyer les données
        $nom = htmlspecialchars(strip_tags($data->nom));
        $prenom = htmlspecialchars(strip_tags($data->prenom));
        $fonction = htmlspecialchars(strip_tags($data->fonction));
        $contact = isset($data->contact) ? htmlspecialchars(strip_tags($data->contact)) : null;
        $entreprise = isset($data->entreprise) ? htmlspecialchars(strip_tags($data->entreprise)) : null;
        
        // Liaison des valeurs
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':prenom', $prenom);
        $stmt->bindParam(':fonction', $fonction);
        $stmt->bindParam(':contact', $contact);
        $stmt->bindParam(':entreprise', $entreprise);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Récupérer l'ID généré et le matricule
            $id = $db->lastInsertId();
            
            $query = "SELECT MATRICULE_soustraiteure FROM soustraiteure WHERE ID_soustraiteure = :id LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $matricule = $row ? $row['MATRICULE_soustraiteure'] : null;
            
            // Réponse - succès
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Sous-traitant créé avec succès.',
                'id' => $id,
                'matricule' => $matricule
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de créer le sous-traitant.'
            ]);
            return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la création: ' . $e->getMessage()
        ]);
    }
}

/**
 * Mettre à jour un sous-traitant
 */
function updateSoustraitant($db, $id) {
    // Récupérer les données JSON envoyées
    $input = file_get_contents("php://input");
    $data = json_decode($input);
    
    // Vérifier si les données JSON sont valides
    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données JSON invalides.'
        ]);
        return;
    }
    
    // Vérifier si les données nécessaires sont fournies
    if (!isset($data->nom) || empty(trim($data->nom)) || 
        !isset($data->prenom) || empty(trim($data->prenom)) || 
        !isset($data->fonction) || empty(trim($data->fonction))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données incomplètes. Nom, prénom et fonction sont requis.'
        ]);
        return;
    }

    if (!$db) {
        // Mode démo
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Sous-traitant mis à jour avec succès (mode démo).'
        ]);
        return;
    }
    
    try {
        // Vérifier si le sous-traitant existe
        $checkQuery = "SELECT COUNT(*) as count FROM soustraiteure WHERE ID_soustraiteure = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Sous-traitant non trouvé.'
            ]);
            return;
        }
        
        // Requête pour mettre à jour le sous-traitant
        $query = "
            UPDATE soustraiteure SET 
                NOM_soustraiteure = :nom, 
                PRENOM_soustraiteure = :prenom, 
                FONCTION_soustraiteure = :fonction, 
                CONTACT_soustraiteure = :contact, 
                ENTREPRISE_soustraiteure = :entreprise 
            WHERE ID_soustraiteure = :id
        ";
        $stmt = $db->prepare($query);
        
        // Nettoyer les données
        $nom = htmlspecialchars(strip_tags($data->nom));
        $prenom = htmlspecialchars(strip_tags($data->prenom));
        $fonction = htmlspecialchars(strip_tags($data->fonction));
        $contact = isset($data->contact) ? htmlspecialchars(strip_tags($data->contact)) : null;
        $entreprise = isset($data->entreprise) ? htmlspecialchars(strip_tags($data->entreprise)) : null;
        
        // Liaison des valeurs
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':prenom', $prenom);
        $stmt->bindParam(':fonction', $fonction);
        $stmt->bindParam(':contact', $contact);
        $stmt->bindParam(':entreprise', $entreprise);
        $stmt->bindParam(':id', $id);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Sous-traitant mis à jour avec succès.'
            ]);
            return;
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de mettre à jour le sous-traitant.'
            ]);
            return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
        ]);
    }
}

/**
 * Supprimer un sous-traitant
 */
function deleteSoustraitant($db, $id) {
    if (!$db) {
        // Mode démo
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Sous-traitant supprimé avec succès (mode démo).'
        ]);
        return;
    }

    try {
        // Vérifier si le sous-traitant existe
        $checkQuery = "SELECT COUNT(*) as count FROM soustraiteure WHERE ID_soustraiteure = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Sous-traitant non trouvé.'
            ]);
            return;
        }
        
        // Commencer une transaction
        $db->beginTransaction();
        
        // Supprimer les liens avec les équipes
        $query = "DELETE FROM equipe_has_soustraiteure WHERE soustraiteure_ID_soustraiteure = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        // Requête pour supprimer le sous-traitant
        $query = "DELETE FROM soustraiteure WHERE ID_soustraiteure = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        // Exécuter la requête
        if ($stmt->execute()) {
            // Valider la transaction
            $db->commit();
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Sous-traitant supprimé avec succès.'
            ]);
            return;
        } else {
            // Annuler la transaction en cas d'erreur
            $db->rollBack();
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer le sous-traitant.'
            ]);
            return;
        }
    } catch (PDOException $e) {
        // Annuler la transaction en cas d'exception
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
        ]);
    }
}
?>