<?php
header("Content-Type: application/json");

define("UPLOAD_DIR", "images/compagnons/");

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            getCompagnon($_GET['id']);
        } else {
            getAllCompagnons();
        }
        break;
    case 'POST':
        createCompagnon();
        break;

    case 'PATCH':
        updateCompagnon($_GET['id']);
        break;

    case 'DELETE':
        deleteCompagnon($_GET['id']);
        break;

    default:
        deliverResponse(400, "Méthode non supportée");
}

function deliverResponse (int $statusCode, string $statusMsg, $data = null) : void {
  if (!is_null($data)) {
    echo json_encode(array(
      'statusCode' => $statusCode,
      'statusMsg' => $statusMsg,
      'compagnons' => $data), true
    );
   } else {
    echo json_encode(array(
      'statusCode' => $statusCode,
      'statusMsg' => $statusMsg), true
    );
  }
}


// Récupérer toutes les tâches (GET)
function getAllCompagnons(): void {
    echo json_encode(json_decode(file_get_contents("compagnons.json"), true)["compagnons"]);
}

// Récupérer une tâche par ID (GET)
function getCompagnon(int $id) : void {
    $json = json_decode(file_get_contents("compagnons.json"), true)["compagnons"];
    foreach ($json["refuges"] as $refuge) { 
        if ($json[$i]["id"] == $id) {
            echo json_encode($json[$i]);
            return;
        }
    }
    http_response_code(404);
    echo "Compagnon avec l'id $id introuvable";
}

function createCompagnon(): void {
    $json = json_decode(file_get_contents("compagnons.json"), true); // Charger le fichier JSON actuel
    $body = $_POST; // Charger le corps de la requête
    $errors = [];

    // Vérification des champs obligatoires
    $requiredFields = [
        "espece",
        "prix"
    ];

    if (especeExiste($json["compagnons"], $body["espece"])) {
        $errors[] = "L'espece $espece existe déjà";
    }

    foreach ($requiredFields as $field) {
        if (!isset($body[$field]) || empty($body[$field])) {
            $errors[] = "Champ $field manquant ou vide";
        }
    }
    
    $fichierResponse = uploadFichier($body["espece"]);
    if ($fichierResponse != true) {
        $errors[] = $fichierResponse;
    }

    // Vérification des erreurs
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode($errors);
    } else {
        // Ajout de l'compagnon si tout est correct
        $newObject = [
            "id" => getMaxId($json) + 1,
            "espece" => $body["espece"],
            "prix" => $body["prix"]
        ];
        $json["compagnons"][] = $newObject; // Ajouter l'compagnon au JSON
        file_put_contents("compagnons.json", json_encode($json, JSON_PRETTY_PRINT)); // Sauvegarder le fichier JSON
        echo json_encode($newObject);
    }
}

function uploadFichier(string $espece): string|bool {
    $errors = [];

    if (is_dir(UPLOAD_DIR . $espece)) {
        $errors[] = "Espece déjà existante";
        return json_encode($errors);
    }

    $humeurs = ["heureux", "moyen", "enerve", "triste"];
    foreach ($humeurs as $humeur) {
        if (isset($_FILES[$humeur])) {
            $file = $_FILES[$humeur];

            // Vérifications de base
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[$humeur][] = "Erreur d'upload de fichier";
            }

            // Valider le type de fichier (ex. : JPEG, PNG)
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowedTypes)) {
                $errors[$humeur][] = "Type de fichier non autorisé";
            }

            $targetPath = UPLOAD_DIR . $espece . "_" . $humeur . "_0.gif";

            // Déplacer le fichier uploadé vers le dossier cible
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                $errors[$humeur][] = "Erreur lors du déplacement du fichier";
            }
        } else {
            $errors[$humeur][] = 'Aucun fichier reçu';
        }
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode($errors);
    } else {
        return true;
    }
}

