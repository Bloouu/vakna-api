<?php
header("Content-Type: application/json");

define("TYPES_VALIDES", ["JOUET", "NOURRITURE"]);
define("UPLOAD_DIR", "images/");

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            getObjet($_GET['id']);
        } else {
            getAllObjets();
        }
        break;
    case 'POST':
        createObjet();
        break;

    case 'PATCH':
        updateObjet($_GET['id']);
        break;

    case 'DELETE':
        deleteObjet($_GET['id']);
        break;

    default:
        deliverResponse(400, "Méthode non supportée");
}

function deliverResponse (int $statusCode, string $statusMsg, $data = null) : void {
  if (!is_null($data)) {
    echo json_encode(array(
      'statusCode' => $statusCode,
      'statusMsg' => $statusMsg,
      'objets' => $data), true
    );
   } else {
    echo json_encode(array(
      'statusCode' => $statusCode,
      'statusMsg' => $statusMsg), true
    );
  }
}


// Récupérer toutes les tâches (GET)
function getAllObjets(): void {
    echo json_encode(json_decode(file_get_contents("objets.json"), true)["objets"]);
}

// Récupérer une tâche par ID (GET)
function getObjet(int $id) : void {
    $json = json_decode(file_get_contents("objets.json"), true)["objets"];
    foreach ($json["refuges"] as $refuge) { 
        if ($json[$i]["id"] == $id) {
            echo json_encode($json[$i]);
            return;
        }
    }
    http_response_code(404);
    echo "Objet avec l'id $id introuvable";
}

function createObjet(): void {
    $json = json_decode(file_get_contents("objets.json"), true); // Charger le fichier JSON actuel
    $body = $_POST; // Charger le corps de la requête
    $errors = [];

    // Vérification du champ "nom[fr]"
    if (!isset($body["nom"]["fr"])) {
        $errors[] = "Pas de nom français dans le corps de la requête";
    } else {
        if (empty($body["nom"]["fr"])) {
            $errors[] = "Nom français vide dans le corps de la requête";
        }
    }

    // Vérification du champ "nom[en]"
    if (!isset($body["nom"]["en"])) {
        $errors[] = "Pas de nom anglais dans le corps de la requête";
    } else {
        if (empty($body["nom"]["en"])) {
            $errors[] = "Nom anglais vide dans le corps de la requête";
        }
    }

    // Vérification du champ "prix"
    if (!array_key_exists("prix", $body)) {
        $errors[] = "Pas de prix dans le corps de la requête";
    } else {
        if (!is_numeric($body["prix"]) || $body["prix"] <= 0) {
            $errors[] = "Le prix doit être un nombre positif";
        }
    }

    // Vérification du champ "niveau"
    if (!array_key_exists("niveau", $body)) {
        $errors[] = "Pas de niveau dans le corps de la requête";
    } else {
        if (!is_numeric($body["niveau"]) || $body["niveau"] < 0) {
            $errors[] = "Le niveau doit être un nombre positif";
        }
    }

    // Vérification du champ "type"
    if (!array_key_exists("type", $body)) {
        $errors[] = "Pas de type dans le corps de la requête";
    } else {
        if (!in_array($body["type"], TYPES_VALIDES)) {
            $errors[] = "Type invalide. Les types valides sont : " . implode(", ", TYPES_VALIDES);
        }
    }

    // Vérification du champ "detail[fr]"
    if (!isset($body["detail"]["fr"])) {
        $errors[] = "Pas de detail français dans le corps de la requête";
    } else {
        if (empty($body["detail"]["fr"])) {
            $errors[] = "Nom français vide dans le corps de la requête";
        }
    }

    // Vérification du champ "detail[en]"
    if (!isset($body["detail"]["en"])) {
        $errors[] = "Pas de detail anglais dans le corps de la requête";
    } else {
        if (empty($body["detail"]["en"])) {
            $errors[] = "Nom anglais vide dans le corps de la requête";
        }
    }

    // Vérification du champ "imageUrl"
    if (!array_key_exists("imageUrl", $body)) {
        $errors[] = "Pas d'URL d'image dans le corps de la requête";
    } else {
        if (empty($body["imageUrl"])) {
            $errors[] = "L'URL de l'image ne peut pas être vide";
        }
    }

    $fichierResponse = uploadFichier();
    if ($fichierResponse != true) {
        $errors[] = $fichierResponse;
    }

    // Vérification des erreurs
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode($errors);
    } else {
        // Ajout de l'objet si tout est correct
        $newObject = [
            "id" => getMaxId($json) + 1,
            "nom" => $body["nom"],
            "prix" => $body["prix"],
            "niveau" => $body["niveau"],
            "type" => $body["type"],
            "detail" => $body["detail"],
            "imageUrl" => $body["imageUrl"]
        ];
        $json["objets"][] = $newObject; // Ajouter l'objet au JSON
        file_put_contents("objets.json", json_encode($json, JSON_PRETTY_PRINT)); // Sauvegarder le fichier JSON
        echo json_encode($newObject);
    }
}

