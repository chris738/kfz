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
    $db->prepare("INSERT INTO fuel_records (vehicle_id, mileage, date_recorded, fuel_price_per_liter, fuel_amount_liters, notes) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([
            $vehicle_id,
            $_POST['mileage'],
            $_POST['date_recorded'],
            $_POST['fuel_price_per_liter'],
            $_POST['fuel_amount_liters'],
            $_POST['notes'] ?? ''
        ]);
    header("Location: fuel_tracking.php?id=" . $vehicle_id);
    exit();
}

// Handle fuel record edit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_fuel'])) {
    $db->prepare("UPDATE fuel_records SET mileage = ?, date_recorded = ?, fuel_price_per_liter = ?, fuel_amount_liters = ?, notes = ? WHERE id = ? AND vehicle_id = ?")
        ->execute([
            $_POST['mileage'],
            $_POST['date_recorded'],
            $_POST['fuel_price_per_liter'],
            $_POST['fuel_amount_liters'],
            $_POST['notes'] ?? '',
            $_POST['fuel_id'],
            $vehicle_id
        ]);
    header("Location: fuel_tracking.php?id=" . $vehicle_id);
    exit();
}

// Handle fuel record deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_fuel'])) {
    $db->prepare("DELETE FROM fuel_records WHERE id = ? AND vehicle_id = ?")
        ->execute([$_POST['fuel_id'], $vehicle_id]);
    header("Location: fuel_tracking.php?id=" . $vehicle_id);
    exit();
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
                    <form method="post" class="row g-3">
                        <div class="col-md-2">
                            <label for="mileage" class="form-label">Kilometerstand</label>
                            <input type="number" name="mileage" id="mileage" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label for="date_recorded" class="form-label">Datum</label>
                            <input type="date" name="date_recorded" id="date_recorded" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label for="fuel_price_per_liter" class="form-label">Preis pro Liter (€)</label>
                            <input type="number" step="0.001" name="fuel_price_per_liter" id="fuel_price_per_liter" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label for="fuel_amount_liters" class="form-label">Spritmenge (L)</label>
                            <input type="number" step="0.01" name="fuel_amount_liters" id="fuel_amount_liters" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label for="notes" class="form-label">Notizen (optional)</label>
                            <input type="text" name="notes" id="notes" class="form-control">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" name="add_fuel" class="btn btn-primary w-100">Hinzufügen</button>
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
                                        <th>Preis/L</th>
                                        <th>Menge (L)</th>
                                        <th>Gesamtkosten</th>
                                        <th>Notizen</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fuel_records as $record): ?>
                                    <tr>
                                        <td><?= date('d.m.Y', strtotime($record['date_recorded'])) ?></td>
                                        <td><?= number_format($record['mileage'], 0, ',', '.') ?> km</td>
                                        <td><?= number_format($record['fuel_price_per_liter'], 3, ',', '.') ?> €</td>
                                        <td><?= number_format($record['fuel_amount_liters'], 2, ',', '.') ?> L</td>
                                        <td><?= number_format($record['total_cost'], 2, ',', '.') ?> €</td>
                                        <td><?= htmlspecialchars($record['notes'] ?? '') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary me-1" onclick="editFuelRecord(<?= $record['id'] ?>, '<?= $record['date_recorded'] ?>', <?= $record['mileage'] ?>, <?= $record['fuel_price_per_liter'] ?>, <?= $record['fuel_amount_liters'] ?>, '<?= htmlspecialchars($record['notes'] ?? '', ENT_QUOTES) ?>')">
                                                Bearbeiten
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteFuelRecord(<?= $record['id'] ?>)">
                                                Löschen
                                            </button>
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

    <!-- Edit Fuel Record Modal -->
    <div class="modal" id="editFuelModal" tabindex="-1" style="display: none; background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editFuelModalLabel">Tankvorgang bearbeiten</h5>
                    <button type="button" class="btn-close" onclick="closeEditModal()" aria-label="Close">×</button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="fuel_id" id="edit_fuel_id">
                        <div class="mb-3">
                            <label for="edit_mileage" class="form-label">Kilometerstand</label>
                            <input type="number" name="mileage" id="edit_mileage" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_date_recorded" class="form-label">Datum</label>
                            <input type="date" name="date_recorded" id="edit_date_recorded" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_fuel_price_per_liter" class="form-label">Preis pro Liter (€)</label>
                            <input type="number" step="0.001" name="fuel_price_per_liter" id="edit_fuel_price_per_liter" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_fuel_amount_liters" class="form-label">Spritmenge (L)</label>
                            <input type="number" step="0.01" name="fuel_amount_liters" id="edit_fuel_amount_liters" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_notes" class="form-label">Notizen (optional)</label>
                            <input type="text" name="notes" id="edit_notes" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Abbrechen</button>
                        <button type="submit" name="edit_fuel" class="btn btn-primary">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS for modals -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function editFuelRecord(id, date, mileage, pricePerLiter, amount, notes) {
        document.getElementById('edit_fuel_id').value = id;
        document.getElementById('edit_date_recorded').value = date;
        document.getElementById('edit_mileage').value = mileage;
        document.getElementById('edit_fuel_price_per_liter').value = pricePerLiter;
        document.getElementById('edit_fuel_amount_liters').value = amount;
        document.getElementById('edit_notes').value = notes;
        
        document.getElementById('editFuelModal').style.display = 'block';
    }

    function closeEditModal() {
        document.getElementById('editFuelModal').style.display = 'none';
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        var modal = document.getElementById('editFuelModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    function deleteFuelRecord(id) {
        if (confirm('Möchten Sie diesen Tankvorgang wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            var fuelIdInput = document.createElement('input');
            fuelIdInput.type = 'hidden';
            fuelIdInput.name = 'fuel_id';
            fuelIdInput.value = id;
            
            var deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_fuel';
            deleteInput.value = '1';
            
            form.appendChild(fuelIdInput);
            form.appendChild(deleteInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>