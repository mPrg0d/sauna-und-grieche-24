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

$eyebrows = [
    "sauna"      => ["Sauna", "eyebrow-sauna"],
    "restaurant" => ["Griechisches Restaurant", "eyebrow-taverne"],
    "combi"      => ["Kombi-Erlebnis", ""],
];

page_start($target["name"]);
?>

<a href="index.php<?= section_anchor($target_type) ?>" class="btn btn-ghost back-link">← Zur Übersicht</a>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="detail-header">
    <div>
        <span class="eyebrow <?= $eyebrows[$target_type][1] ?>"><?= $eyebrows[$target_type][0] ?></span>
        <h1 class="<?= $target_type === "combi" ? "title-combi" : "" ?>"><?= target_title_html($target_type, $target) ?></h1>
        <p class="muted">
            <?php if ($target_type === "combi"): ?>
                <?= format_distance($target["distance_km"]) ?> Luftlinie zwischen Sauna und Taverne
            <?php else: ?>
                <?= htmlspecialchars($target["city"]) ?>
            <?php endif; ?>
        </p>
    </div>
    <?= rating_html($stats[$target_type][$target_id] ?? null, $target_type === "combi" ? "Kombi-Bewertung" : "Bewertung") ?>
</div>

<?php if ($target_type === "combi"): ?>
    <div class="detail-grid">
        <div class="panel">
            <span class="eyebrow eyebrow-sauna">Die Sauna</span>
            <p>
                <a class="place-link" href="index.php?view=reviews_list&type=sauna&id=<?= $target["sauna_id"] ?>">
                    <?= htmlspecialchars($target["sauna_name"]) ?>
                </a>
                <br><span class="muted"><?= htmlspecialchars($target["sauna_city"]) ?></span>
            </p>
            <?= rating_html($stats["sauna"][(int)$target["sauna_id"]] ?? null) ?>
        </div>
        <div class="panel">
            <span class="eyebrow eyebrow-taverne">Die Taverne</span>
            <p>
                <a class="place-link" href="index.php?view=reviews_list&type=restaurant&id=<?= $target["restaurant_id"] ?>">
                    <?= htmlspecialchars($target["rest_name"]) ?>
                </a>
                <br><span class="muted"><?= htmlspecialchars($target["rest_city"]) ?></span>
            </p>
            <?= rating_html($stats["restaurant"][(int)$target["restaurant_id"]] ?? null) ?>
        </div>
    </div>
<?php else: ?>
    <div class="panel panel-spaced">
        <span class="eyebrow">Kombi-Erlebnisse mit <?= $target_type === "sauna" ? "dieser Sauna" : "diesem Restaurant" ?></span>

        <?php if ($related_combis): ?>
            <ul class="combi-list">
                <?php foreach ($related_combis as $c): ?>
                    <li>
                        <span>
                            <span class="ampersand ampersand-sm">&amp;</span>
                            <a class="place-link" href="index.php?view=reviews_list&type=combi&id=<?= $c["id"] ?>">
                                <?= htmlspecialchars($target_type === "sauna" ? $c["rest_name"] : $c["sauna_name"]) ?>
                            </a>
                            <span class="muted"> · <?= format_distance($c["distance_km"]) ?></span>
                        </span>
                        <span class="muted"><?= format_rating($stats["combi"][(int)$c["id"]] ?? null) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">Noch keine Kombi mit diesem Ort.</p>
        <?php endif; ?>

        <?php if (is_author()): ?>
            <a class="btn btn-outline btn-sm"
               href="index.php?view=combi_form&<?= $target_type === "sauna" ? "sauna_id" : "restaurant_id" ?>=<?= $target_id ?>">
                + Kombi anlegen
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="section-header">
    <div>
        <span class="eyebrow">Was andere sagen</span>
        <h2>Bewertungen</h2>
    </div>
    <?php if (is_author()): ?>
        <a class="btn btn-sm" href="index.php?view=review_form&type=<?= $target_type ?>&id=<?= $target_id ?>">
            Bewertung schreiben
        </a>
    <?php endif; ?>
</div>

<?php if (!$reviews): ?>
    <p class="empty-state">Noch keine Bewertungen vorhanden.</p>
<?php endif; ?>

<?php foreach ($reviews as $r): ?>
    <?php $stars = intval($r["rating"]); ?>
    <article class="review">
        <div class="review-head">
            <div>
                <h3><?= htmlspecialchars($r["title"]) ?></h3>
                <span class="review-meta">
                    von <strong><?= htmlspecialchars($r["username"]) ?></strong>
                    · <?= date("d.m.Y", strtotime($r["created_at"])) ?>
                </span>
            </div>
            <span class="stars" title="<?= $stars ?> von <?= RATING_MAX ?> Sternen">
                <?= str_repeat("★", $stars) ?><span class="off"><?= str_repeat("★", max(0, RATING_MAX - $stars)) ?></span>
            </span>
        </div>
        <p class="review-body"><?= nl2br(htmlspecialchars($r["content"])) ?></p>
    </article>
<?php endforeach; ?>

<?php page_end(); ?>
