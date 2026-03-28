<?php
declare(strict_types=1);

require_once __DIR__ . '/pet_card.php';

function renderPetList(array $pets, array $sightingsByPetId, int $currentUserId, string $searchQuery): string {
    ob_start();
    ?>
    <div>
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
                    $petOwnerId = (int) ($pet['user_id'] ?? 0);
                    $isOwner = $currentUserId > 0 && $petOwnerId === $currentUserId;
                    $petSightings = $sightingsByPetId[$petId] ?? [];
                    echo renderPetCard($pet, $petSightings, $currentUserId, $isOwner);
                    ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

?>

