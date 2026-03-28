<?php
declare(strict_types=1);

function renderPetCard(array $pet, array $petSightings, int $currentUserId, bool $isOwner): string {
    ob_start();

    $petId = (int) ($pet['id'] ?? 0);
    $statusRaw = isset($pet['status']) ? strtolower(trim((string) $pet['status'])) : '';
    $badgeClass = $statusRaw === 'found' ? 'bg-success' : ($statusRaw === 'lost' ? 'bg-danger' : 'bg-secondary');

    $breed = isset($pet['breed']) ? trim((string) $pet['breed']) : '';
    $color = isset($pet['color']) ? trim((string) $pet['color']) : '';
    $photoUrl = isset($pet['photo_url']) ? trim((string) $pet['photo_url']) : '';
    $dateReported = isset($pet['date_reported']) ? trim((string) $pet['date_reported']) : '';

    require_once __DIR__ . '/sightings_block.php';
    $sightingsHtml = renderSightingsBlock($petId, $petSightings, $currentUserId);
    ?>

    <li class="col-12">
        <div class="card shadow-sm h-100" id="pet-<?php echo $petId; ?>">
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
                                    href="Controllers/petController.php?delete=<?php echo $petId; ?>"
                                    class="btn btn-outline-danger btn-sm ajax-pet-delete"
                                    data-pet-id="<?php echo $petId; ?>"
                                    onclick="return confirm('Delete this pet?');"
                                >Delete</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php echo $sightingsHtml; ?>
            </div>
        </div>
    </li>
    <?php
    return (string) ob_get_clean();
}

?>

