<?php
// SQLite DB-Verbindung
$dbPath = __DIR__ . '/data/kfzverwaltung.db';
$dataDir = dirname($dbPath);
$sqlInitFile = __DIR__ . '/init.sql';

/**
 * Ensure directory exists and is writable with robust error handling
 */
function ensureDirectoryWritable($dir) {
    try {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true)) {
                throw new Exception("Failed to create directory: $dir");
            }
        }
        
        // Multiple attempts to ensure directory is writable
        for ($i = 0; $i < 3; $i++) {
            if (is_writable($dir)) {
                break;
            }
            
            // Try to fix permissions
            @chmod($dir, 0775);
            
            // Try to fix ownership if running as root or with sufficient privileges
            if (function_exists('posix_getuid')) {
                @chown($dir, 'www-data');
                @chgrp($dir, 'www-data');
            }
            
            clearstatcache();
            usleep(100000); // Wait 100ms before retry
        }
        
        if (!is_writable($dir)) {
            throw new Exception("Directory is not writable after permission fixes: $dir");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("KFZ Database Error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Initialize database with SQL file if needed
 */
function initializeDatabaseFromSQL($db, $sqlFile) {
    try {
        if (!file_exists($sqlFile)) {
            error_log("KFZ Database: SQL init file not found: $sqlFile");
            return false;
        }
        
        // Check if database is already initialized by checking for vehicles table
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='vehicles'")->fetch();
        if ($result) {
            // Database already initialized
            return true;
        }
        
        // Read and execute SQL file
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new Exception("Failed to read SQL init file: $sqlFile");
        }
        
        // Execute SQL statements
        $db->exec($sql);
        
        error_log("KFZ Database: Successfully initialized from SQL file");
        return true;
        
    } catch (Exception $e) {
        error_log("KFZ Database SQL Init Error: " . $e->getMessage());
        throw $e;
    }
}
/**
 * Ensure database file has correct permissions
 */
function ensureDatabaseWritable($dbPath) {
    if (!file_exists($dbPath)) {
        return true; // File doesn't exist yet, will be created with correct permissions
    }
    
    try {
        // Multiple attempts to ensure file is writable
        for ($i = 0; $i < 3; $i++) {
            if (is_writable($dbPath)) {
                break;
            }
            
            // Try to fix ownership first (more likely to be the issue)
            if (function_exists('posix_getuid')) {
                @chown($dbPath, 'www-data');
                @chgrp($dbPath, 'www-data');
            }
            
            // Try to fix file permissions - use 664 for better security
            @chmod($dbPath, 0664);
            
            clearstatcache();
            usleep(100000); // Wait 100ms before retry
        }
        
        // If still not writable, try more permissive permissions as fallback
        if (!is_writable($dbPath)) {
            @chmod($dbPath, 0666); // More permissive permissions as fallback
            clearstatcache();
        }
        
        if (!is_writable($dbPath)) {
            // Log details for debugging
            $fileInfo = sprintf(
                "File: %s, Owner: %d, Group: %d, Perms: %o, WWW-Data UID: %d",
                $dbPath,
                fileowner($dbPath),
                filegroup($dbPath),
                fileperms($dbPath) & 0777,
                function_exists('posix_getpwnam') ? posix_getpwnam('www-data')['uid'] : 'unknown'
            );
            error_log("KFZ Database Permission Details: " . $fileInfo);
            
            throw new Exception("Database file is not writable after permission fixes: $dbPath");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("KFZ Database Error: " . $e->getMessage());
        throw $e;
    }
}

// Ensure data directory exists and is writable
ensureDirectoryWritable($dataDir);

// Ensure database file is writable (if it exists)
ensureDatabaseWritable($dbPath);

// Create database connection with error handling
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SQLite specific optimizations for reliability
    $db->exec('PRAGMA journal_mode=WAL');  // Use WAL mode for better concurrency
    $db->exec('PRAGMA synchronous=NORMAL');  // Balance between safety and performance
    $db->exec('PRAGMA temp_store=MEMORY');  // Use memory for temporary tables
    $db->exec('PRAGMA mmap_size=268435456'); // Use memory-mapped I/O (256MB)
    $db->exec('PRAGMA foreign_keys=ON');    // Enable foreign key support
    
} catch (PDOException $e) {
    error_log("KFZ Database Connection Error: " . $e->getMessage());
    throw new Exception("Database connection failed. Please check file permissions.");
}

// Ensure database file has correct permissions after creation
ensureDatabaseWritable($dbPath);

// Initialize database schema from SQL file
initializeDatabaseFromSQL($db, $sqlInitFile);

// Run database migrations
runDatabaseMigrations($db);

/**
 * Run database migrations for new features
 */
function runDatabaseMigrations($db) {
    try {
        // Add fuel_type column if it doesn't exist
        $stmt = $db->query("PRAGMA table_info(fuel_records)");
        $columns = $stmt->fetchAll();
        $hasFuelType = false;
        
        foreach ($columns as $column) {
            if ($column['name'] === 'fuel_type') {
                $hasFuelType = true;
                break;
            }
        }
        
        if (!$hasFuelType) {
            $db->exec("ALTER TABLE fuel_records ADD COLUMN fuel_type TEXT DEFAULT 'Super'");
            error_log("KFZ Database: Added fuel_type column to fuel_records table");
        }
        
        // Add cost_per_km calculated field support
        $stmt = $db->query("PRAGMA table_info(fuel_records)");
        $columns = $stmt->fetchAll();
        $hasCostPerKm = false;
        
        foreach ($columns as $column) {
            if ($column['name'] === 'distance_driven') {
                $hasCostPerKm = true;
                break;
            }
        }
        
        if (!$hasCostPerKm) {
            $db->exec("ALTER TABLE fuel_records ADD COLUMN distance_driven INTEGER DEFAULT NULL");
            error_log("KFZ Database: Added distance_driven column to fuel_records table");
        }
        
        // Add displayed_consumption column if it doesn't exist
        $stmt = $db->query("PRAGMA table_info(fuel_records)");
        $columns = $stmt->fetchAll();
        $hasDisplayedConsumption = false;
        
        foreach ($columns as $column) {
            if ($column['name'] === 'displayed_consumption') {
                $hasDisplayedConsumption = true;
                break;
            }
        }
        
        if (!$hasDisplayedConsumption) {
            $db->exec("ALTER TABLE fuel_records ADD COLUMN displayed_consumption DECIMAL(4,1) DEFAULT NULL");
            error_log("KFZ Database: Added displayed_consumption column to fuel_records table");
        }
        
        // Add engine_runtime column if it doesn't exist
        $stmt = $db->query("PRAGMA table_info(fuel_records)");
        $columns = $stmt->fetchAll();
        $hasEngineRuntime = false;
        
        foreach ($columns as $column) {
            if ($column['name'] === 'engine_runtime') {
                $hasEngineRuntime = true;
                break;
            }
        }
        
        if (!$hasEngineRuntime) {
            $db->exec("ALTER TABLE fuel_records ADD COLUMN engine_runtime INTEGER DEFAULT NULL");
            error_log("KFZ Database: Added engine_runtime column to fuel_records table");
        }
        
        // Remove CHECK constraint on fuel_type by recreating the table (SQLite limitation)
        // First check if constraint exists
        $schema = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='fuel_records'")->fetch();
        if ($schema && strpos($schema['sql'], "CHECK(fuel_type IN ('Benzin'") !== false) {
            error_log("KFZ Database: Removing fuel_type constraint and updating table structure");
            
            // Begin transaction
            $db->beginTransaction();
            
            try {
                // Create backup table with new structure
                $db->exec("CREATE TABLE fuel_records_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    vehicle_id INTEGER NOT NULL,
                    mileage INTEGER NOT NULL CHECK(mileage >= 0),
                    date_recorded DATE NOT NULL,
                    fuel_price_per_liter DECIMAL(5,3) NOT NULL CHECK(fuel_price_per_liter > 0),
                    fuel_amount_liters DECIMAL(6,2) NOT NULL CHECK(fuel_amount_liters > 0),
                    total_cost DECIMAL(8,2) GENERATED ALWAYS AS (fuel_price_per_liter * fuel_amount_liters) STORED,
                    notes TEXT,
                    fuel_type TEXT DEFAULT 'Super',
                    distance_driven INTEGER DEFAULT NULL,
                    displayed_consumption DECIMAL(4,1) DEFAULT NULL,
                    engine_runtime INTEGER DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
                )");
                
                // Copy data from old table to new table
                $db->exec("INSERT INTO fuel_records_new (id, vehicle_id, mileage, date_recorded, fuel_price_per_liter, fuel_amount_liters, notes, fuel_type, distance_driven, displayed_consumption, engine_runtime, created_at)
                          SELECT id, vehicle_id, mileage, date_recorded, fuel_price_per_liter, fuel_amount_liters, notes, 
                                 CASE fuel_type 
                                   WHEN 'Benzin' THEN 'Super'
                                   WHEN 'LPG' THEN 'Super'
                                   WHEN 'CNG' THEN 'Super'
                                   WHEN 'Elektro' THEN 'Super'
                                   WHEN 'Hybrid' THEN 'Super'
                                   ELSE fuel_type 
                                 END, distance_driven, displayed_consumption, engine_runtime, created_at
                          FROM fuel_records");
                
                // Drop old table
                $db->exec("DROP TABLE fuel_records");
                
                // Rename new table
                $db->exec("ALTER TABLE fuel_records_new RENAME TO fuel_records");
                
                // Recreate index
                $db->exec("CREATE INDEX IF NOT EXISTS idx_fuel_vehicle_date ON fuel_records(vehicle_id, date_recorded)");
                
                $db->commit();
                error_log("KFZ Database: Successfully updated fuel_records table structure");
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("KFZ Database: Failed to update table structure: " . $e->getMessage());
            }
        }
        
    } catch (Exception $e) {
        error_log("KFZ Database Migration Error: " . $e->getMessage());
        // Don't throw, as migrations are not critical for basic functionality
    }
}
?>