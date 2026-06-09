#!/command/with-contenv sh
set -e

APP_DIR="${APP_BASE_DIR:-/var/www/html}"
ENV_TARGET="/data/config/.env"

as_www_data() {
    if [ "$(id -u)" -eq 0 ]; then
        /command/s6-setuidgid www-data "$@"
    else
        "$@"
    fi
}

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
EOF

ln -sf "${ENV_TARGET}" "${APP_DIR}/.env"

if [ "$(id -u)" -eq 0 ]; then
    chown -R www-data:www-data /data "${APP_DIR}/.tempest" 2>/dev/null || true
fi

if ! grep -qE '^SIGNING_KEY=.+' "${ENV_TARGET}" 2>/dev/null; then
    as_www_data sh -c "cd '${APP_DIR}' && php tempest key:generate"
fi

as_www_data sh -c "cd '${APP_DIR}' && php tempest migrate:up --force --validate=0"
as_www_data sh -c "cd '${APP_DIR}' && php tempest tubecast:install-defaults"
as_www_data sh -c "cd '${APP_DIR}' && php tempest tubecast:recover-downloads"

exit 0
