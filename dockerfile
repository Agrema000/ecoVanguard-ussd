# Use official lightweight PHP-Apache image
FROM php:8.2-apache

# Install PDO MySQL extensions for your db.php connection
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite module if needed later
RUN a2enmod rewrite

# Copy all your project files directly into the web server directory
COPY . /var/www/html/

# Set correct permissions for the web root
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 for traffic
EXPOSE 80