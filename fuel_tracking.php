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

// Handle fuel record form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fuel'])) {
    $db->prepare("INSERT INTO fuel_records (vehicle_id, mileage, date_recorded, fuel_price_per_liter, fuel_amount_liters, fuel_type, notes) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $vehicle_id,
            $_POST['mileage'],
            parse_german_date($_POST['date_recorded']),
            parse_german_number($_POST['fuel_price_per_liter']),
            parse_german_number($_POST['fuel_amount_liters']),
            $_POST['fuel_type'] ?? 'Benzin',
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
                    $kilometer_parsed = parse_german_number($kilometer);
                    if ($kilometer_parsed < 0) {
                        $errors[] = "Zeile $row_num: Ungültiger Kilometerstand: $kilometer";
                        $error_count++;
                        continue;
                    }
                    
                    $liter_parsed = parse_german_number($liter);
                    if ($liter_parsed <= 0) {
                        $errors[] = "Zeile $row_num: Ungültige Spritmenge: $liter";
                        $error_count++;
                        continue;
                    }
                    
                    $preiprol_parsed = parse_german_number($preiprol);
                    if ($preiprol_parsed <= 0) {
                        $errors[] = "Zeile $row_num: Ungültiger Preis pro Liter: $preiprol";
                        $error_count++;
                        continue;
                    }
                    
                    // Validate and convert date - now supporting German format
                    $formatted_date = parse_german_date($datum);
                    if (empty($formatted_date)) {
                        // Try other formats if German format fails
                        $date_obj = DateTime::createFromFormat('Y-m-d', $datum);
                        if (!$date_obj) {
                            $date_obj = DateTime::createFromFormat('d/m/Y', $datum);
                        }
                        
                        if (!$date_obj) {
                            $errors[] = "Zeile $row_num: Ungültiges Datum: $datum (Format: DD.MM.YYYY, YYYY-MM-DD oder DD/MM/YYYY)";
                            $error_count++;
                            continue;
                        }
                        $formatted_date = $date_obj->format('Y-m-d');
                    }
                    
                    // Insert into database
                    try {
                        $db->prepare("INSERT INTO fuel_records (vehicle_id, mileage, date_recorded, fuel_price_per_liter, fuel_amount_liters, fuel_type, notes) VALUES (?, ?, ?, ?, ?, ?, ?)")
                            ->execute([
                                $vehicle_id,
                                $kilometer_parsed,
                                $formatted_date,
                                $preiprol_parsed,
                                $liter_parsed,
                                'Benzin', // Default fuel type for CSV import
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

// Calculate overall average consumption if we have at least 2 fuel records
if (count($fuel_records) >= 2) {
    // Sort fuel records by mileage to get the correct range
    $sorted_by_mileage = $fuel_records;
    usort($sorted_by_mileage, function($a, $b) {
        return $a['mileage'] - $b['mileage'];
    });
    
    $min_mileage = $sorted_by_mileage[0]['mileage'];
    $max_mileage = $sorted_by_mileage[count($sorted_by_mileage) - 1]['mileage'];
    $total_km = $max_mileage - $min_mileage;
    
    if ($total_km > 0) {
        $avg_consumption = ($total_fuel / $total_km) * 100;
    }
}

// Prepare data for enhanced charts
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
<!DOCTYPE html>
<html>
<head>
    <title>Spritkosten - <?= htmlspecialchars($vehicle['marke'] . ' ' . $vehicle['modell']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chart-fallback.js"></script>
    <script src="js/chart-config.js"></script>
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
                                <label for="date_recorded" class="form-label">Datum (dd.mm.yyyy)</label>
                                <input type="text" name="date_recorded" id="date_recorded" class="form-control" value="<?= current_german_date() ?>" placeholder="dd.mm.yyyy" pattern="\d{1,2}\.\d{1,2}\.\d{4}" required>
                            </div>
                            <div class="col-md-3">
                                <label for="fuel_price_per_liter" class="form-label">Preis pro Liter (€)</label>
                                <input type="text" name="fuel_price_per_liter" id="fuel_price_per_liter" class="form-control" placeholder="1,45" title="Verwenden Sie Komma als Dezimaltrennzeichen (z.B. 1,45)" required>
                            </div>
                            <div class="col-md-3">
                                <label for="fuel_amount_liters" class="form-label">Spritmenge (L)</label>
                                <input type="text" name="fuel_amount_liters" id="fuel_amount_liters" class="form-control" placeholder="45,20" title="Verwenden Sie Komma als Dezimaltrennzeichen (z.B. 45,20)" required>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="fuel_type" class="form-label">Kraftstoffart</label>
                                <select name="fuel_type" id="fuel_type" class="form-select">
                                    <option value="Benzin">Benzin</option>
                                    <option value="Diesel">Diesel</option>
                                    <option value="LPG">LPG (Autogas)</option>
                                    <option value="CNG">CNG (Erdgas)</option>
                                    <option value="Elektro">Elektro</option>
                                    <option value="Hybrid">Hybrid</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="notes" class="form-label">Notizen (optional)</label>
                                <input type="text" name="notes" id="notes" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" name="add_fuel" class="btn btn-primary w-100">Hinzufügen</button>
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
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-file-earmark-arrow-up"></i> CSV Import
                        <small class="ms-2">Importieren Sie Ihre Tankdaten aus einer CSV-Datei</small>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($import_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($import_message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($import_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> <?= $import_error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data" class="row g-3" id="csvImportForm">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="csv_file" class="form-label">
                                    <i class="bi bi-file-earmark-text"></i> CSV-Datei auswählen
                                </label>
                                <div class="input-group">
                                    <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv,.txt" required>
                                    <label class="btn btn-outline-primary" for="csv_file" id="csvFileButton">
                                        <i class="bi bi-folder2-open"></i> Datei wählen
                                    </label>
                                </div>
                                <div id="fileSelectedInfo" class="mt-2 text-success" style="display: none;">
                                    <i class="bi bi-check-circle"></i> <span id="selectedFileName"></span>
                                </div>
                            </div>
                            <div class="form-text">
                                <strong>Format:</strong> <code>id;datum;kilometer;liter;preiprol</code><br>
                                <strong>Datum-Formate:</strong> YYYY-MM-DD oder DD.MM.YYYY<br>
                                <strong>Beispiel:</strong> <code>1;2024-01-15;15000;45.5;1.65</code><br>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    Klicken Sie auf "Datei wählen" oder wählen Sie eine .csv-Datei direkt aus
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="import_csv" class="btn btn-primary w-100" id="importButton" disabled>
                                <i class="bi bi-upload"></i> CSV importieren
                            </button>
                        </div>
                    </form>
                    
                    <script>
                        // Enhanced CSV file input handling
                        document.addEventListener('DOMContentLoaded', function() {
                            const csvFileInput = document.getElementById('csv_file');
                            const csvFileButton = document.getElementById('csvFileButton');
                            const fileSelectedInfo = document.getElementById('fileSelectedInfo');
                            const selectedFileName = document.getElementById('selectedFileName');
                            const importButton = document.getElementById('importButton');
                            
                            // Label automatically triggers file input, no additional click handler needed
                            // Keep this section for any future enhancements
                            
                            // Handle file selection
                            csvFileInput.addEventListener('change', function() {
                                if (this.files && this.files.length > 0) {
                                    const file = this.files[0];
                                    selectedFileName.textContent = file.name;
                                    fileSelectedInfo.style.display = 'block';
                                    importButton.disabled = false;
                                    
                                    // Update button text
                                    csvFileButton.innerHTML = '<i class="bi bi-check2"></i> Datei gewählt';
                                    csvFileButton.classList.remove('btn-outline-primary');
                                    csvFileButton.classList.add('btn-success');
                                } else {
                                    fileSelectedInfo.style.display = 'none';
                                    importButton.disabled = true;
                                    csvFileButton.innerHTML = '<i class="bi bi-folder2-open"></i> Datei wählen';
                                    csvFileButton.classList.remove('btn-success');
                                    csvFileButton.classList.add('btn-outline-primary');
                                }
                            });
                            
                            // Add drag and drop functionality
                            const dropZone = document.getElementById('csvImportForm');
                            
                            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                                dropZone.addEventListener(eventName, preventDefaults, false);
                            });
                            
                            function preventDefaults(e) {
                                e.preventDefault();
                                e.stopPropagation();
                            }
                            
                            ['dragenter', 'dragover'].forEach(eventName => {
                                dropZone.addEventListener(eventName, highlight, false);
                            });
                            
                            ['dragleave', 'drop'].forEach(eventName => {
                                dropZone.addEventListener(eventName, unhighlight, false);
                            });
                            
                            function highlight(e) {
                                dropZone.classList.add('border-primary', 'border-2');
                            }
                            
                            function unhighlight(e) {
                                dropZone.classList.remove('border-primary', 'border-2');
                            }
                            
                            dropZone.addEventListener('drop', handleDrop, false);
                            
                            function handleDrop(e) {
                                const dt = e.dataTransfer;
                                const files = dt.files;
                                
                                if (files.length > 0) {
                                    const file = files[0];
                                    if (file.type === 'text/csv' || file.name.endsWith('.csv')) {
                                        csvFileInput.files = files;
                                        csvFileInput.dispatchEvent(new Event('change'));
                                    } else {
                                        alert('Bitte wählen Sie eine CSV-Datei aus.');
                                    }
                                }
                            }
                        });
                    </script>
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
                <div class="card-header">
                    <i class="bi bi-graph-up"></i> Kraftstoffpreise Entwicklung 
                    <small class="text-muted">(Scrollbar & Zoombar)</small>
                </div>
                <div class="card-body">
                    <div id="enhancedFuelPriceChart"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-speedometer2"></i> Verbrauchstrends
                    <small class="text-muted">(Erweitert)</small>
                </div>
                <div class="card-body">
                    <div id="enhancedConsumptionChart"></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($fuel_records)): ?>
    <!-- Enhanced Fuel Type Distribution and Monthly Costs -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-pie-chart"></i> Kraftstoffarten Verteilung
                </div>
                <div class="card-body">
                    <div id="enhancedFuelTypeChart"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-bar-chart"></i> Monatliche Kosten 
                    <small class="text-muted">(Scrollbar)</small>
                </div>
                <div class="card-body">
                    <div id="enhancedMonthlyCostChart"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Chart.js Library with fallback -->
    <script>
        // Wait for charts to be ready
        document.addEventListener('chartsReady', function() {
            initializeFuelTrackingCharts();
        });
        
        // Fallback initialization  
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (typeof Chart !== 'undefined') {
                    initializeFuelTrackingCharts();
                } else {
                    handleChartLoadingFailure();
                }
            }, 2000);
        });
        
        function initializeFuelTrackingCharts() {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not available, skipping chart initialization');
                return;
            }
            
            <?php if (!empty($fuel_records)): ?>
            // Enhanced Fuel Price Development Chart
            const fuelPriceData = {
                labels: <?= json_encode(array_slice($price_labels, -20)) ?>, // Last 20 entries
                datasets: [{
                    label: 'Preis pro Liter (€)',
                    data: <?= json_encode(array_slice($price_data, -20)) ?>,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderWidth: 2,
                    tension: 0.1
                }]
            };
            
            createKilometerProgressionChart('enhancedFuelPriceChart', fuelPriceData, {
                title: 'Kraftstoffpreise Entwicklung',
                defaultRange: '6m',
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Preis: ${context.parsed.y.toFixed(3)}€/L`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Preis (€/L)'
                        },
                        beginAtZero: false
                    }
                }
            });
            
            // Enhanced Consumption Chart
            const consumptionData = {
                labels: <?= json_encode($consumption_labels) ?>,
                datasets: [{
                    label: 'Verbrauch (L/100km)',
                    data: <?= json_encode($consumption_chart_data) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            };
            
            createKilometerProgressionChart('enhancedConsumptionChart', consumptionData, {
                title: 'Verbrauchstrends',
                defaultRange: 'all',
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Verbrauch: ${context.parsed.y.toFixed(2)}L/100km`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Verbrauch (L/100km)'
                        },
                        beginAtZero: true
                    }
                }
            });
            
            // Enhanced Fuel Type Distribution Chart - using standard Chart.js for pie chart
            const fuelTypeCtx = document.createElement('canvas');
            fuelTypeCtx.id = 'fuelTypeCanvas';
            document.getElementById('enhancedFuelTypeChart').appendChild(fuelTypeCtx);
            
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
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        title: {
                            display: true,
                            text: 'Kraftstoffarten Verteilung'
                        }
                    }
                }
            });
            
            // Enhanced Monthly Cost Chart
            const monthlyCostData = {
                labels: <?= json_encode($monthly_labels) ?>,
                datasets: [{
                    label: 'Kosten (€)',
                    data: <?= json_encode($monthly_costs) ?>,
                    backgroundColor: 'rgba(153, 102, 255, 0.6)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }]
            };
            
            createKilometerProgressionChart('enhancedMonthlyCostChart', monthlyCostData, {
                title: 'Monatliche Kraftstoffkosten',
                defaultRange: '1y',
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Kosten: ${context.parsed.y.toFixed(2)}€`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Kosten (€)'
                        },
                        beginAtZero: true
                    }
                }
            });
            
            <?php else: ?>
            // Show placeholder for empty data
            const chartContainers = ['enhancedFuelPriceChart', 'enhancedConsumptionChart', 'enhancedFuelTypeChart', 'enhancedMonthlyCostChart'];
            chartContainers.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.innerHTML = '<p class="text-muted text-center"><i class="bi bi-info-circle"></i> Keine Daten für Diagramm verfügbar</p>';
                }
            });
            <?php endif; ?>
        }
        
        function handleChartLoadingFailure() {
            console.warn('Chart.js failed to load. Fuel tracking charts will not be displayed.');
            const chartContainers = ['enhancedFuelPriceChart', 'enhancedConsumptionChart', 'enhancedFuelTypeChart', 'enhancedMonthlyCostChart'];
            chartContainers.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.innerHTML = '<p class="text-muted text-center"><i class="bi bi-exclamation-triangle"></i> Diagramm nicht verfügbar (Chart.js Bibliothek konnte nicht geladen werden)</p>';
                }
            });
        }
    </script>
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
                                        <th>Gesamtkosten</th>
                                        <th>Notizen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fuel_records as $record): ?>
                                    <tr>
                                        <td><?= date('d.m.Y', strtotime($record['date_recorded'])) ?></td>
                                        <td><?= number_format($record['mileage'], 0, ',', '.') ?> km</td>
                                        <td><?= htmlspecialchars($record['fuel_type'] ?? 'Benzin') ?></td>
                                        <td><?= number_format($record['fuel_price_per_liter'], 3, ',', '.') ?> €</td>
                                        <td><?= number_format($record['fuel_amount_liters'], 2, ',', '.') ?> L</td>
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