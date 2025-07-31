<?php
require 'db.php';
require 'auth.php';

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([':username' => $_POST['username']]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user'] = $user['username'];
        header('Location: index.php');
        exit();
    } else {
        $error = "Login fehlgeschlagen!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login KFZ Verwaltung</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="container">
    <h2>Login</h2>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <form method="post">
        <div class="mb-3"><input name="username" class="form-control" placeholder="Benutzername" required></div>
        <div class="mb-3"><input name="password" type="password" class="form-control" placeholder="Passwort" required></div>
        <button class="btn btn-primary">Login</button>
    </form>
</body>
</html>