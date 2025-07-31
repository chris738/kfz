<?php
require 'auth.php';
require_login();
require 'db.php';

// Statistiken
$total = $db->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
$verfuegbar = $db->query("SELECT COUNT(*) FROM vehicles WHERE status = 'verfügbar'")->fetchColumn();
$inBenutzung = $db->query("SELECT COUNT(*) FROM vehicles WHERE status = 'in Benutzung'")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <title>KFZ Verwaltung Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="container">
    <h1>KFZ Verwaltung Dashboard</h1>
    <a href="vehicles.php" class="btn btn-primary mb-2">Fahrzeuge verwalten</a>
    <a href="logout.php" class="btn btn-secondary mb-2">Logout</a>
    <div class="row">
        <div class="col">
            <div class="card text-bg-info mb-3">
                <div class="card-header">Fahrzeuge gesamt</div>
                <div class="card-body"><h3><?= $total ?></h3></div>
            </div>
        </div>
        <div class="col">
            <div class="card text-bg-success mb-3">
                <div class="card-header">Verfügbar</div>
                <div class="card-body"><h3><?= $verfuegbar ?></h3></div>
            </div>
        </div>
        <div class="col">
            <div class="card text-bg-warning mb-3">
                <div class="card-header">In Benutzung</div>
                <div class="card-body"><h3><?= $inBenutzung ?></h3></div>
            </div>
        </div>
    </div>
</body>
</html>