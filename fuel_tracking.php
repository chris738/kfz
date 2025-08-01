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

// Handle fuel record form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fuel'])) {
    // Parse German date format DD.MM.YYYY to YYYY-MM-DD for database storage
    $date_input = $_POST['date_recorded'];
    $date_formatted = $date_input;
    
    // Check if it's in German format DD.MM.YYYY
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $date_input, $matches)) {
        $date_formatted = $matches[3] . '-' . $matches[2] . '-' . $matches[1]; // Convert to YYYY-MM-DD
    }
    
    // Convert German decimal format (comma) to standard decimal format (dot) for price
    $price_input = str_replace(',', '.', $_POST['fuel_price_per_liter']);
    $amount_input = str_replace(',', '.', $_POST['fuel_amount_liters']);
    
    $db->prepare("INSERT INTO fuel_records (vehicle_id, mileage, date_recorded, fuel_price_per_liter, fuel_amount_liters, fuel_type, displayed_consumption, engine_runtime, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $vehicle_id,
            $_POST['mileage'],
            $date_formatted,
            $price_input,
            $amount_input,
            $_POST['fuel_type'] ?? 'Super',
            !empty($_POST['displayed_consumption']) ? str_replace(',', '.', $_POST['displayed_consumption']) : null,
            !empty($_POST['engine_runtime']) ? $_POST['engine_runtime'] : null,
            $_POST['notes'] ?? ''
        ]);
    header("Location: fuel_tracking.php?id=" . $vehicle_id);
    exit();
}

// Handle CSV import
$import_message = '';
$import_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $csv_file = $_FILES['csv_file']['tmp_name'];
        
        try {
            $imported_count = 0;
            $error_count = 0;
            $errors = [];
            
            if (($handle = fopen($csv_file, 'r')) !== FALSE) {
                $row_num = 0;
                while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
                    $row_num++;
                    
                    // Skip header row if it looks like a header
                    if ($row_num === 1 && (stripos($data[0], 'id') !== false || !is_numeric($data[0]))) {
                        continue;
                    }
                    
                    // Validate CSV format: expecting id;datum;kilometer;liter;preiprol
                    if (count($data) < 5) {
                        $errors[] = "Zeile $row_num: Nicht genügend Spalten (erwartet: 5, gefunden: " . count($data) . ")";
                        $error_count++;
                        continue;
                    }
                    
                    $csv_id = trim($data[0]);
                    $datum = trim($data[1]);
                    $kilometer = trim($data[2]);
                    $liter = trim($data[3]);
                    $preiprol = trim($data[4]);
                    
                    // Validate data
                    if (!is_numeric($kilometer) || $kilometer < 0) {
                        $errors[] = "Zeile $row_num: Ungültiger Kilometerstand: $kilometer";
                        $error_count++;
                        continue;
                    }
                    
                    if (!is_numeric($liter) || $liter <= 0) {
                        $errors[] = "Zeile $row_num: Ungültige Spritmenge: $liter";
                        $error_count++;
                        continue;
                    }
                    
                    if (!is_numeric($preiprol) || $preiprol <= 0) {
                        $errors[] = "Zeile $row_num: Ungültiger Preis pro Liter: $preiprol";
                        $error_count++;
                        continue;
                    }
                    
                    // Validate and convert date
                    $date_obj = DateTime::createFromFormat('Y-m-d', $datum);
                    if (!$date_obj) {
                        $date_obj = DateTime::createFromFormat('d.m.Y', $datum);
                    }
                    if (!$date_obj) {
                        $date_obj = DateTime::createFromFormat('d/m/Y', $datum);
                    }
                    
                    if (!$date_obj) {
                        $errors[] = "Zeile $row_num: Ungültiges Datum: $datum (Format: YYYY-MM-DD oder DD.MM.YYYY)";
                        $error_count++;
                        continue;
                    }
                    
                    $formatted_date = $date_obj->format('Y-m-d');
                    
                    // Insert into database
                    try {
                        $db->prepare("INSERT INTO fuel_records (vehicle_id, mileage, date_recorded, fuel_price_per_liter, fuel_amount_liters, fuel_type, notes) VALUES (?, ?, ?, ?, ?, ?, ?)")
                            ->execute([
                                $vehicle_id,
                                (int)$kilometer,
                                $formatted_date,
                                (float)$preiprol,
                                (float)$liter,
                                'Super', // Default fuel type for CSV import
                                "CSV Import - ID: $csv_id"
                            ]);
                        $imported_count++;
                    } catch (PDOException $e) {
                        $errors[] = "Zeile $row_num: Datenbankfehler: " . $e->getMessage();
                        $error_count++;
                    }
                }
                fclose($handle);
            }
            
            if ($imported_count > 0) {
                $import_message = "$imported_count Datensätze erfolgreich importiert.";
            }
            if ($error_count > 0) {
                $import_error = "$error_count Fehler beim Import:<br>" . implode('<br>', array_slice($errors, 0, 10));
                if (count($errors) > 10) {
                    $import_error .= '<br>... und ' . (count($errors) - 10) . ' weitere Fehler.';
                }
            }
            
        } catch (Exception $e) {
            $import_error = "Fehler beim Verarbeiten der CSV-Datei: " . $e->getMessage();
        }
    } else {
        $import_error = "Fehler beim Hochladen der Datei.";
    }
    
    // Redirect to avoid resubmission
    $url_params = "id=$vehicle_id";
    if ($import_message) $url_params .= "&import_success=" . urlencode($import_message);
    if ($import_error) $url_params .= "&import_error=" . urlencode($import_error);
    header("Location: fuel_tracking.php?$url_params");
    exit();
}

