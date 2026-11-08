FROM composer:2 AS composer
FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/* && docker-php-ext-install pdo_mysql
COPY --from=composer /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN --mount=type=cache,target=/tmp/cache composer install --no-dev --prefer-dist --no-interaction && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data bootstrap/cache
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/router.php"]
