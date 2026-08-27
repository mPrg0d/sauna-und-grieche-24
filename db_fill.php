<?php
/**
 * db_fill.php
 * Füllt die Datenbank mit Startdaten:
 * - Saunas
 * - Restaurants
 * - Kombi-Erlebnisse (optional)
 * - Users (Admin + Authors)
 */

$config = require __DIR__ . '/private/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

/**
 * Helper: Insert only if not exists
 */
function insertOnce(PDO $pdo, string $table, array $data, string $uniqueField)
{
    $check = $pdo->prepare("SELECT id FROM $table WHERE $uniqueField = :val LIMIT 1");
    $check->execute(['val' => $data[$uniqueField]]);

    if ($check->fetch()) {
        return; // already exists
    }

    $fields = array_keys($data);
    $columns = implode(",", $fields);
    $placeholders = ":" . implode(",:", $fields);

    $stmt = $pdo->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
    $stmt->execute($data);
}

/* ------------------------------
   USERS
------------------------------ */

$users = [
    [
        "username" => "Marcel",
        "password_hash" => password_hash("admin123", PASSWORD_DEFAULT),
        "role" => "author"  // Admin = author + extra rights später
    ],
    [
        "username" => "David",
        "password_hash" => password_hash("author123", PASSWORD_DEFAULT),
        "role" => "author"
    ],
    [
        "username" => "Mathias",
        "password_hash" => password_hash("author123", PASSWORD_DEFAULT),
        "role" => "author"
    ]
];

foreach ($users as $u) {
    insertOnce($pdo, "users", $u, "username");
}

/* ------------------------------
   SAUNAS (aus deiner index.php)
------------------------------ */

$saunas = [
    [
        "name" => "RheinBad Wesel – Saunalandschaft",
        "city" => "Wesel",
        "lat" => 51.660104,
        "lng" => 6.592046
    ]
];

foreach ($saunas as $s) {
    insertOnce($pdo, "saunas", $s, "name");
}

/* ------------------------------
   RESTAURANTS (aus deiner index.php)
------------------------------ */

$restaurants = [
    [
        "name" => "Restaurant Hellas – seit 1985",
        "city" => "Wesel",
        "lat" => 51.65890,
        "lng" => 6.61770
    ]
];

foreach ($restaurants as $r) {
    insertOnce($pdo, "restaurants", $r, "name");
}

/* ------------------------------
   OPTIONAL: Kombi-Erlebnisse
   (falls du später eine eigene Tabelle willst)
------------------------------ */

echo "<h2>Database filled successfully.</h2>";
echo "<p>Users, Saunas and Restaurants inserted.</p>";
