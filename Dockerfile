# Use official PHP image with Apache
FROM php:8.3-apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application files
COPY . /var/www/html/

# Copy and set up entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
COPY init-database.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/init-database.sh

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create directory for SQLite database with proper permissions
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod -R 755 /var/www/html/data

# Expose port 80
EXPOSE 80

# Use custom entrypoint and start Apache
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]