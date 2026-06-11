#!/bin/sh
set -e

APP_DIR="${APP_BASE_DIR:-/var/www/html}"
ENV_TARGET="/data/config/.env"
PUID="${PUID:-33}"
PGID="${PGID:-33}"

as_www_data() {
    if [ "$(id -u)" -eq 0 ]; then
        gosu www-data "$@"
    else
        "$@"
    fi
}

remap_www_data() {
    if [ "$(id -u)" -ne 0 ]; then
        return 0
    fi

    have_uid="$(id -u www-data)"
    have_gid="$(id -g www-data)"

    if [ "${PUID}" = "${have_uid}" ] && [ "${PGID}" = "${have_gid}" ]; then
        return 0
    fi

    if ! getent group www-data >/dev/null 2>&1; then
        groupadd -g "${PGID}" www-data
    else
        groupmod -o -g "${PGID}" www-data
    fi

    if ! getent passwd www-data >/dev/null 2>&1; then
        useradd -u "${PUID}" -g www-data -d /var/www/html -s /usr/sbin/nologin www-data
    else
        usermod -o -u "${PUID}" -g "${PGID}" www-data
    fi
}

tubecast_bootstrap() {
    if [ -z "${ADMIN_PASSWORD:-}" ]; then
        echo "tubecast init: ADMIN_PASSWORD is required. Set ADMIN_PASSWORD in docker-compose.yml or a .env file." >&2
        exit 1
    fi

    if [ -z "${ADMIN_USERNAME:-}" ]; then
        ADMIN_USERNAME=admin
        export ADMIN_USERNAME
    fi

    mkdir -p /data/downloads /data/podcast /data/config /data/stored-commands
    mkdir -p "${APP_DIR}/.tempest/logs" "${APP_DIR}/.tempest/cache" "${APP_DIR}/.tempest/sessions" \
        "${APP_DIR}/.tempest/scheduler"

    if [ ! -f "${DB_DATABASE:-/data/database.sqlite}" ]; then
        touch "${DB_DATABASE:-/data/database.sqlite}"
    fi

    existing_signing=""
    if [ -f "${ENV_TARGET}" ]; then
        existing_signing=$(grep -m1 '^SIGNING_KEY=' "${ENV_TARGET}" 2>/dev/null | cut -d= -f2- || true)
    fi

    cat > "${ENV_TARGET}" <<EOF
ENVIRONMENT=${ENVIRONMENT:-local}
SIGNING_KEY=${existing_signing}
BASE_URI=${BASE_URI:-http://localhost:8742}
INTERNAL_CACHES=${INTERNAL_CACHES:-true}
DISCOVERY_CACHE=${DISCOVERY_CACHE:-partial}
DEBUG_LOG_PATH=${DEBUG_LOG_PATH:-null}
SERVER_LOG_PATH=${SERVER_LOG_PATH:-null}
DB_DATABASE=${DB_DATABASE:-/data/database.sqlite}
DATA_PATH=${DATA_PATH:-/data}
DOWNLOADS_PATH=${DOWNLOADS_PATH:-/data/downloads}
PODCAST_PATH=${PODCAST_PATH:-/data/podcast}
YT_DLP_BINARY=${YT_DLP_BINARY:-yt-dlp}
YT_DLP_WORKER_CONCURRENCY=${YT_DLP_WORKER_CONCURRENCY:-1}
YT_DLP_SLEEP_INTERVAL=${YT_DLP_SLEEP_INTERVAL:-5}
YT_DLP_SLEEP_REQUESTS=${YT_DLP_SLEEP_REQUESTS:-1}
YT_DLP_LIMIT_RATE=${YT_DLP_LIMIT_RATE:-}
YOUTUBE_API_KEY=${YOUTUBE_API_KEY:-}
ADMIN_USERNAME=${ADMIN_USERNAME}
ADMIN_PASSWORD=${ADMIN_PASSWORD}
EOF

    ln -sf "${ENV_TARGET}" "${APP_DIR}/.env"

    if [ "$(id -u)" -eq 0 ]; then
        chown -R www-data:www-data /data "${APP_DIR}/.tempest" 2>/dev/null || true
    fi

    if ! grep -qE '^SIGNING_KEY=.+' "${ENV_TARGET}" 2>/dev/null; then
        as_www_data sh -c "cd '${APP_DIR}' && php tempest key:generate"
    fi

    signing_key=$(grep -m1 '^SIGNING_KEY=' "${ENV_TARGET}" 2>/dev/null | cut -d= -f2- || true)
    if [ -n "${signing_key}" ]; then
        export SIGNING_KEY="${signing_key}"
    fi

    as_www_data sh -c "cd '${APP_DIR}' && php tempest migrate:up --force --validate=0"
    as_www_data sh -c "cd '${APP_DIR}' && php tempest tubecast:init --skip-deps --admin"
    as_www_data sh -c "cd '${APP_DIR}' && php tempest tubecast:recover-downloads"
}

remap_www_data
chown -R www-data:www-data /var/www/html 2>/dev/null || true
tubecast_bootstrap

exec supervisord -n -c /etc/supervisor/supervisord.conf
