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
else
  {
    printf 'opcache.enable=1\n'
    printf 'opcache.enable_cli=0\n'
    printf 'opcache.memory_consumption=192\n'
    printf 'opcache.interned_strings_buffer=16\n'
    printf 'opcache.max_accelerated_files=20000\n'
    printf 'opcache.validate_timestamps=1\n'
    printf 'opcache.revalidate_freq=30\n'
  } > /usr/local/etc/php/conf.d/appdashboard-opcache.ini
fi
APP_ROOT=/var/www/AppDashboard
APPDATA_BASE_DIR="${APPDATA_BASE_DIR:-/opt/ApplicationData}"
APPDATA_DATA_DIR="${APPDATA_DATA_DIR:-/opt/AppDashboard/data}"
UPLOADS_BASE_DIR="${UPLOADS_BASE_DIR:-${APPDATA_BASE_DIR%/}/dashboard/uploads}"
mkdir -p "$APPDATA_BASE_DIR/cloud_runtime/temp" "$APPDATA_BASE_DIR/cloud_runtime/temp_convert" "$APPDATA_BASE_DIR/cloud_runtime/upload_sessions" "$APPDATA_BASE_DIR/cloud_runtime/transcription_uploads" "$APPDATA_BASE_DIR/cloud_runtime/tts_output" "$APPDATA_BASE_DIR/cloud"
sed -i 's|^listen = .*php[0-9.]*-fpm.sock|listen = /run/php/php-fpm.sock|' /usr/local/etc/php-fpm.d/docker.conf 2>/dev/null || true
mkdir -p "$APPDATA_DATA_DIR" "$UPLOADS_BASE_DIR/avatars" "$UPLOADS_BASE_DIR/support_chat" "$UPLOADS_BASE_DIR/avatar_cache" "$APPDATA_BASE_DIR" "$APPDATA_BASE_DIR/cloud_libreoffice_home" "$APPDATA_BASE_DIR/cloud_libreoffice_home/.cache" "$APPDATA_BASE_DIR/cloud_libreoffice_home/.config" "$APPDATA_BASE_DIR/cloud_libreoffice_home/tmp" /var/www/AppDashboard/apps/cloud/temp_convert /var/www/.cache /var/log/bot
if [ -f "$APP_ROOT/bot/bot.log" ]; then
  [ -f /var/log/bot/bot.log ] || cp -a "$APP_ROOT/bot/bot.log" /var/log/bot/bot.log 2>/dev/null || true
  mv "$APP_ROOT/bot/bot.log" "/tmp/appdashboard-bot-log-migrated-$(date +%s)" 2>/dev/null || true
fi
touch /var/log/bot/bot.log 2>/dev/null || true
if [ -d "$APP_ROOT/data" ] && [ ! -L "$APP_ROOT/data" ]; then
  cp -a "$APP_ROOT/data/." "$APPDATA_DATA_DIR/" 2>/dev/null || true
  rm -rf "$APP_ROOT/data"
fi
if [ -d "$APP_ROOT/uploads" ] && [ ! -L "$APP_ROOT/uploads" ]; then
  cp -a "$APP_ROOT/uploads/." "$UPLOADS_BASE_DIR/" 2>/dev/null || true
  rm -rf "$APP_ROOT/uploads"
fi
[ -e "$APP_ROOT/data" ] || ln -s "$APPDATA_DATA_DIR" "$APP_ROOT/data"
[ -e "$APP_ROOT/uploads" ] || ln -s "$UPLOADS_BASE_DIR" "$APP_ROOT/uploads"
chown -R www-data:www-data "$APPDATA_DATA_DIR" "$UPLOADS_BASE_DIR" "$APPDATA_BASE_DIR/cloud_libreoffice_home" /var/www/AppDashboard/apps/cloud/temp_convert /var/www/.cache /var/log/bot 2>/dev/null || true
chmod -R 0775 "$APPDATA_BASE_DIR/cloud_libreoffice_home" /var/www/AppDashboard/apps/cloud/temp_convert 2>/dev/null || true
find "$APPDATA_DATA_DIR/.ssh" -type d -exec chmod 0700 {} \; 2>/dev/null || true
find "$APPDATA_DATA_DIR/.ssh" -type f -exec chmod 0600 {} \; 2>/dev/null || true
find "$APPDATA_DATA_DIR/ssh_ws_tokens" -type d -exec chmod 0700 {} \; 2>/dev/null || true
find "$APPDATA_DATA_DIR/ssh_ws_tokens" -type f -exec chmod 0600 {} \; 2>/dev/null || true
chmod g-s "$APPDATA_DATA_DIR/.ssh" "$APPDATA_DATA_DIR/ssh_ws_tokens" 2>/dev/null || true
chmod 0600 "$APPDATA_DATA_DIR/db.env" "$APPDATA_DATA_DIR/news_writing_token.json" 2>/dev/null || true
if [ ! -f "$APPDATA_DATA_DIR/db.env" ]; then
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
  } > "$APPDATA_DATA_DIR/db.env"
