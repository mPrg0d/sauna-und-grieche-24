<?php
require_once __DIR__ . '/../helpers.php';

$loggedIn = isset($_SESSION["user_id"]);
$highscores = load_highscores($pdo);

$myBest = null;
if ($loggedIn) {
    $stmt = $pdo->prepare("SELECT MIN(time_ms) FROM game_scores WHERE user_id = :u");
    $stmt->execute(["u" => $_SESSION["user_id"]]);
    $myBest = $stmt->fetchColumn();
}

page_start("Minispiel", "", "narrow");
?>

<a href="index.php" class="btn btn-ghost back-link">← Zurück</a>

<div class="panel">
    <div class="panel-header">
        <span class="eyebrow">Minispiel</span>
        <h1>Erst die <em>Hitze</em>, dann die Taverne</h1>
        <p>
            Lauf in die Sauna und schwitz, bis der Balken im grünen Bereich ist – aber nicht zu lange!
            Danach raus und ab zum Griechen.
        </p>
    </div>

    <?php if (!$loggedIn): ?>
    <div class="alert alert-error">
        Nur angemeldete Nutzer können spielen und in die Bestenliste kommen.
        <a href="index.php?view=login">Jetzt anmelden</a>
    </div>
    <?php else: ?>
    <canvas id="game" width="720" height="420" class="game-canvas"
            aria-label="Minispiel: Sauna und Taverne" role="img"></canvas>

    <div class="form-footer">
        <span class="muted">Steuerung: Pfeiltasten / WASD oder auf die Karte tippen.</span>
        <button type="button" class="btn btn-outline btn-sm" id="restart">Neu starten</button>
    </div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-header">
        <span class="eyebrow eyebrow-taverne">Bestenliste</span>
        <h2>Die schnellsten Schwitzer</h2>
    </div>
    <ol id="highscores" class="highscores"></ol>
    <p id="highscores-empty" class="empty-state" hidden>Noch keine Zeiten – sei die oder der Erste!</p>
</div>

<style>
.game-canvas {
    display: block;
    width: 100%;
    height: auto;
    border: 1px solid var(--line-strong);
    border-radius: var(--radius);
    background: var(--surface-muted);
    touch-action: none;
    margin-bottom: 16px;
}
.highscores { margin: 0; padding: 0; list-style: none; counter-reset: rank; }
.highscores li {
    counter-increment: rank;
    display: flex; gap: 12px; align-items: baseline;
    padding: 8px 0; border-bottom: 1px solid var(--line);
}
.highscores li::before {
    content: counter(rank) ".";
    font-family: var(--font-serif); color: var(--cedar); min-width: 2ch;
}
.highscores li:first-child::before { color: var(--gold); }
.highscores .hs-name { flex: 1; font-weight: 600; }
.highscores .hs-me { color: var(--aegean); }
.highscores .hs-time { font-family: var(--font-serif); }
.highscores .hs-runs { color: var(--ink-soft); font-size: 0.88rem; }
</style>

