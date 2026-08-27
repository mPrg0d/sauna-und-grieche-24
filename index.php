<?php
session_start();
require_once __DIR__ . '/db.php';

$view = $_GET['view'] ?? null;
$action = $_GET['action'] ?? null;

// Views (HTML)
$allowed_views = [
    'login',
    'logout',
    'user_settings',
    'review_form',
    'reviews_list'
];

// Controllers (POST handlers)
$allowed_actions = [
    'review_submit'
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
<link rel="stylesheet" href="style.css">
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
<?
/* ------------------------------
   Load Saunas
------------------------------ */
$saunas = $pdo->query("
    SELECT id, name, city, lat, lng
    FROM saunas
")->fetchAll();

/* ------------------------------
   Load Restaurants
------------------------------ */
$restaurants = $pdo->query("
    SELECT id, name, city, lat, lng
    FROM restaurants
")->fetchAll();

/* ------------------------------
   Build Combis (Sauna + Restaurant)
   → Jede Sauna wird mit jedem Restaurant kombiniert
------------------------------ */
$combis = [];
foreach ($saunas as $s) {
    foreach ($restaurants as $r) {
        $combis[] = [
            "name" => $s["name"] . " + " . $r["name"],
            "city" => $s["city"],
            "lat" => ($s["lat"] + $r["lat"]) / 2,
            "lng" => ($s["lng"] + $r["lng"]) / 2,
            "sauna_lat" => $s["lat"],
            "sauna_lng" => $s["lng"],
            "rest_lat" => $r["lat"],
            "rest_lng" => $r["lng"]
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Sauna & Grieche 24 – Rezensionen</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

<style>
body { margin: 0; font-family: Arial, sans-serif; background: #f4f4f4; color: #333; }
header { background: #2b2b2b; color: white; padding: 20px; text-align: center; }
nav { background: #444; display: flex; justify-content: center; gap: 30px; padding: 10px; }
nav a { color: white; text-decoration: none; font-weight: bold; }
.container { max-width: 1100px; margin: 20px auto; padding: 10px; }
.section-title { font-size: 1.8rem; margin-bottom: 10px; border-left: 5px solid #444; padding-left: 10px; }
.list { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; }
.card { background: white; padding: 15px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
.card {
    background: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 8px 14px;
    background: #2b2b2b;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.9rem;
    transition: 0.2s;
}

.btn:hover {
    background: #1f1f1f;
}

.btn-secondary {
    background: #555;
}

.btn-secondary:hover {
    background: #444;
}
.icon-outline {
    filter: drop-shadow(0 0 2px black) drop-shadow(0 0 2px black);
}
.marker {
    background: white;
    border: 2px solid black;
    border-radius: 50%;
    font-weight: bold;
    font-size: 14px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.marker-sauna {
    color: #e67e22;
}

.marker-grieche {
    color: #2980b9;
}



#map { width: 100%; height: 450px; border-radius: 10px; margin-bottom: 30px; border: 2px solid #ccc; }
</style>
</head>
<body>

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

    <div id="map"></div>

<h2 id="saunen" class="section-title">Saunen</h2>
<div class="list">
    <?php foreach ($saunas as $s): ?>
    <div class="card">
        <h3><?= htmlspecialchars($s["name"]) ?></h3>
        <p><strong>Ort:</strong> <?= htmlspecialchars($s["city"]) ?></p>

        <div class="actions">
            <a class="btn" href="index.php?view=reviews_list&type=sauna&id=<?= $s['id'] ?>">
                Reviews ansehen
            </a>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "author"): ?>
                <a class="btn btn-secondary" href="index.php?view=review_form&type=sauna&id=<?= $s['id'] ?>">
                    Review schreiben
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>





<h2 id="restaurants" class="section-title">Griechische Restaurants</h2>
<div class="list">
    <?php foreach ($restaurants as $r): ?>
    <div class="card">
        <h3><?= htmlspecialchars($r["name"]) ?></h3>
        <p><strong>Ort:</strong> <?= htmlspecialchars($r["city"]) ?></p>

        <div class="actions">
            <a class="btn" href="index.php?view=reviews_list&type=restaurant&id=<?= $r['id'] ?>">
                Reviews ansehen
            </a>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "author"): ?>
                <a class="btn btn-secondary" href="index.php?view=review_form&type=restaurant&id=<?= $r['id'] ?>">
                    Review schreiben
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>




<h2 id="combis" class="section-title">Kombi-Erlebnisse</h2>
<div class="list">
    <?php foreach ($combis as $c): ?>
    <div class="card">
        <h3><?= htmlspecialchars($c["name"]) ?></h3>
        <p><strong>Ort:</strong> <?= htmlspecialchars($c["city"]) ?></p>
    </div>
    <?php endforeach; ?>
</div>




</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
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


var combiIcon = L.icon({ iconUrl: 'https://cdn-icons-png.flaticon.com/512/535/535285.png', iconSize: [32, 32],className: 'icon-outline' });

<?php foreach ($saunas as $s): ?>
markers.addLayer(
    L.marker([<?= $s["lat"] ?>, <?= $s["lng"] ?>], {icon: saunaIcon})
    .bindPopup("<b><?= htmlspecialchars($s['name']) ?></b><br><?= htmlspecialchars($s['city']) ?>")
);
<?php endforeach; ?>

<?php foreach ($restaurants as $r): ?>
markers.addLayer(
    L.marker([<?= $r["lat"] ?>, <?= $r["lng"] ?>], {icon: restaurantIcon})
    .bindPopup("<b><?= htmlspecialchars($r['name']) ?></b><br><?= htmlspecialchars($r['city']) ?>")
);
<?php endforeach; 
foreach ($combis as $c): ?>
var line = L.polyline(
    [
        [<?= $c["sauna_lat"] ?>, <?= $c["sauna_lng"] ?>],
        [<?= $c["rest_lat"] ?>, <?= $c["rest_lng"] ?>]
    ],
    { color: 'red', weight: 4, opacity: 0.7, dashArray: '10,6' }
).addTo(map);

line.bindPopup("<b><?= htmlspecialchars($c['name']) ?></b><br>Kombi-Erlebnis");
<?php endforeach; ?>

map.addLayer(markers);
</script>

</body>
</html>
