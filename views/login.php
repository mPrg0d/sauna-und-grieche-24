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
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Login</title>
<style>
body { font-family: Arial; background: #f4f4f4; }
.login-box {
    max-width: 400px; margin: 80px auto; background: white;
    padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
input { width: 80%; padding: 10px; margin: 10px 0; }
button { padding: 10px; width: 100%; background: #444; color: white; border: none; }
.error { color: red; }
</style>
</head>
<body>

<div class="login-box">
    <h2>Login</h2>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" required />
        <input type="password" name="password" placeholder="Password" required />
        <button type="submit">Login</button>
    </form>
</div>

</body>
</html>
