<?php
require_once __DIR__ . '/../helpers.php';

// Nur Autoren dürfen schreiben
if (!is_author()) {
    die("Nur Autoren dürfen Bewertungen schreiben.");
}

// Bearbeiten: ?review_id=… (nur eigene Bewertungen), sonst neu: ?type=…&id=…
$review = null;

if (isset($_GET["review_id"])) {
    $review = load_own_review($pdo, (int)$_GET["review_id"], (int)$_SESSION["user_id"]);

    if (!$review) {
        die("Bewertung nicht gefunden oder nicht deine eigene.");
    }

    $target_type = $review["target_type"];
    $target_id   = (int)$review["target_id"];
} else {
    $target_type = $_GET["type"] ?? null; // sauna / restaurant / combi
    $target_id   = (int)($_GET["id"] ?? 0);
}

$editing = $review !== null;
$currentRating = $editing ? (int)$review["rating"] : 0;

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

page_start($editing ? "Bewertung bearbeiten" : "Bewertung schreiben", "", "narrow");
?>

<a href="<?= $editing
        ? "index.php?view=reviews_list&type=" . $target_type . "&id=" . $target_id
        : "index.php" . section_anchor($target_type) ?>" class="btn btn-ghost back-link"
   onclick="return confirm('Wenn du zurück gehst, werden deine Änderungen nicht gespeichert. Wirklich zurück?');">
    ← Zurück
</a>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <span class="eyebrow"><?= $typeLabels[$target_type] ?> <?= $editing ? "– Bewertung bearbeiten" : "bewerten" ?></span>
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

    <?php if ($editing): ?>
    <form action="index.php?action=review_update" method="POST">
        <input type="hidden" name="review_id" value="<?= (int)$review["id"] ?>">
    <?php else: ?>
    <form action="index.php?action=review_submit" method="POST">
        <input type="hidden" name="target_type" value="<?= htmlspecialchars($target_type) ?>">
        <input type="hidden" name="target_id" value="<?= $target_id ?>">
    <?php endif; ?>

        <fieldset class="field">
            <legend>Sterne</legend>
            <div class="star-input">
                <?php for ($i = RATING_MAX; $i >= 1; $i--): ?>
                    <input type="radio" id="star-<?= $i ?>" name="rating" value="<?= $i ?>" required <?= $i === $currentRating ? "checked" : "" ?>>
                    <label for="star-<?= $i ?>" title="<?= $i ?> von <?= RATING_MAX ?> Sternen">★</label>
                <?php endfor; ?>
            </div>
            <span class="field-hint" id="star-hint">
                <?= $currentRating ? $currentRating . " von " . RATING_MAX . " Sternen" : "Wähle 1 bis " . RATING_MAX . " Sterne." ?>
            </span>
        </fieldset>

        <div class="field">
            <label for="title">Titel</label>
            <input type="text" id="title" name="title" maxlength="255" required
                   value="<?= htmlspecialchars($review["title"] ?? "") ?>">
        </div>

        <div class="field">
            <label for="content">Deine Bewertung</label>
            <textarea id="content" name="content" rows="7" required><?= htmlspecialchars($review["content"] ?? "") ?></textarea>
        </div>

        <div class="form-footer">
            <span class="muted">Deine Bewertung erscheint mit deinem Benutzernamen.</span>
            <button type="submit" class="btn"><?= $editing ? "Änderungen speichern" : "Bewertung absenden" ?></button>
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
