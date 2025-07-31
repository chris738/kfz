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

-- Create mileage tracking table for Issue #3
CREATE TABLE IF NOT EXISTS mileage_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    mileage INTEGER NOT NULL CHECK(mileage >= 0),
    date_recorded DATE NOT NULL,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- Create fuel cost tracking table for Issue #4  
CREATE TABLE IF NOT EXISTS fuel_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    mileage INTEGER NOT NULL CHECK(mileage >= 0),
    date_recorded DATE NOT NULL,
    fuel_price_per_liter DECIMAL(5,3) NOT NULL CHECK(fuel_price_per_liter > 0),
    fuel_amount_liters DECIMAL(6,2) NOT NULL CHECK(fuel_amount_liters > 0),
    total_cost DECIMAL(8,2) GENERATED ALWAYS AS (fuel_price_per_liter * fuel_amount_liters) STORED,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- Create maintenance records table for Issue #5
CREATE TABLE IF NOT EXISTS maintenance_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    maintenance_type TEXT NOT NULL CHECK(maintenance_type IN ('kleine_wartung', 'grosse_wartung', 'tuev', 'hu', 'other')),
    date_performed DATE NOT NULL,
    mileage INTEGER CHECK(mileage >= 0),
    cost DECIMAL(8,2) NOT NULL CHECK(cost >= 0),
    description TEXT,
    next_maintenance_km INTEGER CHECK(next_maintenance_km >= 0),
    next_maintenance_date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- Create indexes for better performance on new tables
CREATE INDEX IF NOT EXISTS idx_mileage_vehicle_date ON mileage_records(vehicle_id, date_recorded);
CREATE INDEX IF NOT EXISTS idx_fuel_vehicle_date ON fuel_records(vehicle_id, date_recorded);
CREATE INDEX IF NOT EXISTS idx_maintenance_vehicle_date ON maintenance_records(vehicle_id, date_performed);
CREATE INDEX IF NOT EXISTS idx_maintenance_next_date ON maintenance_records(next_maintenance_date);

-- Insert sample vehicles for testing (only if table is empty)
INSERT OR IGNORE INTO vehicles (marke, modell, kennzeichen, baujahr, status) VALUES
('BMW', '320i', 'MU-TEST-001', 2020, 'verfügbar'),
('Audi', 'A4', 'MU-TEST-002', 2019, 'in Benutzung'),
('Mercedes', 'C180', 'MU-TEST-003', 2021, 'wartung');