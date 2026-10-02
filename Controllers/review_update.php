<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if (!is_author()) {
    die("Nur Autoren dürfen Bewertungen bearbeiten.");
}

$user_id   = $_SESSION["user_id"];
$review_id = (int)($_POST["review_id"] ?? 0);
$title     = trim((string)($_POST["title"] ?? ""));
$content   = trim((string)($_POST["content"] ?? ""));
$rating    = intval($_POST["rating"] ?? 0);

// Nur die eigene Bewertung darf geändert werden
$review = load_own_review($pdo, $review_id, $user_id);

if (!$review) {
    die("Bewertung nicht gefunden oder nicht deine eigene.");
}

if ($title === "" || $content === "" || $rating < 1 || $rating > RATING_MAX) {
    die("Ungültige Bewertungsdaten.");
}

$pdo->beginTransaction();

$stmt = $pdo->prepare("
    UPDATE reviews
    SET title = :title, content = :content, updated_at = NOW()
    WHERE id = :id AND user_id = :u
");
$stmt->execute([
    "title"   => $title,
    "content" => $content,
    "id"      => $review_id,
    "u"       => $user_id,
]);

if ($review["rating_id"]) {
    $stmt = $pdo->prepare("UPDATE ratings SET rating = :rating WHERE id = :id");
    $stmt->execute(["rating" => $rating, "id" => $review["rating_id"]]);
} else {
    // Bewertung ohne verknüpfte Sterne (Altdaten) → Sterne neu anlegen
    $stmt = $pdo->prepare("
        INSERT INTO ratings (user_id, target_type, target_id, rating, review_id)
        VALUES (:u, :t, :tid, :rating, :rid)
    ");
    $stmt->execute([
        "u"      => $user_id,
        "t"      => $review["target_type"],
        "tid"    => $review["target_id"],
        "rating" => $rating,
        "rid"    => $review_id,
    ]);
}

$pdo->commit();

flash_set("Deine Bewertung wurde aktualisiert.");
header("Location: index.php?view=reviews_list&type=" . $review["target_type"] . "&id=" . $review["target_id"]);
exit;
