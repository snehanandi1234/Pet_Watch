<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . "/../Models/Pet.php";
require_once __DIR__ . "/../Models/Sighting.php";
require_once __DIR__ . "/../Models/Csrf.php";

require_once __DIR__ . "/../Views/partials/pet_list.php";
require_once __DIR__ . "/../Views/partials/pagination.php";

function json_response(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit();
}

function validatePetInputForApi(array $post): array {
    $errors = [];

    $name = trim((string) ($post['name'] ?? ''));
    if ($name === '') {
        $errors[] = 'Pet name is required.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Pet name must be 100 characters or fewer.';
    }

    $species = trim((string) ($post['species'] ?? ''));
    if ($species === '') {
        $errors[] = 'Species is required.';
    } elseif (mb_strlen($species) > 100) {
        $errors[] = 'Species must be 100 characters or fewer.';
    }

    $status = (string) ($post['status'] ?? '');
    if (!in_array($status, ['lost', 'found'], true)) {
        $errors[] = 'Status must be either “lost” or “found”.';
    }

    $breed = trim((string) ($post['breed'] ?? ''));
    if ($breed !== '' && mb_strlen($breed) > 100) {
        $errors[] = 'Breed must be 100 characters or fewer.';
    }

    $color = trim((string) ($post['color'] ?? ''));
    if ($color !== '' && mb_strlen($color) > 100) {
        $errors[] = 'Color must be 100 characters or fewer.';
    }

    $photo_url_raw = trim((string) ($post['photo_url'] ?? ''));
    $photo_url = $photo_url_raw !== '' ? $photo_url_raw : null;
    if ($photo_url !== null) {
        if (mb_strlen($photo_url) > 255) {
            $errors[] = 'Photo URL must be 255 characters or fewer.';
        } else {
            if (!filter_var($photo_url, FILTER_VALIDATE_URL)) {
                $errors[] = 'Photo URL must be a valid URL.';
            } else {
                $parts = parse_url($photo_url);
                $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
                if (!in_array($scheme, ['http', 'https'], true)) {
                    $errors[] = 'Photo URL must use http or https.';
                }
            }
        }
    }

    $description = trim((string) ($post['description'] ?? ''));
    $description = $description !== '' ? $description : null;
    if ($description !== null && mb_strlen($description) > 500) {
        $errors[] = 'Description must be 500 characters or fewer.';
    }

    return [
        'errors' => $errors,
        'data' => [
            'name' => $name,
            'species' => $species,
            'breed' => $breed,
            'color' => $color,
            'photo_url' => $photo_url,
            'description' => $description,
            'status' => $status,
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

// LIST (public)
if ($action === 'list') {
    $searchQuery = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 5;
    $offset = ($page - 1) * $perPage;

    $total = $searchQuery !== ''
        ? $petModel->countSearchPets($searchQuery)
        : $petModel->countAllPets();

    $totalPages = (int) ceil($total / $perPage);
    $totalPages = max(1, $totalPages);
    $page = min($page, max(1, $totalPages));
    $offset = ($page - 1) * $perPage;

    $pets = $searchQuery !== ''
        ? $petModel->searchPets($searchQuery, $perPage, $offset)
        : $petModel->getAllPets($perPage, $offset);

    $sightingsByPetId = [];
    $sightingModel = new Sighting();
    if (!empty($pets)) {
        $petIds = [];
        foreach ($pets as $p) {
            $petIds[] = (int) ($p['id'] ?? 0);
        }
        $petIds = array_values(array_filter($petIds, static fn($x) => $x > 0));
        if (!empty($petIds)) {
            $sightings = $sightingModel->getSightingsByPetIds($petIds);
            foreach ($sightings as $s) {
                $pid = (int) ($s['pet_id'] ?? 0);
                if ($pid > 0) {
                    $sightingsByPetId[$pid][] = $s;
                }
            }
        }
    }

    $petsForMap = [];
    $mapMarkers = [];
    foreach ($pets as $pet) {
        $petId = (int) ($pet['id'] ?? 0);
        if ($petId <= 0) continue;

        $petsForMap[$petId] = [
            'id' => $petId,
            'name' => (string) ($pet['name'] ?? ''),
            'species' => (string) ($pet['species'] ?? ''),
            'breed' => (string) ($pet['breed'] ?? ''),
            'color' => (string) ($pet['color'] ?? ''),
            'photo_url' => (string) ($pet['photo_url'] ?? ''),
            'description' => (string) ($pet['description'] ?? ''),
            'status' => (string) ($pet['status'] ?? ''),
            'date_reported' => (string) ($pet['date_reported'] ?? ''),
        ];
    }

    foreach ($sightingsByPetId as $petId => $sightings) {
        $pid = (int) $petId;
        if ($pid <= 0) continue;

        foreach ($sightings as $s) {
            $lat = isset($s['latitude']) ? (float) $s['latitude'] : null;
            $lon = isset($s['longitude']) ? (float) $s['longitude'] : null;

            if ($lat === null || $lon === null) continue;
            if (!is_finite($lat) || !is_finite($lon)) continue;

            $mapMarkers[] = [
                'pet_id' => $pid,
                'lat' => $lat,
                'lon' => $lon,
                'timestamp' => isset($s['timestamp']) ? (string) $s['timestamp'] : '',
                'comment' => isset($s['comment']) ? (string) $s['comment'] : '',
            ];
        }
    }

    $currentUserId = isset($_SESSION['user']) ? (int) $_SESSION['user']['id'] : 0;

    $petsHtml = renderPetList($pets, $sightingsByPetId, $currentUserId, $searchQuery);
    $paginationHtml = renderPagination($totalPages, $page, $searchQuery);

    json_response([
        'ok' => true,
        'petsHtml' => $petsHtml,
        'paginationHtml' => $paginationHtml,
        'petsForMap' => $petsForMap,
        'mapMarkers' => $mapMarkers,
    ]);
}

// MUTATIONS (login required)
$userId = isset($_SESSION['user']) ? (int) $_SESSION['user']['id'] : 0;
if ($userId <= 0) {
    json_response(['ok' => false, 'errors' => ['Please log in to continue.']]);
}

if ($action === 'add') {
    $validation = validatePetInputForApi($_POST);
    if (!empty($validation['errors'])) {
        json_response(['ok' => false, 'errors' => $validation['errors']]);
    }

    $date_reported = date('Y-m-d');
    $ok = $petModel->addPet(
        $validation['data']['name'],
        $validation['data']['species'],
        $validation['data']['breed'],
        $validation['data']['color'],
        $validation['data']['photo_url'],
        $validation['data']['description'],
        $validation['data']['status'],
        $date_reported,
        $userId
    );

    json_response(['ok' => $ok ? true : false, 'errors' => $ok ? [] : ['Failed to save pet.']]);
}

if ($action === 'update') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        json_response(['ok' => false, 'errors' => ['Invalid pet identifier.']]);
    }

    if (!$petModel->isOwner($id, $userId)) {
        json_response(['ok' => false, 'errors' => ['You can only edit your own pets.']]);
    }

    $validation = validatePetInputForApi($_POST);
    if (!empty($validation['errors'])) {
        json_response(['ok' => false, 'errors' => $validation['errors']]);
    }

    $ok = $petModel->updatePet(
        $id,
        $userId,
        $validation['data']['name'],
        $validation['data']['species'],
        $validation['data']['breed'],
        $validation['data']['color'],
        $validation['data']['photo_url'],
        $validation['data']['description'],
        $validation['data']['status']
    );

    json_response(['ok' => $ok ? true : false, 'errors' => $ok ? [] : ['Failed to update pet.']]);
}

if ($action === 'delete') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        json_response(['ok' => false, 'errors' => ['Invalid pet identifier.']]);
    }

    if (!$petModel->isOwner($id, $userId)) {
        json_response(['ok' => false, 'errors' => ['You can only delete your own pets.']]);
    }

    $ok = $petModel->deletePet($id, $userId);
    json_response(['ok' => $ok ? true : false, 'errors' => $ok ? [] : ['Failed to delete pet.']]);
}

json_response(['ok' => false, 'errors' => ['Unknown action.']]);

?>

