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
    'combi_form',
    'password_forgot',
    'password_reset'
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

/* ------------------------------
   Sauna- und Restaurant-Abschnitt (gleicher Aufbau)
------------------------------ */
$placeSections = [
    [
        "type"    => "sauna",
        "anchor"  => "saunen",
        "eyebrow" => "Die Hitze",
        "title"   => "Saunen",
        "add"     => "Sauna hinzufügen",
        "card"    => "card-sauna",
        "tone"    => "eyebrow-sauna",
        "items"   => $saunas,
    ],
    [
        "type"    => "restaurant",
        "anchor"  => "restaurants",
        "eyebrow" => "Die Taverne",
        "title"   => "Griechische Restaurants",
        "add"     => "Restaurant hinzufügen",
        "card"    => "card-taverne",
        "tone"    => "eyebrow-taverne",
        "items"   => $restaurants,
    ],
];

page_start(
    "Saunen, Tavernen & Kombis",
    '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />'
);
?>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<section class="hero">
    <div>
        <span class="eyebrow">Bewertungen von Schwitzenden für Hungrige</span>
        <h1>Erst die <em>Hitze</em>,<br>dann die <span class="accent-blue">Taverne</span>.</h1>
        <p class="hero-lead">
            Wir bewerten Saunen, griechische Restaurants – und vor allem das, was dazwischen passiert:
            den perfekten Tag aus Aufguss und Gyros.
        </p>
    </div>

    <div class="hero-stats">
        <a href="#saunen"><strong><?= count($saunas) ?></strong><span>Saunen</span></a>
        <a href="#restaurants"><strong><?= count($restaurants) ?></strong><span>Tavernen</span></a>
        <a href="#combis"><strong><?= count($combis) ?></strong><span>Kombis</span></a>
    </div>
</section>

<div id="map" class="map"></div>
<div class="map-legend">
    <span><i class="legend-dot sauna"></i> Sauna</span>
    <span><i class="legend-dot taverne"></i> Griechisches Restaurant</span>
    <span><i class="legend-line"></i> Kombi-Erlebnis</span>
</div>

<?php foreach ($placeSections as $sec): ?>
<section class="section">
    <div class="section-header">
        <div>
            <span class="eyebrow <?= $sec["tone"] ?>"><?= $sec["eyebrow"] ?></span>
            <h2 id="<?= $sec["anchor"] ?>"><?= $sec["title"] ?></h2>
        </div>
        <?php if (is_author()): ?>
            <a class="btn btn-outline btn-sm" href="index.php?view=place_form&type=<?= $sec["type"] ?>">+ <?= $sec["add"] ?></a>
        <?php endif; ?>
    </div>

    <div class="list">
        <?php if (!$sec["items"]): ?>
            <p class="empty-state">Noch keine Einträge vorhanden.</p>
        <?php endif; ?>

        <?php foreach ($sec["items"] as $p): ?>
        <?php $n = $combiCount[$sec["type"]][$p["id"]] ?? 0; ?>
        <article class="card <?= $sec["card"] ?>">
            <h3><?= htmlspecialchars($p["name"]) ?></h3>
            <p class="card-meta">
                <span><?= htmlspecialchars($p["city"]) ?></span>
                <?php if ($n): ?>
                    <span>Teil von <?= $n ?> Kombi<?= $n === 1 ? "" : "s" ?></span>
                <?php endif; ?>
            </p>

            <?= rating_html($stats[$sec["type"]][(int)$p["id"]] ?? null) ?>

            <div class="actions">
                <a class="btn btn-sm" href="index.php?view=reviews_list&type=<?= $sec["type"] ?>&id=<?= $p['id'] ?>">
                    Bewertungen
                </a>

                <?php if (is_author()): ?>
                    <a class="btn btn-outline btn-sm" href="index.php?view=review_form&type=<?= $sec["type"] ?>&id=<?= $p['id'] ?>">
                        Bewerten
                    </a>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<section class="section">
    <div class="section-header">
        <div>
            <span class="eyebrow">Das Gesamterlebnis</span>
            <h2 id="combis">Kombi-Erlebnisse</h2>
        </div>
        <?php if (is_author()): ?>
            <a class="btn btn-outline btn-sm" href="index.php?view=combi_form">+ Kombi anlegen</a>
        <?php endif; ?>
    </div>
    <p class="section-intro">
        Eine Kombi verbindet eine Sauna mit einem griechischen Restaurant und wird als ganzer Tag bewertet:
        Passt das zusammen? Wie weit ist der Weg? Schmeckt das Souvlaki nach dem dritten Aufguss?
    </p>

    <div class="list">
        <?php if (!$combis): ?>
            <p class="empty-state">Noch keine Kombis vorhanden.</p>
        <?php endif; ?>

        <?php foreach ($combis as $c): ?>
        <article class="card card-combi">
            <div class="combi-pair">
                <div>
                    <span class="eyebrow eyebrow-sauna">Sauna</span>
                    <div class="combi-pair-name"><?= htmlspecialchars($c["sauna_name"]) ?></div>
                </div>
                <span class="ampersand" aria-hidden="true">&amp;</span>
                <div>
                    <span class="eyebrow eyebrow-taverne">Taverne</span>
                    <div class="combi-pair-name"><?= htmlspecialchars($c["rest_name"]) ?></div>
                </div>
            </div>

            <p class="card-meta">
                <span><?= htmlspecialchars($c["sauna_city"] === $c["rest_city"] ? $c["sauna_city"] : $c["sauna_city"] . " / " . $c["rest_city"]) ?></span>
                <span><?= format_distance($c["distance_km"]) ?> Luftlinie</span>
            </p>

            <?= rating_html($stats["combi"][(int)$c["id"]] ?? null, "Kombi") ?>

            <div class="combi-sub-scores">
                <?= rating_html($stats["sauna"][(int)$c["sauna_id"]] ?? null, "Sauna") ?>
                <?= rating_html($stats["restaurant"][(int)$c["restaurant_id"]] ?? null, "Taverne") ?>
            </div>

            <div class="actions">
                <a class="btn btn-sm" href="index.php?view=reviews_list&type=combi&id=<?= $c['id'] ?>">
                    Bewertungen
                </a>

                <?php if (is_author()): ?>
                    <a class="btn btn-outline btn-sm" href="index.php?view=review_form&type=combi&id=<?= $c['id'] ?>">
                        Bewerten
                    </a>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<?php
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$combisJson = array_map(function ($c) {
    return [
        "id" => $c["id"],
        "name" => $c["name"],
        "distance" => format_distance($c["distance_km"]),
        "sauna" => [(float)$c["sauna_lat"], (float)$c["sauna_lng"]],
        "rest" => [(float)$c["rest_lat"], (float)$c["rest_lng"]],
    ];
}, $combis);

