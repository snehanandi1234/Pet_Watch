<?php
session_start();

require_once __DIR__ . "/../Models/Pet.php";
require_once __DIR__ . "/../Models/Sighting.php";

function validateSightingInput(array $post): array {
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

    if (!empty($errors)) {
        return [
            'errors' => $errors,
            'data' => null,
        ];
    }

    return [
        'errors' => [],
        'data' => [
            'comment' => $comment,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ],
    ];
}

$petModel = new Pet();
$sightingModel = new Sighting();

$userId = isset($_SESSION['user']) ? (int) $_SESSION['user']['id'] : 0;
if ($userId <= 0) {
    $_SESSION['flash_sighting_error'] = 'Please log in to add sightings.';
    header('Location: ../Views/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['add_sighting'])) {
    header('Location: ../index.php');
    exit();
}

$petId = isset($_POST['pet_id']) ? (int) $_POST['pet_id'] : 0;
if ($petId <= 0) {
    $_SESSION['flash_sighting_error'] = 'Invalid pet identifier.';
    header('Location: ../index.php');
    exit();
}

// Ensure the pet exists. (We allow any logged-in user to add sightings for any pet.)
$pet = $petModel->getPetById($petId);
if (!$pet) {
    $_SESSION['flash_sighting_error'] = 'That pet no longer exists.';
    header('Location: ../index.php');
    exit();
}

$validation = validateSightingInput($_POST);
$errors = $validation['errors'];
$data = $validation['data'];

if (!empty($errors)) {
    $_SESSION['flash_sighting_form'] = [
        'pet_id' => $petId,
        'comment' => trim((string) ($_POST['comment'] ?? '')),
        'latitude' => trim((string) ($_POST['latitude'] ?? '')),
        'longitude' => trim((string) ($_POST['longitude'] ?? '')),
    ];
    $_SESSION['flash_sighting_error'] = implode(PHP_EOL, $errors);

    $redirect = $_POST['return_to'] ?? ($_SERVER['HTTP_REFERER'] ?? '../index.php');
    if (!is_string($redirect) || $redirect === '') {
        $redirect = '../index.php';
    }
    if (preg_match('#^https?://#i', $redirect)) {
        $redirect = '../index.php';
    }
    header('Location: ' . $redirect);
    exit();
}

$ok = $sightingModel->addSighting(
    $petId,
    $userId,
    $data['comment'] ?? null,
    $data['latitude'],
    $data['longitude']
);

if (!$ok) {
    $_SESSION['flash_sighting_error'] = 'Failed to save the sighting. Please try again.';
    $redirect = $_POST['return_to'] ?? ($_SERVER['HTTP_REFERER'] ?? '../index.php');
    if (!is_string($redirect) || $redirect === '') {
        $redirect = '../index.php';
    }
    if (preg_match('#^https?://#i', $redirect)) {
        $redirect = '../index.php';
    }
    header('Location: ' . $redirect);
    exit();
}

unset($_SESSION['flash_sighting_form']);
unset($_SESSION['flash_sighting_error']);

$redirect = $_POST['return_to'] ?? ($_SERVER['HTTP_REFERER'] ?? '../index.php');
if (!is_string($redirect) || $redirect === '') {
    $redirect = '../index.php';
}
if (preg_match('#^https?://#i', $redirect)) {
    $redirect = '../index.php';
}

header('Location: ' . $redirect);
exit();

?>

