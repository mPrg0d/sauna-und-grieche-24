<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "author") {
    die("Only authors may write reviews.");
}

$user_id     = $_SESSION["user_id"];
$target_type = $_POST["target_type"];
$target_id   = $_POST["target_id"];
$title       = trim($_POST["title"]);
$content     = trim($_POST["content"]);
$rating      = intval($_POST["rating"]);

if (!$target_type || !$target_id || !$title || !$content || $rating < 1 || $rating > 5) {
    die("Invalid review data.");
}

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

// Rating speichern
$stmt = $pdo->prepare("
    INSERT INTO ratings (user_id, target_type, target_id, rating)
    VALUES (:u, :t, :id, :rating)
");
$stmt->execute([
    "u" => $user_id,
    "t" => $target_type,
    "id" => $target_id,
    "rating" => $rating
]);

header("Location: index.php?view=reviews_list&type=$target_type&id=$target_id");
exit;
