# Use an official web server that handles PHP, HTML, CSS, and JS
FROM php:8.4-apache

# Install the drivers needed to talk to external databases
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy all files from your GitHub repository into the server
COPY . /var/www/html/

# Expose port 80 for web traffic
EXPOSE 80
