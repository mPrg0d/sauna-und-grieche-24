<?php

// Nur Autoren dürfen schreiben
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "author") {
    die("Only authors may write reviews.");
}

// Zieltyp & ID müssen übergeben werden
$target_type = $_GET["type"] ?? null; // sauna / restaurant
$target_id   = $_GET["id"] ?? null;

if (!$target_type || !$target_id) {
    die("Invalid review target.");
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Write Review</title>
<style>
body { font-family: Arial; background: #f4f4f4; }
.box { max-width: 600px; margin: 40px auto; background: white; padding: 20px; border-radius: 10px; }
input, textarea, select { width: 100%; padding: 10px; margin: 10px 0; }
button { padding: 10px; background: #444; color: white; border: none; width: 100%; }
</style>
</head>
<body>

<div class="box">
      <?php
$section = "";

if ($target_type === "sauna") {
    $section = "#saunen";
} elseif ($target_type === "restaurant") {
    $section = "#restaurants";
} elseif ($target_type === "combi") {
    $section = "#combis";
}
?>
<a href="index.php<?= $section ?>" class="btn" onclick="return confirm('Wenn du zurück gehst, wird deine Eingabe nicht gespeichert. Wirklich zurück?');">
    ← Zurück
</a>

    <h2>Write Review</h2>

    <form action="index.php?action=review_submit" method="POST">
        <input type="hidden" name="target_type" value="<?= htmlspecialchars($target_type) ?>">
        <input type="hidden" name="target_id" value="<?= htmlspecialchars($target_id) ?>">

        <label>Title</label>
        <input type="text" name="title" required>

        <label>Review</label>
        <textarea name="content" rows="6" required></textarea>

        <label>Rating (1–5)</label>
        <select name="rating" required>
            <option value="1">1 ★</option>
            <option value="2">2 ★★</option>
            <option value="3">3 ★★★</option>
            <option value="4">4 ★★★★</option>
            <option value="5">5 ★★★★★</option>
        </select>

        <button type="submit">Submit Review</button>
    </form>
</div>