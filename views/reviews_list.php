<?php
require_once __DIR__ . '/../helpers.php';

$target_type = $_GET["type"] ?? null;
$target_id   = (int)($_GET["id"] ?? 0);

if (!in_array($target_type, TARGET_TYPES, true)) {
    die("Ungültiges Bewertungsziel.");
}

$target = load_target($pdo, $target_type, $target_id);

if (!$target) {
    die("Bewertungsziel nicht gefunden.");
}

$stats = load_rating_stats($pdo);
$flash = flash_take();

// Verknüpfte Kombis: bei Sauna/Restaurant alle Kombis, in denen der Ort vorkommt
$related_combis = $target_type === "combi" ? [] : load_combis($pdo, $target_type, $target_id);

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
<title>Bewertungen – <?= htmlspecialchars($target["name"]) ?></title>
<style>
body { font-family: Arial; background: #f4f4f4; }
.box { max-width: 800px; margin: 40px auto; }
.review, .info {
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
.btn-secondary { background: #555; }
.flash { background: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 6px; }
.combi-parts { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.combi-parts > div { background: #f8f8f8; padding: 10px; border-radius: 8px; }
.combi-list { padding-left: 20px; }
.combi-list li { margin-bottom: 6px; }
.muted { color: #666; }
</style>
</head>
<body>

<div class="box">
<a href="index.php<?= section_anchor($target_type) ?>" class="btn">← Zurück</a>

    <?php if ($flash): ?>
        <p class="flash"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <h2><?= htmlspecialchars($target["name"]) ?></h2>

    <div class="info">
        <?php if ($target_type === "combi"): ?>
            <p><strong>Kombi-Bewertung:</strong> <?= format_rating($stats["combi"][$target_id] ?? null) ?></p>
            <p><strong>Weg zwischen Sauna und Restaurant:</strong> <?= format_distance($target["distance_km"]) ?> (Luftlinie)</p>

            <div class="combi-parts">
                <div>
                    <p>🧖 <strong>Sauna</strong></p>
                    <p>
                        <a href="index.php?view=reviews_list&type=sauna&id=<?= $target["sauna_id"] ?>">
                            <?= htmlspecialchars($target["sauna_name"]) ?>
                        </a>
                        <br><span class="muted"><?= htmlspecialchars($target["sauna_city"]) ?></span>
                    </p>
                    <p><?= format_rating($stats["sauna"][(int)$target["sauna_id"]] ?? null) ?></p>
                </div>
                <div>
                    <p>🍽️ <strong>Restaurant</strong></p>
                    <p>
                        <a href="index.php?view=reviews_list&type=restaurant&id=<?= $target["restaurant_id"] ?>">
                            <?= htmlspecialchars($target["rest_name"]) ?>
                        </a>
                        <br><span class="muted"><?= htmlspecialchars($target["rest_city"]) ?></span>
                    </p>
                    <p><?= format_rating($stats["restaurant"][(int)$target["restaurant_id"]] ?? null) ?></p>
                </div>
            </div>
        <?php else: ?>
            <p><strong>Ort:</strong> <?= htmlspecialchars($target["city"]) ?></p>
            <p><strong>Bewertung:</strong> <?= format_rating($stats[$target_type][$target_id] ?? null) ?></p>

            <h3>Kombi-Erlebnisse mit <?= $target_type === "sauna" ? "dieser Sauna" : "diesem Restaurant" ?></h3>
            <?php if ($related_combis): ?>
                <ul class="combi-list">
                    <?php foreach ($related_combis as $c): ?>
                        <li>
                            <a href="index.php?view=reviews_list&type=combi&id=<?= $c["id"] ?>">
                                <?= htmlspecialchars($target_type === "sauna" ? $c["rest_name"] : $c["sauna_name"]) ?>
                            </a>
                            <span class="muted">
                                · <?= format_distance($c["distance_km"]) ?>
                                · <?= format_rating($stats["combi"][(int)$c["id"]] ?? null) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted">Noch keine Kombi mit diesem Ort.</p>
            <?php endif; ?>

            <?php if (is_author()): ?>
                <a class="btn btn-secondary"
                   href="index.php?view=combi_form&<?= $target_type === "sauna" ? "sauna_id" : "restaurant_id" ?>=<?= $target_id ?>">
                    + Kombi anlegen
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <h2>Bewertungen</h2>

    <?php if (is_author()): ?>
        <p>
            <a class="btn" href="index.php?view=review_form&type=<?= $target_type ?>&id=<?= $target_id ?>">
                Bewertung schreiben
            </a>
        </p>
    <?php endif; ?>

    <?php if (!$reviews): ?>
        <p class="muted">Noch keine Bewertungen vorhanden.</p>
    <?php endif; ?>

    <?php foreach ($reviews as $r): ?>
        <div class="review">
            <h3><?= htmlspecialchars($r["title"]) ?></h3>
            <p><strong>Von:</strong> <?= htmlspecialchars($r["username"]) ?></p>

            <p class="stars">
                <?php
                    echo str_repeat("★", intval($r["rating"]));
                    echo str_repeat("☆", RATING_MAX - intval($r["rating"]));
                ?>
            </p>
            <p><?= nl2br(htmlspecialchars($r["content"])) ?></p>
            <small><?= date("d.m.Y, H:i", strtotime($r["created_at"])) ?> Uhr</small>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
