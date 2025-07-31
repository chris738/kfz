#!/bin/bash

# KFZ Application Deployment Script
# This script automatically pulls the latest code, restarts the service, and reloads the database

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to show usage
show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --reset-db    Reset database (removes all existing data)"
    echo "  --help        Show this help message"
    echo ""
    echo "This script will:"
    echo "  1. Pull the latest code from git"
    echo "  2. Stop and restart the Docker containers"
    echo "  3. Reload/setup the database"
}

# Parse command line arguments
RESET_DB=false
while [[ $# -gt 0 ]]; do
    case $1 in
        --reset-db)
            RESET_DB=true
            shift
            ;;
        --help)
            show_usage
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            show_usage
            exit 1
            ;;
    esac
done

print_status "Starting KFZ application deployment..."

# Step 1: Pull latest code
print_status "Step 1: Pulling latest code from git repository..."
# Get current branch name
CURRENT_BRANCH=$(git branch --show-current)
print_status "Current branch: $CURRENT_BRANCH"

if git pull origin "$CURRENT_BRANCH"; then
    print_success "Code updated successfully"
else
    print_error "Failed to pull latest code"
    exit 1
fi

# Step 2: Stop existing containers
print_status "Step 2: Stopping existing Docker containers..."
if docker compose down; then
    print_success "Containers stopped successfully"
else
    print_warning "No containers were running or failed to stop"
fi

# Step 3: Remove any existing containers with the same name
print_status "Step 3: Removing any existing containers with conflicting names..."
if docker ps -a --format "{{.Names}}" | grep -q "^kfz-webapp$"; then
    docker rm -f kfz-webapp >/dev/null 2>&1
    print_success "Existing container 'kfz-webapp' removed successfully"
else
    print_status "No existing container 'kfz-webapp' found to remove"
fi

# Step 4: Handle database reset if requested
if [ "$RESET_DB" = true ]; then
    print_status "Step 4a: Resetting database (removing existing data)..."
    if docker volume rm kfz_kfz_data 2>/dev/null; then
        print_success "Database volume removed successfully"
    else
        print_warning "Database volume was already removed or didn't exist"
    fi
fi

# Step 5: Rebuild and restart containers
print_status "Step 5: Rebuilding and starting Docker containers..."
if docker compose up -d --build; then
    print_success "Containers built and started successfully"
else
    print_error "Failed to build and start containers"
    exit 1
fi

# Step 6: Wait for application to be ready
print_status "Step 6: Waiting for application to be ready..."
sleep 10

# Step 7: Setup database
print_status "Step 7: Setting up database and default user..."
if ./setup-default-user.sh; then
    print_success "Database setup completed successfully"
else
    print_error "Failed to setup database"
    exit 1
fi

# Step 8: Verify deployment
print_status "Step 8: Verifying deployment..."
if docker ps | grep -q kfz-webapp; then
    print_success "Application is running successfully"
    print_status "Application URL: http://localhost:8080"
    print_status "Default login: admin / admin"
else
    print_error "Application container is not running"
    exit 1
fi

print_success "Deployment completed successfully!"
print_status "The KFZ application has been updated and is ready to use."