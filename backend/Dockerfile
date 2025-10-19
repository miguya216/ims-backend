FROM php:8.2-apache

# Copy source code to /var/www/html
COPY . /var/www/html/

# Install dependencies if needed
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Expose port (default for Apache)
EXPOSE 80
