<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if (!is_author()) {
    die("Nur Autoren dürfen Kombis anlegen.");
}

$sauna_id      = (int)($_POST["sauna_id"] ?? 0);
$restaurant_id = (int)($_POST["restaurant_id"] ?? 0);

if (!load_target($pdo, "sauna", $sauna_id) || !load_target($pdo, "restaurant", $restaurant_id)) {
    $_SESSION["form_error"] = "Bitte wähle eine Sauna und ein Restaurant aus.";
    header("Location: index.php?view=combi_form");
    exit;
}

// Gibt es die Kombi schon? Dann direkt dorthin
$stmt = $pdo->prepare("SELECT id FROM combis WHERE sauna_id = :s AND restaurant_id = :r LIMIT 1");
$stmt->execute(["s" => $sauna_id, "r" => $restaurant_id]);
$existing = $stmt->fetch();

if ($existing) {
    flash_set("Diese Kombi gibt es schon – hier sind ihre Bewertungen.");
    header("Location: index.php?view=reviews_list&type=combi&id=" . $existing["id"]);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO combis (sauna_id, restaurant_id, created_by)
    VALUES (:s, :r, :u)
");
$stmt->execute([
    "s" => $sauna_id,
    "r" => $restaurant_id,
    "u" => $_SESSION["user_id"],
]);

flash_set("Kombi angelegt – schreib gleich die erste Bewertung!");
header("Location: index.php?view=review_form&type=combi&id=" . $pdo->lastInsertId());
exit;
