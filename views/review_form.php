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
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Bewertung schreiben</title>
<style>
body { font-family: Arial; background: #f4f4f4; }
.box { max-width: 600px; margin: 40px auto; background: white; padding: 20px; border-radius: 10px; }
input, textarea, select { width: 100%; padding: 10px; margin: 10px 0; }
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
.flash { background: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 6px; }
.hint { color: #666; font-size: 0.9rem; }
</style>
</head>
<body>

<div class="box">
<a href="index.php<?= section_anchor($target_type) ?>" class="btn" onclick="return confirm('Wenn du zurück gehst, wird deine Eingabe nicht gespeichert. Wirklich zurück?');">
    ← Zurück
</a>

    <?php if ($flash): ?>
        <p class="flash"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <h2>Bewertung schreiben</h2>
    <p><strong><?= htmlspecialchars($target["name"]) ?></strong></p>

    <?php if ($target_type === "combi"): ?>
        <p class="hint">
            Bewerte das Gesamterlebnis: Wie gut passen Sauna und Restaurant zusammen?
            Wie war der Weg, das Timing, das Essen nach dem Saunagang?
            Sauna und Restaurant einzeln bewertest du auf deren eigenen Seiten.
        </p>
    <?php endif; ?>

    <form action="index.php?action=review_submit" method="POST">
        <input type="hidden" name="target_type" value="<?= htmlspecialchars($target_type) ?>">
        <input type="hidden" name="target_id" value="<?= $target_id ?>">

        <label>Titel</label>
        <input type="text" name="title" required>

        <label>Bewertung</label>
        <textarea name="content" rows="6" required></textarea>

        <label>Sterne (1–7)</label>
        <select name="rating" required>
            <option value="1">1 ★</option>
            <option value="2">2 ★★</option>
            <option value="3">3 ★★★</option>
            <option value="4">4 ★★★★</option>
            <option value="5">5 ★★★★★</option>
            <option value="6">6 ★★★★★★</option>
            <option value="7">7 ★★★★★★★</option>
        </select>

        <button type="submit" class="btn">Bewertung absenden</button>
    </form>
</div>
</body>
</html>
