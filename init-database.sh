#!/bin/bash

# Database initialization script for KFZ application
# This script ensures proper database setup with correct permissions

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() {
    echo -e "${BLUE}[DB-INIT]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[DB-INIT]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[DB-INIT]${NC} $1"
}

print_error() {
    echo -e "${RED}[DB-INIT]${NC} $1"
}

# Configuration
DB_DIR="/var/www/html/data"
DB_FILE="$DB_DIR/kfzverwaltung.db"
SQL_INIT_FILE="/var/www/html/init.sql"
LOCK_FILE="$DB_DIR/.db_initialized"

print_status "Starting database initialization..."

# Function to ensure directory permissions
setup_directory() {
    print_status "Setting up database directory..."
    
    # Create directory if it doesn't exist
    if [ ! -d "$DB_DIR" ]; then
        mkdir -p "$DB_DIR"
        print_status "Created database directory: $DB_DIR"
    fi
    
    # Set proper ownership and permissions
    chown -R www-data:www-data "$DB_DIR"
    chmod 775 "$DB_DIR"
    
    print_success "Database directory setup complete"
}

# Function to fix database file permissions
fix_database_permissions() {
    if [ -f "$DB_FILE" ]; then
        print_status "Fixing database file permissions..."
        
        # Set ownership to www-data
        chown www-data:www-data "$DB_FILE"
        
        # Set permissions to allow read/write for owner and group
        chmod 664 "$DB_FILE"
        
        # Verify permissions
        if [ -w "$DB_FILE" ]; then
            print_success "Database file is writable"
        else
            print_error "Database file is not writable after permission fix"
            return 1
        fi
    fi
}

# Function to initialize database schema
initialize_database() {
    print_status "Initializing database schema..."
    
    # Check if SQL init file exists
    if [ ! -f "$SQL_INIT_FILE" ]; then
        print_error "SQL initialization file not found: $SQL_INIT_FILE"
        return 1
    fi
    
    # Run SQL initialization as www-data user
    if su -s /bin/bash www-data -c "sqlite3 '$DB_FILE' < '$SQL_INIT_FILE'"; then
        print_success "Database schema initialized successfully"
    else
        print_error "Failed to initialize database schema"
        return 1
    fi
}

# Function to verify database
verify_database() {
    print_status "Verifying database setup..."
    
    # Check if tables exist
    if su -s /bin/bash www-data -c "sqlite3 '$DB_FILE' '.tables'" | grep -q "vehicles"; then
        print_success "Vehicles table exists"
    else
        print_error "Vehicles table not found"
        return 1
    fi
    
    if su -s /bin/bash www-data -c "sqlite3 '$DB_FILE' '.tables'" | grep -q "users"; then
        print_success "Users table exists"
    else
        print_error "Users table not found"
        return 1
    fi
    
    # Check if admin user exists
    if su -s /bin/bash www-data -c "sqlite3 '$DB_FILE' 'SELECT COUNT(*) FROM users WHERE username=\"admin\"'" | grep -q "1"; then
        print_success "Admin user exists"
    else
        print_warning "Admin user not found, will be created"
    fi
}

# Function to create admin user with correct password hash
create_admin_user() {
    print_status "Ensuring admin user exists with correct password..."
    
    # Use PHP to create proper password hash and insert user
    su -s /bin/bash www-data -c "php << 'EOF'
<?php
\$db = new PDO('sqlite:$DB_FILE');
\$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Delete any existing admin user first
\$db->exec('DELETE FROM users WHERE username = \"admin\"');

// Create admin user with hashed password
\$hashedPassword = password_hash('admin', PASSWORD_DEFAULT);
\$db->prepare('INSERT INTO users (username, password) VALUES (?, ?)')
    ->execute(['admin', \$hashedPassword]);
echo 'Admin user created with username: admin, password: admin';
?>
EOF"
    
    if [ $? -eq 0 ]; then
        print_success "Admin user setup complete"
    else
        print_error "Failed to setup admin user"
        return 1
    fi
}

# Main execution
main() {
    # Check if already initialized
    if [ -f "$LOCK_FILE" ]; then
        print_status "Database already initialized, checking permissions..."
        setup_directory
        fix_database_permissions
        print_success "Database maintenance complete"
        return 0
    fi
    
    # Full initialization
    setup_directory
    initialize_database
    fix_database_permissions
    verify_database
    create_admin_user
    
    # Create lock file to indicate successful initialization
    touch "$LOCK_FILE"
    chown www-data:www-data "$LOCK_FILE"
    
    print_success "Database initialization completed successfully!"
    print_status "Default login: admin / admin"
}

# Run main function
main "$@"