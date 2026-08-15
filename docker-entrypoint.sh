#!/bin/bash
set -e
if [ "${RUN_ENSURE_CONTAINER_PACKAGES:-0}" = "1" ] && [ -f /var/www/AppDashboard/deploy/scripts/ensure-container-packages.sh ]; then
  sh /var/www/AppDashboard/deploy/scripts/ensure-container-packages.sh || true
fi
if [ "${PHP_OPCACHE_ENABLE:-1}" = "0" ]; then
  {
    printf 'opcache.enable=0\n'
    printf 'opcache.enable_cli=0\n'
    printf 'opcache.validate_timestamps=1\n'
    printf 'opcache.revalidate_freq=0\n'
    printf 'opcache.file_update_protection=0\n'
  } > /usr/local/etc/php/conf.d/appdashboard-opcache.ini
fi
mkdir -p /var/www/AppDashboard/data /var/www/AppDashboard/uploads/avatars /var/www/AppDashboard/uploads/support_chat /opt/AppDashboard/data /opt/ApplicationData
if [ ! -f /opt/AppDashboard/data/db.env ]; then
  {
    printf 'DB_DRIVER=pgsql\n'
    printf 'DB_HOST=%s\n' "${DB_HOST:-127.0.0.1}"
    printf 'DB_PORT=%s\n' "${DB_PORT:-15432}"
    printf 'DB_NAME=%s\n' "${DB_NAME:-appdashboard}"
    printf 'DB_USER=%s\n' "${DB_USER:-appdashboard}"
    printf 'DB_PASSWORD=%s\n' "${DB_PASSWORD:-appdashboard_dev_password}"
    printf 'DB_SSLMODE=%s\n' "${DB_SSLMODE:-prefer}"
    printf 'REDIS_HOST=%s\n' "${REDIS_HOST:-127.0.0.1}"
    printf 'REDIS_PORT=%s\n' "${REDIS_PORT:-16379}"
    printf 'REDIS_DATABASE=%s\n' "${REDIS_DATABASE:-0}"
    printf 'REDIS_PREFIX=%s\n' "${REDIS_PREFIX:-appdashboard:}"
  } > /opt/AppDashboard/data/db.env
fi
if [ -f /opt/AppDashboard/data/db.env ]; then
  sed -i "s/^DB_HOST=.*/DB_HOST=${DB_HOST:-127.0.0.1}/" /opt/AppDashboard/data/db.env 2>/dev/null || true
  sed -i "s/^DB_PORT=.*/DB_PORT=${DB_PORT:-15432}/" /opt/AppDashboard/data/db.env 2>/dev/null || true
  sed -i "s/^DB_NAME=.*/DB_NAME=${DB_NAME:-appdashboard}/" /opt/AppDashboard/data/db.env 2>/dev/null || true
  sed -i "s/^DB_USER=.*/DB_USER=${DB_USER:-appdashboard}/" /opt/AppDashboard/data/db.env 2>/dev/null || true
  sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD:-appdashboard_dev_password}/" /opt/AppDashboard/data/db.env 2>/dev/null || true
  grep -q '^REDIS_HOST=' /opt/AppDashboard/data/db.env || printf 'REDIS_HOST=%s\n' "${REDIS_HOST:-127.0.0.1}" >> /opt/AppDashboard/data/db.env
  grep -q '^REDIS_PORT=' /opt/AppDashboard/data/db.env || printf 'REDIS_PORT=%s\n' "${REDIS_PORT:-16379}" >> /opt/AppDashboard/data/db.env
  grep -q '^REDIS_DATABASE=' /opt/AppDashboard/data/db.env || printf 'REDIS_DATABASE=%s\n' "${REDIS_DATABASE:-0}" >> /opt/AppDashboard/data/db.env
  grep -q '^REDIS_PREFIX=' /opt/AppDashboard/data/db.env || printf 'REDIS_PREFIX=%s\n' "${REDIS_PREFIX:-appdashboard:}" >> /opt/AppDashboard/data/db.env
