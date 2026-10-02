<?php
/**
 * Passwort vergessen: Reset-Link per E-Mail anfordern
 *
 * Optional in private/config.php:
 *  - 'base_url'  => 'https://example.de/SaunaUndGrieche24'  (sonst aus dem Request ermittelt)
 *  - 'mail_from' => 'noreply@example.de'
 */

const RESET_VALID_MINUTES = 60;
const RESET_COOLDOWN_MINUTES = 5;

$sent = false;
$error = "";
$email = "";

/**
 * Absolute Adresse von index.php für den Link in der E-Mail
 */
function reset_base_url(array $config): string
{
    if (!empty($config["base_url"])) {
        return rtrim($config["base_url"], "/") . "/index.php";
    }

    $https  = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";
    $scheme = $https ? "https" : "http";
    $dir    = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
    return $scheme . "://" . $_SERVER["HTTP_HOST"] . $dir . "/index.php";
}

function send_reset_mail(array $config, string $to, string $username, string $link): bool
{
    $from = $config["mail_from"] ?? ("noreply@" . preg_replace('/:\d+$/', '', $_SERVER["HTTP_HOST"]));

    $subject = "=?UTF-8?B?" . base64_encode("Sauna & Grieche 24 – Passwort zurücksetzen") . "?=";

    $body = "Hallo {$username},\r\n\r\n"
          . "für dein Konto bei Sauna & Grieche 24 wurde ein neues Passwort angefordert.\r\n"
          . "Über diesen Link kannst du ein neues Passwort festlegen:\r\n\r\n"
          . $link . "\r\n\r\n"
          . "Der Link ist " . RESET_VALID_MINUTES . " Minuten gültig und kann nur einmal verwendet werden.\r\n\r\n"
          . "Falls du das nicht warst, kannst du diese E-Mail einfach ignorieren – dein Passwort bleibt unverändert.\r\n\r\n"
          . "Viele Grüße\r\n"
          . "Sauna & Grieche 24\r\n";

    $headers = "From: Sauna & Grieche 24 <{$from}>\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit\r\n";

    return mail($to, $subject, $body, $headers);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim((string)($_POST["email"] ?? ""));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Bitte gib eine gültige E-Mail-Adresse ein.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = :e");
        $stmt->execute(["e" => $email]);

        foreach ($stmt->fetchAll() as $user) {

            // Nicht bei jedem Klick eine neue Mail verschicken
            $recent = $pdo->prepare("
                SELECT COUNT(*) FROM password_resets
                WHERE user_id = :u AND created_at > NOW() - INTERVAL " . RESET_COOLDOWN_MINUTES . " MINUTE
            ");
            $recent->execute(["u" => $user["id"]]);
            if ((int)$recent->fetchColumn() > 0) {
                continue;
            }

            $token = bin2hex(random_bytes(32));

            $insert = $pdo->prepare("
                INSERT INTO password_resets (user_id, token_hash, expires_at)
                VALUES (:u, :h, NOW() + INTERVAL " . RESET_VALID_MINUTES . " MINUTE)
            ");
            $insert->execute(["u" => $user["id"], "h" => hash("sha256", $token)]);

            $link = reset_base_url($config) . "?view=password_reset&token=" . $token;

            if (!send_reset_mail($config, $user["email"], $user["username"], $link)) {
                error_log("Passwort-Reset: E-Mail an Benutzer {$user['id']} konnte nicht versendet werden.");
            }
        }

        // Immer dieselbe Antwort – verrät nicht, ob die Adresse registriert ist
        $sent = true;
    }
}

page_start("Passwort vergessen", "", "narrow");
?>

<div class="panel">
    <div class="panel-header">
        <span class="eyebrow">Zugang wiederherstellen</span>
        <h1>Passwort vergessen</h1>
        <?php if (!$sent): ?>
            <p>Gib die E-Mail-Adresse deines Kontos ein. Wir schicken dir einen Link, mit dem du ein neues Passwort festlegen kannst.</p>
        <?php endif; ?>
    </div>

    <?php if ($sent): ?>
        <div class="alert alert-success">
            Falls ein Konto mit dieser E-Mail-Adresse existiert, haben wir dir einen Link zum
            Zurücksetzen deines Passworts geschickt. Schau auch im Spam-Ordner nach.
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" autocomplete="email" required
                       value="<?= htmlspecialchars($email) ?>" />
            </div>
            <button type="submit" class="btn btn-block">Link anfordern</button>
        </form>
    <?php endif; ?>

    <div class="form-footer">
        <a href="index.php?view=login" class="btn btn-ghost">← Zurück zum Login</a>
    </div>
</div>

<?php page_end(); ?>
