<?php

$target_type = $_GET["type"] ?? null;
$target_id   = $_GET["id"] ?? null;

if (!$target_type || !$target_id) {
    die("Invalid target.");
}

// Reviews laden
$stmt = $pdo->prepare("
    SELECT r.title, r.content, r.created_at, u.username,
           (SELECT rating FROM ratings WHERE user_id = r.user_id AND target_type = r.target_type AND target_id = r.target_id LIMIT 1) AS rating
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.target_type = :t AND r.target_id = :id
    ORDER BY r.created_at DESC
");
$stmt->execute(["t" => $target_type, "id" => $target_id]);
$reviews = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Reviews</title>
<style>
body { font-family: Arial; background: #f4f4f4; }
.box { max-width: 800px; margin: 40px auto; }
.review {
    background: white; padding: 20px; margin-bottom: 20px;
    border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
.stars { color: gold; font-size: 1.4rem; }
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

<a href="index.php<?= $section ?>" class="btn">← Zurück</a>

    <h2>Reviews</h2>

    <?php foreach ($reviews as $r): ?>
        <div class="review">
            <h3><?= htmlspecialchars($r["title"]) ?></h3>
            <p><strong>By:</strong> <?= htmlspecialchars($r["username"]) ?></p>

            <p class="stars">
                <?= str_repeat("★", intval($r["rating"])) ?>
                <?= str_repeat("☆", 7 - intval($r["rating"])) ?>
            </p>

            <p><?= nl2br(htmlspecialchars($r["content"])) ?></p>
            <small><?= $r["created_at"] ?></small>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>
