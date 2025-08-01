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
            $db->exec("ALTER TABLE fuel_records ADD COLUMN fuel_type TEXT DEFAULT 'Benzin' CHECK(fuel_type IN ('Benzin', 'Diesel', 'LPG', 'CNG', 'Elektro', 'Hybrid'))");
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
        
    } catch (Exception $e) {
        error_log("KFZ Database Migration Error: " . $e->getMessage());
        // Don't throw, as migrations are not critical for basic functionality
    }
}
?>