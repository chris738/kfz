<?php
require 'auth.php';
require_login();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->prepare("INSERT INTO vehicles (marke, modell, kennzeichen, baujahr, status) VALUES (?, ?, ?, ?, ?)")
        ->execute([
            $_POST['marke'],
            $_POST['modell'],
            $_POST['kennzeichen'],
            $_POST['baujahr'],
            $_POST['status']
        ]);
    header("Location: vehicles.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fahrzeug hinzufügen</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="container">
    <h2>Fahrzeug hinzufügen</h2>
    <form method="post">
        <div class="mb-3"><input name="marke" class="form-control" placeholder="Marke" required></div>
        <div class="mb-3"><input name="modell" class="form-control" placeholder="Modell" required></div>
        <div class="mb-3"><input name="kennzeichen" class="form-control" placeholder="Kennzeichen" required></div>
        <div class="mb-3"><input name="baujahr" type="number" class="form-control" placeholder="Baujahr" required></div>
        <div class="mb-3">
            <select name="status" class="form-control">
                <option value="verfügbar">Verfügbar</option>
                <option value="in Benutzung">In Benutzung</option>
                <option value="wartung">Wartung</option>
            </select>
        </div>
        <button class="btn btn-success">Speichern</button>
    </form>
    <a href="vehicles.php" class="btn btn-secondary mt-2">Zurück</a>
</body>
</html>