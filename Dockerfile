FROM php:7.4-apache

# Instalar dependencias y extensiones PHP
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo_mysql zip

# Habilitar mod_rewrite para Apache
RUN a2enmod rewrite

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Establecer el directorio de trabajo
WORKDIR /var/www/html

# Copiar el código de la aplicación
COPY . /var/www/html/

# Dar permisos al directorio de storage
RUN mkdir -p storage/logs storage/cache storage/session storage/exports \
    && chmod -R 775 storage \
    && chown -R www-data:www-data storage

# Instalar dependencias de Composer
RUN composer install --no-dev --optimize-autoloader

# Configurar Apache para usar el directorio public
RUN sed -i 's/DocumentRoot \/var\/www\/html/DocumentRoot \/var\/www\/html\/public/g' /etc/apache2/sites-available/000-default.conf

# Volúmenes para persistencia
VOLUME ["/var/www/html/storage"]

# Exponer el puerto 80
EXPOSE 80

# Iniciar Apache
CMD ["apache2-foreground"]
