<?php
/**
 * bootstrap.php
 * Erstellt alle Tabellen für Sauna & Greek Review System
 * Rollen:
 *  - author: darf Rezensionen schreiben
 *  - user: darf nur lesen
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
 * Helper: Execute SQL safely
 */
function execQuery(PDO $pdo, string $sql)
{
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    }
}

/**
 * Helper: Add column only if it does not exist yet (bootstrap can be re-run safely)
 */
function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ");
    $stmt->execute(["t" => $table, "c" => $column]);

    if ((int)$stmt->fetchColumn() === 0) {
        execQuery($pdo, "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

/* ------------------------------
   USERS TABLE
------------------------------ */
execQuery($pdo, "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('author', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ------------------------------
   SAUNAS TABLE
------------------------------ */
execQuery($pdo, "
CREATE TABLE IF NOT EXISTS saunas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    city VARCHAR(255) NOT NULL,
    lat DECIMAL(10,6) NOT NULL,
    lng DECIMAL(10,6) NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ------------------------------
   RESTAURANTS TABLE
------------------------------ */
execQuery($pdo, "
CREATE TABLE IF NOT EXISTS restaurants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    city VARCHAR(255) NOT NULL,
    lat DECIMAL(10,6) NOT NULL,
    lng DECIMAL(10,6) NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ------------------------------
   REVIEWS TABLE
   Only authors may write reviews
------------------------------ */
execQuery($pdo, "
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_type ENUM('sauna', 'restaurant', 'combi') NOT NULL,
    target_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ------------------------------
   RATINGS TABLE
   Numeric rating (1–7)
------------------------------ */
execQuery($pdo, "
CREATE TABLE IF NOT EXISTS ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_type ENUM('sauna', 'restaurant', 'combi') NOT NULL,
    target_id INT NOT NULL,
    rating INT NOT NULL,
    review_id INT NULL,
    INDEX idx_ratings_review (review_id),
    CONSTRAINT chk_rating_range CHECK (rating BETWEEN 1 AND 7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ------------------------------
   COMBIS TABLE
   Eine konkrete Kombi aus einer Sauna und einem Restaurant,
   die von Autoren angelegt und als Ganzes bewertet wird
------------------------------ */
execQuery($pdo, "
CREATE TABLE IF NOT EXISTS combis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sauna_id INT NOT NULL,
    restaurant_id INT NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_combi (sauna_id, restaurant_id),
    FOREIGN KEY (sauna_id) REFERENCES saunas(id) ON DELETE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ------------------------------
   MIGRATIONS for existing installations
------------------------------ */

// Kombis können bewertet werden
execQuery($pdo, "ALTER TABLE reviews MODIFY target_type ENUM('sauna', 'restaurant', 'combi') NOT NULL");
execQuery($pdo, "ALTER TABLE ratings MODIFY target_type ENUM('sauna', 'restaurant', 'combi') NOT NULL");

// Bewertungen bis 7 Sterne erlauben: alte CHECK-Regel (1–5) durch 1–7 ersetzen
$stmt = $pdo->query("
    SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ratings' AND CONSTRAINT_TYPE = 'CHECK'
");
$checks = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!in_array("chk_rating_range", $checks, true)) {
    // MariaDB speichert Spalten-CHECKs an der Spalte selbst → Spalte ohne CHECK neu definieren
    execQuery($pdo, "ALTER TABLE ratings MODIFY rating INT NOT NULL");

    // MySQL legt sie als eigene Constraints an (z. B. ratings_chk_1) → einzeln entfernen
    foreach ($checks as $name) {
        execQuery($pdo, "ALTER TABLE ratings DROP CONSTRAINT `$name`");
    }

    execQuery($pdo, "ALTER TABLE ratings ADD CONSTRAINT chk_rating_range CHECK (rating BETWEEN 1 AND 7)");
}

// Bewertungen bearbeiten: Sterne fest mit ihrem Bewertungstext verknüpfen
addColumnIfMissing($pdo, "ratings", "review_id", "INT NULL, ADD INDEX idx_ratings_review (review_id)");
addColumnIfMissing($pdo, "reviews", "updated_at", "DATETIME NULL");

// Bestehende Sterne ihrer Bewertung zuordnen. Beide wurden immer direkt
// nacheinander gespeichert, daher passen sie je User + Ziel in ID-Reihenfolge zusammen.
$unlinked = $pdo->query("
    SELECT id, user_id, target_type, target_id
    FROM ratings
    WHERE review_id IS NULL
    ORDER BY id
")->fetchAll();

$findReview = $pdo->prepare("
    SELECT r.id FROM reviews r
    WHERE r.user_id = :u AND r.target_type = :t AND r.target_id = :tid
      AND NOT EXISTS (SELECT 1 FROM ratings x WHERE x.review_id = r.id)
    ORDER BY r.id
    LIMIT 1
");
$linkRating = $pdo->prepare("UPDATE ratings SET review_id = :rid WHERE id = :id");

foreach ($unlinked as $rt) {
    $findReview->execute(["u" => $rt["user_id"], "t" => $rt["target_type"], "tid" => $rt["target_id"]]);
    $reviewId = $findReview->fetchColumn();
    if ($reviewId) {
        $linkRating->execute(["rid" => $reviewId, "id" => $rt["id"]]);
    }
}

// E-Mail-Adresse für "Passwort vergessen"
addColumnIfMissing($pdo, "users", "email", "VARCHAR(255) NULL");

/* ------------------------------
   PASSWORD RESETS TABLE
   Es wird nur der SHA-256-Hash des Tokens gespeichert
------------------------------ */
execQuery($pdo, "
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_token (token_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Wer hat den Ort angelegt?
addColumnIfMissing($pdo, "saunas", "created_by", "INT NULL");
addColumnIfMissing($pdo, "restaurants", "created_by", "INT NULL");

echo "<h2>Bootstrap completed successfully.</h2>";
echo "<p>All required tables have been created.</p>";
