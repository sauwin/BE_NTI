FROM php:8.4-cli

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git curl zip unzip libzip-dev libxml2-dev libonig-dev \
 && docker-php-ext-install pdo pdo_mysql zip mbstring xml

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]