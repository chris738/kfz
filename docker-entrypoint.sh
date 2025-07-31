#!/bin/bash
set -e

echo "KFZ Application: Starting initialization..."

# Run database initialization script
echo "Running database initialization..."
if /usr/local/bin/init-database.sh; then
    echo "Database initialization completed successfully"
else
    echo "Database initialization failed, but continuing..."
fi

# Execute the original command
exec "$@"