FROM php:8.2-apache

# Install dependencies and PHP extensions (mysqli, pdo_mysql, gd)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Set recommended PHP configuration
RUN { \
    echo 'upload_max_filesize = 25M'; \
    echo 'post_max_size = 30M'; \
    echo 'memory_limit = 256M'; \
    echo 'max_execution_time = 300'; \
    echo 'date.timezone = Asia/Jakarta'; \
} > /usr/local/etc/php/conf.d/custom-php.ini

WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Create necessary upload directories and adjust permissions
RUN mkdir -p /var/www/html/uploads/documents/generated \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads

EXPOSE 80

CMD ["apache2-foreground"]
