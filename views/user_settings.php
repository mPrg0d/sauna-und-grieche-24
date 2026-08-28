<?php

// Nur eingeloggt
if (!isset($_SESSION["user_id"])) {
    die("You must be logged in to access settings.");
}

$username = $_SESSION["username"];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>User Settings</title>
<style>
body { font-family: Arial; background: #f4f4f4; }
.box {
    max-width: 450px; margin: 60px auto; background: white;
    padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
input {
    padding: 10px;
    width: 100%;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 6px;
    box-sizing: border-box;
}
button {
    padding: 10px; width: 100%; background: #444;
    color: white; border: none; border-radius: 6px;
}
.success { color: green; }
.error { color: red; }
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

    <a href="index.php" class="btn">← Zurück</a>


    <h2>User Settings</h2>
    <p><strong>Logged in as:</strong> <?= htmlspecialchars($username) ?></p>

    <?php if (isset($_GET["success"])): ?>
        <p class="success">Password updated successfully.</p>
    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>
        <p class="error"><?= htmlspecialchars($_GET["error"]) ?></p>
    <?php endif; ?>

    <form action="password_update.php" method="POST">
        <label>Current Password</label>
        <input type="password" name="current_password" required>

        <label>New Password</label>
        <input type="password" name="new_password" required>

        <label>Repeat New Password</label>
        <input type="password" name="new_password_repeat" required>

        <button type="submit">Update Password</button>
    </form>
</div>

</body>
</html>