ob_start();
?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
var SAUNAS = <?= json_encode($saunas, $jsonFlags) ?>;
var RESTAURANTS = <?= json_encode($restaurants, $jsonFlags) ?>;
var COMBIS = <?= json_encode($combisJson, $jsonFlags) ?>;

// Namen stammen von Nutzern → vor dem Einfügen ins Popup escapen
function esc(s) {
    return String(s).replace(/[&<>"']/g, function (ch) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
}

var map = L.map('map', { scrollWheelZoom: false }).setView([51.65, 6.6], 10);

// OpenStreetMap-Kacheln, per CSS farblich gedämpft (siehe .map)
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
}).addTo(map);

var markers = L.markerClusterGroup();

var saunaIcon = L.divIcon({
    html: '<div class="marker marker-sauna">S</div>',
    className: '',
    iconSize: [30, 30]
});

var restaurantIcon = L.divIcon({
    html: '<div class="marker marker-grieche">G</div>',
    className: '',
    iconSize: [30, 30]
});

var bounds = [];

function addPlace(p, type, icon) {
    var latlng = [parseFloat(p.lat), parseFloat(p.lng)];
    bounds.push(latlng);
    markers.addLayer(
        L.marker(latlng, { icon: icon }).bindPopup(
            '<b>' + esc(p.name) + '</b><br>' + esc(p.city) +
            '<br><a href="index.php?view=reviews_list&type=' + type + '&id=' + p.id + '">Bewertungen ansehen</a>'
        )
    );
}

SAUNAS.forEach(function (s) { addPlace(s, 'sauna', saunaIcon); });
RESTAURANTS.forEach(function (r) { addPlace(r, 'restaurant', restaurantIcon); });

COMBIS.forEach(function (c) {
    L.polyline([c.sauna, c.rest], { color: '#1e2a32', weight: 3, opacity: 0.75, dashArray: '8,6' })
        .addTo(map)
        .bindPopup(
            '<b>' + esc(c.name) + '</b><br>Kombi-Erlebnis · ' + esc(c.distance) +
            '<br><a href="index.php?view=reviews_list&type=combi&id=' + c.id + '">Bewertungen ansehen</a>'
        );
});

map.addLayer(markers);

// Mausrad-Zoom erst nach Klick auf die Karte, damit die Seite normal scrollt
map.on('click', function () { map.scrollWheelZoom.enable(); });

// Karte auf alle Orte zoomen (auch wenn neue Orte weiter weg hinzukommen)
if (bounds.length > 1) {
    map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
}
</script>
<?php
page_end(ob_get_clean());
