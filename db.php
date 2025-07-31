<?php
// SQLite DB-Verbindung
$db = new PDO('sqlite:kfzverwaltung.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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