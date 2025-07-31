<?php
// SQLite DB-Verbindung
$dbPath = __DIR__ . '/data/kfzverwaltung.db';
$dataDir = dirname($dbPath);

// Ensure data directory exists
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Ensure proper permissions for database file if it exists
if (file_exists($dbPath)) {
    // Check if file is writable, if not try to fix permissions
    if (!is_writable($dbPath)) {
        chmod($dbPath, 0664);
    }
}

$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// If database was just created, ensure proper permissions
if (file_exists($dbPath) && !is_writable($dbPath)) {
    chmod($dbPath, 0664);
}

// Tabelle erzeugen, falls nicht vorhanden
$db->exec("CREATE TABLE IF NOT EXISTS vehicles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    marke TEXT,
    modell TEXT,
    kennzeichen TEXT,
    baujahr INTEGER,
    status TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT,
    password TEXT
)");
?>