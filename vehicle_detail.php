<?php
require 'auth.php';
require_login();
require 'db.php';
require 'locale_de.php';

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
            parse_german_date($_POST['date_recorded']),
            $_POST['notes'] ?? ''
        ]);
    header("Location: vehicle_detail.php?id=" . $vehicle_id);
    exit();
}

// Get mileage records for this vehicle
$stmt = $db->prepare("SELECT * FROM mileage_records WHERE vehicle_id = ? ORDER BY date_recorded DESC");
$stmt->execute([$vehicle_id]);
$mileage_records = $stmt->fetchAll();

// Get data for chart (all data for testing, can be restricted later)
$stmt = $db->prepare("
    SELECT date_recorded, mileage 
    FROM mileage_records 
    WHERE vehicle_id = ? 
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="js/chart-fallback.js"></script>
    <script src="js/chart-config.js"></script>
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
                            <label for="date_recorded" class="form-label">Datum (dd.mm.yyyy)</label>
                            <input type="text" name="date_recorded" id="date_recorded" class="form-control" value="<?= current_german_date() ?>" placeholder="dd.mm.yyyy" pattern="\d{1,2}\.\d{1,2}\.\d{4}" required>
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

    <!-- Enhanced Mileage Chart with Integration -->
    <?php if (!empty($chart_data)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up"></i> Erweiterte Kilometer-Progression 
                        <small class="text-muted">(mit Tankungen und Wartungen)</small>
                    </h5>
                </div>
                <div class="card-body">
                    <div id="enhancedMileageChart"></div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle"></i> Kilometerstand-Progression
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Keine Daten verfügbar</strong><br>
                        Fügen Sie Kilometerstand-Einträge hinzu, um die Progression zu sehen.
                    </div>
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
    // Wait for charts to be ready
    document.addEventListener('chartsReady', function() {
        // Prepare data for enhanced kilometer progression chart
        const kilometerData = {
            labels: <?= json_encode(array_map(function($item) { 
                return date('d.m.Y', strtotime($item['date_recorded'])); 
            }, $chart_data)) ?>,
            values: <?= json_encode(array_map(function($item) { 
                return (int)$item['mileage']; 
            }, $chart_data)) ?>,
            datasets: [{
                label: 'Kilometerstand (Mileage Records)',
                data: <?= json_encode(array_map(function($item) { 
                    return (int)$item['mileage']; 
                }, $chart_data)) ?>,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.1
            }]
        };
        
        // Get fuel data for this vehicle
        <?php
        $fuel_stmt = $db->prepare("SELECT date_recorded, mileage, fuel_amount_liters, total_cost FROM fuel_records WHERE vehicle_id = ? ORDER BY date_recorded ASC");
        $fuel_stmt->execute([$vehicle_id]);
        $fuel_data = $fuel_stmt->fetchAll();
        ?>
        
        const fuelData = <?= json_encode(array_map(function($item) {
            return [
                'date' => date('d.m.Y', strtotime($item['date_recorded'])),
                'mileage' => (int)$item['mileage'],
                'liters' => (float)$item['fuel_amount_liters'],
                'cost' => number_format($item['total_cost'], 2, ',', '.')
            ];
        }, $fuel_data)) ?>;
        
        // Get maintenance data for this vehicle
        <?php
        $maintenance_stmt = $db->prepare("SELECT date_performed, mileage, maintenance_type, cost FROM maintenance_records WHERE vehicle_id = ? AND mileage IS NOT NULL ORDER BY date_performed ASC");
        $maintenance_stmt->execute([$vehicle_id]);
        $maintenance_data = $maintenance_stmt->fetchAll();
        ?>
        
        const maintenanceData = <?= json_encode(array_map(function($item) {
            return [
                'date' => date('d.m.Y', strtotime($item['date_performed'])),
                'mileage' => (int)$item['mileage'],
                'type' => $item['maintenance_type'],
                'cost' => number_format($item['cost'], 2, ',', '.')
            ];
        }, $maintenance_data)) ?>;
        
        // Create integrated chart
        createIntegratedKilometerChart('enhancedMileageChart', kilometerData, fuelData, maintenanceData, {
            title: 'Kilometer-Progression mit Tankungen und Wartungen',
            defaultRange: '6m',
            plugins: {
                tooltip: {
                    callbacks: {
                        afterBody: function(context) {
                            const point = context[0];
                            if (point.dataset.label === 'Tankungen') {
                                const fuel = fuelData[point.dataIndex];
                                return [`Kraftstoff: ${fuel.liters}L`, `Kosten: ${fuel.cost}€`];
                            } else if (point.dataset.label === 'Wartungen') {
                                const maintenance = maintenanceData[point.dataIndex];
                                return [`Art: ${maintenance.type}`, `Kosten: ${maintenance.cost}€`];
                            }
                            return [];
                        }
                    }
                }
            }
        });
    });
    
    // Fallback for when Chart.js fails to load
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            if (typeof Chart === 'undefined') {
                const container = document.getElementById('enhancedMileageChart');
                if (container) {
                    container.innerHTML = `
                        <div class="alert alert-warning" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> 
                            <strong>Diagramm nicht verfügbar</strong><br>
                            Die Chart-Bibliothek konnte nicht geladen werden. Hier ist eine Übersicht der Daten:
                            <ul class="mt-2 mb-0">
                                <li>Kilometerstand-Einträge: <?= count($chart_data) ?></li>
                                <li>Tankungen: <?= count($fuel_data) ?></li>
                                <li>Wartungen: <?= count($maintenance_data) ?></li>
                            </ul>
                        </div>
                    `;
                }
            }
        }, 3000);
    });
    </script>
    <?php endif; ?>
</body>
</html>