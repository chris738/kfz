<?php
require 'auth.php';
require_login();
require 'db.php';

// Statistiken
$total = $db->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
$verfuegbar = $db->query("SELECT COUNT(*) FROM vehicles WHERE status = 'verfügbar'")->fetchColumn();
$inBenutzung = $db->query("SELECT COUNT(*) FROM vehicles WHERE status = 'in Benutzung'")->fetchColumn();

// Neue Statistiken für die Features
$total_fuel_cost = $db->query("SELECT COALESCE(SUM(total_cost), 0) FROM fuel_records")->fetchColumn();
$total_maintenance_cost = $db->query("SELECT COALESCE(SUM(cost), 0) FROM maintenance_records")->fetchColumn();

// Anstehende Wartungen
$upcoming_maintenance = $db->query("
    SELECT COUNT(*) FROM maintenance_records 
    WHERE (next_maintenance_date IS NOT NULL AND next_maintenance_date <= date('now', '+30 days'))
    OR (next_maintenance_km IS NOT NULL)
")->fetchColumn();

// Letzte Aktivitäten
$recent_fuel = $db->query("
    SELECT v.marke, v.modell, f.date_recorded, f.total_cost 
    FROM fuel_records f 
    JOIN vehicles v ON f.vehicle_id = v.id 
    ORDER BY f.date_recorded DESC 
    LIMIT 5
")->fetchAll();

$recent_maintenance = $db->query("
    SELECT v.marke, v.modell, m.date_performed, m.maintenance_type, m.cost 
    FROM maintenance_records m 
    JOIN vehicles v ON m.vehicle_id = v.id 
    ORDER BY m.date_performed DESC 
    LIMIT 5
")->fetchAll();

$maintenance_types = [
    'kleine_wartung' => 'Kleine Wartung',
    'grosse_wartung' => 'Große Wartung', 
    'tuev' => 'TÜV',
    'hu' => 'HU',
    'other' => 'Sonstiges'
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>KFZ Verwaltung Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>KFZ Verwaltung Dashboard</h1>
        <div>
            <a href="vehicles.php" class="btn btn-primary">Fahrzeuge verwalten</a>
            <a href="logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </div>
    
    <!-- Fahrzeug Statistiken -->
    <div class="row mb-4">
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

    <!-- Kosten Übersicht -->
    <div class="row mb-4">
        <div class="col">
            <div class="card text-bg-primary mb-3">
                <div class="card-header">Gesamte Spritkosten</div>
                <div class="card-body"><h3><?= number_format($total_fuel_cost, 2, ',', '.') ?> €</h3></div>
            </div>
        </div>
        <div class="col">
            <div class="card text-bg-secondary mb-3">
                <div class="card-header">Gesamte Wartungskosten</div>
                <div class="card-body"><h3><?= number_format($total_maintenance_cost, 2, ',', '.') ?> €</h3></div>
            </div>
        </div>
        <div class="col">
            <div class="card <?= $upcoming_maintenance > 0 ? 'text-bg-danger' : 'text-bg-success' ?> mb-3">
                <div class="card-header">Anstehende Wartungen</div>
                <div class="card-body">
                    <h3><?= $upcoming_maintenance ?></h3>
                    <?php if ($upcoming_maintenance > 0): ?>
                        <small>Wartungen erforderlich!</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Letzte Aktivitäten -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Letzte Tankungen</div>
                <div class="card-body">
                    <?php if (empty($recent_fuel)): ?>
                        <p class="text-muted">Keine Tankungen erfasst.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_fuel as $fuel): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($fuel['marke'] . ' ' . $fuel['modell']) ?></strong><br>
                                        <small class="text-muted"><?= date('d.m.Y', strtotime($fuel['date_recorded'])) ?></small>
                                    </div>
                                    <span class="badge bg-primary rounded-pill"><?= number_format($fuel['total_cost'], 2, ',', '.') ?> €</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Letzte Wartungen</div>
                <div class="card-body">
                    <?php if (empty($recent_maintenance)): ?>
                        <p class="text-muted">Keine Wartungen erfasst.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_maintenance as $maint): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($maint['marke'] . ' ' . $maint['modell']) ?></strong><br>
                                        <small class="text-muted">
                                            <?= $maintenance_types[$maint['maintenance_type']] ?? $maint['maintenance_type'] ?> - 
                                            <?= date('d.m.Y', strtotime($maint['date_performed'])) ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-secondary rounded-pill"><?= number_format($maint['cost'], 2, ',', '.') ?> €</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>