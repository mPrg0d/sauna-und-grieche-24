<?php
/**
 * helpers.php
 * Gemeinsame Hilfsfunktionen für Startseite, Views und Controller
 */

const RATING_MAX = 7;

// Erlaubte Bewertungsziele
const TARGET_TYPES = ['sauna', 'restaurant', 'combi'];

// Orte, die Nutzer selbst anlegen dürfen → zugehörige Tabelle
const PLACE_TABLES = [
    'sauna'      => 'saunas',
    'restaurant' => 'restaurants',
];

function is_author(): bool
{
    return isset($_SESSION["role"]) && $_SESSION["role"] === "author";
}

/**
 * Einmal-Nachricht (z. B. "Sauna wurde hinzugefügt") für den nächsten Seitenaufruf
 */
function flash_set(string $message): void
{
    $_SESSION["flash"] = $message;
}

function flash_take(): ?string
{
    $message = $_SESSION["flash"] ?? null;
    unset($_SESSION["flash"]);
    return $message;
}

/**
 * Sprungmarke auf der Startseite je Zieltyp
 */
function section_anchor(string $type): string
{
    $sections = [
        'sauna'      => '#saunen',
        'restaurant' => '#restaurants',
        'combi'      => '#combis',
    ];
    return $sections[$type] ?? '';
}

/**
 * Luftlinie zwischen zwei Koordinaten in km
 */
function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function format_distance(float $km): string
{
    if ($km < 1) {
        return round($km * 1000) . " m";
    }
    return number_format($km, 1, ',', '.') . " km";
}

/**
 * Durchschnittsbewertungen aller Ziele
 * Rückgabe: [target_type][target_id] => ['avg' => float, 'count' => int]
 */
