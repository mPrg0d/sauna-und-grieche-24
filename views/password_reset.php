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
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Neues Passwort festlegen</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: Arial; background: #f4f4f4; }
.login-box {
    max-width: 400px; margin: 80px auto; background: white;
    padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
input {  padding: 10px;
    width: 100%;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 6px;
    box-sizing: border-box;
}
button { padding: 10px; width: 100%; background: #444; color: white; border: none; }
.error { color: red; }
a { color: #444; }
</style>
</head>
<body>

<div class="login-box">
    <h2>Neues Passwort festlegen</h2>

    <?php if (!$reset): ?>
        <p class="error">Dieser Link ist ungültig, abgelaufen oder wurde bereits verwendet.</p>
        <p><a href="index.php?view=password_forgot">Neuen Link anfordern</a></p>
    <?php else: ?>
        <p>Konto: <strong><?= htmlspecialchars($reset["username"]) ?></strong></p>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="index.php?view=password_reset&token=<?= htmlspecialchars($token) ?>">
            <input type="password" name="new_password" placeholder="Neues Passwort (mind. <?= PASSWORD_MIN_LENGTH ?> Zeichen)"
                   minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required />
            <input type="password" name="new_password_repeat" placeholder="Neues Passwort wiederholen"
                   minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required />
            <button type="submit">Passwort speichern</button>
        </form>
    <?php endif; ?>

    <p><a href="index.php?view=login">← Zurück zum Login</a></p>
</div>

</body>
</html>
