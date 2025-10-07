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

# Crear directorios necesarios si no existen
RUN mkdir -p storage/logs \
    && mkdir -p storage/framework/cache \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p bootstrap/cache

# Configurar permisos CORRECTAMENTE
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 storage \
    && chmod -R 775 bootstrap/cache

# Script de inicio mejorado
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
# Asegurar permisos al inicio\n\
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache\n\
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache\n\
\n\
# Limpiar caches\n\
php artisan config:clear\n\
php artisan cache:clear\n\
\n\
# Generar llaves de Passport si no existen\n\
if [ ! -f /var/www/html/storage/oauth-private.key ]; then\n\
    php artisan passport:keys --force\n\
fi\n\
\n\
# Cachear configuración\n\
php artisan config:cache\n\
php artisan route:cache\n\
php artisan view:cache\n\
\n\
# Iniciar servicios\n\
nginx -g "daemon off;" &\n\
php-fpm' > /start.sh && chmod +x /start.sh

EXPOSE 81  # Cambiar de 80 a 81

CMD ["/start.sh"]
