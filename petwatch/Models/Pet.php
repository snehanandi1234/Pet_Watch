<?php
require_once __DIR__ . "/Database.php";

class Pet {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function countAllPets(): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM pets");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function countSearchPets($keyword): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM pets
             WHERE name LIKE ?
             OR species LIKE ?
             OR description LIKE ?"
        );
        $search = "%" . $keyword . "%";
        $stmt->execute([$search, $search, $search]);
        return (int) $stmt->fetchColumn();
    }

    // GET ALL PETS (paginated)
    public function getAllPets($limit, $offset) {
        $stmt = $this->db->prepare(
            "SELECT * FROM pets ORDER BY id DESC LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ADD PET
    public function addPet(
        string $name,
        string $species,
        string $breed,
        string $color,
        ?string $photo_url,
        ?string $description,
        string $status,
        string $date_reported,
        int $user_id
    ) {
        $stmt = $this->db->prepare(
            "INSERT INTO pets (name, species, breed, color, photo_url, description, status, date_reported, user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $name,
            $species,
            $breed,
            $color,
            $photo_url,
            $description,
            $status,
            $date_reported,
            $user_id
        ]);
    }

    // DELETE PET
    public function deletePet(int $id, int $user_id): bool {
        $stmt = $this->db->prepare("DELETE FROM pets WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $user_id]);
    }

    public function isOwner(int $petId, int $userId): bool {
        $stmt = $this->db->prepare("SELECT user_id FROM pets WHERE id = ?");
        $stmt->execute([$petId]);
        $ownerId = $stmt->fetchColumn();
        return $ownerId !== false && (int) $ownerId === (int) $userId;
    }

    // SEARCH PETS (paginated)
    public function searchPets($keyword, $limit, $offset) {
        $stmt = $this->db->prepare(
            "SELECT * FROM pets
             WHERE name LIKE ?
             OR species LIKE ?
             OR description LIKE ?
             ORDER BY id DESC LIMIT ? OFFSET ?"
        );

        $search = "%" . $keyword . "%";
        $stmt->bindValue(1, $search, PDO::PARAM_STR);
        $stmt->bindValue(2, $search, PDO::PARAM_STR);
        $stmt->bindValue(3, $search, PDO::PARAM_STR);
        $stmt->bindValue(4, (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(5, (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // GET PET BY ID
    public function getPetById($id) {
        $stmt = $this->db->prepare("SELECT * FROM pets WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // UPDATE PET
    public function updatePet(
        int $id,
        int $user_id,
        string $name,
        string $species,
        string $breed,
        string $color,
        ?string $photo_url,
        ?string $description,
        string $status
    ): bool {
        $stmt = $this->db->prepare(
            "UPDATE pets
             SET name = ?,
                 species = ?,
                 breed = ?,
                 color = ?,
                 photo_url = ?,
                 description = ?,
                 status = ?
             WHERE id = ? AND user_id = ?"
        );
        return $stmt->execute([
            $name,
            $species,
            $breed,
            $color,
            $photo_url,
            $description,
            $status,
            $id,
            $user_id
        ]);
    }
}
?>
