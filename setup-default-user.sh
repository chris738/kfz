#!/bin/bash

# Setup script for KFZ application
# This script ensures the database is properly initialized and accessible

echo "Setting up KFZ application..."

# Wait for the application to be ready
sleep 5

# Run database initialization inside the container
echo "Running database initialization inside container..."
if docker exec kfz-webapp /usr/local/bin/init-database.sh; then
    echo "Database initialization completed successfully"
else
    echo "Database initialization encountered issues, attempting fallback..."
    
    # Fallback: Ensure basic functionality
    docker exec kfz-webapp chown -R www-data:www-data /var/www/html/data/
    docker exec kfz-webapp chmod 775 /var/www/html/data/
    docker exec kfz-webapp chmod 664 /var/www/html/data/*.db 2>/dev/null || true
fi

# Test database connectivity
echo "Testing database connectivity..."
docker exec kfz-webapp php -r "
try {
    require '/var/www/html/db.php';
    echo 'Database connection successful\n';
    
    // Test basic query
    \$stmt = \$db->prepare('SELECT COUNT(*) as count FROM vehicles');
    \$stmt->execute();
    \$result = \$stmt->fetch(PDO::FETCH_ASSOC);
    echo 'Vehicle count: ' . \$result['count'] . '\n';
    
    \$stmt = \$db->prepare('SELECT COUNT(*) as count FROM users');
    \$stmt->execute();
    \$result = \$stmt->fetch(PDO::FETCH_ASSOC);
    echo 'User count: ' . \$result['count'] . '\n';
    
} catch (Exception \$e) {
    echo 'Database error: ' . \$e->getMessage() . '\n';
    exit(1);
}
"

echo "Setup complete!"