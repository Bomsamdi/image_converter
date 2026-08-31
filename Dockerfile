# GD has to be compiled with WebP and AVIF; the official image ships neither by
# default, which is why the old compose file pointed at php:7-alpine and could
# not have run this application at all.
FROM php:8.4-cli-alpine

RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS freetype-dev libjpeg-turbo-dev libpng-dev libwebp-dev \
        libavif-dev libzip-dev \
    && apk add --no-cache freetype libjpeg-turbo libpng libwebp libavif libzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install -j"$(nproc)" gd zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

COPY . .

# Uploads have to fit through PHP before the application sees them, and
# post_max_size cannot be raised at runtime - the original tried, in the request
# handler, where ini_set does nothing for it.
RUN printf 'upload_max_filesize=32M\npost_max_size=64M\nmemory_limit=512M\n' \
        > /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
