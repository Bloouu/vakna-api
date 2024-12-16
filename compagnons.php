<?php
header("Content-Type: application/json");

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

// Récupérer un compagnon par ID (GET)
function getCompagnon(int $id) : void {
    $json = json_decode(file_get_contents("compagnons.json"), true)["compagnons"];
    for ($i=0; $i < count($json); $i++) { 
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
    $body = json_decode(file_get_contents("php://input"), true); // Charger le corps de la requête
    $errors = [];

    // Vérification du champ "espece"
    if (!array_key_exists("espece", $body)) {
        $errors[] = "Pas d'espèce dans le corps de la requête";
    } else {
        if (empty($body["image"])) {
            $errors[] = "Espèce vide dans le corps de la requête";
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
        // Ajout de l'compagnon si tout est correct
        $newObject = [
            "id" => getMaxId($json) + 1,
            "espece" => $body["espece"],
            "prix" => $body["prix"],
            "imageUrl" => $body["imageUrl"]
        ];
        $json["compagnons"][] = $newObject; // Ajouter l'compagnon au JSON
        file_put_contents("compagnons.json", json_encode($json, JSON_PRETTY_PRINT)); // Sauvegarder le fichier JSON
        echo json_encode($newObject);
    }
}


// Mettre à jour un compagnon existante (PATCH)
function updateCompagnon($id) {
    $json = json_decode(file_get_contents("compagnons.json"), true); // Charger le fichier JSON actuel

    if (!idExiste($json, $id)) {
        http_response_code(404);
        echo "Compagnon avec l'id $id introuvable";
    } else {
        $body = json_decode(file_get_contents("php://input"), true); // Charger le corps de la requête
        $errors = [];
        $arrayId = getArrayId($json["compagnons"], $id);

        // Vérification du champ "espece"
        if (array_key_exists("espece", $body)) {
            if (empty($body["espece"])) {
                $errors[] = "L'espèce ne peut pas être vide";
            } else {
                $json["compagnons"][$arrayId]["espece"] = $body["espece"];
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
            array_unshift($body, ["id" => $id]);
            echo json_encode($body);
        }
    }
}

// Supprimer un compagnon (DELETE)
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
    for ($i=0; $i < count($compagnons); $i++) { 
        if ($compagnons[$i]["id"] == $id) {
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