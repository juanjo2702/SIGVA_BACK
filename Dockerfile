FROM php:8.4-fpm-alpine

# Instalar dependencias del sistema, Nginx y Supervisor
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev

# Instalar instalador de extensiones PHP oficial
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Instalar extensiones requeridas por Laravel, dompdf y maatwebsite/excel
RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache \
    intl \
    iconv

# Copiar Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Copiar definiciones de dependencias primero para optimizar cache de capas
COPY composer.json composer.lock* ./

# Instalar dependencias PHP de producción sin ejecutar scripts aún
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --no-autoloader --ignore-platform-reqs

# Copiar el código fuente completo de la aplicación
COPY . .

# Limpiar caches residuales y regenerar autoloader optimizado sin scripts de artisan
RUN rm -f bootstrap/cache/*.php \
    && composer dump-autoload --optimize --no-dev --no-scripts

# Configurar Nginx, Supervisor y Entrypoint
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Permisos para el usuario web
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8002

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
