<?php
header("Content-Type: application/json");

define("UPLOAD_DIR", "images/refuges/");

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            getRefuge($_GET['id']);
        } else {
            getAllRefuges();
        }
        break;
    case 'POST':
        createRefuge();
        break;

    case 'PATCH':
        if (isset($_GET['id'])) {
            updateRefuge($_GET['id']);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "ID requis pour la mise à jour"]);
        }
        break;

    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteRefuge($_GET['id']);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "ID requis pour la suppression"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Méthode non supportée"]);
}

// Récupérer tous les refuges (GET)
function getAllRefuges(): void {
    $json = json_decode(file_get_contents("refuges.json"), true);
    if ($json === null) {
        http_response_code(500);
        echo json_encode(["error" => "Erreur de lecture du fichier JSON"]);
        return;
    }
    echo json_encode($json["refuges"] ?? []);
}

// Récupérer un refuge par ID (GET)
function getRefuge(int $id) : void {
    $json = json_decode(file_get_contents("refuges.json"), true);
    if ($json === null) {
        http_response_code(500);
        echo json_encode(["error" => "Erreur de lecture du fichier JSON"]);
        return;
    }

    $refuges = $json["refuges"] ?? [];
    foreach ($refuges as $refuge) {
        if ($refuge["id"] === $id) {
            echo json_encode($refuge);
            return;
        }
    }

    http_response_code(404);
    echo json_encode(["error" => "Refuge avec l'ID $id introuvable"]);
}

function createRefuge(): void {
    $json = json_decode(file_get_contents("refuges.json"), true) ?? ["refuges" => []];
    $body = $_POST;
    $errors = [];

    // Vérification des champs obligatoires
    $requiredFields = [
        "nom",
        "detail",
        "imageUrl"
    ];

    foreach ($requiredFields as $field) {
        if (!isset($body[$field]) || empty($body[$field])) {
            $errors[] = "Champ $field manquant ou vide";
        }
    }

    // Vérifier si l'ID existe déjà
    foreach ($json["refuges"] as $existingRefuge) {
        if (!$found && $existingRefuge["id"] === $body["id"]) {
            $errors[] = "Un refuge avec cet ID existe déjà";
            $found = true;
            break;
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
        return;
    }

    // Ajout du refuge
    $newRefuge = [
        "id" => getMaxId($json) + 1,
        "nom" => $body["nom"],
        "detail" => $body["detail"],
        "imageUrl" => $body["imageUrl"]
    ];
    $json["refuges"][] = $newRefuge;
    
    // Sauvegarder le fichier JSON
    $result = file_put_contents("refuges.json", json_encode($json, JSON_PRETTY_PRINT));
    if ($result === false) {
        http_response_code(500);
        echo json_encode(["error" => "Impossible de sauvegarder le refuge"]);
        return;
    }

    http_response_code(201);
    echo json_encode($newRefuge);
}

// Fonctions à implémenter plus tard
function updateRefuge($id): void {
    http_response_code(501);
    echo json_encode(["error" => "Mise à jour non implémentée"]);
}

function deleteRefuge($id): void {
    http_response_code(501);
    echo json_encode(["error" => "Suppression non implémentée"]);
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
        if (file_exists(UPLOAD_DIR . $file["name"])) {
            return "Fichier avec ce nom déjà existant";
        }

        // Déplacer le fichier uploadé vers le dossier cible
        $targetPath = UPLOAD_DIR . $file["name"];
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return "Erreur lors du déplacement du fichier";
        }

        return true;
    } else {
        return 'Aucun fichier reçu';
    }
}

// Fonctions utilitaires
function getMaxId($json): int {
    $max = 0;
    foreach ($json["refuges"] as $refuge) {
        if ($refuge["id"] > $max) {
            $max = $refuge["id"];
        }
    }
    return $max;
}

function idExiste($id, $refuges): bool {
    foreach ($refuges as $refuge) {
        if ($refuge["id"] == $id) {
            return true;
        }
    }
    return false;
}

function getArrayId($id, $refuges): int {
    for ($i = 0; $i < count($refuges); $i++) {
        if ($refuges[$i]["id"] == $id) {
            return $i;
        }
    }
    return -1;
}