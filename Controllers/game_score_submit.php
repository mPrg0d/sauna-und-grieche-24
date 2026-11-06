<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Bitte melde dich an, um zu spielen."]);
    exit;
}

$timeMs = filter_var($_POST["time_ms"] ?? "", FILTER_VALIDATE_INT);

// shortcut: Zeit wird im Browser gemessen und lässt sich fälschen; Untergrenze < schnellster möglicher Lauf
// (5 Aufgüsse à 3,3 s + Bestellung vorlesen ≈ 21 s).
// Bei Schummel-Problemen serverseitig Start/Ende per Session-Zeitstempel prüfen.
if ($timeMs === false || $timeMs < 20000 || $timeMs > 3600000) {
    http_response_code(400);
    echo json_encode(["error" => "Ungültige Zeit."]);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO game_scores (user_id, time_ms, version) VALUES (:u, :t, :v)");
$stmt->execute(["u" => $_SESSION["user_id"], "t" => $timeMs, "v" => GAME_VERSION]);

echo json_encode(["highscores" => load_highscores($pdo)]);
exit;