// Mettre à jour une tâche existante (PATCh)
function updateCompagnon($id) {
    $json = json_decode(file_get_contents("compagnons.json"), true); // Charger le fichier JSON actuel

    if (!idExiste($json, $id)) {
        http_response_code(404);
        echo "Compagnon avec l'id $id introuvable";
    } else {
        $body = $_POST; // Charger le corps de la requête
        $errors = [];
        $arrayId = getArrayId($json["compagnons"], $id);
        
        // Vérification du champ "nom"
        if (array_key_exists("nom", $body)) {
            if (empty($body["nom"])) {
                $errors[] = "Nom vide dans le corps de la requête";
            } else {
                $json["compagnons"][$arrayId]["nom"] = $body["nom"];
            }
        }

        // Vérification du champ "prix"
        if (array_key_exists("prix", $body)) {
            if (!is_numeric($body["prix"]) || $body["prix"] <= 0) {
                $errors[] = "Le prix doit être un nombre positif";
            } else {
                $json["compagnons"][$arrayId]["prix"] = $body["prix"];
            }
        }

        // Vérification du champ "niveau"
        if (array_key_exists("niveau", $body)) {
            if (!is_numeric($body["niveau"]) || $body["niveau"] < 0) {
                $errors[] = "Le niveau doit être un nombre positif";
            }  else {
                $json["compagnons"][$arrayId]["niveau"] = $body["niveau"];
            }
        }

        // Vérification du champ "type"
        if (array_key_exists("type", $body)) {
            if (!in_array($body["type"], TYPES_VALIDES)) {
                $errors[] = "Type invalide. Les types valides sont : " . implode(", ", TYPES_VALIDES);
            } else {
                $json["compagnons"][$arrayId]["type"] = $body["type"];
            }
        }

        // Vérification du champ "detail"
        if (array_key_exists("detail", $body)) {
            if (empty($body["detail"])) {
                $errors[] = "Le détail ne peut pas être vide";
            } else {
                $json["compagnons"][$arrayId]["detail"] = $body["detail"];
            }
        }

        // Vérification du champ "imageUrl"
        if (array_key_exists("imageUrl", $body)) {
            if (empty($body["imageUrl"])) {
                $errors[] = "L'URL de l'image ne peut pas être vide";
            } else {
                $json["compagnons"][$arrayId]["imageUrl"] = $body["imageUrl"];
            }
        }

        // Vérification des erreurs
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode($errors);
        } else {
            file_put_contents("compagnons.json", json_encode($json, JSON_PRETTY_PRINT)); // Sauvegarder le fichier JSON
            echo json_encode($body);
        }
    }
}

// Supprimer une tâche (DELETE)
function deleteCompagnon($id): void {
    $json = json_decode(file_get_contents("compagnons.json"), true);
    if (!idExiste($json["compagnons"], $id)) {
        http_response_code(400);
        echo "Compagnon avec l'id $id introuvable";
    } else {
        $compagnonSupprime = $json["compagnons"][getArrayId($json["compagnons"], $id)];
        unset($json["compagnons"][getArrayId($json["compagnons"], $id)]);
        file_put_contents("compagnons.json", json_encode($json, JSON_PRETTY_PRINT)); // Sauvegarder le fichier JSON
        echo json_encode($compagnonSupprime);
    }
}

function getMaxId($json): int {
    $compagnons = $json["compagnons"];
    $maxId = 0;

    for ($i = 0; $i < count($compagnons); $i++) { 
        if ($maxId < $compagnons[$i]["id"]) {
            $maxId = $compagnons[$i]["id"];
        }
    }

    return $maxId;
}

function idExiste($compagnons, $id): bool {
    foreach ($compagnons as $comp) { 
        if ($comp["id"] == $id) {
            return true;
        }
    }
    return false;
}

function especeExiste($compagnons, $espece): bool {
    foreach ($compagnons as $comp) { 
        if ($comp["espece"] == $espece) {
            return true;
        }
    }
    return false;
}

function getArrayId($compagnons, $id): int {
    for ($i=0; $i < count($compagnons); $i++) { 
        if ($compagnons[$i]["id"] == $id) {
            return $i;
        }
    }
    return -1;
}