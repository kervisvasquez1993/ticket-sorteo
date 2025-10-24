# Usar PHP-FPM
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

# Limpiar cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP
RUN docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# Instalar Redis
RUN pecl install redis && docker-php-ext-enable redis

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar PHP-FPM para escuchar en TCP
RUN sed -i 's/listen = \/run\/php\/php8.3-fpm.sock/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf

# Configuración de Nginx
COPY nginx.conf /etc/nginx/sites-available/default

# Directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos
COPY . .

# Instalar dependencias
RUN composer install --no-dev --optimize-autoloader

# Crear directorios necesarios (INCLUYE app/secrets/oauth)
RUN mkdir -p storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache \
    app/secrets/oauth

# Permisos MEJORADOS - www-data debe ser dueño
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 storage \
    && chmod -R 775 bootstrap/cache \
    && chmod -R 775 app/secrets

# Script de inicio MEJORADO
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
echo "Configurando permisos..."\n\
chown -R www-data:www-data /var/www/html/storage\n\
chown -R www-data:www-data /var/www/html/bootstrap/cache\n\
chown -R www-data:www-data /var/www/html/app/secrets\n\
chmod -R 775 /var/www/html/storage\n\
chmod -R 775 /var/www/html/bootstrap/cache\n\
chmod -R 775 /var/www/html/app/secrets\n\
\n\
echo "Limpiando caches..."\n\
php artisan config:clear || true\n\
php artisan cache:clear || true\n\
\n\
echo "Generando llaves de Passport en app/secrets/oauth..."\n\
if [ ! -f /var/www/html/app/secrets/oauth/oauth-private.key ]; then\n\
    php artisan passport:keys --force\n\
    # Mover las llaves generadas a la carpeta correcta\n\
    if [ -f /var/www/html/storage/oauth-private.key ]; then\n\
        mv /var/www/html/storage/oauth-private.key /var/www/html/app/secrets/oauth/\n\
        mv /var/www/html/storage/oauth-public.key /var/www/html/app/secrets/oauth/\n\
        chown www-data:www-data /var/www/html/app/secrets/oauth/*\n\
        chmod 600 /var/www/html/app/secrets/oauth/oauth-private.key\n\
        chmod 644 /var/www/html/app/secrets/oauth/oauth-public.key\n\
    fi\n\
fi\n\
\n\
echo "Cacheando configuración..."\n\
php artisan config:cache || true\n\
php artisan route:cache || true\n\
php artisan view:cache || true\n\
\n\
echo "Iniciando PHP-FPM..."\n\
php-fpm -D\n\
\n\
echo "Iniciando Nginx..."\n\
nginx -g "daemon off;"' > /start.sh && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
