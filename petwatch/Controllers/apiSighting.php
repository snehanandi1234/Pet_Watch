<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . "/../Models/Pet.php";
require_once __DIR__ . "/../Models/Sighting.php";
require_once __DIR__ . "/../Models/Csrf.php";

require_once __DIR__ . "/../Views/partials/sightings_block.php";

function json_response(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit();
}

function validateSightingInputForApi(array $post): array {
    $errors = [];

    $comment = trim((string) ($post['comment'] ?? ''));
    if ($comment === '') {
        $errors[] = 'Comment is required.';
    } elseif (mb_strlen($comment) > 1000) {
        $errors[] = 'Comment must be 1000 characters or fewer.';
    }

    $latitudeRaw = trim((string) ($post['latitude'] ?? ''));
    $latitude = filter_var($latitudeRaw, FILTER_VALIDATE_FLOAT);
    if ($latitude === false) {
        $errors[] = 'Latitude must be a valid number.';
    } elseif ($latitude < -90 || $latitude > 90) {
        $errors[] = 'Latitude must be between -90 and 90.';
    }

    $longitudeRaw = trim((string) ($post['longitude'] ?? ''));
    $longitude = filter_var($longitudeRaw, FILTER_VALIDATE_FLOAT);
    if ($longitude === false) {
        $errors[] = 'Longitude must be a valid number.';
    } elseif ($longitude < -180 || $longitude > 180) {
        $errors[] = 'Longitude must be between -180 and 180.';
    }

    return [
        'errors' => $errors,
        'data' => [
            'comment' => $comment,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ],
    ];
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
if ($action === '') {
    json_response(['ok' => false, 'errors' => ['Missing action.']]);
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
if (!Csrf::validate($csrfToken)) {
    json_response([
        'ok' => false,
        'errors' => ['Session expired or invalid request. Please reload the page and try again.'],
    ]);
}

$petModel = new Pet();
$sightingModel = new Sighting();

// LIST (requires token because JS calls it; keeps behavior consistent)
if ($action === 'list') {
    $petId = isset($_GET['pet_id']) ? (int) $_GET['pet_id'] : 0;
    if ($petId <= 0) {
        json_response(['ok' => false, 'errors' => ['Invalid pet identifier.']]);
    }

    $pet = $petModel->getPetById($petId);
    if (!$pet) {
        json_response(['ok' => false, 'errors' => ['That pet no longer exists.']]);
    }

    $sightings = $sightingModel->getSightingsByPetId($petId);
    $currentUserId = isset($_SESSION['user']) ? (int) $_SESSION['user']['id'] : 0;

    $html = renderSightingsBlock($petId, $sightings, $currentUserId);
    json_response(['ok' => true, 'html' => $html]);
}

if ($action === 'add') {
    $userId = isset($_SESSION['user']) ? (int) $_SESSION['user']['id'] : 0;
    if ($userId <= 0) {
        json_response(['ok' => false, 'errors' => ['Please log in to add sightings.']]);
    }

    $petId = isset($_POST['pet_id']) ? (int) $_POST['pet_id'] : 0;
    if ($petId <= 0) {
        json_response(['ok' => false, 'errors' => ['Invalid pet identifier.']]);
    }

    $pet = $petModel->getPetById($petId);
    if (!$pet) {
        json_response(['ok' => false, 'errors' => ['That pet no longer exists.']]);
    }

    $validation = validateSightingInputForApi($_POST);
    if (!empty($validation['errors'])) {
        json_response(['ok' => false, 'errors' => $validation['errors']]);
    }

    $ok = $sightingModel->addSighting(
        $petId,
        $userId,
        $validation['data']['comment'],
        $validation['data']['latitude'],
        $validation['data']['longitude']
    );

    if (!$ok) {
        json_response(['ok' => false, 'errors' => ['Failed to save the sighting. Please try again.']]);
    }

    $sightings = $sightingModel->getSightingsByPetId($petId);
    $currentUserId = isset($_SESSION['user']) ? (int) $_SESSION['user']['id'] : 0;
    $html = renderSightingsBlock($petId, $sightings, $currentUserId);

    json_response(['ok' => true, 'html' => $html]);
}

json_response(['ok' => false, 'errors' => ['Unknown action.']]);

?>

