<?php

// Nur eingeloggt
if (!isset($_SESSION["user_id"])) {
    die("Bitte melde dich an, um die Einstellungen zu öffnen.");
}

$username = $_SESSION["username"];

page_start("Einstellungen", "", "narrow");
?>

<a href="index.php" class="btn btn-ghost back-link">← Zurück</a>

<div class="panel">
    <div class="panel-header">
        <span class="eyebrow">Dein Konto</span>
        <h1>Einstellungen</h1>
        <p>Angemeldet als <strong><?= htmlspecialchars($username) ?></strong></p>
    </div>

    <?php if (isset($_GET["success"])): ?>
        <div class="alert alert-success">Dein Passwort wurde geändert.</div>
    <?php endif; ?>

    <?php if (isset($_GET["error"]) && is_string($_GET["error"])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_GET["error"]) ?></div>
    <?php endif; ?>

    <h2>Passwort ändern</h2>

    <form action="password_update.php" method="POST">
        <div class="field">
            <label for="current_password">Aktuelles Passwort</label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
        </div>

        <div class="field">
            <label for="new_password">Neues Passwort</label>
            <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
            <span class="field-hint">Mindestens 8 Zeichen.</span>
        </div>

        <div class="field">
            <label for="new_password_repeat">Neues Passwort wiederholen</label>
            <input type="password" id="new_password_repeat" name="new_password_repeat" minlength="8" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn">Passwort ändern</button>
    </form>
</div>

<?php page_end(); ?>
