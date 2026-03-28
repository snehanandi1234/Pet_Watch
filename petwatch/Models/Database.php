<?php
class Database {
    private static $instance = null;
    private $dbHandle;

    private function __construct() {
        $path = __DIR__ . '/../petwatch.sqlite';
        $this->dbHandle = new PDO('sqlite:' . $path);
        $this->dbHandle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->ensureSchema();
    }

    private function ensureSchema() {
        $this->dbHandle->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL
            )
        ");
        $this->dbHandle->exec("
            CREATE TABLE IF NOT EXISTS pets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                species TEXT NOT NULL,
                description TEXT,
                status TEXT,
                user_id INTEGER NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        $this->dbHandle->exec("
            CREATE TABLE IF NOT EXISTS sightings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pet_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                comment TEXT,
                latitude REAL NOT NULL,
                longitude REAL NOT NULL,
                timestamp TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pet_id) REFERENCES pets(id)
            )
        ");
        $this->migratePetsColumns();
    }

    private function migratePetsColumns(): void {
        $stmt = $this->dbHandle->query('PRAGMA table_info(pets)');
        $cols = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols[$row['name']] = true;
        }
        $add = [
            'breed' => 'TEXT',
            'color' => 'TEXT',
            'photo_url' => 'TEXT',
            'date_reported' => 'TEXT',
        ];
        foreach ($add as $name => $type) {
            if (empty($cols[$name])) {
                $this->dbHandle->exec("ALTER TABLE pets ADD COLUMN {$name} {$type}");
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->dbHandle;
    }
}
