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

// Handle maintenance record form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_maintenance'])) {
    $db->prepare("INSERT INTO maintenance_records (vehicle_id, maintenance_type, date_performed, mileage, cost, description, next_maintenance_km, next_maintenance_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $vehicle_id,
            $_POST['maintenance_type'],
            parse_german_date($_POST['date_performed']),
            !empty($_POST['mileage']) ? $_POST['mileage'] : null,
            parse_german_number($_POST['cost']),
            $_POST['description'] ?? '',
            !empty($_POST['next_maintenance_km']) ? $_POST['next_maintenance_km'] : null,
            !empty($_POST['next_maintenance_date']) ? parse_german_date($_POST['next_maintenance_date']) : null
        ]);
    header("Location: maintenance.php?id=" . $vehicle_id);
    exit();
}

// Get maintenance records for this vehicle
$stmt = $db->prepare("SELECT * FROM maintenance_records WHERE vehicle_id = ? ORDER BY date_performed DESC");
$stmt->execute([$vehicle_id]);
$maintenance_records = $stmt->fetchAll();

// Get upcoming maintenance (next maintenance dates/km)
$stmt = $db->prepare("
    SELECT *, 
           CASE 
               WHEN next_maintenance_date IS NOT NULL AND next_maintenance_date <= date('now', '+30 days') THEN 1
               ELSE 0
           END as date_due_soon
    FROM maintenance_records 
    WHERE vehicle_id = ? 
    AND (next_maintenance_date > date('now') OR next_maintenance_km IS NOT NULL)
    ORDER BY next_maintenance_date ASC, next_maintenance_km ASC
");
$stmt->execute([$vehicle_id]);
$upcoming_maintenance = $stmt->fetchAll();

// Get current mileage from latest mileage record
$stmt = $db->prepare("SELECT mileage FROM mileage_records WHERE vehicle_id = ? ORDER BY date_recorded DESC LIMIT 1");
$stmt->execute([$vehicle_id]);
$current_mileage_record = $stmt->fetch();
$current_mileage = $current_mileage_record ? $current_mileage_record['mileage'] : 0;

// Calculate maintenance statistics
$total_maintenance_cost = 0;
$maintenance_by_type = [];

foreach ($maintenance_records as $record) {
    $total_maintenance_cost += $record['cost'];
    $type = $record['maintenance_type'];
    if (!isset($maintenance_by_type[$type])) {
        $maintenance_by_type[$type] = ['count' => 0, 'total_cost' => 0];
    }
    $maintenance_by_type[$type]['count']++;
    $maintenance_by_type[$type]['total_cost'] += $record['cost'];
}

// Define maintenance type labels
$maintenance_types = [
    'kleine_wartung' => 'Kleine Wartung',
    'grosse_wartung' => 'Große Wartung', 
    'tuev' => 'TÜV',
    'hu' => 'HU (Hauptuntersuchung)',
    'other' => 'Sonstiges'
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Wartung - <?= htmlspecialchars($vehicle['marke'] . ' ' . $vehicle['modell']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Wartung: <?= htmlspecialchars($vehicle['marke'] . ' ' . $vehicle['modell']) ?></h2>
        <div>
            <a href="vehicle_detail.php?id=<?= $vehicle_id ?>" class="btn btn-secondary">Fahrzeug Details</a>
            <a href="vehicles.php" class="btn btn-secondary">Zurück zur Übersicht</a>
        </div>
    </div>

    <!-- Maintenance Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-bg-primary">
                <div class="card-header">Gesamtkosten Wartung</div>
                <div class="card-body">
                    <h4><?= number_format($total_maintenance_cost, 2, ',', '.') ?> €</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-info">
                <div class="card-header">Anzahl Wartungen</div>
                <div class="card-body">
                    <h4><?= count($maintenance_records) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-warning">
                <div class="card-header">Aktueller KM-Stand</div>
                <div class="card-body">
                    <h4><?= number_format($current_mileage, 0, ',', '.') ?> km</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-success">
                <div class="card-header">Anstehende Termine</div>
                <div class="card-body">
                    <h4><?= count($upcoming_maintenance) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Maintenance Alert -->
    <?php if (!empty($upcoming_maintenance)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">🔧 Anstehende Wartungen</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($upcoming_maintenance as $upcoming): ?>
                        <div class="alert <?= $upcoming['date_due_soon'] ? 'alert-danger' : 'alert-info' ?>" role="alert">
                            <strong><?= $maintenance_types[$upcoming['maintenance_type']] ?? $upcoming['maintenance_type'] ?></strong>
                            <?php if ($upcoming['next_maintenance_date']): ?>
                                - Fällig am: <?= date('d.m.Y', strtotime($upcoming['next_maintenance_date'])) ?>
                                <?php if ($upcoming['date_due_soon']): ?>
                                    <span class="badge bg-danger">Innerhalb 30 Tage!</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($upcoming['next_maintenance_km']): ?>
                                - Bei: <?= number_format($upcoming['next_maintenance_km'], 0, ',', '.') ?> km
                                <?php if ($current_mileage >= $upcoming['next_maintenance_km']): ?>
                                    <span class="badge bg-danger">Überfällig!</span>
                                <?php elseif (($upcoming['next_maintenance_km'] - $current_mileage) <= 1000): ?>
                                    <span class="badge bg-warning">Bald fällig!</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <br><small class="text-muted">Basierend auf: <?= htmlspecialchars($upcoming['description']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Maintenance Record Form -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Wartung hinzufügen</div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <div class="col-md-3">
                            <label for="maintenance_type" class="form-label">Wartungsart</label>
                            <select name="maintenance_type" id="maintenance_type" class="form-select" required>
                                <option value="">Bitte wählen...</option>
                                <?php foreach ($maintenance_types as $value => $label): ?>
                                    <option value="<?= $value ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="date_performed" class="form-label">Datum (dd.mm.yyyy)</label>
                            <input type="text" name="date_performed" id="date_performed" class="form-control" value="<?= current_german_date() ?>" placeholder="dd.mm.yyyy" pattern="\d{1,2}\.\d{1,2}\.\d{4}" required>
                        </div>
                        <div class="col-md-2">
                            <label for="mileage" class="form-label">Kilometerstand</label>
                            <input type="number" name="mileage" id="mileage" class="form-control" value="<?= $current_mileage ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="cost" class="form-label">Kosten (€)</label>
                            <input type="text" name="cost" id="cost" class="form-control" placeholder="125,50" title="Verwenden Sie Komma als Dezimaltrennzeichen (z.B. 125,50)" required>
                        </div>
                        <div class="col-md-3">
                            <label for="description" class="form-label">Beschreibung</label>
                            <input type="text" name="description" id="description" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label for="next_maintenance_date" class="form-label">Nächste Wartung (dd.mm.yyyy)</label>
                            <input type="text" name="next_maintenance_date" id="next_maintenance_date" class="form-control" placeholder="dd.mm.yyyy" pattern="\d{1,2}\.\d{1,2}\.\d{4}">
                        </div>
                        <div class="col-md-3">
                            <label for="next_maintenance_km" class="form-label">Nächste Wartung (KM)</label>
                            <input type="number" name="next_maintenance_km" id="next_maintenance_km" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" name="add_maintenance" class="btn btn-primary w-100">Wartung hinzufügen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Breakdown by Type -->
    <?php if (!empty($maintenance_by_type)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Wartungsübersicht nach Art</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Wartungsart</th>
                                    <th>Anzahl</th>
                                    <th>Gesamtkosten</th>
                                    <th>Durchschnittliche Kosten</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenance_by_type as $type => $stats): ?>
                                <tr>
                                    <td><?= $maintenance_types[$type] ?? $type ?></td>
                                    <td><?= $stats['count'] ?></td>
                                    <td><?= number_format($stats['total_cost'], 2, ',', '.') ?> €</td>
                                    <td><?= number_format($stats['total_cost'] / $stats['count'], 2, ',', '.') ?> €</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Maintenance History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Wartungshistorie</div>
                <div class="card-body">
                    <?php if (empty($maintenance_records)): ?>
                        <p class="text-muted">Noch keine Wartungen erfasst.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Art</th>
                                        <th>Kilometerstand</th>
                                        <th>Kosten</th>
                                        <th>Beschreibung</th>
                                        <th>Nächste Wartung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maintenance_records as $record): ?>
                                    <tr>
                                        <td><?= date('d.m.Y', strtotime($record['date_performed'])) ?></td>
                                        <td><?= $maintenance_types[$record['maintenance_type']] ?? $record['maintenance_type'] ?></td>
                                        <td><?= $record['mileage'] ? number_format($record['mileage'], 0, ',', '.') . ' km' : '-' ?></td>
                                        <td><?= number_format($record['cost'], 2, ',', '.') ?> €</td>
                                        <td><?= htmlspecialchars($record['description'] ?? '') ?></td>
                                        <td>
                                            <?php if ($record['next_maintenance_date']): ?>
                                                <?= date('d.m.Y', strtotime($record['next_maintenance_date'])) ?>
                                            <?php endif; ?>
                                            <?php if ($record['next_maintenance_km']): ?>
                                                <?= $record['next_maintenance_date'] ? '<br>' : '' ?>
                                                <?= number_format($record['next_maintenance_km'], 0, ',', '.') ?> km
                                            <?php endif; ?>
                                            <?php if (!$record['next_maintenance_date'] && !$record['next_maintenance_km']): ?>
                                                -
                                            <?php endif; ?>
                                        </td>
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
</body>
</html>