#!/bin/sh
set -e

# Crear carpetas requeridas de Laravel si no existen
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# Ajustar permisos para www-data
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Enlace simbólico de storage público
if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link --force || true
fi

# Ejecutar comando principal (supervisord)
exec "$@"