// Display import messages from redirect
if (isset($_GET['import_success'])) {
    $import_message = $_GET['import_success'];
}
if (isset($_GET['import_error'])) {
    $import_error = $_GET['import_error'];
}

// Get fuel records for this vehicle
$stmt = $db->prepare("SELECT * FROM fuel_records WHERE vehicle_id = ? ORDER BY date_recorded DESC");
$stmt->execute([$vehicle_id]);
$fuel_records = $stmt->fetchAll();

// Calculate fuel consumption statistics
$consumption_stats = [];
if (count($fuel_records) >= 2) {
    $sorted_records = array_reverse($fuel_records); // Sort chronologically
    
    for ($i = 1; $i < count($sorted_records); $i++) {
        $prev_record = $sorted_records[$i-1];
        $curr_record = $sorted_records[$i];
        
        $km_driven = $curr_record['mileage'] - $prev_record['mileage'];
        $fuel_used = $prev_record['fuel_amount_liters']; // Fuel used for this distance
        
        if ($km_driven > 0 && $fuel_used > 0) {
            $consumption = ($fuel_used / $km_driven) * 100; // L/100km
            $consumption_stats[] = [
                'from_date' => $prev_record['date_recorded'],
                'to_date' => $curr_record['date_recorded'],
                'km_driven' => $km_driven,
                'fuel_used' => $fuel_used,
                'consumption' => $consumption
            ];
        }
    }
}

// Calculate total costs and average consumption
$total_cost = 0;
$total_fuel = 0;
$total_km = 0;
$avg_consumption = 0;

foreach ($fuel_records as $record) {
    $total_cost += $record['total_cost'];
    $total_fuel += $record['fuel_amount_liters'];
}

