<?php
require 'auth.php';
require_login();
require 'db.php';

// Get vehicle ID from URL
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: vehicles.php");
    exit();
}

// Get current vehicle data
$stmt = $db->prepare("SELECT * FROM vehicles WHERE id = ?");
$stmt->execute([$id]);
$vehicle = $stmt->fetch();

if (!$vehicle) {
    header("Location: vehicles.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->prepare("UPDATE vehicles SET marke = ?, modell = ?, kennzeichen = ?, baujahr = ?, status = ? WHERE id = ?")
        ->execute([
            $_POST['marke'],
            $_POST['modell'],
            $_POST['kennzeichen'],
            $_POST['baujahr'],
            $_POST['status'],
            $id
        ]);
    header("Location: vehicles.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fahrzeug bearbeiten</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="container">
    <h2>Fahrzeug bearbeiten</h2>
    <form method="post">
        <div class="mb-3">
            <input name="marke" class="form-control" placeholder="Marke" value="<?= htmlspecialchars($vehicle['marke']) ?>" required>
        </div>
        <div class="mb-3">
            <input name="modell" class="form-control" placeholder="Modell" value="<?= htmlspecialchars($vehicle['modell']) ?>" required>
        </div>
        <div class="mb-3">
            <input name="kennzeichen" class="form-control" placeholder="Kennzeichen" value="<?= htmlspecialchars($vehicle['kennzeichen']) ?>" required>
        </div>
        <div class="mb-3">
            <input name="baujahr" type="number" class="form-control" placeholder="Baujahr" value="<?= htmlspecialchars($vehicle['baujahr']) ?>" required>
        </div>
        <div class="mb-3">
            <select name="status" class="form-control">
                <option value="verfügbar" <?= $vehicle['status'] === 'verfügbar' ? 'selected' : '' ?>>Verfügbar</option>
                <option value="in Benutzung" <?= $vehicle['status'] === 'in Benutzung' ? 'selected' : '' ?>>In Benutzung</option>
                <option value="wartung" <?= $vehicle['status'] === 'wartung' ? 'selected' : '' ?>>Wartung</option>
            </select>
        </div>
        <button class="btn btn-success">Speichern</button>
    </form>
    <a href="vehicles.php" class="btn btn-secondary mt-2">Zurück</a>
</body>
</html>