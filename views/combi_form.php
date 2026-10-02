<?php
require_once __DIR__ . '/../helpers.php';

// Nur Autoren dürfen Kombis anlegen
if (!is_author()) {
    die("Nur Autoren dürfen Kombis anlegen.");
}

$saunas      = $pdo->query("SELECT id, name, city, lat, lng FROM saunas ORDER BY name")->fetchAll();
$restaurants = $pdo->query("SELECT id, name, city, lat, lng FROM restaurants ORDER BY name")->fetchAll();

// Optional vorausgewählt (z. B. über "Kombi anlegen" bei einer Sauna)
$selectedSauna      = (int)($_GET["sauna_id"] ?? 0);
$selectedRestaurant = (int)($_GET["restaurant_id"] ?? 0);

$error = $_SESSION["form_error"] ?? null;
unset($_SESSION["form_error"]);

page_start("Kombi anlegen", "", "narrow");
?>

<a href="index.php#combis" class="btn btn-ghost back-link">← Zurück</a>

<div class="panel">
    <div class="panel-header">
        <span class="eyebrow">Das Gesamterlebnis</span>
        <h1>Kombi anlegen</h1>
        <p>
            Eine Kombi ist dein Tagesablauf aus Sauna und anschließendem Besuch beim Griechen.
            Nach dem Anlegen kannst du das Gesamterlebnis direkt bewerten.
        </p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$saunas || !$restaurants): ?>
        <div class="alert alert-info">Für eine Kombi braucht es mindestens eine Sauna und ein griechisches Restaurant.</div>
        <div class="actions">
            <a class="btn btn-outline" href="index.php?view=place_form&type=sauna">+ Sauna hinzufügen</a>
            <a class="btn btn-outline" href="index.php?view=place_form&type=restaurant">+ Restaurant hinzufügen</a>
        </div>
    <?php else: ?>
    <form action="index.php?action=combi_submit" method="POST">
        <div class="field">
            <label for="sauna"><span class="eyebrow eyebrow-sauna">Zuerst</span>Sauna</label>
            <select name="sauna_id" id="sauna" required>
                <option value="">– Sauna wählen –</option>
                <?php foreach ($saunas as $s): ?>
                    <option value="<?= $s["id"] ?>" <?= $s["id"] == $selectedSauna ? "selected" : "" ?>>
                        <?= htmlspecialchars($s["name"] . " (" . $s["city"] . ")") ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="restaurant"><span class="eyebrow eyebrow-taverne">Danach</span>Griechisches Restaurant</label>
            <select name="restaurant_id" id="restaurant" required>
                <option value="">– Restaurant wählen –</option>
                <?php foreach ($restaurants as $r): ?>
                    <option value="<?= $r["id"] ?>" <?= $r["id"] == $selectedRestaurant ? "selected" : "" ?>>
                        <?= htmlspecialchars($r["name"] . " (" . $r["city"] . ")") ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="field-hint">Sobald eine Sauna gewählt ist, werden die Restaurants nach Entfernung sortiert.</span>
        </div>

        <div class="form-footer">
            <span class="muted">Jede Kombi gibt es nur einmal.</span>
            <button type="submit" class="btn">Kombi anlegen &amp; bewerten</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php
ob_start();
?>
<script>
var JSON_SAUNAS = <?= json_encode($saunas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var JSON_RESTAURANTS = <?= json_encode($restaurants, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

var saunaSelect = document.getElementById('sauna');
var restSelect  = document.getElementById('restaurant');

function distanceKm(lat1, lng1, lat2, lng2) {
    var rad = Math.PI / 180;
    var dLat = (lat2 - lat1) * rad;
    var dLng = (lng2 - lng1) * rad;
    var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
          + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function formatKm(km) {
    return km < 1 ? Math.round(km * 1000) + ' m' : km.toFixed(1).replace('.', ',') + ' km';
}

function sortRestaurants() {
    var sauna = JSON_SAUNAS.find(function (s) { return String(s.id) === saunaSelect.value; });
    var selected = restSelect.value;

    var list = JSON_RESTAURANTS.map(function (r) {
        return {
            r: r,
            km: sauna ? distanceKm(+sauna.lat, +sauna.lng, +r.lat, +r.lng) : null
        };
    });

    if (sauna) {
        list.sort(function (a, b) { return a.km - b.km; });
    }

    restSelect.length = 1; // Platzhalter behalten
    list.forEach(function (item) {
        var text = item.r.name + ' (' + item.r.city + ')';
        if (item.km !== null) {
            text += ' – ' + formatKm(item.km) + ' entfernt';
        }
        restSelect.add(new Option(text, item.r.id, false, String(item.r.id) === selected));
    });
}

if (saunaSelect) {
    saunaSelect.addEventListener('change', sortRestaurants);
    sortRestaurants();
}
</script>
<?php
page_end(ob_get_clean());
