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

    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Monatliche Kraftstoffkosten</div>
                <div class="card-body">
                    <canvas id="monthlyFuelChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Kostenverteilung</div>
                <div class="card-body">
                    <canvas id="costDistributionChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Kraftstoffverbrauch Trends</div>
                <div class="card-body">
                    <canvas id="consumptionTrendChart" width="800" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js Library with fallback -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script>
        // Check if Chart.js loaded successfully
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js failed to load. Dashboard charts will not be displayed.');
            // Hide chart containers if Chart.js is not available
            const chartContainers = ['monthlyFuelChart', 'costDistributionChart', 'consumptionTrendChart'];
            chartContainers.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.parentElement.innerHTML = '<p class="text-muted text-center">Diagramm nicht verfügbar (Chart.js Bibliothek konnte nicht geladen werden)</p>';
                }
            });
        } else {
            // Chart.js is available, proceed with chart creation
            // Monthly Fuel Costs Chart
            const monthlyFuelCtx = document.getElementById('monthlyFuelChart').getContext('2d');
        
        // Get monthly fuel data from PHP
        <?php
        // Generate monthly fuel cost data for the last 12 months
        $monthly_fuel_data = [];
        $labels = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $month_start = date('Y-m-01', strtotime("-$i months"));
            $month_end = date('Y-m-t', strtotime("-$i months"));
            
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(total_cost), 0) as monthly_cost 
                FROM fuel_records 
                WHERE date_recorded BETWEEN ? AND ?
            ");
            $stmt->execute([$month_start, $month_end]);
            $monthly_cost = $stmt->fetchColumn();
            
            $monthly_fuel_data[] = round($monthly_cost, 2);
            $labels[] = date('M Y', strtotime($month_start));
        }
        
        // Get consumption data for each vehicle
        $consumption_data = [];
        $vehicle_labels = [];
        
        $vehicles_stmt = $db->query("SELECT id, marke, modell FROM vehicles");
        $vehicles = $vehicles_stmt->fetchAll();
        
        foreach ($vehicles as $vehicle) {
            $stmt = $db->prepare("
                SELECT 
                    COALESCE(AVG(
                        (fuel_amount_liters / 
                         NULLIF((mileage - LAG(mileage) OVER (ORDER BY date_recorded)), 0)
                        ) * 100
                    ), 0) as avg_consumption
                FROM fuel_records 
                WHERE vehicle_id = ? 
                AND mileage > (
                    SELECT MIN(mileage) FROM fuel_records WHERE vehicle_id = ?
                )
            ");
            $stmt->execute([$vehicle['id'], $vehicle['id']]);
            $avg_consumption = $stmt->fetchColumn();
            
            if ($avg_consumption > 0 && $avg_consumption < 50) { // Reasonable consumption values
                $consumption_data[] = round($avg_consumption, 1);
                $vehicle_labels[] = $vehicle['marke'] . ' ' . $vehicle['modell'];
            }
        }
        ?>
        
        const monthlyFuelChart = new Chart(monthlyFuelCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Kraftstoffkosten (€)',
                    data: <?= json_encode($monthly_fuel_data) ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Kosten (€)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true
                    },
                    title: {
                        display: true,
                        text: 'Monatliche Kraftstoffkosten Entwicklung'
                    }
                }
            }
        });

        // Cost Distribution Pie Chart
        const costDistributionCtx = document.getElementById('costDistributionChart').getContext('2d');
        const costDistributionChart = new Chart(costDistributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Kraftstoffkosten', 'Wartungskosten'],
                datasets: [{
                    data: [<?= $total_fuel_cost ?>, <?= $total_maintenance_cost ?>],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 99, 132, 0.8)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 99, 132, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    title: {
                        display: true,
                        text: 'Gesamtkostenverteilung'
                    }
                }
            }
        });

        // Consumption Trend Chart
        const consumptionTrendCtx = document.getElementById('consumptionTrendChart').getContext('2d');
        const consumptionTrendChart = new Chart(consumptionTrendCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($vehicle_labels) ?>,
                datasets: [{
                    label: 'Durchschnittsverbrauch (L/100km)',
                    data: <?= json_encode($consumption_data) ?>,
                    backgroundColor: 'rgba(153, 102, 255, 0.6)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Verbrauch (L/100km)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true
                    },
                    title: {
                        display: true,
                        text: 'Kraftstoffverbrauch pro Fahrzeug'
                    }
                }
            }
        });
        } // End of Chart.js available check
    </script>
</body>
</html>