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

// Data for charts
// Monthly fuel costs (last 6 months)
$monthly_fuel_costs = $db->query("
    SELECT 
        strftime('%Y-%m', date_recorded) as month,
        SUM(total_cost) as total_cost
    FROM fuel_records 
    WHERE date_recorded >= date('now', '-6 months')
    GROUP BY strftime('%Y-%m', date_recorded)
    ORDER BY month ASC
")->fetchAll();

// Fuel costs per vehicle
$fuel_costs_by_vehicle = $db->query("
    SELECT 
        v.marke || ' ' || v.modell as vehicle_name,
        COALESCE(SUM(f.total_cost), 0) as total_cost
    FROM vehicles v
    LEFT JOIN fuel_records f ON v.id = f.vehicle_id
    GROUP BY v.id, v.marke, v.modell
    ORDER BY total_cost DESC
")->fetchAll();

// Monthly maintenance costs (last 6 months)
$monthly_maintenance_costs = $db->query("
    SELECT 
        strftime('%Y-%m', date_performed) as month,
        SUM(cost) as total_cost
    FROM maintenance_records 
    WHERE date_performed >= date('now', '-6 months')
    GROUP BY strftime('%Y-%m', date_performed)
    ORDER BY month ASC
")->fetchAll();

// Vehicle status distribution
$vehicle_status_distribution = $db->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM vehicles
    GROUP BY status
")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>KFZ Verwaltung Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Spritkosten Verlauf (letzte 6 Monate)</div>
                <div class="card-body">
                    <canvas id="fuelCostsChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Fahrzeugstatus Verteilung</div>
                <div class="card-body">
                    <canvas id="vehicleStatusChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Spritkosten pro Fahrzeug</div>
                <div class="card-body">
                    <canvas id="fuelCostsByVehicleChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Wartungskosten Verlauf (letzte 6 Monate)</div>
                <div class="card-body">
                    <canvas id="maintenanceCostsChart" width="400" height="200"></canvas>
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

    <script>
    // Chart configuration
    Chart.defaults.font.family = 'Arial, sans-serif';
    Chart.defaults.responsive = true;
    Chart.defaults.maintainAspectRatio = false;

    // 1. Fuel Costs Chart (Monthly trend)
    const fuelCostsCtx = document.getElementById('fuelCostsChart').getContext('2d');
    const fuelCostsData = <?= json_encode($monthly_fuel_costs) ?>;
    
    const fuelCostsChart = new Chart(fuelCostsCtx, {
        type: 'line',
        data: {
            labels: fuelCostsData.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('de-DE', { year: 'numeric', month: 'short' });
            }),
            datasets: [{
                label: 'Spritkosten (€)',
                data: fuelCostsData.map(item => parseFloat(item.total_cost)),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('de-DE') + ' €';
                        }
                    }
                }
            }
        }
    });

    // 2. Vehicle Status Chart (Pie chart)
    const vehicleStatusCtx = document.getElementById('vehicleStatusChart').getContext('2d');
    const vehicleStatusData = <?= json_encode($vehicle_status_distribution) ?>;
    
    const vehicleStatusChart = new Chart(vehicleStatusCtx, {
        type: 'doughnut',
        data: {
            labels: vehicleStatusData.map(item => {
                switch(item.status) {
                    case 'verfügbar': return 'Verfügbar';
                    case 'in Benutzung': return 'In Benutzung';
                    case 'wartung': return 'Wartung';
                    default: return item.status;
                }
            }),
            datasets: [{
                data: vehicleStatusData.map(item => parseInt(item.count)),
                backgroundColor: [
                    '#28a745', // Green for available
                    '#ffc107', // Yellow for in use
                    '#dc3545'  // Red for maintenance
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // 3. Fuel Costs by Vehicle Chart (Bar chart)
    const fuelCostsByVehicleCtx = document.getElementById('fuelCostsByVehicleChart').getContext('2d');
    const fuelCostsByVehicleData = <?= json_encode($fuel_costs_by_vehicle) ?>;
    
    const fuelCostsByVehicleChart = new Chart(fuelCostsByVehicleCtx, {
        type: 'bar',
        data: {
            labels: fuelCostsByVehicleData.map(item => item.vehicle_name),
            datasets: [{
                label: 'Spritkosten (€)',
                data: fuelCostsByVehicleData.map(item => parseFloat(item.total_cost)),
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('de-DE') + ' €';
                        }
                    }
                }
            }
        }
    });

    // 4. Maintenance Costs Chart (Monthly trend)
    const maintenanceCostsCtx = document.getElementById('maintenanceCostsChart').getContext('2d');
    const maintenanceCostsData = <?= json_encode($monthly_maintenance_costs) ?>;
    
    const maintenanceCostsChart = new Chart(maintenanceCostsCtx, {
        type: 'bar',
        data: {
            labels: maintenanceCostsData.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('de-DE', { year: 'numeric', month: 'short' });
            }),
            datasets: [{
                label: 'Wartungskosten (€)',
                data: maintenanceCostsData.map(item => parseFloat(item.total_cost)),
                backgroundColor: 'rgba(255, 99, 132, 0.6)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('de-DE') + ' €';
                        }
                    }
                }
            }
        }
    });
    </script>
</body>
</html>