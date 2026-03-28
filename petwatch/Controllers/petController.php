<?php
session_start();
require_once __DIR__ . "/../Models/Pet.php";

function validatePetInput(array $post): array {
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
            // Basic URL validation to prevent malformed / scriptable inputs.
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

$userId = isset($_SESSION['user']) ? (int) $_SESSION['user']['id'] : 0;
if ($userId <= 0) {
    $_SESSION['flash_error'] = 'Please log in to manage pet listings.';
    header('Location: ../Views/login.php');
    exit();
}

$petModel = new Pet();

// ADD PET
if (isset($_POST['add'])) {
    $validation = validatePetInput($_POST);
    $errors = $validation['errors'];
    $data = $validation['data'];
    if (!empty($errors)) {
        $_SESSION['flash_pet_form'] = $data;
        $_SESSION['flash_error'] = implode(PHP_EOL, $errors);
        header("Location: ../index.php");
        exit();
    }

    // Auto-fill the report date.
    $date_reported = date('Y-m-d');
    $petModel->addPet(
        $data['name'],
        $data['species'],
        $data['breed'],
        $data['color'],
        $data['photo_url'],
        $data['description'],
        $data['status'],
        $date_reported,
        $userId
    );

    unset($_SESSION['flash_pet_form']);
    unset($_SESSION['flash_error']);
    header("Location: ../index.php");
    exit();
}

// UPDATE PET
if (isset($_POST['update'])) {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        $_SESSION['flash_error'] = 'Invalid pet identifier.';
        header('Location: ../index.php');
        exit();
    }

    // Ownership enforcement.
    if (!$petModel->isOwner($id, $userId)) {
        $_SESSION['flash_error'] = 'You can only edit your own pets.';
        header('Location: ../index.php');
        exit();
    }

    $validation = validatePetInput($_POST);
    $errors = $validation['errors'];
    $data = $validation['data'];
    if (!empty($errors)) {
        $data['id'] = $id; // helpful for the view
        $_SESSION['flash_pet_form'] = $data;
        $_SESSION['flash_error'] = implode(PHP_EOL, $errors);
        header("Location: ../edit.php?id=" . $id);
        exit();
    }

    $petModel->updatePet(
        $id,
        $userId,
        $data['name'],
        $data['species'],
        $data['breed'],
        $data['color'],
        $data['photo_url'],
        $data['description'],
        $data['status']
    );

    unset($_SESSION['flash_pet_form']);
    unset($_SESSION['flash_error']);

    header("Location: ../index.php");
    exit();
}

// DELETE PET
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id > 0) {
        // Ownership enforcement.
        if ($petModel->isOwner($id, $userId)) {
            $petModel->deletePet($id, $userId);
        } else {
            $_SESSION['flash_error'] = 'You can only delete your own pets.';
        }
    } else {
        $_SESSION['flash_error'] = 'Invalid pet identifier.';
    }

    header("Location: ../index.php");
    exit();
}
?>