fi
if [ -f "$APPDATA_DATA_DIR/db.env" ]; then
  [ -n "${DB_HOST:-}" ] && sed -i "s/^DB_HOST=.*/DB_HOST=${DB_HOST}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  [ -n "${DB_PORT:-}" ] && sed -i "s/^DB_PORT=.*/DB_PORT=${DB_PORT}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  [ -n "${DB_NAME:-}" ] && sed -i "s/^DB_NAME=.*/DB_NAME=${DB_NAME}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  [ -n "${DB_USER:-}" ] && sed -i "s/^DB_USER=.*/DB_USER=${DB_USER}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  [ -n "${DB_PASSWORD:-}" ] && sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  grep -q '^REDIS_HOST=' "$APPDATA_DATA_DIR/db.env" || printf 'REDIS_HOST=%s\n' "${REDIS_HOST:-appdashboard-redis}" >> "$APPDATA_DATA_DIR/db.env"
  grep -q '^REDIS_PORT=' "$APPDATA_DATA_DIR/db.env" || printf 'REDIS_PORT=%s\n' "${REDIS_PORT:-6379}" >> "$APPDATA_DATA_DIR/db.env"
  grep -q '^REDIS_DATABASE=' "$APPDATA_DATA_DIR/db.env" || printf 'REDIS_DATABASE=%s\n' "${REDIS_DATABASE:-0}" >> "$APPDATA_DATA_DIR/db.env"
  grep -q '^REDIS_PREFIX=' "$APPDATA_DATA_DIR/db.env" || printf 'REDIS_PREFIX=%s\n' "${REDIS_PREFIX:-appdashboard:}" >> "$APPDATA_DATA_DIR/db.env"
  chmod 0600 "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
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
chown -h www-data:www-data "$APP_ROOT/data" "$APP_ROOT/uploads" 2>/dev/null || true
chmod -R u+rwX,g+rwX "$UPLOADS_BASE_DIR" 2>/dev/null || true
chown www-data:www-data "$APPDATA_BASE_DIR" 2>/dev/null || true
chmod 750 "$APPDATA_DATA_DIR" 2>/dev/null || true
mkdir -p /var/lib/mosquitto /etc/mosquitto
touch /etc/mosquitto/smarthome.passwd /etc/mosquitto/smarthome.acl
chown -R mosquitto:mosquitto /var/lib/mosquitto 2>/dev/null || true
chmod 0600 /etc/mosquitto/smarthome.passwd /etc/mosquitto/smarthome.acl 2>/dev/null || true
if [ -f /var/www/AppDashboard/deploy/scripts/ensure-apps-appdata.sh ]; then
  sh /var/www/AppDashboard/deploy/scripts/ensure-apps-appdata.sh /var/www/AppDashboard || true
elif [ -f /var/www/AppDashboard/deploy/scripts/ensure-spends-appdata.sh ]; then
  sh /var/www/AppDashboard/deploy/scripts/ensure-spends-appdata.sh /var/www/AppDashboard/apps/spends || true
fi
if [ -d /usr/local/share/lopaka ]; then
  rm -rf /var/www/AppDashboard/apps/lopaka
  mkdir -p /var/www/AppDashboard/apps/lopaka
  cp -a /usr/local/share/lopaka/. /var/www/AppDashboard/apps/lopaka/
fi
git config --system --add safe.directory /var/www/AppDashboard 2>/dev/null || true
if [ "${RUN_MCP_PACKAGE_BUILD:-0}" = "1" ] && [ -f /var/www/AppDashboard/deploy/scripts/build-appdashboard-mcp.sh ]; then
  sh /var/www/AppDashboard/deploy/scripts/build-appdashboard-mcp.sh /var/www/AppDashboard >/var/log/supervisor/mcp-package-build.log 2>&1
fi
if [ "${RUN_STARTUP_DEPS:-0}" = "1" ]; then
  export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-$APPDATA_BASE_DIR/.composer-cache}"
  mkdir -p "$COMPOSER_CACHE_DIR"
  chown -R www-data:www-data "$COMPOSER_CACHE_DIR"
  install_composer_deps() {
    dir="$1"
    [ -f "$dir/composer.json" ] || return 0
    [ -f "$dir/vendor/autoload.php" ] && return 0
    su -s /bin/bash www-data -c "cd $dir && composer install --no-interaction --no-progress"
  }
  {
  mkdir -p /var/www/AppDashboard/vendor /var/www/AppDashboard/webssh/vendor /var/www/AppDashboard/apps/cloud/vendor
  chown -R www-data:www-data /var/www/AppDashboard/vendor /var/www/AppDashboard/webssh/vendor /var/www/AppDashboard/apps/cloud/vendor
  install_composer_deps /var/www/AppDashboard
  install_composer_deps /var/www/AppDashboard/webssh
  install_composer_deps /var/www/AppDashboard/apps/cloud
  VENV=/var/www/AppDashboard/apps/steps/utils/.venv
  if [ ! -d "$VENV" ]; then
    python3 -m venv "$VENV"
  fi
  if [ ! -x "$VENV/bin/pip" ]; then
    python3 -m venv --clear "$VENV"
  fi
  if [ -f /var/www/AppDashboard/apps/steps/utils/requirements.txt ] && ! "$VENV/bin/python3" -c 'import pyzipper, psycopg2' 2>/dev/null; then
    "$VENV/bin/pip" install -q -r /var/www/AppDashboard/apps/steps/utils/requirements.txt || true
  fi
  BOT_VENV=/var/www/AppDashboard/bot/.venv
  if [ ! -d "$BOT_VENV" ]; then
    python3 -m venv "$BOT_VENV"
  fi
  "$BOT_VENV/bin/pip" install -q -r /var/www/AppDashboard/bot/requirements.txt || true
  } >/var/log/supervisor/startup-deps.log 2>&1
fi
if [ -f /var/www/AppDashboard/apps/smart_home/mqtt_auth_sync.php ]; then
  /usr/local/bin/php /var/www/AppDashboard/apps/smart_home/mqtt_auth_sync.php --once >/var/log/supervisor/smart-home-mqtt-auth-boot.log 2>&1 || true
fi
exec "$@"
