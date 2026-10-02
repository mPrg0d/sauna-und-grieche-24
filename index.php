<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$view = $_GET['view'] ?? null;
$action = $_GET['action'] ?? null;

// Views (HTML)
$allowed_views = [
    'login',
    'logout',
    'user_settings',
    'review_form',
    'reviews_list',
    'place_form',
    'combi_form'
];

// Controllers (POST handlers)
$allowed_actions = [
    'review_submit',
    'place_submit',
    'combi_submit'
];

// Controller routing
if ($action && in_array($action, $allowed_actions)) {
    $file = __DIR__ . "/Controllers/{$action}.php";

    if (file_exists($file)) {
        require $file;
        exit;
    }

    die("Action not found.");
}

// View routing
if ($view && in_array($view, $allowed_views)) {
    $file = __DIR__ . "/views/{$view}.php";

    if (file_exists($file)) {
        require $file;
        exit;
    }

    die("View not found.");
}

// Default: Startseite

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Sauna & Grieche 24</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="SaunaUndGrieche24/style.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
</head>
<body>

<nav>
     <?php if (isset($_SESSION["user_id"])): ?>
     <span style="color:white;">Hallo, <?= htmlspecialchars($_SESSION["username"]) ?></span>
     <?php endif; ?>
    <a href="index.php">Home</a>

    <?php if (!isset($_SESSION["user_id"])): ?>
        <a href="index.php?view=login">Login</a>
    <?php else: ?>
        <a href="index.php?view=user_settings">Einstellungen</a>
        <a href="index.php?view=logout">Logout</a>
    <?php endif; ?>
