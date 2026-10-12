#
# Build Step
#
FROM composer:2.10.3 AS build

WORKDIR /app

# fetch external libraries
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-autoloader

# copy our code now, so that Composer can create the autoloader
COPY --chown=0:0 --chmod=a=rx ./bin/migrate-confluence ./bin/migrate-confluence
COPY --chown=0:0 --chmod=a=rX ./src ./src
COPY --chown=0:0 --chmod=a=r ./LICENSE ./LICENSE
COPY --chown=0:0 --chmod=a=r ./README.md ./README.md
COPY --chown=0:0 --chmod=a=r ./VERSION ./VERSION

# create the autoloader
RUN composer dump-autoload --no-dev --classmap-authoritative


#
# Main Image
#
FROM php:8.5.11-cli

# install pandoc
RUN apt-get update && \
    apt-get -y --no-install-recommends install pandoc && \
    rm -rf /var/lib/apt/lists/*

# set our php.ini settings
COPY --chown=0:0 --chmod=a=r ./docker/php/php.ini /usr/local/etc/php/php.ini

# set a non-root user and group for security
ARG UID=10001
ARG GID=10001

# set up the user, group and the /data directory
RUN groupadd -g "${GID}" app && \
    useradd -u "${UID}" -g "${GID}" \
        --no-create-home \
        --no-log-init \
        --shell /usr/sbin/nologin \
        app && \
    install -d -o "${UID}" -g 0 -m 775 /data

# fetch the built final code from the "build" step
COPY --from=build --chown=0:0 /app /app

# set a writable home directory
ENV HOME=/tmp
# work in /data, our suggested mountpoint for data
WORKDIR /data
# use the non-privileged user
USER ${UID}:${GID}
# run our main command as default
ENTRYPOINT ["php", "/app/bin/migrate-confluence"]
