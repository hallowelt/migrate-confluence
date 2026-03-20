FROM php:8.5-cli

WORKDIR /

RUN apt-get update && apt-get -y --no-install-recommends install \
    pandoc \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY ./docker/php/php.ini /usr/local/etc/php/php.ini

RUN mkdir app
COPY ./bin /app/bin
COPY ./src /app/src
COPY ./composer.json /app/composer.json
COPY ./LICENSE /app/LICENSE
COPY ./README.md /app/README.md
RUN chmod -R 755 /app

WORKDIR /app
RUN composer update --prefer-source --no-dev --optimize-autoloader
WORKDIR /

RUN mkdir /data

ENTRYPOINT ["php", "/app/bin/migrate-confluence"]
