FROM fhfa/yt-dlp:2026.3.17 AS tools

FROM serversideup/php:8.5-fpm-nginx

ARG PUID=33
ARG PGID=33

USER root

# fhfa ships yt-dlp, patched ffmpeg/ffprobe, deno, and a private Python stack under /usr/local.
COPY --from=tools /usr/local /opt/fhfa/usr/local

ENV PATH="/opt/fhfa/usr/local/bin:${PATH}" \
    LD_LIBRARY_PATH="/opt/fhfa/usr/local/lib:${LD_LIBRARY_PATH}" \
    YT_DLP_BINARY=yt-dlp

RUN install-php-extensions intl \
    && for bin in python python3 python3.14 yt-dlp ffmpeg ffprobe deno; do \
        ln -sf "/opt/fhfa/usr/local/bin/${bin}" "/usr/local/bin/${bin}"; \
    done

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .
RUN composer dump-autoload --optimize && php tempest discovery:generate --no-interaction

COPY docker/nginx/tubecast.conf /etc/nginx/server-opts.d/tubecast.conf
COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/
RUN docker-php-serversideup-s6-init
COPY --chmod=755 docker/s6-overlay/ /etc/s6-overlay/

RUN docker-php-serversideup-set-id www-data "${PUID}:${PGID}" \
    && docker-php-serversideup-set-file-permissions --owner "${PUID}:${PGID}" \
    && chown -R www-data:www-data /var/www/html

# Root at runtime so init can remap PUID/PGID and chown volumes; nginx/php-fpm still run as www-data.
USER root

EXPOSE 8080
