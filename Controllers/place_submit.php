<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if (!is_author()) {
    die("Nur Autoren dürfen neue Orte hinzufügen.");
}

$type = $_POST["type"] ?? null;

if (!is_string($type) || !array_key_exists($type, PLACE_TABLES)) {
    die("Ungültiger Ortstyp.");
}

$table = PLACE_TABLES[$type];
$name  = trim((string)($_POST["name"] ?? ""));
$city  = trim((string)($_POST["city"] ?? ""));
$lat   = filter_var($_POST["lat"] ?? "", FILTER_VALIDATE_FLOAT);
$lng   = filter_var($_POST["lng"] ?? "", FILTER_VALIDATE_FLOAT);

// Bei Fehlern zurück zum Formular, Eingaben bleiben erhalten
function back_with_error(string $type, string $message): void
{
    $_SESSION["form_error"] = $message;
    $_SESSION["form_old"] = [
        "name" => $_POST["name"] ?? "",
        "city" => $_POST["city"] ?? "",
        "lat"  => $_POST["lat"] ?? "",
        "lng"  => $_POST["lng"] ?? "",
    ];
    header("Location: index.php?view=place_form&type=$type");
    exit;
}

if ($name === "" || mb_strlen($name) > 255) {
    back_with_error($type, "Bitte gib einen Namen an (max. 255 Zeichen).");
}

if ($city === "" || mb_strlen($city) > 255) {
    back_with_error($type, "Bitte gib einen Ort an (max. 255 Zeichen).");
}

if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    back_with_error($type, "Bitte wähle den Standort auf der Karte aus.");
}

// Doppelte Einträge vermeiden
$stmt = $pdo->prepare("SELECT id FROM $table WHERE name = :name AND city = :city LIMIT 1");
$stmt->execute(["name" => $name, "city" => $city]);

if ($stmt->fetch()) {
    back_with_error($type, "„{$name}“ in {$city} gibt es bereits.");
}

$stmt = $pdo->prepare("
    INSERT INTO $table (name, city, lat, lng, created_by)
    VALUES (:name, :city, :lat, :lng, :u)
");
$stmt->execute([
    "name" => $name,
    "city" => $city,
    "lat"  => round($lat, 6),
    "lng"  => round($lng, 6),
    "u"    => $_SESSION["user_id"],
]);

flash_set(($type === "sauna" ? "Sauna" : "Restaurant") . " „{$name}“ wurde hinzugefügt.");

header("Location: index.php" . section_anchor($type));
exit;
