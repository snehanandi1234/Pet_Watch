<?php
require_once __DIR__ . "/Database.php";

class Sighting {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function addSighting(
        int $pet_id,
        int $user_id,
        ?string $comment,
        float $latitude,
        float $longitude
    ): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO sightings (pet_id, user_id, comment, latitude, longitude)
             VALUES (?, ?, ?, ?, ?)"
        );

        return $stmt->execute([
            $pet_id,
            $user_id,
            $comment,
            $latitude,
            $longitude
        ]);
    }

    public function getSightingsByPetId(int $pet_id): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM sightings
             WHERE pet_id = ?
             ORDER BY timestamp DESC, id DESC"
        );
        $stmt->execute([$pet_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSightingsByPetIds(array $petIds): array {
        $petIds = array_values(array_filter(array_map('intval', $petIds), static fn($x) => $x > 0));
        if (empty($petIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($petIds), '?'));

        $stmt = $this->db->prepare(
            "SELECT * FROM sightings
             WHERE pet_id IN ($placeholders)
             ORDER BY pet_id ASC, timestamp DESC, id DESC"
        );

        $stmt->execute($petIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

