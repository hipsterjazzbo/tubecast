#!/command/with-contenv sh
set -e

PUID="${PUID:-33}"
PGID="${PGID:-33}"

if [ "$(id -u)" -ne 0 ]; then
    exit 0
fi

have_uid="$(id -u www-data)"
have_gid="$(id -g www-data)"

if [ "${PUID}" != "${have_uid}" ] || [ "${PGID}" != "${have_gid}" ]; then
    docker-php-serversideup-set-id www-data "${PUID}:${PGID}"
    docker-php-serversideup-set-file-permissions --owner "${PUID}:${PGID}"
fi

exit 0
