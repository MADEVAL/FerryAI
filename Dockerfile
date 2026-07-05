FROM php:8.5-cli

RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libzip-dev \
    libsodium-dev \
    && docker-php-ext-install ffi pdo_sqlite zip sodium \
    && rm -rf /var/lib/apt/lists/*

COPY . /app
WORKDIR /app

RUN composer install --no-dev --no-progress --prefer-dist

ENV FERRY_AI_BACKEND=auto
ENV FERRY_AI_DEVICE=auto
ENV FERRY_AI_MODEL_CACHE=/app/models

RUN mkdir -p /app/models

ENTRYPOINT ["php", "-r", "require 'vendor/autoload.php'; echo 'FerryAI Docker image ready' . PHP_EOL;"]
