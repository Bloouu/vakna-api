<?php
header("Content-Type: application/json");

define("TYPES_VALIDES", ["JOUET", "NOURRITURE"]);

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
    for ($i=0; $i < count($json); $i++) { 
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
    $body = json_decode(file_get_contents("php://input"), true); // Charger le corps de la requête
    $errors = [];

    // Vérification du champ "nom"
    if (!array_key_exists("nom", $body)) {
        $errors[] = "Pas de nom dans le corps de la requête";
    } else {
        if (empty($body["nom"])) {
            $errors[] = "Nom vide dans le corps de la requête";
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

    // Vérification du champ "detail"
    if (!array_key_exists("detail", $body)) {
        $errors[] = "Pas de détail dans le corps de la requête";
    } else {
        if (empty($body["detail"])) {
            $errors[] = "Le détail ne peut pas être vide";
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


// Mettre à jour une tâche existante (PATCh)
function updateObjet($id) {
    $json = json_decode(file_get_contents("objets.json"), true); // Charger le fichier JSON actuel

    if (!idExiste($json, $id)) {
        http_response_code(404);
        echo "Objet avec l'id $id introuvable";
    } else {
        $body = json_decode(file_get_contents("php://input"), true); // Charger le corps de la requête
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
            array_unshift($body, ["id" => $id]);
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