#!/bin/bash

# Create default user for KFZ application
# This script should be run after the application is started

echo "Setting up default user for KFZ application..."

# Wait for the application to be ready
sleep 5

# Create default user (admin/admin) if not exists
docker exec kfz-webapp php -r "
require '/var/www/html/db.php';
\$stmt = \$db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
\$stmt->execute(['admin']);
if (\$stmt->fetchColumn() == 0) {
    \$hashedPassword = password_hash('admin', PASSWORD_DEFAULT);
    \$db->prepare('INSERT INTO users (username, password) VALUES (?, ?)')
        ->execute(['admin', \$hashedPassword]);
    echo 'Default user created: admin/admin\n';
} else {
    echo 'Default user already exists\n';
}
"

echo "Setup complete!"