function uploadFichier(): string|bool {
    if (isset($_FILES['image'])) {
        $file = $_FILES['image'];

        // Vérifications de base
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return "Erreur d'upload de fichier";
        }

        // Valider le type de fichier (ex. : JPEG, PNG)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes)) {
            return "Type de fichier non autorisé";
        }

        // Vérifier que le fichier n'existe pas déjà
        if (file_exists(UPLOAD_DIR .$file["name"])) {
            return "Fichier avec ce nom déjà existant";
        }

        // Déplacer le fichier uploadé vers le dossier cible
        $targetPath = UPLOAD_DIR . $file["name"];
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return "Erreur lors du déplacement du fichier";
        }

        // Répondre avec succès et fournir l'URL du fichier
        return true;
    } else {
        return 'Aucun fichier reçu';
    }
}

// Mettre à jour une tâche existante (PATCh)
function updateObjet($id) {
    $json = json_decode(file_get_contents("objets.json"), true); // Charger le fichier JSON actuel

    if (!idExiste($json, $id)) {
        http_response_code(404);
        echo "Objet avec l'id $id introuvable";
    } else {
        $body = $_POST; // Charger le corps de la requête
        $errors = [];
        $arrayId = getArrayId($json["objets"], $id);
        
        // Vérification du champ "nom"
        if (array_key_exists("nom", $body)) {
            if (empty($body["nom"])) {
                $errors[] = "Nom vide dans le corps de la requête";
            } else {
                $json["objets"][$arrayId]["nom"] = $body["nom"];
            }
        }

        // Vérification du champ "prix"
        if (array_key_exists("prix", $body)) {
            if (!is_numeric($body["prix"]) || $body["prix"] <= 0) {
                $errors[] = "Le prix doit être un nombre positif";
            } else {
                $json["objets"][$arrayId]["prix"] = $body["prix"];
            }
        }

        // Vérification du champ "niveau"
        if (array_key_exists("niveau", $body)) {
            if (!is_numeric($body["niveau"]) || $body["niveau"] < 0) {
                $errors[] = "Le niveau doit être un nombre positif";
            }  else {
                $json["objets"][$arrayId]["niveau"] = $body["niveau"];
            }
        }

        // Vérification du champ "type"
        if (array_key_exists("type", $body)) {
            if (!in_array($body["type"], TYPES_VALIDES)) {
                $errors[] = "Type invalide. Les types valides sont : " . implode(", ", TYPES_VALIDES);
            } else {
                $json["objets"][$arrayId]["type"] = $body["type"];
            }
        }

        // Vérification du champ "detail"
        if (array_key_exists("detail", $body)) {
            if (empty($body["detail"])) {
                $errors[] = "Le détail ne peut pas être vide";
            } else {
                $json["objets"][$arrayId]["detail"] = $body["detail"];
            }
        }

        // Vérification du champ "imageUrl"
        if (array_key_exists("imageUrl", $body)) {
            if (empty($body["imageUrl"])) {
                $errors[] = "L'URL de l'image ne peut pas être vide";
            } else {
                $json["objets"][$arrayId]["imageUrl"] = $body["imageUrl"];
            }
        }

        // Vérification des erreurs
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode($errors);
        } else {
            file_put_contents("objets.json", json_encode($json, JSON_PRETTY_PRINT)); // Sauvegarder le fichier JSON
            echo json_encode($body);
        }
    }
}

// Supprimer une tâche (DELETE)
function deleteObjet($id): void {
    $json = json_decode(file_get_contents("objets.json"), true);
    if (!idExiste($json["objets"], $id)) {
        http_response_code(400);
        echo "Objet avec l'id $id introuvable";
    } else {
        $objetSupprime = $json["objets"][getArrayId($json["objets"], $id)];
        unset($json["objets"][getArrayId($json["objets"], $id)]);
        file_put_contents("objets.json", json_encode($json, JSON_PRETTY_PRINT)); // Sauvegarder le fichier JSON
        echo json_encode($objetSupprime);
    }
}

function getMaxId($json): int {
    $objets = $json["objets"];
    $maxId = 0;

    for ($i = 0; $i < count($objets); $i++) { 
        if ($maxId < $objets[$i]["id"]) {
            $maxId = $objets[$i]["id"];
        }
    }

    return $maxId;
}

function idExiste($objets, $id): bool {
    for ($i=0; $i < count($objets); $i++) { 
        if ($objets[$i]["id"] == $id) {
            return true;
        }
    }
    

    return false;
}

function getArrayId($objets, $id): int {
    for ($i=0; $i < count($objets); $i++) { 
        if ($objets[$i]["id"] == $id) {
            return $i;
        }
    }
    return -1;
}