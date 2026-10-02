<?php
require_once __DIR__ . '/../helpers.php';

// Nur Autoren dürfen schreiben
if (!is_author()) {
    die("Nur Autoren dürfen Bewertungen schreiben.");
}

// Zieltyp & ID müssen übergeben werden
$target_type = $_GET["type"] ?? null; // sauna / restaurant / combi
$target_id   = (int)($_GET["id"] ?? 0);

if (!in_array($target_type, TARGET_TYPES, true)) {
    die("Ungültiges Bewertungsziel.");
}

$target = load_target($pdo, $target_type, $target_id);

if (!$target) {
    die("Bewertungsziel nicht gefunden.");
}

$flash = flash_take();

$typeLabels = [
    "sauna"      => "Sauna",
    "restaurant" => "Taverne",
    "combi"      => "Kombi-Erlebnis",
];

page_start("Bewertung schreiben", "", "narrow");
?>

<a href="index.php<?= section_anchor($target_type) ?>" class="btn btn-ghost back-link"
   onclick="return confirm('Wenn du zurück gehst, wird deine Eingabe nicht gespeichert. Wirklich zurück?');">
    ← Zurück
</a>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <span class="eyebrow"><?= $typeLabels[$target_type] ?> bewerten</span>
        <h1><?= target_title_html($target_type, $target) ?></h1>
        <?php if ($target_type === "combi"): ?>
            <p>
                Bewerte das Gesamterlebnis: Wie gut passen Sauna und Restaurant zusammen?
                Wie war der Weg, das Timing, das Essen nach dem Saunagang?
                Sauna und Restaurant einzeln bewertest du auf deren eigenen Seiten.
            </p>
        <?php else: ?>
            <p><?= htmlspecialchars($target["city"]) ?></p>
        <?php endif; ?>
    </div>

    <form action="index.php?action=review_submit" method="POST">
        <input type="hidden" name="target_type" value="<?= htmlspecialchars($target_type) ?>">
        <input type="hidden" name="target_id" value="<?= $target_id ?>">

        <fieldset class="field">
            <legend>Sterne</legend>
            <div class="star-input">
                <?php for ($i = RATING_MAX; $i >= 1; $i--): ?>
                    <input type="radio" id="star-<?= $i ?>" name="rating" value="<?= $i ?>" required>
                    <label for="star-<?= $i ?>" title="<?= $i ?> von <?= RATING_MAX ?> Sternen">★</label>
                <?php endfor; ?>
            </div>
            <span class="field-hint" id="star-hint">Wähle 1 bis <?= RATING_MAX ?> Sterne.</span>
        </fieldset>

        <div class="field">
            <label for="title">Titel</label>
            <input type="text" id="title" name="title" maxlength="255" required>
        </div>

        <div class="field">
            <label for="content">Deine Bewertung</label>
            <textarea id="content" name="content" rows="7" required></textarea>
        </div>

        <div class="form-footer">
            <span class="muted">Deine Bewertung erscheint mit deinem Benutzernamen.</span>
            <button type="submit" class="btn">Bewertung absenden</button>
        </div>
    </form>
</div>

<?php
ob_start();
?>
<script>
// Gewählte Sternezahl als Text anzeigen
document.querySelectorAll('.star-input input').forEach(function (input) {
    input.addEventListener('change', function () {
        document.getElementById('star-hint').textContent = input.value + ' von <?= RATING_MAX ?> Sternen';
    });
});
</script>
<?php
page_end(ob_get_clean());