function load_rating_stats(PDO $pdo): array
{
    $rows = $pdo->query("
        SELECT target_type, target_id, AVG(rating) AS avg_rating, COUNT(*) AS cnt
        FROM ratings
        GROUP BY target_type, target_id
    ")->fetchAll();

    $stats = [];
    foreach ($rows as $row) {
        $stats[$row["target_type"]][(int)$row["target_id"]] = [
            "avg"   => (float)$row["avg_rating"],
            "count" => (int)$row["cnt"],
        ];
    }
    return $stats;
}

function format_rating(?array $stat): string
{
    if (!$stat) {
        return "Noch keine Bewertungen";
    }
    $label = $stat["count"] === 1 ? "Bewertung" : "Bewertungen";
    return "Ø " . number_format($stat["avg"], 1, ',', '') . " / " . RATING_MAX . " ★ ("
         . $stat["count"] . " " . $label . ")";
}

/**
 * Bewertung als HTML-Block: große Zahl, "/ 7" und Anzahl der Bewertungen
 */
function rating_html(?array $stat, string $label = ""): string
{
    $prefix = $label !== "" ? '<span class="score-label">' . htmlspecialchars($label) . '</span>' : "";

    if (!$stat) {
        return '<div class="score score-empty">' . $prefix . '<span class="score-none">Noch nicht bewertet</span></div>';
    }

    $count = $stat["count"] . " " . ($stat["count"] === 1 ? "Bewertung" : "Bewertungen");

    return '<div class="score">' . $prefix
         . '<span class="score-value">' . number_format($stat["avg"], 1, ',', '') . '</span>'
         . '<span class="score-max">/ ' . RATING_MAX . ' ★</span>'
         . '<span class="score-count">' . $count . '</span>'
         . '</div>';
}

/**
 * Titel eines Bewertungsziels als HTML; bei Kombis "Sauna & Taverne" mit kursivem &
 */
function target_title_html(string $type, array $target): string
{
    if ($type === "combi") {
        return htmlspecialchars($target["sauna_name"])
             . ' <em class="title-amp">&amp;</em> '
             . htmlspecialchars($target["rest_name"]);
    }
    return htmlspecialchars($target["name"]);
}

/**
 * Gemeinsamer Seitenkopf für alle Seiten
 *  - $title:     Seitentitel (Browser-Tab)
 *  - $extraHead: zusätzliche Tags im <head>, z. B. Leaflet-CSS
 *  - $width:     "wide" für Übersichten, "narrow" für Formulare
 */
function page_start(string $title, string $extraHead = "", string $width = "wide"): void
{
    $loggedIn = isset($_SESSION["user_id"]);
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> · Sauna &amp; Grieche 24</title>
<?= $extraHead ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400&family=Source+Sans+3:wght@400;600&display=swap">
<?php // Inline eingebunden, damit es unabhängig vom URL-Pfad der Seite funktioniert ?>
<style>
<?php readfile(__DIR__ . "/style.css"); ?>
</style>
</head>
<body>

<a class="skip-link" href="#main">Zum Inhalt springen</a>

<header class="site-header">
    <div class="site-header-inner">
        <a class="brand" href="index.php">
            <span class="brand-name">Sauna <em>&amp;</em> Grieche</span>
            <span class="brand-badge">24</span>
        </a>

        <nav class="site-nav" aria-label="Hauptnavigation">
            <a href="index.php#saunen">Saunen</a>
            <a href="index.php#restaurants">Tavernen</a>
            <a href="index.php#combis">Kombis</a>
            <span class="site-nav-sep" aria-hidden="true"></span>
            <?php if ($loggedIn): ?>
                <a href="index.php?view=user_settings" class="site-nav-user">
                    <?= htmlspecialchars($_SESSION["username"]) ?>
                </a>
                <a href="index.php?view=logout">Abmelden</a>
            <?php else: ?>
                <a href="index.php?view=login" class="site-nav-cta">Anmelden</a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="meander" aria-hidden="true"></div>
</header>

<main id="main" class="page page-<?= $width === "narrow" ? "narrow" : "wide" ?>">
    <?php
}

/**
 * Gemeinsamer Seitenfuß
 *  - $extraScripts: Script-Tags, die am Ende des <body> stehen sollen
 */
function page_end(string $extraScripts = ""): void
{
    ?>
</main>

<footer class="site-footer">
    <div class="meander" aria-hidden="true"></div>
    <div class="site-footer-inner">
        <span class="brand-name">Sauna <em>&amp;</em> Grieche 24</span>
        <span>Erst schwitzen, dann Gyros.</span>
    </div>
</footer>

<?= $extraScripts ?>
</body>
</html>
    <?php
}

/**
 * Kombis (Sauna + Restaurant) inkl. Namen, Koordinaten und Entfernung laden.
 * Optional gefiltert auf eine bestimmte Sauna oder ein bestimmtes Restaurant.
 */
function load_combis(PDO $pdo, ?string $placeType = null, ?int $placeId = null): array
{
    $sql = "
        SELECT c.id, c.sauna_id, c.restaurant_id,
               s.name AS sauna_name, s.city AS sauna_city, s.lat AS sauna_lat, s.lng AS sauna_lng,
               r.name AS rest_name,  r.city AS rest_city,  r.lat AS rest_lat,  r.lng AS rest_lng
        FROM combis c
        JOIN saunas s      ON s.id = c.sauna_id
        JOIN restaurants r ON r.id = c.restaurant_id
    ";
    $params = [];

    if ($placeType === 'sauna') {
        $sql .= " WHERE c.sauna_id = :pid";
        $params["pid"] = $placeId;
    } elseif ($placeType === 'restaurant') {
        $sql .= " WHERE c.restaurant_id = :pid";
        $params["pid"] = $placeId;
    } elseif ($placeType === 'combi') {
        $sql .= " WHERE c.id = :pid";
        $params["pid"] = $placeId;
    }

    $sql .= " ORDER BY s.name, r.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $combis = $stmt->fetchAll();

    foreach ($combis as &$c) {
        $c["name"] = $c["sauna_name"] . " + " . $c["rest_name"];
        $c["distance_km"] = haversine_km(
            (float)$c["sauna_lat"], (float)$c["sauna_lng"],
            (float)$c["rest_lat"],  (float)$c["rest_lng"]
        );
    }
    unset($c);

    return $combis;
}

/**
 * Lädt ein Bewertungsziel (Sauna, Restaurant oder Kombi) oder null, wenn es nicht existiert
 */
function load_target(PDO $pdo, string $type, int $id): ?array
{
    if ($type === 'combi') {
        $combis = load_combis($pdo, 'combi', $id);
        return $combis[0] ?? null;
    }

    if (!is_string($type) || !array_key_exists($type, PLACE_TABLES)) {
        return null;
    }

    $table = PLACE_TABLES[$type];
    $stmt = $pdo->prepare("SELECT id, name, city, lat, lng FROM $table WHERE id = :id LIMIT 1");
    $stmt->execute(["id" => $id]);
    return $stmt->fetch() ?: null;
}
