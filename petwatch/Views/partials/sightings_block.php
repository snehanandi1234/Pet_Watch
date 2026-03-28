<?php
declare(strict_types=1);

function renderSightingsBlock(int $petId, array $sightings, int $currentUserId): string {
    ob_start();
    ?>

    <div id="sightings-<?php echo $petId; ?>">
        <hr class="my-3">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h4 class="h6 mb-0">Sightings</h4>
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

        <?php if ($currentUserId > 0): ?>
            <form method="post" action="Controllers/sightingController.php" class="mt-2 ajax-sighting-add-form" data-pet-id="<?php echo $petId; ?>">
                <input type="hidden" name="pet_id" value="<?php echo $petId; ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1" for="lat-<?php echo $petId; ?>">Latitude</label>
                        <input
                            type="number"
                            step="any"
                            min="-90"
                            max="90"
                            id="lat-<?php echo $petId; ?>"
                            name="latitude"
                            class="form-control form-control-sm"
                            required
                        >
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1" for="lon-<?php echo $petId; ?>">Longitude</label>
                        <input
                            type="number"
                            step="any"
                            min="-180"
                            max="180"
                            id="lon-<?php echo $petId; ?>"
                            name="longitude"
                            class="form-control form-control-sm"
                            required
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
                        ></textarea>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php
    return (string) ob_get_clean();
}

?>

