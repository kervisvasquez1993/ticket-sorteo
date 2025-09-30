# Usar PHP-FPM en lugar de Apache
FROM php:8.3-fpm

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    nginx

# Limpiar cache de apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar extensiones de PHP
RUN docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# Instalar extensión de Redis (importante para tu configuración)
RUN pecl install redis && docker-php-ext-enable redis

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuración de Nginx
COPY nginx.conf /etc/nginx/sites-available/default

# Establecer directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos del proyecto
COPY . .

# Instalar dependencias de Composer
RUN composer install --no-dev --optimize-autoloader

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Script de inicio
RUN echo '#!/bin/bash\n\
php artisan config:cache\n\
php artisan route:cache\n\
nginx -g "daemon off;" &\n\
php-fpm' > /start.sh && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
