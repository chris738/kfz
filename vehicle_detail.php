<?php
require 'auth.php';
require_login();
require 'db.php';

// Get vehicle ID from URL
$vehicle_id = $_GET['id'] ?? null;
if (!$vehicle_id) {
    header("Location: vehicles.php");
    exit();
}

// Get vehicle data
$stmt = $db->prepare("SELECT * FROM vehicles WHERE id = ?");
$stmt->execute([$vehicle_id]);
$vehicle = $stmt->fetch();

if (!$vehicle) {
    header("Location: vehicles.php");
    exit();
}

// Handle mileage form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_mileage'])) {
    $db->prepare("INSERT INTO mileage_records (vehicle_id, mileage, date_recorded, notes) VALUES (?, ?, ?, ?)")
        ->execute([
            $vehicle_id,
            $_POST['mileage'],
            $_POST['date_recorded'],
            $_POST['notes'] ?? ''
        ]);
    header("Location: vehicle_detail.php?id=" . $vehicle_id);
    exit();
}

// Get mileage records for this vehicle
$stmt = $db->prepare("SELECT * FROM mileage_records WHERE vehicle_id = ? ORDER BY date_recorded DESC");
$stmt->execute([$vehicle_id]);
$mileage_records = $stmt->fetchAll();

// Get data for chart (last 12 months)
$stmt = $db->prepare("
    SELECT date_recorded, mileage 
    FROM mileage_records 
    WHERE vehicle_id = ? AND date_recorded >= date('now', '-12 months')
    ORDER BY date_recorded ASC
");
$stmt->execute([$vehicle_id]);
$chart_data = $stmt->fetchAll();

// Get current mileage (latest record)
$current_mileage = null;
if (!empty($mileage_records)) {
    $current_mileage = $mileage_records[0]['mileage'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fahrzeug Details - <?= htmlspecialchars($vehicle['marke'] . ' ' . $vehicle['modell']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Fahrzeug Details: <?= htmlspecialchars($vehicle['marke'] . ' ' . $vehicle['modell']) ?></h2>
        <div>
            <a href="fuel_tracking.php?id=<?= $vehicle_id ?>" class="btn btn-success">Spritkosten</a>
            <a href="maintenance.php?id=<?= $vehicle_id ?>" class="btn btn-warning">Wartung</a>
            <a href="vehicles.php" class="btn btn-secondary">Zurück zur Übersicht</a>
        </div>
    </div>

    <!-- Vehicle Info Card -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Fahrzeugdaten</div>
                <div class="card-body">
                    <p><strong>Marke:</strong> <?= htmlspecialchars($vehicle['marke']) ?></p>
                    <p><strong>Modell:</strong> <?= htmlspecialchars($vehicle['modell']) ?></p>
                    <p><strong>Kennzeichen:</strong> <?= htmlspecialchars($vehicle['kennzeichen']) ?></p>
                    <p><strong>Baujahr:</strong> <?= htmlspecialchars($vehicle['baujahr']) ?></p>
                    <p><strong>Status:</strong> <?= htmlspecialchars($vehicle['status']) ?></p>
                    <?php if ($current_mileage): ?>
                    <p><strong>Aktueller Kilometerstand:</strong> <?= number_format($current_mileage, 0, ',', '.') ?> km</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Kilometerstand hinzufügen</div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label for="mileage" class="form-label">Kilometerstand</label>
                            <input type="number" name="mileage" id="mileage" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="date_recorded" class="form-label">Datum</label>
                            <input type="date" name="date_recorded" id="date_recorded" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notizen (optional)</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" name="add_mileage" class="btn btn-primary">Kilometerstand hinzufügen</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Mileage Chart -->
    <?php if (!empty($chart_data)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Kilometerverlauf (Letzte 12 Monate)</div>
                <div class="card-body">
                    <canvas id="mileageChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Mileage History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Kilometerstand-Verlauf</div>
                <div class="card-body">
                    <?php if (empty($mileage_records)): ?>
                        <p class="text-muted">Noch keine Kilometerstand-Einträge vorhanden.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Kilometerstand</th>
                                        <th>Notizen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mileage_records as $record): ?>
                                    <tr>
                                        <td><?= date('d.m.Y', strtotime($record['date_recorded'])) ?></td>
                                        <td><?= number_format($record['mileage'], 0, ',', '.') ?> km</td>
                                        <td><?= htmlspecialchars($record['notes'] ?? '') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($chart_data)): ?>
    <script>
    // Mileage chart implementation
    const ctx = document.getElementById('mileageChart').getContext('2d');
    const chartData = <?= json_encode($chart_data) ?>;
    
    const labels = chartData.map(item => {
        const date = new Date(item.date_recorded);
        return date.toLocaleDateString('de-DE');
    });
    
    const data = chartData.map(item => item.mileage);
    
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Kilometerstand',
                data: data,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Kilometerverlauf'
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('de-DE') + ' km';
                        }
                    }
                }
            }
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>