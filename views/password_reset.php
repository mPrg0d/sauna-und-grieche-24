<?php
/**
 * Neues Passwort über den Link aus der "Passwort vergessen"-E-Mail setzen
 */

const PASSWORD_MIN_LENGTH = 8;

$token = (string)($_GET["token"] ?? "");
$error = "";

// Token prüfen: existiert, nicht abgelaufen, noch nicht benutzt
$reset = null;
if (preg_match('/^[0-9a-f]{64}$/', $token)) {
    $stmt = $pdo->prepare("
        SELECT pr.id, pr.user_id, u.username
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = :h AND pr.used_at IS NULL AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute(["h" => hash("sha256", $token)]);
    $reset = $stmt->fetch() ?: null;
}

if ($reset && $_SERVER["REQUEST_METHOD"] === "POST") {

    $new    = (string)($_POST["new_password"] ?? "");
    $repeat = (string)($_POST["new_password_repeat"] ?? "");

    if (mb_strlen($new) < PASSWORD_MIN_LENGTH) {
        $error = "Das Passwort muss mindestens " . PASSWORD_MIN_LENGTH . " Zeichen lang sein.";
    } elseif ($new !== $repeat) {
        $error = "Die Passwörter stimmen nicht überein.";
    } else {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE users SET password_hash = :pw WHERE id = :id");
        $stmt->execute(["pw" => password_hash($new, PASSWORD_DEFAULT), "id" => $reset["user_id"]]);

        // Diesen und alle anderen offenen Links des Benutzers entwerten
        $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = :id AND used_at IS NULL");
        $stmt->execute(["id" => $reset["user_id"]]);

        $pdo->commit();

        header("Location: index.php?view=login&reset=1");
        exit;
    }
}

// Token in der URL nicht per Referrer an Fremdseiten (z. B. Google Fonts) weitergeben
page_start("Neues Passwort", '<meta name="referrer" content="no-referrer">', "narrow");
?>

<div class="panel">
    <div class="panel-header">
        <span class="eyebrow">Zugang wiederherstellen</span>
        <h1>Neues Passwort festlegen</h1>
        <?php if ($reset): ?>
            <p>Konto: <strong><?= htmlspecialchars($reset["username"]) ?></strong></p>
        <?php endif; ?>
    </div>

    <?php if (!$reset): ?>
        <div class="alert alert-error">Dieser Link ist ungültig, abgelaufen oder wurde bereits verwendet.</div>
        <a class="btn" href="index.php?view=password_forgot">Neuen Link anfordern</a>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php?view=password_reset&token=<?= htmlspecialchars($token) ?>">
            <div class="field">
                <label for="new_password">Neues Passwort</label>
                <input type="password" id="new_password" name="new_password"
                       minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required />
                <span class="field-hint">Mindestens <?= PASSWORD_MIN_LENGTH ?> Zeichen.</span>
            </div>
            <div class="field">
                <label for="new_password_repeat">Neues Passwort wiederholen</label>
                <input type="password" id="new_password_repeat" name="new_password_repeat"
                       minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required />
            </div>
            <button type="submit" class="btn btn-block">Passwort speichern</button>
        </form>
    <?php endif; ?>

    <div class="form-footer">
        <a href="index.php?view=login" class="btn btn-ghost">← Zurück zum Login</a>
    </div>
</div>

<?php page_end(); ?>
