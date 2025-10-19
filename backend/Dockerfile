# Use official PHP with Apache
FROM php:8.2-apache

# --- Enable Apache modules and allow .htaccess ---
RUN a2enmod rewrite
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# --- Install required PHP extensions ---
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd

# --- Copy backend files ---
COPY . /var/www/html/
WORKDIR /var/www/html/

# --- Set permissions ---
RUN chown -R www-data:www-data /var/www/html

# --- Expose port ---
EXPOSE 80

# --- Start Apache ---
CMD ["apache2-foreground"]
