# Usar PHP-FPM
FROM php:8.3-fpm

# Instalar dependencias del sistema (AGREGAMOS supervisor)
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
    nginx \
    supervisor

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

# Crear directorios necesarios
RUN mkdir -p storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache \
    app/secrets/oauth

# Permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 storage \
    && chmod -R 775 bootstrap/cache \
    && chmod -R 775 app/secrets

# Configuración de Supervisor para Laravel Queue Worker
RUN echo '[supervisord]\n\
nodaemon=true\n\
logfile=/var/www/html/storage/logs/supervisord.log\n\
pidfile=/var/run/supervisord.pid\n\
\n\
[program:php-fpm]\n\
command=/usr/local/sbin/php-fpm -F\n\
autostart=true\n\
autorestart=true\n\
priority=5\n\
stdout_logfile=/var/www/html/storage/logs/php-fpm.log\n\
stderr_logfile=/var/www/html/storage/logs/php-fpm-error.log\n\
\n\
[program:nginx]\n\
command=/usr/sbin/nginx -g "daemon off;"\n\
autostart=true\n\
autorestart=true\n\
priority=10\n\
stdout_logfile=/var/www/html/storage/logs/nginx.log\n\
stderr_logfile=/var/www/html/storage/logs/nginx-error.log\n\
\n\
[program:laravel-worker]\n\
process_name=%(program_name)s_%(process_num)02d\n\
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=90\n\
autostart=true\n\
autorestart=true\n\
stopasgroup=true\n\
killasgroup=true\n\
user=www-data\n\
numprocs=1\n\
redirect_stderr=true\n\
stdout_logfile=/var/www/html/storage/logs/worker.log\n\
stopwaitsecs=3600\n\
priority=15' > /etc/supervisor/conf.d/supervisord.conf

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
echo "Iniciando servicios con Supervisor..."\n\
/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf' > /start.sh && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
