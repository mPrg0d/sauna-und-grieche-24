<?php
session_start();
require_once __DIR__ . '/db.php';

const PASSWORD_MIN_LENGTH = 8;

if (!isset($_SESSION["user_id"])) {
    die("Nicht eingeloggt.");
}

function back_to_settings(string $query): void
{
    header("Location: index.php?view=user_settings&" . $query);
    exit;
}

$user_id = $_SESSION["user_id"];

$current = (string)($_POST["current_password"] ?? "");
$new     = (string)($_POST["new_password"] ?? "");
$repeat  = (string)($_POST["new_password_repeat"] ?? "");

// Validierung
if (mb_strlen($new) < PASSWORD_MIN_LENGTH) {
    back_to_settings("error=" . urlencode("Das neue Passwort muss mindestens " . PASSWORD_MIN_LENGTH . " Zeichen lang sein."));
}

if ($new !== $repeat) {
    back_to_settings("error=" . urlencode("Die neuen Passwörter stimmen nicht überein."));
}

// Aktuelles Passwort prüfen
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
$stmt->execute(["id" => $user_id]);
$user = $stmt->fetch();

if (!$user || !password_verify($current, $user["password_hash"])) {
    back_to_settings("error=" . urlencode("Das aktuelle Passwort ist falsch."));
}

// Neues Passwort speichern
$new_hash = password_hash($new, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password_hash = :pw WHERE id = :id");
$stmt->execute(["pw" => $new_hash, "id" => $user_id]);

back_to_settings("success=1");
