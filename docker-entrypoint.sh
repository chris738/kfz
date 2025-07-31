#!/bin/bash
set -e

# Ensure data directory exists and has correct permissions
mkdir -p /var/www/html/data
chown -R www-data:www-data /var/www/html/data
chmod 775 /var/www/html/data

# Fix database file permissions if it exists
if [ -f /var/www/html/data/kfzverwaltung.db ]; then
    chown www-data:www-data /var/www/html/data/kfzverwaltung.db
    chmod 664 /var/www/html/data/kfzverwaltung.db
fi

# Execute the original command
exec "$@"