fi
for docker_sock in /var/run/docker.sock /run/docker-desktop.sock; do
if [ -S "$docker_sock" ]; then
  DOCKER_GID="$(stat -c '%g' "$docker_sock" 2>/dev/null || true)"
  if [ -n "$DOCKER_GID" ]; then
    if ! getent group "$DOCKER_GID" >/dev/null; then
      groupadd -g "$DOCKER_GID" host-docker 2>/dev/null || true
    fi
    DOCKER_GROUP="$(getent group "$DOCKER_GID" | cut -d: -f1)"
    if [ -n "$DOCKER_GROUP" ]; then
      usermod -aG "$DOCKER_GROUP" www-data 2>/dev/null || true
    fi
  fi
fi
done
chown -R www-data:www-data /var/www/AppDashboard/data /var/www/AppDashboard/uploads /opt/AppDashboard/data /var/www/AppDashboard/bot 2>/dev/null || true
chmod -R u+rwX,g+rwX /var/www/AppDashboard/uploads 2>/dev/null || true
chown www-data:www-data /opt/ApplicationData 2>/dev/null || true
chmod 750 /opt/AppDashboard/data 2>/dev/null || true
if [ -f /var/www/AppDashboard/deploy/scripts/ensure-apps-appdata.sh ]; then
  sh /var/www/AppDashboard/deploy/scripts/ensure-apps-appdata.sh /var/www/AppDashboard || true
elif [ -f /var/www/AppDashboard/deploy/scripts/ensure-spends-appdata.sh ]; then
  sh /var/www/AppDashboard/deploy/scripts/ensure-spends-appdata.sh /var/www/AppDashboard/apps/spends || true
fi
git config --system --add safe.directory /var/www/AppDashboard 2>/dev/null || true
if [ "${RUN_STARTUP_DEPS:-0}" = "1" ]; then
  (
  mkdir -p /var/www/AppDashboard/vendor /var/www/AppDashboard/webssh/vendor /var/www/AppDashboard/apps/cloud/vendor
  chown -R www-data:www-data /var/www/AppDashboard/vendor /var/www/AppDashboard/webssh/vendor /var/www/AppDashboard/apps/cloud/vendor
  if [ ! -f /var/www/AppDashboard/vendor/autoload.php ]; then
    su -s /bin/bash www-data -c "cd /var/www/AppDashboard && composer install --no-interaction --prefer-dist" || true
  fi
  if [ ! -f /var/www/AppDashboard/webssh/vendor/autoload.php ]; then
    su -s /bin/bash www-data -c "cd /var/www/AppDashboard/webssh && composer install --no-interaction --prefer-dist" || true
  fi
  if [ ! -f /var/www/AppDashboard/apps/cloud/vendor/autoload.php ]; then
    su -s /bin/bash www-data -c "cd /var/www/AppDashboard/apps/cloud && composer install --no-interaction --prefer-dist" || true
  fi
  VENV=/var/www/AppDashboard/apps/steps/utils/.venv
  if [ ! -d "$VENV" ]; then
    python3 -m venv "$VENV"
  fi
  if [ ! -x "$VENV/bin/pip" ]; then
    python3 -m venv --clear "$VENV"
  fi
  if ! "$VENV/bin/python3" -c 'import pyzipper, psycopg2' 2>/dev/null; then
    "$VENV/bin/pip" install -q -r /var/www/AppDashboard/apps/steps/utils/requirements.txt || true
  fi
  BOT_VENV=/var/www/AppDashboard/bot/.venv
  if [ ! -d "$BOT_VENV" ]; then
    python3 -m venv "$BOT_VENV"
  fi
  "$BOT_VENV/bin/pip" install -q -r /var/www/AppDashboard/bot/requirements.txt || true
  ) >/var/log/supervisor/startup-deps.log 2>&1 &
fi
exec "$@"