<?php
ob_start();
?>
<script>
var HIGHSCORES = <?= json_encode($highscores, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var ME = <?= json_encode($loggedIn ? $_SESSION["username"] : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function renderHighscores(list) {
    var ol = document.getElementById('highscores');
    ol.innerHTML = '';
    document.getElementById('highscores-empty').hidden = list.length > 0;
    list.forEach(function (h) {
        var li = document.createElement('li');
        [['hs-name' + (h.username === ME ? ' hs-me' : ''), h.username],
         ['hs-time', (h.best_ms / 1000).toFixed(2).replace('.', ',') + ' s'],
         ['hs-runs', h.runs + (h.runs == 1 ? ' Lauf' : ' Läufe')]].forEach(function (c) {
            var span = document.createElement('span');
            span.className = c[0];
            span.textContent = c[1]; // textContent → Nutzernamen sicher eingefügt
            li.appendChild(span);
        });
        ol.appendChild(li);
    });
}
renderHighscores(HIGHSCORES);

(function () {
    var canvas = document.getElementById('game');
    if (!canvas) return; // nicht angemeldet
    var ctx = canvas.getContext('2d');
    var W = canvas.width, H = canvas.height;

    // Farben aus dem Seiten-Theme (style.css)
    var css = getComputedStyle(document.documentElement);
    function v(name) { return css.getPropertyValue(name).trim(); }
    var C = {
        paper: v('--paper'), surface: v('--surface'), ink: v('--ink'), inkSoft: v('--ink-soft'),
        line: v('--line'), lineStrong: v('--line-strong'),
        aegean: v('--aegean'), aegeanSoft: v('--aegean-soft'),
        cedar: v('--cedar'), cedarSoft: v('--cedar-soft'),
        olive: v('--olive'), oliveSoft: v('--olive-soft'), gold: v('--gold'), danger: v('--danger')
    };
    var SERIF = 'Fraunces, Georgia, serif';
    var SANS = '"Source Sans 3", "Segoe UI", sans-serif';

    var SAUNA = { x: 60, y: 70, w: 170, h: 130 };
    var TAVERNE = { x: 490, y: 220, w: 180, h: 140 };
    var OLIVES = [[330, 80], [390, 130], [300, 330], [130, 330], [620, 110], [450, 380]];

    var HEAT_OK = 60, HEAT_MAX = 100, HEAT_SPEED = 18; // Prozent pro Sekunde
    var SPEED = 170;                                    // Pixel pro Sekunde

    var state, keys = {}, target = null, best = <?= $myBest ? round($myBest / 1000, 2) : 0 ?>, saveMsg = '';

    function reset() {
        state = {
            phase: 'toSauna', // toSauna → inSauna → toTaverne → won | fainted
            x: 360, y: 230, heat: 0, time: 0,
            msg: 'Ab in die Sauna!', msgTime: 2.5, steam: []
        };
        target = null;
    }

    function inside(r, x, y) { return x > r.x && x < r.x + r.w && y > r.y && y < r.y + r.h; }
    function say(text) { state.msg = text; state.msgTime = 2.5; }

    function update(dt) {
        var s = state;
        if (s.phase === 'won' || s.phase === 'fainted') return;
        s.time += dt;
        s.msgTime -= dt;

        // Bewegung: Tasten haben Vorrang vor dem Tipp-Ziel
        var dx = (keys.right ? 1 : 0) - (keys.left ? 1 : 0);
        var dy = (keys.down ? 1 : 0) - (keys.up ? 1 : 0);
        if (dx || dy) {
            target = null;
        } else if (target) {
            dx = target[0] - s.x; dy = target[1] - s.y;
            if (Math.hypot(dx, dy) < 4) { target = null; dx = dy = 0; }
        }
        var len = Math.hypot(dx, dy);
        if (len) {
            s.x = Math.min(W - 12, Math.max(12, s.x + dx / len * SPEED * dt));
            s.y = Math.min(H - 12, Math.max(12, s.y + dy / len * SPEED * dt));
        }

        var inSauna = inside(SAUNA, s.x, s.y);

        if (s.phase === 'toSauna' && inSauna) {
            s.phase = 'inSauna';
            say('Aufguss! Bleib, bis der Balken grün ist.');
        } else if (s.phase === 'inSauna') {
            if (inSauna) {
                s.heat += HEAT_SPEED * dt;
                if (Math.random() < dt * 8) s.steam.push({ x: s.x + (Math.random() - 0.5) * 20, y: s.y - 10, life: 1 });
                if (s.heat >= HEAT_MAX) { s.phase = 'fainted'; s.heat = HEAT_MAX; }
            } else if (s.heat >= HEAT_OK) {
                s.phase = 'toTaverne';
                say('Durchgeschwitzt! Jetzt ab zum Griechen.');
            } else {
                // zu früh raus: Hitze verfliegt, also zurück in die Sauna
                s.phase = 'toSauna';
                s.heat = 0;
                say('Zu früh raus – noch nicht durchgeschwitzt!');
            }
        }

        if (inside(TAVERNE, s.x, s.y)) {
            if (s.phase === 'toTaverne') {
                s.phase = 'won';
                if (!best || s.time < best) best = s.time;
                saveScore(s.time);
            } else if (s.phase === 'toSauna' && s.msgTime < 1) {
                say('Erst die Hitze, dann die Taverne!');
            }
        }

        s.steam.forEach(function (p) { p.y -= 30 * dt; p.life -= dt; });
        s.steam = s.steam.filter(function (p) { return p.life > 0; });
    }

    function saveScore(seconds) {
        saveMsg = 'Zeit wird gespeichert …';
        var body = new URLSearchParams({ time_ms: Math.round(seconds * 1000) });
        fetch('index.php?action=game_score_submit', { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.error) { saveMsg = data.error; return; }
                saveMsg = 'Zeit in der Bestenliste gespeichert.';
                renderHighscores(data.highscores);
            })
            .catch(function () { saveMsg = 'Speichern fehlgeschlagen.'; });
    }

    function roundRect(x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
    }

    function text(str, x, y, font, color, align) {
        ctx.font = font; ctx.fillStyle = color; ctx.textAlign = align || 'center';
        ctx.fillText(str, x, y);
    }

    function drawSauna() {
        var r = SAUNA;
        ctx.fillStyle = C.cedarSoft; roundRect(r.x, r.y, r.w, r.h, 6); ctx.fill();
        ctx.strokeStyle = C.cedar; ctx.lineWidth = 2; ctx.stroke();
        // Holzbretter
        ctx.strokeStyle = 'rgba(169, 80, 43, 0.25)'; ctx.lineWidth = 1;
        for (var y = r.y + 18; y < r.y + r.h; y += 18) {
            ctx.beginPath(); ctx.moveTo(r.x + 4, y); ctx.lineTo(r.x + r.w - 4, y); ctx.stroke();
        }
        // Dach
        ctx.fillStyle = C.cedar;
        ctx.beginPath(); ctx.moveTo(r.x - 10, r.y + 4); ctx.lineTo(r.x + r.w / 2, r.y - 34); ctx.lineTo(r.x + r.w + 10, r.y + 4); ctx.fill();
        text('SAUNA', r.x + r.w / 2, r.y + r.h + 22, '600 13px ' + SANS, C.cedar);
    }

    function drawTaverne() {
        var r = TAVERNE;
        ctx.fillStyle = C.surface; roundRect(r.x, r.y, r.w, r.h, 6); ctx.fill();
        ctx.strokeStyle = C.aegean; ctx.lineWidth = 2; ctx.stroke();
        // gestreifte Markise in Blau-Weiß
        var stripes = 9, sw = (r.w + 16) / stripes;
        for (var i = 0; i < stripes; i++) {
            ctx.fillStyle = i % 2 ? C.surface : C.aegean;
            ctx.fillRect(r.x - 8 + i * sw, r.y - 22, sw, 22);
        }
        ctx.strokeStyle = C.aegean; ctx.strokeRect(r.x - 8, r.y - 22, r.w + 16, 22);
        // Tische
        ctx.fillStyle = C.aegeanSoft; ctx.strokeStyle = C.aegean; ctx.lineWidth = 1.5;
        [[r.x + 45, r.y + 50], [r.x + 135, r.y + 50], [r.x + 90, r.y + 100]].forEach(function (t) {
            ctx.beginPath(); ctx.arc(t[0], t[1], 16, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
        });
        text('TAVERNE', r.x + r.w / 2, r.y + r.h + 22, '600 13px ' + SANS, C.aegean);
    }

    function drawPlayer() {
        var s = state;
        var t = s.heat / HEAT_MAX; // je heißer, desto röter
        ctx.fillStyle = 'rgba(30, 42, 50, 0.12)';
        ctx.beginPath(); ctx.ellipse(s.x, s.y + 12, 11, 4, 0, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = s.phase === 'toTaverne' || s.phase === 'won' ? C.cedar : (t > 0 ? mix(C.ink, C.danger, t) : C.ink);
        ctx.beginPath(); ctx.arc(s.x, s.y, 11, 0, Math.PI * 2); ctx.fill();
        // Handtuch
        ctx.fillStyle = C.surface; ctx.fillRect(s.x - 11, s.y + 2, 22, 5);
        s.steam.forEach(function (p) {
            ctx.fillStyle = 'rgba(255, 252, 246,' + (p.life * 0.8) + ')';
            ctx.beginPath(); ctx.arc(p.x, p.y, 4 + (1 - p.life) * 4, 0, Math.PI * 2); ctx.fill();
        });
    }

    function mix(a, b, t) {
        var pa = parseInt(a.slice(1), 16), pb = parseInt(b.slice(1), 16);
        var ch = function (sh) { return Math.round(((pa >> sh) & 255) * (1 - t) + ((pb >> sh) & 255) * t); };
        return 'rgb(' + ch(16) + ',' + ch(8) + ',' + ch(0) + ')';
    }

    function drawHeatBar() {
        var x = 20, y = H - 36, w = 200, h = 14;
        text('Hitze', x, y - 6, '600 12px ' + SANS, C.inkSoft, 'left');
        ctx.fillStyle = C.surface; ctx.fillRect(x, y, w, h);
        ctx.fillStyle = C.oliveSoft; ctx.fillRect(x + w * HEAT_OK / 100, y, w * (90 - HEAT_OK) / 100, h);
        ctx.fillStyle = state.heat >= 90 ? C.danger : state.heat >= HEAT_OK ? C.olive : C.cedar;
        ctx.fillRect(x, y, w * state.heat / 100, h);
        ctx.strokeStyle = C.lineStrong; ctx.lineWidth = 1; ctx.strokeRect(x, y, w, h);
    }

    function overlay(title, sub) {
        ctx.fillStyle = 'rgba(244, 239, 229, 0.92)'; ctx.fillRect(0, 0, W, H);
        text(title, W / 2, H / 2 - 10, '500 36px ' + SERIF, C.ink);
        text(sub, W / 2, H / 2 + 24, '16px ' + SANS, C.inkSoft);
        if (state.phase === 'won') text(saveMsg, W / 2, H / 2 + 50, 'italic 14px ' + SERIF, C.olive);
        text('Tippen oder Leertaste für eine neue Runde', W / 2, H / 2 + 80, '600 13px ' + SANS, C.aegean);
    }

    function draw() {
        var s = state;
        ctx.fillStyle = C.paper; ctx.fillRect(0, 0, W, H);

        // gestrichelter Weg von der Sauna zur Taverne
        ctx.strokeStyle = C.lineStrong; ctx.lineWidth = 3; ctx.setLineDash([8, 6]);
        ctx.beginPath(); ctx.moveTo(SAUNA.x + SAUNA.w / 2, SAUNA.y + SAUNA.h);
        ctx.quadraticCurveTo(360, 380, TAVERNE.x, TAVERNE.y + TAVERNE.h / 2); ctx.stroke();
        ctx.setLineDash([]);

        OLIVES.forEach(function (o) {
            ctx.fillStyle = C.oliveSoft; ctx.beginPath(); ctx.arc(o[0], o[1], 18, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = C.olive; ctx.beginPath(); ctx.arc(o[0] - 5, o[1] - 3, 3, 0, Math.PI * 2); ctx.arc(o[0] + 6, o[1] + 4, 3, 0, Math.PI * 2); ctx.fill();
        });

        drawSauna();
        drawTaverne();
        drawPlayer();

        // Kopfzeile
        var goal = { toSauna: 'Ziel: Sauna', inSauna: 'Schwitzen …', toTaverne: 'Ziel: Taverne' }[s.phase] || '';
        text(goal, 20, 30, '500 20px ' + SERIF, s.phase === 'toTaverne' ? C.aegean : C.cedar, 'left');
        text(s.time.toFixed(1) + ' s' + (best ? '   ·   Bestzeit ' + best.toFixed(1) + ' s' : ''), W - 20, 30, '600 13px ' + SANS, C.inkSoft, 'right');
        if (s.msgTime > 0) text(s.msg, W / 2, H - 20, 'italic 17px ' + SERIF, C.ink);
        if (s.phase === 'inSauna' || s.phase === 'toSauna') drawHeatBar();

        if (s.phase === 'won') overlay('Kalí órexi!', 'Gyros nach dem Aufguss – geschafft in ' + s.time.toFixed(1) + ' s.');
        if (s.phase === 'fainted') overlay('Zu heiß!', 'Du bist zu lange in der Sauna geblieben. Raus, wenn der Balken grün ist.');
    }

    var last = performance.now();
    function loop(now) {
        update(Math.min((now - last) / 1000, 0.05));
        last = now;
        draw();
        requestAnimationFrame(loop);
    }

    var KEYMAP = { ArrowUp: 'up', w: 'up', ArrowDown: 'down', s: 'down', ArrowLeft: 'left', a: 'left', ArrowRight: 'right', d: 'right' };
    function over() { return state.phase === 'won' || state.phase === 'fainted'; }

    document.addEventListener('keydown', function (e) {
        var k = KEYMAP[e.key.length === 1 ? e.key.toLowerCase() : e.key];
        if (k) { keys[k] = true; e.preventDefault(); }
        if (e.key === ' ' && over()) { reset(); e.preventDefault(); }
    });
    document.addEventListener('keyup', function (e) {
        var k = KEYMAP[e.key.length === 1 ? e.key.toLowerCase() : e.key];
        if (k) keys[k] = false;
    });
    canvas.addEventListener('pointerdown', function (e) {
        if (over()) { reset(); return; }
        var r = canvas.getBoundingClientRect();
        target = [(e.clientX - r.left) * W / r.width, (e.clientY - r.top) * H / r.height];
    });
    document.getElementById('restart').addEventListener('click', reset);

    reset();
    requestAnimationFrame(loop);
})();
</script>
<?php
page_end(ob_get_clean());
