FROM php:8.5-cli

WORKDIR /

RUN apt-get update && apt-get -y --no-install-recommends install \
    pandoc \
    vim \
    nano \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/* \
    && useradd -m app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mkdir app
COPY ./bin /app/bin
COPY ./src /app/src
COPY ./vendor /app/vendor
COPY ./composer.json /app/composer.json
COPY ./composer.lock /app/composer.lock
RUN chmod -R 755 /app

RUN mkdir /input
RUN mkdir /workspace

ENTRYPOINT ["php", "/app/bin/migrate-confluence"]
