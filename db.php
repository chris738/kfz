<?php
// SQLite DB-Verbindung
$dbPath = __DIR__ . '/data/kfzverwaltung.db';
$dataDir = dirname($dbPath);

// Ensure data directory exists with proper permissions
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
    // Set ownership to www-data if running in web context
    if (function_exists('posix_getuid') && posix_getuid() === 0) {
        chown($dataDir, 'www-data');
        chgrp($dataDir, 'www-data');
    }
}

// Ensure directory is writable
if (!is_writable($dataDir)) {
    chmod($dataDir, 0775);
}

// Create database connection
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ensure proper permissions for database file after creation
if (file_exists($dbPath)) {
    // Make sure database file is writable
    chmod($dbPath, 0664);
    
    // Set ownership to www-data if running as root
    if (function_exists('posix_getuid') && posix_getuid() === 0) {
        chown($dbPath, 'www-data');
        chgrp($dbPath, 'www-data');
    }
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