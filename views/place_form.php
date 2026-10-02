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

$heading = ucfirst($label) . " hinzufügen";

page_start($heading, '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />', "narrow");
?>

<a href="index.php<?= section_anchor($type) ?>" class="btn btn-ghost back-link">← Zurück</a>

<div class="panel">
    <div class="panel-header">
        <span class="eyebrow <?= $type === "sauna" ? "eyebrow-sauna" : "eyebrow-taverne" ?>">Neuer Eintrag</span>
        <h1><?= htmlspecialchars($heading) ?></h1>
        <p>Danach kann der Ort von allen Autoren bewertet und mit anderen Orten zu einer Kombi verbunden werden.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form id="place-form" action="index.php?action=place_submit" method="POST">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
        <input type="hidden" name="lat" id="lat" value="<?= htmlspecialchars($old["lat"] ?? "") ?>">
        <input type="hidden" name="lng" id="lng" value="<?= htmlspecialchars($old["lng"] ?? "") ?>">

        <div class="field">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" maxlength="255" required
                   value="<?= htmlspecialchars($old["name"] ?? "") ?>">
        </div>

        <div class="field">
            <label for="city">Ort / Stadt</label>
            <input type="text" id="city" name="city" maxlength="255" required
                   value="<?= htmlspecialchars($old["city"] ?? "") ?>">
        </div>

        <div class="field">
            <label for="search">Standort</label>
            <div class="input-row">
                <input type="text" id="search" placeholder="Adresse suchen, z. B. Am Rhein 1, Wesel">
                <button type="button" class="btn btn-outline" id="search-btn">Suchen</button>
            </div>
            <ul id="search-results" class="search-results"></ul>
            <span class="field-hint">Oder direkt auf die Karte klicken. Die Markierung lässt sich verschieben.</span>

            <div id="pick-map" class="map map-small"></div>
            <p id="coords" class="coords">Noch kein Standort gewählt.</p>
        </div>

        <div class="form-footer">
            <span class="muted">Alle Felder sind Pflichtfelder.</span>
            <button type="submit" class="btn"><?= htmlspecialchars(ucfirst($label)) ?> speichern</button>
        </div>
    </form>
</div>

<?php
ob_start();
?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var latInput  = document.getElementById('lat');
var lngInput  = document.getElementById('lng');
var cityInput = document.getElementById('city');
var coordsText = document.getElementById('coords');

var map = L.map('pick-map').setView([51.65, 6.6], 10);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
}).addTo(map);

var pinIcon = L.divIcon({
    html: '<div class="marker <?= $type === "sauna" ? "marker-sauna" : "marker-grieche" ?>"><?= $type === "sauna" ? "S" : "G" ?></div>',
    className: '',
    iconSize: [30, 30]
});

var marker = null;

function setPosition(lat, lng, zoom) {
    lat = Number(lat).toFixed(6);
    lng = Number(lng).toFixed(6);
    latInput.value = lat;
    lngInput.value = lng;
    coordsText.textContent = 'Gewählter Standort: ' + lat + ', ' + lng;

    if (!marker) {
        marker = L.marker([lat, lng], { draggable: true, icon: pinIcon }).addTo(map);
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

function showMessage(text) {
    resultsList.innerHTML = '';
    var li = document.createElement('li');
    li.textContent = text;
    resultsList.appendChild(li);
}

function search() {
    var q = searchInput.value.trim();
    if (!q) return;

    showMessage('Suche läuft …');

    fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=5&accept-language=de&q='
          + encodeURIComponent(q))
        .then(function (res) { return res.json(); })
        .then(function (results) {
            resultsList.innerHTML = '';
            if (!results.length) {
                showMessage('Nichts gefunden. Setze den Standort einfach per Klick auf die Karte.');
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
            showMessage('Suche fehlgeschlagen. Setze den Standort per Klick auf die Karte.');
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
<?php
page_end(ob_get_clean());
