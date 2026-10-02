<?php

$error = "";

// Wenn Formular abgeschickt wurde
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");

    $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = :u LIMIT 1");
    $stmt->execute(["u" => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password_hash"])) {

        // Login erfolgreich
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];

        header("Location: index.php");
        exit;

    } else {
        $error = "Benutzername oder Passwort ist falsch.";
    }
}

page_start("Anmelden", "", "narrow");
?>

<div class="panel">
    <div class="panel-header">
        <span class="eyebrow">Willkommen zurück</span>
        <h1>Anmelden</h1>
        <p>Melde dich an, um Bewertungen zu schreiben und neue Orte einzutragen.</p>
    </div>

    <?php if (isset($_GET["reset"])): ?>
        <div class="alert alert-success">Dein Passwort wurde geändert. Du kannst dich jetzt anmelden.</div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="field">
            <label for="username">Benutzername</label>
            <input type="text" id="username" name="username" autocomplete="username" required
                   value="<?= htmlspecialchars($username ?? "") ?>" />
        </div>

        <div class="field">
            <label for="password">Passwort</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required />
        </div>

        <button type="submit" class="btn btn-block">Anmelden</button>
    </form>

    <div class="form-footer">
        <a href="index.php" class="btn btn-ghost">← Zur Startseite</a>
        <a href="index.php?view=password_forgot">Passwort vergessen?</a>
    </div>
</div>

<?php page_end(); ?>
