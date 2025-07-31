<?php
require 'auth.php';
require_login();
require 'db.php';

// Fahrzeuge laden
$stmt = $db->query("SELECT * FROM vehicles ORDER BY id DESC");
$vehicles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fahrzeuge verwalten</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="container">
    <h2>Fahrzeuge</h2>
    <a href="add_vehicle.php" class="btn btn-success mb-2">Fahrzeug hinzufügen</a>
    <a href="index.php" class="btn btn-secondary mb-2">Zurück zum Dashboard</a>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th><th>Marke</th><th>Modell</th><th>Kennzeichen</th><th>Baujahr</th><th>Status</th><th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vehicles as $v): ?>
            <tr>
                <td><?= $v['id'] ?></td>
                <td><?= $v['marke'] ?></td>
                <td><?= $v['modell'] ?></td>
                <td><?= $v['kennzeichen'] ?></td>
                <td><?= $v['baujahr'] ?></td>
                <td><?= $v['status'] ?></td>
                <td>
                    <a href="edit_vehicle.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-warning">Bearbeiten</a>
                    <a href="vehicles.php?delete=<?= $v['id'] ?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Wirklich löschen?');">Löschen</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    if (isset($_GET['delete'])) {
        $db->prepare("DELETE FROM vehicles WHERE id = ?")->execute([$_GET['delete']]);
        header("Location: vehicles.php");
        exit();
    }
    ?>
</body>
</html>