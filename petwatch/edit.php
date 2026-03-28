<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/Models/Pet.php';
require_once __DIR__ . '/Models/Sighting.php';
require_once __DIR__ . '/Models/Csrf.php';

$csrfToken = Csrf::getToken();

$formError = '';
if (!empty($_SESSION['flash_error'])) {
    $formError = (string) $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$sightingError = '';
if (!empty($_SESSION['flash_sighting_error'])) {
    $sightingError = (string) $_SESSION['flash_sighting_error'];
    unset($_SESSION['flash_sighting_error']);
}

$flashPetForm = [];
if (!empty($_SESSION['flash_pet_form']) && is_array($_SESSION['flash_pet_form'])) {
    $flashPetForm = $_SESSION['flash_pet_form'];
    unset($_SESSION['flash_pet_form']);
}

$flashSightingForm = [];
if (!empty($_SESSION['flash_sighting_form']) && is_array($_SESSION['flash_sighting_form'])) {
    $flashSightingForm = $_SESSION['flash_sighting_form'];
    unset($_SESSION['flash_sighting_form']);
}

if (!isset($_SESSION['user'])) {
    header('Location: Views/login.php');
    exit();
}

$petModel = new Pet();
$currentUserId = isset($_SESSION['user']) ? (int) $_SESSION['user']['id'] : 0;

if (!isset($_GET['id']) || $_GET['id'] === '') {
    header('Location: index.php');
    exit();
}

$pet = $petModel->getPetById((int) $_GET['id']);
if (!$pet) {
    header('Location: index.php');
    exit();
}

$petId = (int) ($pet['id'] ?? 0);
if ($petId <= 0 || $currentUserId <= 0 || !$petModel->isOwner($petId, $currentUserId)) {
    header('Location: index.php');
    exit();
}

// If validation failed on update, prefer the user-submitted values.
$formValues = $pet;
if (!empty($flashPetForm) && isset($flashPetForm['id']) && (int) $flashPetForm['id'] === $petId) {
    $formValues = array_merge($pet, $flashPetForm);
}

$statusVal = isset($formValues['status']) ? strtolower(trim((string) $formValues['status'])) : 'lost';
$breedVal = isset($formValues['breed']) ? (string) $formValues['breed'] : '';
$colorVal = isset($formValues['color']) ? (string) $formValues['color'] : '';
$photoUrlVal = isset($formValues['photo_url']) ? (string) $formValues['photo_url'] : '';
$descriptionVal = isset($formValues['description']) ? (string) $formValues['description'] : '';

$sightingModel = new Sighting();
$sightings = $sightingModel->getSightingsByPetId($petId);

$prefillSightingPetId = isset($flashSightingForm['pet_id']) ? (int) $flashSightingForm['pet_id'] : 0;
$sightingPrefillComment = ($prefillSightingPetId === $petId) ? (string) ($flashSightingForm['comment'] ?? '') : '';
$sightingPrefillLat = ($prefillSightingPetId === $petId) ? (string) ($flashSightingForm['latitude'] ?? '') : '';
$sightingPrefillLon = ($prefillSightingPetId === $petId) ? (string) ($flashSightingForm['longitude'] ?? '') : '';
$showSightingErrorHere = $sightingError !== '' && $prefillSightingPetId === $petId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit pet — PetWatch</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script>
        window.PETWATCH = window.PETWATCH || {};
        window.PETWATCH.csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="js/app.js" defer></script>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php"><span aria-hidden="true">&#x1F43E;</span> PetWatch</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">Hi, <strong><?php echo htmlspecialchars($_SESSION['user']['username'], ENT_QUOTES, 'UTF-8'); ?></strong></span>
                <a class="btn btn-outline-light btn-sm" href="logout.php">Log out</a>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <section class="card shadow-sm mb-4" aria-labelledby="edit-heading">
                    <div class="card-body">
                        <h1 id="edit-heading" class="card-title h4">Edit pet</h1>
                        <p class="text-muted">Update the details below and save.</p>
                        <?php if ($formError !== ''): ?>
                            <div class="alert alert-danger" role="alert"><?php echo nl2br(htmlspecialchars($formError, ENT_QUOTES, 'UTF-8')); ?></div>
                        <?php endif; ?>
                        <div id="pet-edit-error" class="alert alert-danger d-none" role="alert"></div>
                        <form id="pet-edit-form" method="post" action="Controllers/petController.php" class="row g-3">
                            <input type="hidden" name="id" value="<?php echo (int) $pet['id']; ?>">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Name</label>
                                <input type="text" id="name" name="name" class="form-control" maxlength="100" required value="<?php echo htmlspecialchars($formValues['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="species">Species</label>
                                <input type="text" id="species" name="species" class="form-control" maxlength="100" required value="<?php echo htmlspecialchars($formValues['species'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="breed">Breed (optional)</label>
                                <input type="text" id="breed" name="breed" class="form-control" maxlength="100" value="<?php echo htmlspecialchars($breedVal, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="color">Color (optional)</label>
                                <input type="text" id="color" name="color" class="form-control" maxlength="100" value="<?php echo htmlspecialchars($colorVal, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="photo_url">Photo URL (optional)</label>
                                <input type="url" id="photo_url" name="photo_url" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($photoUrlVal, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="date_reported">Date reported</label>
                                <?php $dateReportedVal = isset($pet['date_reported']) ? trim((string) $pet['date_reported']) : ''; ?>
                                <input
                                    type="date"
                                    id="date_reported"
                                    name="date_reported"
                                    class="form-control"
                                    disabled
                                    value="<?php echo htmlspecialchars(substr($dateReportedVal, 0, 10), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="description">Description</label>
                                <textarea id="description" name="description" class="form-control" rows="3" maxlength="500"><?php echo htmlspecialchars($descriptionVal, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <?php if ($photoUrlVal !== ''): ?>
                                <div class="col-12">
                                    <div class="small text-muted mb-2">Current photo</div>
                                    <img
                                        src="<?php echo htmlspecialchars($photoUrlVal, ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="Pet photo"
                                        class="pet-photo pet-photo--preview"
                                        loading="lazy"
                                    >
                                </div>
                            <?php endif; ?>
                            <div class="col-md-6">
                                <label class="form-label" for="status">Status</label>
                                <select id="status" name="status" class="form-select">
                                    <option value="lost"<?php echo $statusVal === 'lost' ? ' selected' : ''; ?>>Lost</option>
                                    <option value="found"<?php echo $statusVal === 'found' ? ' selected' : ''; ?>>Found</option>
                                </select>
                            </div>
                            <div class="col-12 d-flex gap-2 flex-wrap">
                                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" name="update" class="btn btn-primary">Save changes</button>
                            </div>
                        </form>
                        <div id="sightings-<?php echo $petId; ?>">
                            <hr class="my-4">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <h2 class="h6 mb-0">Sightings</h2>
                                <span class="text-muted small"><?php echo count($sightings); ?> total</span>
                            </div>

                            <div class="alert alert-danger d-none py-2 px-3 mb-3" id="sighting-error-<?php echo $petId; ?>" role="alert"></div>

                        <?php if (empty($sightings)): ?>
                            <p class="text-muted small mb-3">No sightings yet.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush mb-3">
                                <?php foreach ($sightings as $s): ?>
                                    <?php
                                    $lat = isset($s['latitude']) ? (float) $s['latitude'] : null;
                                    $lon = isset($s['longitude']) ? (float) $s['longitude'] : null;
                                    $ts = isset($s['timestamp']) ? (string) $s['timestamp'] : '';
                                    ?>
                                    <li class="list-group-item px-0 border-0 border-top">
                                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                            <div class="small">
                                                <span class="fw-semibold"><?php echo $ts !== '' ? htmlspecialchars($ts, ENT_QUOTES, 'UTF-8') : 'Sighting'; ?></span>
                                            </div>
                                            <div class="small text-muted">
                                                <?php if ($lat !== null && $lon !== null): ?>
                                                    (<?php echo htmlspecialchars(number_format($lat, 6, '.', ''), ENT_QUOTES, 'UTF-8'); ?>,
                                                    <?php echo htmlspecialchars(number_format($lon, 6, '.', ''), ENT_QUOTES, 'UTF-8'); ?>)
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($s['comment'])): ?>
                                            <div class="mt-1">
                                                <?php echo nl2br(htmlspecialchars((string) $s['comment'], ENT_QUOTES, 'UTF-8')); ?>
                                            </div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <h3 class="h6 mt-4">Add a sighting</h3>
                        <?php if ($showSightingErrorHere): ?>
                            <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
                                <?php echo nl2br(htmlspecialchars($sightingError, ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="Controllers/sightingController.php" class="mt-2 ajax-sighting-add-form" data-pet-id="<?php echo $petId; ?>">
                            <input type="hidden" name="pet_id" value="<?php echo $petId; ?>">
                            <input type="hidden" name="return_to" value="<?php echo htmlspecialchars('edit.php?id=' . (int) $petId, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-4">
                                    <label class="form-label small mb-1" for="edit-lat">Latitude</label>
                                    <input
                                        type="number"
                                        step="any"
                                        min="-90"
                                        max="90"
                                        id="edit-lat"
                                        name="latitude"
                                        class="form-control form-control-sm"
                                        required
                                        value="<?php echo htmlspecialchars($sightingPrefillLat, ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label small mb-1" for="edit-lon">Longitude</label>
                                    <input
                                        type="number"
                                        step="any"
                                        min="-180"
                                        max="180"
                                        id="edit-lon"
                                        name="longitude"
                                        class="form-control form-control-sm"
                                        required
                                        value="<?php echo htmlspecialchars($sightingPrefillLon, ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                </div>
                                <div class="col-12 col-md-4">
                                    <button type="submit" name="add_sighting" class="btn btn-primary btn-sm w-100">Add sighting</button>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="edit-comment">Comment</label>
                                    <textarea
                                        id="edit-comment"
                                        name="comment"
                                        class="form-control form-control-sm"
                                        rows="2"
                                        maxlength="1000"
                                        required
                                    ><?php echo htmlspecialchars($sightingPrefillComment, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                            </div>
                        </form>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
