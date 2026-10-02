<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if (!is_author()) {
    die("Nur Autoren dürfen Bewertungen schreiben.");
}

$user_id     = $_SESSION["user_id"];
$target_type = $_POST["target_type"] ?? null;
$target_id   = (int)($_POST["target_id"] ?? 0);
$title       = trim((string)($_POST["title"] ?? ""));
$content     = trim((string)($_POST["content"] ?? ""));
$rating      = intval($_POST["rating"] ?? 0);

if (!in_array($target_type, TARGET_TYPES, true) || !load_target($pdo, $target_type, $target_id)) {
    die("Bewertungsziel nicht gefunden.");
}

if (!$title || !$content || $rating < 1 || $rating > RATING_MAX) {
    die("Ungültige Bewertungsdaten.");
}

$pdo->beginTransaction();

// Review speichern
$stmt = $pdo->prepare("
    INSERT INTO reviews (user_id, target_type, target_id, title, content)
    VALUES (:u, :t, :id, :title, :content)
");
$stmt->execute([
    "u" => $user_id,
    "t" => $target_type,
    "id" => $target_id,
    "title" => $title,
    "content" => $content
]);
$review_id = $pdo->lastInsertId();

// Rating speichern, fest mit dem Review verknüpft
$stmt = $pdo->prepare("
    INSERT INTO ratings (user_id, target_type, target_id, rating, review_id)
    VALUES (:u, :t, :id, :rating, :rid)
");
$stmt->execute([
    "u" => $user_id,
    "t" => $target_type,
    "id" => $target_id,
    "rating" => $rating,
    "rid" => $review_id
]);

$pdo->commit();

header("Location: index.php?view=reviews_list&type=$target_type&id=$target_id");
exit;
