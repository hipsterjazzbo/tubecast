FROM docker.io/fhfa/yt-dlp:2026.3.17 AS tools

FROM docker.io/node:22-alpine AS assets

WORKDIR /build

ENV TEMPEST_PLUGIN_CONFIGURATION_PATH=vite.tempest.json

COPY package.json package-lock.json vite.config.ts vite.tempest.json ./
RUN npm ci

COPY app ./app
RUN npm run build

FROM docker.io/composer:2 AS composer

FROM docker.io/dunglas/frankenphp:1-php8.5-trixie

ARG PUID=33
ARG PGID=33
ARG COMPOSER_DEV=0

USER root

RUN apt-get update \
    && apt-get install -y --no-install-recommends gosu supervisor unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

# fhfa ships yt-dlp, patched ffmpeg/ffprobe, deno, and a private Python stack under /usr/local.
COPY --from=tools /usr/local /opt/fhfa/usr/local

ENV PATH="/opt/fhfa/usr/local/bin:${PATH}" \
    LD_LIBRARY_PATH="/opt/fhfa/usr/local/lib:${LD_LIBRARY_PATH}" \
    YT_DLP_BINARY=yt-dlp \
    SERVER_NAME=":8080"

RUN install-php-extensions intl zip \
    && for bin in python python3 python3.14 yt-dlp ffmpeg ffprobe deno; do \
        if [ -x "/opt/fhfa/usr/local/bin/${bin}" ]; then \
            ln -sf "/opt/fhfa/usr/local/bin/${bin}" "/usr/local/bin/${bin}"; \
        fi; \
    done

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN if [ "$COMPOSER_DEV" = "1" ]; then \
        composer install --optimize-autoloader --no-interaction --no-scripts; \
    else \
        composer install --no-dev --optimize-autoloader --no-interaction --no-scripts; \
    fi

COPY . .
COPY --from=assets /build/public/build ./public/build
RUN composer dump-autoload --optimize && php tempest discovery:generate --no-interaction

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/supervisord.conf /etc/supervisor/conf.d/tubecast.conf
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/tubecast-entrypoint

RUN chown -R www-data:www-data /var/www/html

# Root at runtime so init can remap PUID/PGID and chown volumes; services run as www-data.
USER root

ENTRYPOINT ["tubecast-entrypoint"]
EXPOSE 8080
