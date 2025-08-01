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

// Get combined mileage data from all sources for chart
$stmt = $db->prepare("
    SELECT 
        'mileage' as source,
        date_recorded as date,
        mileage,
        notes as description
    FROM mileage_records 
    WHERE vehicle_id = ? AND mileage IS NOT NULL AND mileage > 0
    
    UNION ALL
    
    SELECT 
        'fuel' as source,
        date_recorded as date,
        mileage,
        COALESCE('Tankung: ' || fuel_amount_liters || 'L ' || fuel_type, 'Tankung') as description
    FROM fuel_records 
    WHERE vehicle_id = ? AND mileage IS NOT NULL AND mileage > 0
    
    UNION ALL
    
    SELECT 
        'maintenance' as source,
        date_performed as date,
        mileage,
        COALESCE('Wartung: ' || description, 'Wartung') as description
    FROM maintenance_records 
    WHERE vehicle_id = ? AND mileage IS NOT NULL AND mileage > 0
    
    ORDER BY date ASC, mileage ASC
");
$stmt->execute([$vehicle_id, $vehicle_id, $vehicle_id]);
$all_mileage_data = $stmt->fetchAll();

// Remove duplicate entries for the same date and mileage, keeping the most descriptive one
$chart_data = [];
$seen_combinations = [];

foreach ($all_mileage_data as $record) {
    $key = $record['date'] . '_' . $record['mileage'];
    
    if (!isset($seen_combinations[$key])) {
        $chart_data[] = $record;
        $seen_combinations[$key] = true;
    } else {
        // If we already have this date/mileage combination, 
        // replace it if current record has more detailed description
        $existing_index = null;
        foreach ($chart_data as $index => $existing) {
            if ($existing['date'] == $record['date'] && $existing['mileage'] == $record['mileage']) {
                $existing_index = $index;
                break;
            }
        }
        
        if ($existing_index !== null && strlen($record['description']) > strlen($chart_data[$existing_index]['description'])) {
            $chart_data[$existing_index] = $record;
        }
    }
}

// Get current mileage (latest record from any source)
$stmt = $db->prepare("
    SELECT MAX(mileage) as max_mileage
    FROM (
        SELECT mileage, date_recorded as date FROM mileage_records WHERE vehicle_id = ? AND mileage IS NOT NULL
        UNION ALL
        SELECT mileage, date_recorded as date FROM fuel_records WHERE vehicle_id = ? AND mileage IS NOT NULL
        UNION ALL
        SELECT mileage, date_performed as date FROM maintenance_records WHERE vehicle_id = ? AND mileage IS NOT NULL
    ) combined_mileage
    WHERE date = (
        SELECT MAX(date) FROM (
            SELECT date_recorded as date FROM mileage_records WHERE vehicle_id = ? AND mileage IS NOT NULL
            UNION ALL
            SELECT date_recorded as date FROM fuel_records WHERE vehicle_id = ? AND mileage IS NOT NULL
            UNION ALL
            SELECT date_performed as date FROM maintenance_records WHERE vehicle_id = ? AND mileage IS NOT NULL
        ) dates
    )
");
$stmt->execute([$vehicle_id, $vehicle_id, $vehicle_id, $vehicle_id, $vehicle_id, $vehicle_id]);
$current_mileage_result = $stmt->fetch();
$current_mileage = $current_mileage_result ? $current_mileage_result['max_mileage'] : null;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fahrzeug Details - <?= htmlspecialchars($vehicle['marke'] . ' ' . $vehicle['modell']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }
        .chart-controls {
            text-align: center;
            margin-bottom: 10px;
        }
        .chart-controls button {
            margin: 0 5px;
        }
        .chart-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 0.9em;
        }
    </style>
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

    <!-- Enhanced Mileage Chart -->
    <?php if (!empty($chart_data)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Kilometerverlauf (Alle Datenquellen)</span>
                    <small class="text-muted">Zoom: Mausrad | Pan: Ziehen | Reset: Doppelklick</small>
                </div>
                <div class="card-body">
                    <div class="chart-info">
                        <strong>Datenquellen:</strong>
                        <span class="badge bg-primary">Manueller Kilometerstand</span>
                        <span class="badge bg-success">Tankungen</span>
                        <span class="badge bg-warning">Wartungen</span>
                        <br><small>Insgesamt <?= count($chart_data) ?> Datenpunkte aus allen Quellen</small>
                    </div>
                    <div class="chart-controls">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="resetZoom()">Zurücksetzen</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="zoomIn()">Zoom In</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="zoomOut()">Zoom Out</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="mileageChart"></canvas>
                    </div>
                    <!-- Fallback table when Chart.js is not available -->
                    <div id="chartFallback" style="display: none;">
                        <div class="alert alert-info">
                            <strong>Chart.js ist nicht verfügbar.</strong> Hier sind die Daten in Tabellenform:
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Kilometerstand</th>
                                        <th>Quelle</th>
                                        <th>Beschreibung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($chart_data as $data): ?>
                                    <tr>
                                        <td><?= date('d.m.Y', strtotime($data['date'])) ?></td>
                                        <td><?= number_format($data['mileage'], 0, ',', '.') ?> km</td>
                                        <td>
                                            <?php 
                                            $source_labels = [
                                                'mileage' => '<span class="badge bg-primary">Manuell</span>',
                                                'fuel' => '<span class="badge bg-success">Tankung</span>',
                                                'maintenance' => '<span class="badge bg-warning">Wartung</span>'
                                            ];
                                            echo $source_labels[$data['source']] ?? $data['source'];
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($data['description']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
    // Enhanced mileage chart implementation with zoom and pan
    let mileageChart;
    
    // Check if Chart.js and zoom plugin are available
    if (typeof Chart !== 'undefined') {
        // Register the zoom plugin
        if (typeof Chart.register === 'function' && typeof ChartZoom !== 'undefined') {
            Chart.register(ChartZoom);
        }
        
        const ctx = document.getElementById('mileageChart').getContext('2d');
        const chartData = <?= json_encode($chart_data) ?>;
        
        // Prepare data with color coding by source
        const labels = chartData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('de-DE');
        });
        
        const data = chartData.map(item => parseInt(item.mileage));
        const sources = chartData.map(item => item.source);
        const descriptions = chartData.map(item => item.description);
        
        // Create point colors based on source
        const pointColors = sources.map(source => {
            switch(source) {
                case 'mileage': return '#0d6efd'; // Primary blue
                case 'fuel': return '#198754';    // Success green  
                case 'maintenance': return '#ffc107'; // Warning yellow
                default: return '#6c757d';        // Secondary gray
            }
        });
        
        const pointBorderColors = sources.map(source => {
            switch(source) {
                case 'mileage': return '#0a58ca';
                case 'fuel': return '#146c43';
                case 'maintenance': return '#ffca2c';
                default: return '#5c636a';
            }
        });
        
        mileageChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Kilometerstand',
                    data: data,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.1,
                    fill: true,
                    pointBackgroundColor: pointColors,
                    pointBorderColor: pointBorderColors,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Kilometerverlauf - Scrollbar und Skalierbar'
                    },
                    legend: {
                        display: true,
                        labels: {
                            generateLabels: function(chart) {
                                return [
                                    {
                                        text: 'Kilometerstand (alle Quellen)',
                                        fillStyle: '#0d6efd',
                                        strokeStyle: '#0d6efd',
                                        lineWidth: 2,
                                        pointStyle: 'circle'
                                    }
                                ];
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                const index = context[0].dataIndex;
                                return 'Datum: ' + labels[index];
                            },
                            label: function(context) {
                                const index = context.dataIndex;
                                const km = context.parsed.y.toLocaleString('de-DE');
                                const source = sources[index];
                                const sourceLabel = {
                                    'mileage': 'Manuell erfasst',
                                    'fuel': 'Tankung',
                                    'maintenance': 'Wartung'
                                }[source] || source;
                                
                                return [
                                    'Kilometerstand: ' + km + ' km',
                                    'Quelle: ' + sourceLabel,
                                    'Details: ' + descriptions[index]
                                ];
                            }
                        }
                    },
                    zoom: {
                        limits: {
                            y: {min: 0, max: 'original'}
                        },
                        pan: {
                            enabled: true,
                            mode: 'xy',
                            modifierKey: null
                        },
                        zoom: {
                            wheel: {
                                enabled: true,
                            },
                            pinch: {
                                enabled: true
                            },
                            mode: 'xy',
                            drag: {
                                enabled: true,
                                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                                borderColor: '#0d6efd',
                                borderWidth: 1,
                                modifierKey: 'ctrl'
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Kilometer'
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('de-DE') + ' km';
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Datum'
                        },
                        ticks: {
                            maxTicksLimit: 10
                        }
                    }
                }
            }
        });
    } else {
        // Fallback if Chart.js is not available
        document.getElementById('mileageChart').style.display = 'none';
        document.getElementById('chartFallback').style.display = 'block';
        
        // Hide chart controls
        const controls = document.querySelector('.chart-controls');
        if (controls) controls.style.display = 'none';
    }
    
    // Chart control functions
    function resetZoom() {
        if (mileageChart && mileageChart.resetZoom) {
            mileageChart.resetZoom();
        }
    }
    
    function zoomIn() {
        if (mileageChart && mileageChart.zoom) {
            mileageChart.zoom(1.2);
        }
    }
    
    function zoomOut() {
        if (mileageChart && mileageChart.zoom) {
            mileageChart.zoom(0.8);
        }
    }
    </script>
    <?php endif; ?>
</body>
</html>