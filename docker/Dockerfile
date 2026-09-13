FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql zip gd exif \
    && echo "date.timezone=America/Sao_Paulo" > /usr/local/etc/php/conf.d/99-timezone.ini \
    && printf "upload_max_filesize=5M\npost_max_size=6M\nmemory_limit=256M\n" > /usr/local/etc/php/conf.d/99-uploads.ini \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
