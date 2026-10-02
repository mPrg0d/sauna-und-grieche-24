<?php
require_once __DIR__ . '/../helpers.php';

// Nur Autoren dürfen neue Orte anlegen
if (!is_author()) {
    die("Nur Autoren dürfen neue Orte hinzufügen.");
}

$type = $_GET["type"] ?? null; // sauna / restaurant

if (!is_string($type) || !array_key_exists($type, PLACE_TABLES)) {
    die("Ungültiger Ortstyp.");
}

$label = $type === "sauna" ? "Sauna" : "griechisches Restaurant";

// Fehler und Eingaben aus dem letzten Versuch (falls die Prüfung fehlgeschlagen ist)
$error = $_SESSION["form_error"] ?? null;
$old   = $_SESSION["form_old"] ?? [];
unset($_SESSION["form_error"], $_SESSION["form_old"]);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars(ucfirst($label)) ?> hinzufügen</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
body { font-family: Arial; background: #f4f4f4; }
.box { max-width: 700px; margin: 40px auto; background: white; padding: 20px; border-radius: 10px; }
input { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
.btn {
    display: inline-block;
    padding: 8px 14px;
    background: #2b2b2b;
    color: white;
    text-decoration: none;
    border: none;
    border-radius: 6px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: 0.2s;
}
.btn:hover { background: #1f1f1f; }
.error { color: red; }
.hint { color: #666; font-size: 0.9rem; }
.search-row { display: flex; gap: 10px; align-items: center; }
.search-row input { flex: 1; }
#search-results { list-style: none; padding: 0; margin: 0 0 10px; }
#search-results li {
    padding: 8px; border-bottom: 1px solid #eee; cursor: pointer; font-size: 0.9rem;
}
#search-results li:hover { background: #f0f0f0; }
#pick-map { height: 350px; border-radius: 10px; border: 2px solid #ccc; margin: 10px 0; }
#coords { font-size: 0.9rem; color: #444; }
</style>
</head>
<body>

<div class="box">
    <a href="index.php<?= section_anchor($type) ?>" class="btn">← Zurück</a>

    <h2><?= htmlspecialchars(ucfirst($label)) ?> hinzufügen</h2>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form id="place-form" action="index.php?action=place_submit" method="POST">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
        <input type="hidden" name="lat" id="lat" value="<?= htmlspecialchars($old["lat"] ?? "") ?>">
        <input type="hidden" name="lng" id="lng" value="<?= htmlspecialchars($old["lng"] ?? "") ?>">

        <label>Name</label>
        <input type="text" name="name" maxlength="255" required
               value="<?= htmlspecialchars($old["name"] ?? "") ?>">

        <label>Ort / Stadt</label>
        <input type="text" name="city" id="city" maxlength="255" required
               value="<?= htmlspecialchars($old["city"] ?? "") ?>">

        <label>Standort</label>
        <p class="hint">Adresse suchen oder direkt auf die Karte klicken. Die Markierung lässt sich verschieben.</p>

        <div class="search-row">
            <input type="text" id="search" placeholder="z. B. Am Rhein 1, Wesel">
            <button type="button" class="btn" id="search-btn">Suchen</button>
        </div>
        <ul id="search-results"></ul>

        <div id="pick-map"></div>
        <p id="coords">Noch kein Standort gewählt.</p>

        <button type="submit" class="btn"><?= htmlspecialchars(ucfirst($label)) ?> speichern</button>
    </form>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var latInput  = document.getElementById('lat');
var lngInput  = document.getElementById('lng');
var cityInput = document.getElementById('city');
var coordsText = document.getElementById('coords');

var map = L.map('pick-map').setView([51.65, 6.6], 10);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
}).addTo(map);

var marker = null;

function setPosition(lat, lng, zoom) {
    lat = Number(lat).toFixed(6);
    lng = Number(lng).toFixed(6);
    latInput.value = lat;
    lngInput.value = lng;
    coordsText.textContent = 'Gewählter Standort: ' + lat + ', ' + lng;

    if (!marker) {
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', function () {
            var p = marker.getLatLng();
            setPosition(p.lat, p.lng);
        });
    } else {
        marker.setLatLng([lat, lng]);
    }

    if (zoom) {
        map.setView([lat, lng], zoom);
    }
}

// Werte aus einem fehlgeschlagenen Versuch wiederherstellen
if (latInput.value && lngInput.value) {
    setPosition(latInput.value, lngInput.value, 15);
}

map.on('click', function (e) {
    setPosition(e.latlng.lat, e.latlng.lng);
});

// Adresssuche über OpenStreetMap Nominatim
var resultsList = document.getElementById('search-results');
var searchInput = document.getElementById('search');

function search() {
    var q = searchInput.value.trim();
    if (!q) return;

    resultsList.innerHTML = '<li>Suche läuft …</li>';

    fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=5&accept-language=de&q='
          + encodeURIComponent(q))
        .then(function (res) { return res.json(); })
        .then(function (results) {
            resultsList.innerHTML = '';
            if (!results.length) {
                resultsList.innerHTML = '<li>Nichts gefunden. Setze den Standort einfach per Klick auf die Karte.</li>';
                return;
            }
            results.forEach(function (r) {
                var li = document.createElement('li');
                li.textContent = r.display_name;
                li.addEventListener('click', function () {
                    setPosition(r.lat, r.lon, 16);
                    var a = r.address || {};
                    var city = a.city || a.town || a.village || a.municipality;
                    if (city && !cityInput.value.trim()) {
                        cityInput.value = city;
                    }
                    resultsList.innerHTML = '';
                });
                resultsList.appendChild(li);
            });
        })
        .catch(function () {
            resultsList.innerHTML = '<li>Suche fehlgeschlagen. Setze den Standort per Klick auf die Karte.</li>';
        });
}

document.getElementById('search-btn').addEventListener('click', search);
searchInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault(); // nicht das ganze Formular absenden
        search();
    }
});

document.getElementById('place-form').addEventListener('submit', function (e) {
    if (!latInput.value || !lngInput.value) {
        e.preventDefault();
        alert('Bitte wähle den Standort auf der Karte aus.');
    }
});
</script>

</body>
</html>
