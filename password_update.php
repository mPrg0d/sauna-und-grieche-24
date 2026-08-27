<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION["user_id"])) {
    die("Not logged in.");
}

$stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE id = :id LIMIT 1");
$stmt->execute(["id" => $user_id]);
var_dump($stmt->fetch());
exit;

$user_id = $_SESSION["user_id"];

$current = $_POST["current_password"] ?? "";
$new = $_POST["new_password"] ?? "";
$repeat = $_POST["new_password_repeat"] ?? "";


// Validierung
if ($new !== $repeat) {
    header("Location: user_settings.php?error=Passwords do not match");
    exit;
}

// Aktuelles Passwort prüfen
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
$stmt->execute(["id" => $user_id]);
$user = $stmt->fetch();

if (!$user || !password_verify($current, $user["password_hash"])) {
    header("Location: user_settings.php?error=Current password incorrect");
    exit;
}

// Neues Passwort speichern
$new_hash = password_hash($new, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password_hash = :pw WHERE id = :id");
$stmt->execute(["pw" => $new_hash, "id" => $user_id]);

header("Location: user_settings.php?success=1");
exit;
