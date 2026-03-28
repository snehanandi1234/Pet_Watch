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

$petModel = new Pet();
$currentUserId = isset($_SESSION['user']) ? (int) $_SESSION['user']['id'] : 0;
$searchQuery = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

$perPage = 5;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$total = $searchQuery !== ''
    ? $petModel->countSearchPets($searchQuery)
    : $petModel->countAllPets();
$totalPages = (int) ceil($total / $perPage);
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
            if ($pid <= 0) {
                continue;
            }
            $sightingsByPetId[$pid][] = $s;
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

$searchParam = $searchQuery !== '' ? '&search=' . rawurlencode($searchQuery) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetWatch</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="css/style.css">
    <script>
        window.PETWATCH = window.PETWATCH || {};
        window.PETWATCH.csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
    <script src="js/app.js" defer></script>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php"><span aria-hidden="true">&#x1F43E;</span> PetWatch</a>
            <div class="navbar-nav ms-auto flex-row gap-2 align-items-center">
                <?php if (isset($_SESSION['user'])): ?>
                    <span class="navbar-text text-white">Hi, <strong><?php echo htmlspecialchars($_SESSION['user']['username'], ENT_QUOTES, 'UTF-8'); ?></strong></span>
                    <a class="btn btn-outline-light btn-sm" href="logout.php">Log out</a>
                <?php else: ?>
                    <a class="btn btn-light btn-sm" href="Views/login.php">Log in</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <div class="mb-4">
            <h1 class="h2">Lost &amp; found pets</h1>
            <p class="text-muted mb-0">Search listings or add a pet when you’re signed in.</p>
        </div>

        <section class="card shadow-sm mb-4" aria-labelledby="search-heading">
            <div class="card-body">
                <h2 id="search-heading" class="card-title h5">Search</h2>
                <form id="pet-search-form" method="get" class="row g-2 align-items-end" action="index.php">
                    <div class="col-md">
                        <label class="form-label visually-hidden" for="search">Search pets</label>
                        <input type="search" id="search" name="search" class="form-control" placeholder="Name, species, or description…" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card shadow-sm mb-4" aria-labelledby="map-heading">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-baseline mb-3 flex-wrap gap-2">
                    <h2 id="map-heading" class="card-title h5 mb-0">Sightings Map</h2>
                    <span class="text-muted small">One marker per sighting on this page.</span>
                </div>

                <div class="petwatch-map-wrap">
                    <div
                        id="petwatch-map"
                        class="petwatch-map"
                        aria-label="Map showing pet sighting markers"
                    ></div>
                </div>

                <div id="petwatch-map-status" class="text-muted small mt-2 d-none" aria-live="polite"></div>
            </div>

            <script>
                window.PETWATCH = window.PETWATCH || {};
                window.PETWATCH.petsForMap = <?php echo json_encode(
                    $petsForMap,
                    JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                ); ?>;
                window.PETWATCH.mapMarkers = <?php echo json_encode(
                    $mapMarkers,
                    JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                ); ?>;
            </script>
        </section>

        <?php if (isset($_SESSION['user'])): ?>
        <section class="card shadow-sm mb-4" aria-labelledby="add-heading">
            <div class="card-body">
                <h2 id="add-heading" class="card-title h5">Add a pet</h2>
                <?php if ($formError !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?php echo nl2br(htmlspecialchars($formError, ENT_QUOTES, 'UTF-8')); ?></div>
                <?php endif; ?>
                <div id="pet-add-error" class="alert alert-danger d-none" role="alert"></div>
                <form id="pet-add-form" method="post" action="Controllers/petController.php" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">Name</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-control"
                            placeholder="e.g. Luna"
                            maxlength="100"
                            required
                            value="<?php echo htmlspecialchars((string) ($flashPetForm['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="species">Species</label>
                        <input
                            type="text"
                            id="species"
                            name="species"
                            class="form-control"
                            placeholder="e.g. Dog"
                            maxlength="100"
                            required
                            value="<?php echo htmlspecialchars((string) ($flashPetForm['species'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="breed">Breed (optional)</label>
                        <input
                            type="text"
                            id="breed"
                            name="breed"
                            class="form-control"
                            placeholder="e.g. Labrador Retriever"
                            maxlength="100"
                            value="<?php echo htmlspecialchars((string) ($flashPetForm['breed'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="color">Color (optional)</label>
                        <input
                            type="text"
                            id="color"
                            name="color"
                            class="form-control"
                            placeholder="e.g. Black"
                            maxlength="100"
                            value="<?php echo htmlspecialchars((string) ($flashPetForm['color'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="photo_url">Photo URL (optional)</label>
                        <input
                            type="url"
                            id="photo_url"
                            name="photo_url"
                            class="form-control"
                            placeholder="https://..."
                            maxlength="255"
                            value="<?php echo htmlspecialchars((string) ($flashPetForm['photo_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="status">Status</label>
                        <?php $petStatusVal = (string) ($flashPetForm['status'] ?? 'lost'); ?>
                        <select id="status" name="status" class="form-select">
                            <option value="lost" <?php echo $petStatusVal === 'lost' ? 'selected' : ''; ?>>Lost</option>
                            <option value="found" <?php echo $petStatusVal === 'found' ? 'selected' : ''; ?>>Found</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea
                            id="description"
                            name="description"
                            class="form-control"
                            rows="3"
                            maxlength="500"
                            placeholder="Color, collar, last seen…"
                        ><?php echo htmlspecialchars((string) ($flashPetForm['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add" class="btn btn-primary">Add pet</button>
                    </div>
                </form>
            </div>
        </section>
        <?php elseif ($formError !== ''): ?>
            <div class="alert alert-danger mb-4" role="alert"><?php echo nl2br(htmlspecialchars($formError, ENT_QUOTES, 'UTF-8')); ?></div>
        <?php endif; ?>

        <section aria-labelledby="list-heading">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h2 id="list-heading" class="h5 mb-0"><?php echo $searchQuery !== '' ? 'Search results' : 'All pets'; ?></h2>
                <?php if ($searchQuery !== ''): ?>
                    <a href="index.php" class="link-secondary small">Clear search</a>
                <?php endif; ?>
            </div>

            <div id="pet-list-container">
            <?php if (empty($pets)): ?>
                <div class="card shadow-sm">
                    <div class="card-body text-muted">
                        No pets yet<?php echo $searchQuery !== '' ? ' match your search' : ''; ?>.
                    </div>
                </div>
            <?php else: ?>
                <ul class="list-unstyled row g-3">
                    <?php foreach ($pets as $pet): ?>
                        <?php
                        $petId = (int) ($pet['id'] ?? 0);
                        $statusRaw = isset($pet['status']) ? strtolower(trim((string) $pet['status'])) : '';
                        $badgeClass = $statusRaw === 'found' ? 'bg-success' : ($statusRaw === 'lost' ? 'bg-danger' : 'bg-secondary');
                        $petOwnerId = (int) ($pet['user_id'] ?? 0);
                        $isOwner = $currentUserId > 0 && $petOwnerId === $currentUserId;
                        $petSightings = $sightingsByPetId[$petId] ?? [];
                        $breed = isset($pet['breed']) ? trim((string) $pet['breed']) : '';
                        $color = isset($pet['color']) ? trim((string) $pet['color']) : '';
                        $photoUrl = isset($pet['photo_url']) ? trim((string) $pet['photo_url']) : '';
                        $dateReported = isset($pet['date_reported']) ? trim((string) $pet['date_reported']) : '';

                        $prefillSightingPetId = isset($flashSightingForm['pet_id']) ? (int) $flashSightingForm['pet_id'] : 0;
                        $sightingPrefillComment = ($prefillSightingPetId === $petId) ? (string) ($flashSightingForm['comment'] ?? '') : '';
                        $sightingPrefillLat = ($prefillSightingPetId === $petId) ? (string) ($flashSightingForm['latitude'] ?? '') : '';
                        $sightingPrefillLon = ($prefillSightingPetId === $petId) ? (string) ($flashSightingForm['longitude'] ?? '') : '';
                        $showSightingErrorHere = $sightingError !== '' && $prefillSightingPetId === $petId;
                        ?>
                        <li class="col-12">
                            <div class="card shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex gap-3 align-items-start flex-wrap">
                                        <?php if ($photoUrl !== ''): ?>
                                            <div class="pet-photo-wrap">
                                                <img
                                                    src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                                    alt="<?php echo htmlspecialchars((string) ($pet['name'] ?? 'Pet'), ENT_QUOTES, 'UTF-8'); ?>"
                                                    class="pet-photo"
                                                    loading="lazy"
                                                >
                                            </div>
                                        <?php endif; ?>

                                        <div class="flex-grow-1" style="min-width: 220px;">
                                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                                <h3 class="h5 mb-1"><?php echo htmlspecialchars((string) ($pet['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                                                <?php if ($statusRaw !== ''): ?>
                                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars(ucfirst($statusRaw), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endif; ?>
                                            </div>

                                            <p class="text-muted mb-1"><?php echo htmlspecialchars((string) ($pet['species'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>

                                            <?php if ($breed !== '' || $color !== ''): ?>
                                                <div class="small text-muted mb-2">
                                                    <?php if ($breed !== ''): ?>
                                                        <span class="me-2">Breed: <?php echo htmlspecialchars($breed, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($color !== ''): ?>
                                                        <span>Color: <?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($pet['description'])): ?>
                                                <p class="mb-2"><?php echo nl2br(htmlspecialchars((string) $pet['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                                            <?php endif; ?>

                                            <?php if ($dateReported !== ''): ?>
                                                <div class="text-muted small mb-2">
                                                    Reported: <?php echo htmlspecialchars($dateReported, ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($isOwner): ?>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <a class="btn btn-outline-secondary btn-sm" href="edit.php?id=<?php echo $petId; ?>">Edit</a>
                                                    <a
                                                        class="btn btn-outline-danger btn-sm ajax-pet-delete"
                                                        href="Controllers/petController.php?delete=<?php echo $petId; ?>"
                                                        data-pet-id="<?php echo $petId; ?>"
                                                        onclick="return confirm('Delete this pet?');"
                                                    >Delete</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div id="sightings-<?php echo $petId; ?>">
                                        <hr class="my-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                            <h4 class="h6 mb-0">Sightings</h4>
                                            <span class="text-muted small"><?php echo count($petSightings); ?> total</span>
                                        </div>

                                        <div class="alert alert-danger d-none py-2 px-3 mb-3" id="sighting-error-<?php echo $petId; ?>" role="alert"></div>

                                        <?php if (empty($petSightings)): ?>
                                            <p class="text-muted small mb-3">No sightings yet.</p>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush mb-3">
                                                <?php foreach ($petSightings as $s): ?>
                                                    <?php
                                                    $lat = isset($s['latitude']) ? (float) $s['latitude'] : null;
                                                    $lon = isset($s['longitude']) ? (float) $s['longitude'] : null;
                                                    $ts = isset($s['timestamp']) ? (string) $s['timestamp'] : '';
                                                    ?>
                                                    <li class="list-group-item px-0 border-0 border-top">
                                                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                                            <div class="small">
                                                                <span class="fw-semibold">
                                                                    <?php echo $ts !== '' ? htmlspecialchars($ts, ENT_QUOTES, 'UTF-8') : 'Sighting'; ?>
                                                                </span>
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

                                        <?php if ($currentUserId > 0): ?>
                                            <?php if ($showSightingErrorHere): ?>
                                                <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
                                                    <?php echo nl2br(htmlspecialchars($sightingError, ENT_QUOTES, 'UTF-8')); ?>
                                                </div>
                                            <?php endif; ?>

                                            <form method="post" action="Controllers/sightingController.php" class="mt-2 ajax-sighting-add-form" data-pet-id="<?php echo $petId; ?>">
                                                <input type="hidden" name="pet_id" value="<?php echo $petId; ?>">
                                                <input type="hidden" name="return_to" value="<?php echo htmlspecialchars('index.php?page=' . (int) $page . $searchParam, ENT_QUOTES, 'UTF-8'); ?>">
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-12 col-md-4">
                                                        <label class="form-label small mb-1" for="latitude-<?php echo $petId; ?>">Latitude</label>
                                                        <input
                                                            type="number"
                                                            step="any"
                                                            min="-90"
                                                            max="90"
                                                            id="latitude-<?php echo $petId; ?>"
                                                            name="latitude"
                                                            class="form-control form-control-sm"
                                                            required
                                                            value="<?php echo htmlspecialchars($sightingPrefillLat, ENT_QUOTES, 'UTF-8'); ?>"
                                                        >
                                                    </div>
                                                    <div class="col-12 col-md-4">
                                                        <label class="form-label small mb-1" for="longitude-<?php echo $petId; ?>">Longitude</label>
                                                        <input
                                                            type="number"
                                                            step="any"
                                                            min="-180"
                                                            max="180"
                                                            id="longitude-<?php echo $petId; ?>"
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
                                                        <label class="form-label small mb-1" for="comment-<?php echo $petId; ?>">Comment</label>
                                                        <textarea
                                                            id="comment-<?php echo $petId; ?>"
                                                            name="comment"
                                                            class="form-control form-control-sm"
                                                            rows="2"
                                                            maxlength="1000"
                                                            required
                                                        ><?php echo htmlspecialchars($sightingPrefillComment, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    </div>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            </div>

                <div id="pet-pagination-container">
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4" aria-label="Pet list pagination">
                        <ul class="pagination justify-content-center flex-wrap">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="index.php?page=<?php echo (int) ($page - 1); ?><?php echo $searchParam; ?>">Previous</a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link">Previous</span></li>
                            <?php endif; ?>

                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item<?php echo $p === $page ? ' active' : ''; ?>">
                                    <?php if ($p === $page): ?>
                                        <span class="page-link"><?php echo (int) $p; ?></span>
                                    <?php else: ?>
                                        <a class="page-link" href="index.php?page=<?php echo (int) $p; ?><?php echo $searchParam; ?>"><?php echo (int) $p; ?></a>
                                    <?php endif; ?>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="index.php?page=<?php echo (int) ($page + 1); ?><?php echo $searchParam; ?>">Next</a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link">Next</span></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
                </div>
        </section>
    </main>
</body>
</html>