if (!empty($consumption_stats)) {
    $total_km = end($fuel_records)['mileage'] - $fuel_records[count($fuel_records)-1]['mileage'];
    if ($total_km > 0) {
        $avg_consumption = ($total_fuel / $total_km) * 100;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Spritkosten - <?= htmlspecialchars($vehicle['marke'] . ' ' . $vehicle['modell']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Spritkosten: <?= htmlspecialchars($vehicle['marke'] . ' ' . $vehicle['modell']) ?></h2>
        <div>
            <a href="vehicle_detail.php?id=<?= $vehicle_id ?>" class="btn btn-secondary">Fahrzeug Details</a>
            <a href="vehicles.php" class="btn btn-secondary">Zurück zur Übersicht</a>
        </div>
    </div>

    <!-- Fuel Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-bg-primary">
                <div class="card-header">Gesamtkosten</div>
                <div class="card-body">
                    <h4><?= number_format($total_cost, 2, ',', '.') ?> €</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-info">
                <div class="card-header">Gesamte Spritmenge</div>
                <div class="card-body">
                    <h4><?= number_format($total_fuel, 1, ',', '.') ?> L</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-success">
                <div class="card-header">Durchschnittsverbrauch</div>
                <div class="card-body">
                    <h4><?= $avg_consumption > 0 ? number_format($avg_consumption, 1, ',', '.') . ' L/100km' : 'N/A' ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-warning">
                <div class="card-header">Anzahl Tankungen</div>
                <div class="card-body">
                    <h4><?= count($fuel_records) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Fuel Record Form -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Tankvorgang hinzufügen</div>
                <div class="card-body">
                    <form method="post">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label for="mileage" class="form-label">Kilometerstand</label>
                                <input type="number" name="mileage" id="mileage" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label for="date_recorded" class="form-label">Datum (DD.MM.YYYY)</label>
                                <input type="text" name="date_recorded" id="date_recorded" class="form-control" placeholder="DD.MM.YYYY" value="<?= date('d.m.Y') ?>" pattern="\d{2}\.\d{2}\.\d{4}" required>
                            </div>
                            <div class="col-md-3">
                                <label for="fuel_price_per_liter" class="form-label">Preis pro Liter (€)</label>
                                <input type="text" step="0.001" name="fuel_price_per_liter" id="fuel_price_per_liter" class="form-control" placeholder="1,650" pattern="[0-9]+([,\.][0-9]+)?" required>
                            </div>
                            <div class="col-md-3">
                                <label for="fuel_amount_liters" class="form-label">Spritmenge (L)</label>
                                <input type="text" step="0.01" name="fuel_amount_liters" id="fuel_amount_liters" class="form-control" placeholder="45,5" pattern="[0-9]+([,\.][0-9]+)?" required>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="fuel_type" class="form-label">Kraftstoffart</label>
                                <select name="fuel_type" id="fuel_type" class="form-select">
                                    <option value="Super">Super</option>
                                    <option value="Super E10">Super E10</option>
                                    <option value="Diesel">Diesel</option>
                                    <option value="Super Premium">Super Premium</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="displayed_consumption" class="form-label">Angezeigter Verbrauch (L/100km)</label>
                                <input type="text" step="0.1" name="displayed_consumption" id="displayed_consumption" class="form-control" placeholder="6,5" pattern="[0-9]+([,\.][0-9]+)?">
                            </div>
                            <div class="col-md-3">
                                <label for="engine_runtime" class="form-label">Motor Laufzeit (min)</label>
                                <input type="number" name="engine_runtime" id="engine_runtime" class="form-control" placeholder="120" min="0">
                            </div>
                            <div class="col-md-3">
                                <label for="notes" class="form-label">Notizen (optional)</label>
                                <input type="text" name="notes" id="notes" class="form-control">
                            </div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-12">
                                <button type="submit" name="add_fuel" class="btn btn-primary">Hinzufügen</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- CSV Import -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">CSV Import</div>
                <div class="card-body">
                    <?php if ($import_message): ?>
                        <div class="alert alert-success" role="alert">
                            <?= htmlspecialchars($import_message) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($import_error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= $import_error ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="csv_file" class="form-label">CSV-Datei auswählen</label>
                                <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                            </div>
                            <div class="form-text">
                                Format: <code>id;datum;kilometer;liter;preiprol</code><br>
                                Datum-Formate: YYYY-MM-DD oder DD.MM.YYYY<br>
                                Beispiel: <code>1;2024-01-15;15000;45.5;1.65</code>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="import_csv" class="btn btn-secondary w-100">CSV importieren</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Consumption Analysis -->
    <?php if (!empty($consumption_stats)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Verbrauchsanalyse</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Zeitraum</th>
                                    <th>Gefahrene Kilometer</th>
                                    <th>Verbrauchte Spritmenge</th>
                                    <th>Verbrauch (L/100km)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consumption_stats as $stat): ?>
                                <tr>
                                    <td><?= date('d.m.Y', strtotime($stat['from_date'])) ?> - <?= date('d.m.Y', strtotime($stat['to_date'])) ?></td>
                                    <td><?= number_format($stat['km_driven'], 0, ',', '.') ?> km</td>
                                    <td><?= number_format($stat['fuel_used'], 2, ',', '.') ?> L</td>
                                    <td><?= number_format($stat['consumption'], 2, ',', '.') ?> L/100km</td>
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

    <!-- Enhanced Analytics Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Kraftstoffpreise Entwicklung</div>
                <div class="card-body">
                    <canvas id="fuelPriceChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Verbrauchstrends</div>
                <div class="card-body">
                    <canvas id="consumptionChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($fuel_records)): ?>
    <!-- Fuel Type Distribution -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Kraftstoffarten Verteilung</div>
                <div class="card-body">
                    <canvas id="fuelTypeChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Monatliche Kosten</div>
                <div class="card-body">
                    <canvas id="monthlyCostChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Chart.js Library with fallback -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script>
        // Check if Chart.js loaded successfully
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js failed to load. Charts will not be displayed.');
            // Hide chart containers if Chart.js is not available
            const chartContainers = ['monthlyFuelChart', 'consumptionChart', 'fuelTypeChart', 'monthlyCostChart'];
            chartContainers.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.parentElement.innerHTML = '<p class="text-muted text-center">Diagramm nicht verfügbar (Chart.js Bibliothek konnte nicht geladen werden)</p>';
                }
            });
        } else {
            // Chart.js is available, proceed with chart creation
            <?php if (!empty($fuel_records)): ?>
            // Fuel Price Development Chart
            const fuelPriceCtx = document.getElementById('fuelPriceChart').getContext('2d');
        
        <?php
        // Prepare data for fuel price chart
        $price_data = [];
        $price_labels = [];
        foreach (array_reverse($fuel_records) as $record) {
            $price_data[] = round($record['fuel_price_per_liter'], 3);
            $price_labels[] = date('d.m.Y', strtotime($record['date_recorded']));
        }
        
        // Prepare consumption data
        $consumption_chart_data = [];
        $consumption_labels = [];
        foreach ($consumption_stats as $stat) {
            $consumption_chart_data[] = round($stat['consumption'], 2);
            $consumption_labels[] = date('d.m', strtotime($stat['to_date']));
        }
        
        // Prepare fuel type distribution
        $fuel_type_counts = [];
        foreach ($fuel_records as $record) {
            $type = $record['fuel_type'] ?? 'Benzin';
            $fuel_type_counts[$type] = ($fuel_type_counts[$type] ?? 0) + 1;
        }
        
        // Prepare monthly costs
        $monthly_costs = [];
        $monthly_labels = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $month_start = date('Y-m-01', strtotime("-$i months"));
            $month_end = date('Y-m-t', strtotime("-$i months"));
            
            $monthly_cost = 0;
            foreach ($fuel_records as $record) {
                if ($record['date_recorded'] >= $month_start && $record['date_recorded'] <= $month_end) {
                    $monthly_cost += $record['total_cost'];
                }
            }
            
            $monthly_costs[] = round($monthly_cost, 2);
            $monthly_labels[] = date('M Y', strtotime($month_start));
        }
        ?>
        
        const fuelPriceChart = new Chart(fuelPriceCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_slice($price_labels, -20)) ?>, // Last 20 entries
                datasets: [{
                    label: 'Preis pro Liter (€)',
                    data: <?= json_encode(array_slice($price_data, -20)) ?>,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Preis (€/L)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true
                    }
                }
            }
        });

        // Consumption Trend Chart
        const consumptionCtx = document.getElementById('consumptionChart').getContext('2d');
        const consumptionChart = new Chart(consumptionCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($consumption_labels) ?>,
                datasets: [{
                    label: 'Verbrauch (L/100km)',
                    data: <?= json_encode($consumption_chart_data) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
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
                    }
                }
            }
        });

        // Fuel Type Distribution Chart
        const fuelTypeCtx = document.getElementById('fuelTypeChart').getContext('2d');
        const fuelTypeChart = new Chart(fuelTypeCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_keys($fuel_type_counts)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($fuel_type_counts)) ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Monthly Cost Chart
        const monthlyCostCtx = document.getElementById('monthlyCostChart').getContext('2d');
        const monthlyCostChart = new Chart(monthlyCostCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthly_labels) ?>,
                datasets: [{
                    label: 'Kosten (€)',
                    data: <?= json_encode($monthly_costs) ?>,
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
                            text: 'Kosten (€)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true
                    }
                }
            }
        });
        <?php else: ?>
        // Show placeholder for empty data
        const chartContainers = ['fuelPriceChart', 'consumptionChart', 'fuelTypeChart', 'monthlyCostChart'];
        chartContainers.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.parentElement.innerHTML = '<p class="text-muted text-center">Keine Daten für Diagramm verfügbar</p>';
            }
        });
        <?php endif; ?>
        } // End of Chart.js available check
    </script>

    <!-- Fuel Records History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Tankhistorie</div>
                <div class="card-body">
                    <?php if (empty($fuel_records)): ?>
                        <p class="text-muted">Noch keine Tankungen erfasst.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Kilometerstand</th>
                                        <th>Kraftstoffart</th>
                                        <th>Preis/L</th>
                                        <th>Menge (L)</th>
                                        <th>Angezeigter Verbrauch</th>
                                        <th>Motor Laufzeit</th>
                                        <th>Gesamtkosten</th>
                                        <th>Notizen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fuel_records as $record): ?>
                                    <tr>
                                        <td><?= date('d.m.Y', strtotime($record['date_recorded'])) ?></td>
                                        <td><?= number_format($record['mileage'], 0, ',', '.') ?> km</td>
                                        <td><?= htmlspecialchars($record['fuel_type'] ?? 'Super') ?></td>
                                        <td><?= number_format($record['fuel_price_per_liter'], 3, ',', '.') ?> €</td>
                                        <td><?= number_format($record['fuel_amount_liters'], 2, ',', '.') ?> L</td>
                                        <td><?= !empty($record['displayed_consumption']) ? number_format($record['displayed_consumption'], 1, ',', '.') . ' L/100km' : '-' ?></td>
                                        <td><?= !empty($record['engine_runtime']) ? $record['engine_runtime'] . ' min' : '-' ?></td>
                                        <td><?= number_format($record['total_cost'], 2, ',', '.') ?> €</td>
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
</body>
</html>