</nav>
<?php
/* ------------------------------
   Load Saunas
------------------------------ */
$saunas = $pdo->query("
    SELECT id, name, city, lat, lng
    FROM saunas
    ORDER BY name
")->fetchAll();

/* ------------------------------
   Load Restaurants
------------------------------ */
$restaurants = $pdo->query("
    SELECT id, name, city, lat, lng
    FROM restaurants
    ORDER BY name
")->fetchAll();

/* ------------------------------
   Load Combis (Sauna + Restaurant)
   → Nur Kombis, die ein Autor bewusst angelegt hat
------------------------------ */
$combis = load_combis($pdo);

/* ------------------------------
   Ø-Bewertungen & Anzahl Kombis je Ort
------------------------------ */
$stats = load_rating_stats($pdo);

$combiCount = ["sauna" => [], "restaurant" => []];
foreach ($combis as $c) {
    $combiCount["sauna"][$c["sauna_id"]] = ($combiCount["sauna"][$c["sauna_id"]] ?? 0) + 1;
    $combiCount["restaurant"][$c["restaurant_id"]] = ($combiCount["restaurant"][$c["restaurant_id"]] ?? 0) + 1;
}

$flash = flash_take();
?>

<header>
    <h1>Sauna & Grieche 24</h1>
    <p>Rezensionen für Saunen, griechische Restaurants & Kombi-Erlebnisse</p>
</header>

<nav>
    <a href="#saunen">Saunen</a>
    <a href="#restaurants">Griechische Restaurants</a>
    <a href="#combis">Kombi-Erlebnisse</a>
</nav>

<div class="container">

    <?php if ($flash): ?>
        <p class="flash"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <div id="map"></div>

<?php
/* ------------------------------
   Sauna- und Restaurant-Abschnitt (gleicher Aufbau)
------------------------------ */
$placeSections = [
    ["type" => "sauna",      "anchor" => "saunen",      "title" => "Saunen",                  "add" => "+ Sauna hinzufügen",      "items" => $saunas],
    ["type" => "restaurant", "anchor" => "restaurants", "title" => "Griechische Restaurants", "add" => "+ Restaurant hinzufügen", "items" => $restaurants],
];
?>

<?php foreach ($placeSections as $sec): ?>
<div class="section-header">
    <h2 id="<?= $sec["anchor"] ?>" class="section-title"><?= $sec["title"] ?></h2>
    <?php if (is_author()): ?>
        <a class="btn" href="index.php?view=place_form&type=<?= $sec["type"] ?>"><?= $sec["add"] ?></a>
    <?php endif; ?>
</div>
<div class="list">
    <?php if (!$sec["items"]): ?>
        <p>Noch keine Einträge vorhanden.</p>
    <?php endif; ?>

    <?php foreach ($sec["items"] as $p): ?>
    <div class="card">
        <h3><?= htmlspecialchars($p["name"]) ?></h3>
        <p><strong>Ort:</strong> <?= htmlspecialchars($p["city"]) ?></p>
        <p class="rating"><?= format_rating($stats[$sec["type"]][(int)$p["id"]] ?? null) ?></p>
        <?php $n = $combiCount[$sec["type"]][$p["id"]] ?? 0; ?>
        <?php if ($n): ?>
            <p class="muted">Teil von <?= $n ?> Kombi<?= $n === 1 ? "" : "s" ?></p>
        <?php endif; ?>

        <div class="actions">
            <a class="btn" href="index.php?view=reviews_list&type=<?= $sec["type"] ?>&id=<?= $p['id'] ?>">
                Reviews ansehen
            </a>

            <?php if (is_author()): ?>
                <a class="btn btn-secondary" href="index.php?view=review_form&type=<?= $sec["type"] ?>&id=<?= $p['id'] ?>">
                    Review schreiben
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>




<div class="section-header">
    <h2 id="combis" class="section-title">Kombi-Erlebnisse</h2>
    <?php if (is_author()): ?>
        <a class="btn" href="index.php?view=combi_form">+ Kombi anlegen</a>
    <?php endif; ?>
</div>
<p class="muted">Erst schwitzen, dann Gyros: Eine Kombi verbindet eine Sauna mit einem griechischen Restaurant und wird als Gesamterlebnis bewertet.</p>
<div class="list">
    <?php if (!$combis): ?>
        <p>Noch keine Kombis vorhanden.</p>
    <?php endif; ?>

    <?php foreach ($combis as $c): ?>
    <div class="card card-combi">
        <h3>🧖 <?= htmlspecialchars($c["sauna_name"]) ?><br>+ 🍽️ <?= htmlspecialchars($c["rest_name"]) ?></h3>
        <p><strong>Ort:</strong> <?= htmlspecialchars($c["sauna_city"] === $c["rest_city"] ? $c["sauna_city"] : $c["sauna_city"] . " / " . $c["rest_city"]) ?></p>
        <p><strong>Entfernung:</strong> <?= format_distance($c["distance_km"]) ?></p>
        <p class="rating"><strong>Kombi:</strong> <?= format_rating($stats["combi"][(int)$c["id"]] ?? null) ?></p>
        <p class="muted">
            Sauna: <?= format_rating($stats["sauna"][(int)$c["sauna_id"]] ?? null) ?><br>
            Restaurant: <?= format_rating($stats["restaurant"][(int)$c["restaurant_id"]] ?? null) ?>
        </p>

        <div class="actions">
            <a class="btn" href="index.php?view=reviews_list&type=combi&id=<?= $c['id'] ?>">
                Reviews ansehen
            </a>

            <?php if (is_author()): ?>
                <a class="btn btn-secondary" href="index.php?view=review_form&type=combi&id=<?= $c['id'] ?>">
                    Review schreiben
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>




</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
<?php $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT; ?>
var SAUNAS = <?= json_encode($saunas, $jsonFlags) ?>;
var RESTAURANTS = <?= json_encode($restaurants, $jsonFlags) ?>;
var COMBIS = <?= json_encode(array_map(function ($c) {
    return [
        "id" => $c["id"],
        "name" => $c["name"],
        "distance" => format_distance($c["distance_km"]),
        "sauna" => [(float)$c["sauna_lat"], (float)$c["sauna_lng"]],
        "rest" => [(float)$c["rest_lat"], (float)$c["rest_lng"]],
    ];
}, $combis), $jsonFlags) ?>;

// Namen stammen von Nutzern → vor dem Einfügen ins Popup escapen
function esc(s) {
    return String(s).replace(/[&<>"']/g, function (ch) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
}

var map = L.map('map').setView([51.65, 6.6], 10);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
}).addTo(map);

var markers = L.markerClusterGroup();

var saunaIcon = L.divIcon({
    html: '<div class="marker marker-sauna">S</div>',
    className: '',
    iconSize: [28, 28]
});

var restaurantIcon = L.divIcon({
    html: '<div class="marker marker-grieche">G</div>',
    className: '',
    iconSize: [28, 28]
});

var bounds = [];

function addPlace(p, type, icon) {
    var latlng = [parseFloat(p.lat), parseFloat(p.lng)];
    bounds.push(latlng);
    markers.addLayer(
        L.marker(latlng, { icon: icon }).bindPopup(
            '<b>' + esc(p.name) + '</b><br>' + esc(p.city) +
            '<br><a href="index.php?view=reviews_list&type=' + type + '&id=' + p.id + '">Reviews ansehen</a>'
        )
    );
}

SAUNAS.forEach(function (s) { addPlace(s, 'sauna', saunaIcon); });
RESTAURANTS.forEach(function (r) { addPlace(r, 'restaurant', restaurantIcon); });

COMBIS.forEach(function (c) {
    L.polyline([c.sauna, c.rest], { color: 'red', weight: 4, opacity: 0.7, dashArray: '10,6' })
        .addTo(map)
        .bindPopup(
            '<b>' + esc(c.name) + '</b><br>Kombi-Erlebnis · ' + esc(c.distance) +
            '<br><a href="index.php?view=reviews_list&type=combi&id=' + c.id + '">Reviews ansehen</a>'
        );
});

map.addLayer(markers);

// Karte auf alle Orte zoomen (auch wenn neue Orte weiter weg hinzukommen)
if (bounds.length > 1) {
    map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
}
</script>

</body>
</html>
