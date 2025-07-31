-- KFZ Vehicle Management Database Schema
-- This file initializes the database with proper tables and constraints

-- Enable foreign key support
PRAGMA foreign_keys = ON;

-- Set up database settings for better performance and reliability
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA temp_store = MEMORY;
PRAGMA mmap_size = 268435456;

-- Create vehicles table with proper constraints
CREATE TABLE IF NOT EXISTS vehicles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    marke TEXT NOT NULL CHECK(length(marke) > 0),
    modell TEXT NOT NULL CHECK(length(modell) > 0),
    kennzeichen TEXT NOT NULL UNIQUE CHECK(length(kennzeichen) > 0),
    baujahr INTEGER NOT NULL CHECK(baujahr > 1900 AND baujahr <= 2030),
    status TEXT NOT NULL CHECK(status IN ('verfügbar', 'in Benutzung', 'wartung')) DEFAULT 'verfügbar',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create users table with proper constraints
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE CHECK(length(username) >= 3),
    password TEXT NOT NULL CHECK(length(password) >= 60), -- bcrypt hash minimum length
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_vehicles_status ON vehicles(status);
CREATE INDEX IF NOT EXISTS idx_vehicles_kennzeichen ON vehicles(kennzeichen);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

-- Create trigger to update updated_at timestamp for vehicles
CREATE TRIGGER IF NOT EXISTS update_vehicles_timestamp 
    AFTER UPDATE ON vehicles
BEGIN
    UPDATE vehicles SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

-- Create trigger to update last_login for users
CREATE TRIGGER IF NOT EXISTS update_user_last_login 
    AFTER UPDATE OF password ON users
BEGIN
    UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

-- Insert default admin user if not exists
INSERT OR IGNORE INTO users (username, password) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password: admin

-- Insert sample vehicles for testing (only if table is empty)
INSERT OR IGNORE INTO vehicles (marke, modell, kennzeichen, baujahr, status) VALUES
('BMW', '320i', 'MU-TEST-001', 2020, 'verfügbar'),
('Audi', 'A4', 'MU-TEST-002', 2019, 'in Benutzung'),
('Mercedes', 'C180', 'MU-TEST-003', 2021, 'wartung');