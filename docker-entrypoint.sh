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
    printf 'opcache.validate_timestamps=%s\n' "${PHP_OPCACHE_VALIDATE_TIMESTAMPS:-1}"
    printf 'opcache.revalidate_freq=30\n'
    printf 'opcache.jit=tracing\n'
    printf 'opcache.jit_buffer_size=%s\n' "${PHP_OPCACHE_JIT_BUFFER:-64M}"
    printf 'realpath_cache_size=16M\n'
    printf 'realpath_cache_ttl=300\n'
  } > /usr/local/etc/php/conf.d/appdashboard-opcache.ini
fi
APP_WAF_RULE_ENGINE="${APP_WAF_RULE_ENGINE:-On}"
case "$APP_WAF_RULE_ENGINE" in
  On|DetectionOnly|Off) ;;
  *) APP_WAF_RULE_ENGINE=On ;;
esac
mkdir -p /etc/nginx/modsecurity /var/log/modsecurity
printf 'SecRuleEngine %s\n' "$APP_WAF_RULE_ENGINE" > /etc/nginx/modsecurity/appdashboard-engine.conf
chown -R www-data:adm /var/log/modsecurity 2>/dev/null || true
chmod 0775 /var/log/modsecurity 2>/dev/null || true
{
  printf 'upload_max_filesize=%s\n' "${PHP_UPLOAD_MAX_FILESIZE:-64M}"
  printf 'post_max_size=%s\n' "${PHP_POST_MAX_SIZE:-64M}"
  printf 'max_file_uploads=20\n'
} > /usr/local/etc/php/conf.d/appdashboard-upload.ini
APP_ROOT=/var/www/AppDashboard
# The code tree is a host bind mount on Docker Desktop (virtiofs). When that
# filesystem daemon runs out of host descriptors every open() on the mount fails
# with EMFILE and nginx answers 500. Keep a small container-local copy of the
# public assets so nginx has something to serve during such an outage (see the
# @project_static_fallback locations in nginx/appdashboard.conf.template).
STATIC_FALLBACK_DIR=/var/www/static-fallback
mkdir -p "$STATIC_FALLBACK_DIR/project" 2>/dev/null || true
for _static_dir in assets onboarding; do
  rm -rf "$STATIC_FALLBACK_DIR/project/$_static_dir" 2>/dev/null || true
  cp -a "$APP_ROOT/project/$_static_dir" "$STATIC_FALLBACK_DIR/project/" 2>/dev/null || true
done
APPDATA_BASE_DIR="${APPDATA_BASE_DIR:-/opt/ApplicationData}"
APPDATA_DATA_DIR="${APPDATA_DATA_DIR:-/opt/AppDashboard/data}"
UPLOADS_BASE_DIR="${UPLOADS_BASE_DIR:-${APPDATA_BASE_DIR%/}/dashboard/uploads}"
# Every temporary file must live on a volume. These paths are pinned to the
# APPDATA volume (not the code tree, not the container layer) and are exported
# to PHP, supervisord children and nginx below.
APPDATA_TMP_DIR="${APPDATA_TMP_DIR:-${APPDATA_BASE_DIR%/}/tmp}"
APPDATA_SESSION_DIR="${APPDATA_SESSION_DIR:-${APPDATA_BASE_DIR%/}/php-sessions}"
APPDATA_NGINX_TMP_DIR="${APPDATA_NGINX_TMP_DIR:-${APPDATA_BASE_DIR%/}/nginx}"
APPDATA_MOSQUITTO_DIR="${APPDATA_MOSQUITTO_DIR:-${APPDATA_BASE_DIR%/}/mosquitto}"
export TMPDIR="$APPDATA_TMP_DIR"
mkdir -p "$APPDATA_BASE_DIR/cloud_runtime/temp" "$APPDATA_BASE_DIR/cloud_runtime/temp_convert" "$APPDATA_BASE_DIR/cloud_runtime/upload_sessions" "$APPDATA_BASE_DIR/cloud_runtime/transcription_uploads" "$APPDATA_BASE_DIR/cloud_runtime/tts_output" "$APPDATA_BASE_DIR/cloud" \
         "$APPDATA_TMP_DIR" "$APPDATA_SESSION_DIR" "$APPDATA_MOSQUITTO_DIR" \
         "$APPDATA_NGINX_TMP_DIR/client_body" "$APPDATA_NGINX_TMP_DIR/proxy" "$APPDATA_NGINX_TMP_DIR/fastcgi" "$APPDATA_NGINX_TMP_DIR/uwsgi" "$APPDATA_NGINX_TMP_DIR/scgi"
chown -R www-data:www-data "$APPDATA_TMP_DIR" "$APPDATA_SESSION_DIR" "$APPDATA_NGINX_TMP_DIR" 2>/dev/null || true
chmod 0700 "$APPDATA_SESSION_DIR" 2>/dev/null || true
chmod 0770 "$APPDATA_TMP_DIR" "$APPDATA_NGINX_TMP_DIR" 2>/dev/null || true
# Persist PHP's temporary/upload/session paths on the volume for FPM and CLI alike.
{
  printf 'sys_temp_dir=%s\n' "$APPDATA_TMP_DIR"
  printf 'upload_tmp_dir=%s\n' "$APPDATA_TMP_DIR"
  printf 'session.save_path=%s\n' "$APPDATA_SESSION_DIR"
} > /usr/local/etc/php/conf.d/appdashboard-storage.ini
sed -i 's|^listen = .*php[0-9.]*-fpm.sock|listen = /run/php/php-fpm.sock|' /usr/local/etc/php-fpm.d/docker.conf 2>/dev/null || true
if [ -f /usr/local/etc/php-fpm.d/www.conf ]; then
  sed -i 's/^pm = .*/pm = dynamic/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  sed -i 's/^pm.max_children = .*/pm.max_children = '"${PHP_FPM_MAX_CHILDREN:-48}"'/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  sed -i 's/^pm.start_servers = .*/pm.start_servers = '"${PHP_FPM_START_SERVERS:-24}"'/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = '"${PHP_FPM_MIN_SPARE:-16}"'/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = '"${PHP_FPM_MAX_SPARE:-32}"'/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  sed -i 's/^;\?pm.max_requests = .*/pm.max_requests = '"${PHP_FPM_MAX_REQUESTS:-500}"'/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  grep -q '^pm.max_requests' /usr/local/etc/php-fpm.d/www.conf || printf '\npm.max_requests = %s\n' "${PHP_FPM_MAX_REQUESTS:-500}" >> /usr/local/etc/php-fpm.d/www.conf
  sed -i 's/^;\?listen.backlog = .*/listen.backlog = 4096/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  grep -q '^listen.backlog' /usr/local/etc/php-fpm.d/www.conf || printf 'listen.backlog = 4096\n' >> /usr/local/etc/php-fpm.d/www.conf
  # The pool and its seeded APPDATA_BASE_DIR/UPLOADS_BASE_DIR must reach PHP-FPM.
  sed -i 's/^;\?clear_env\s*=.*/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  grep -q '^clear_env' /usr/local/etc/php-fpm.d/www.conf || printf 'clear_env = no\n' >> /usr/local/etc/php-fpm.d/www.conf
  if grep -q '^;request_terminate_timeout' /usr/local/etc/php-fpm.d/www.conf; then
    sed -i 's/^;request_terminate_timeout = .*/request_terminate_timeout = '"${PHP_FPM_REQUEST_TERMINATE_TIMEOUT:-30}"'/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  elif grep -q '^request_terminate_timeout' /usr/local/etc/php-fpm.d/www.conf; then
    sed -i 's/^request_terminate_timeout = .*/request_terminate_timeout = '"${PHP_FPM_REQUEST_TERMINATE_TIMEOUT:-30}"'/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  else
    printf '\nrequest_terminate_timeout = %s\n' "${PHP_FPM_REQUEST_TERMINATE_TIMEOUT:-30}" >> /usr/local/etc/php-fpm.d/www.conf
  fi
fi
if [ -f /etc/nginx/nginx.conf ]; then
  sed -i 's/^worker_processes .*/worker_processes auto;/' /etc/nginx/nginx.conf
  sed -i 's/^\s*worker_connections .*/    worker_connections '"${NGINX_WORKER_CONNECTIONS:-16384}"';/' /etc/nginx/nginx.conf
  # Each proxied request holds a client socket plus an upstream socket, and static
  # files add more; with worker_connections 16384 the old 65535 limit let a busy
  # worker run out of descriptors (nginx then answers 500 with "Too many open files").
  sed -i 's/^\s*worker_rlimit_nofile .*/worker_rlimit_nofile '"${NGINX_WORKER_RLIMIT_NOFILE:-200000}"';/' /etc/nginx/nginx.conf
  grep -q '^worker_rlimit_nofile' /etc/nginx/nginx.conf || sed -i '1i worker_rlimit_nofile '"${NGINX_WORKER_RLIMIT_NOFILE:-200000}"';' /etc/nginx/nginx.conf
fi
{
  printf 'max_execution_time=%s\n' "${PHP_MAX_EXECUTION_TIME:-30}"
  printf 'default_socket_timeout=%s\n' "${PHP_DEFAULT_SOCKET_TIMEOUT:-15}"
} > /usr/local/etc/php/conf.d/appdashboard-timeouts.ini
# $APPDATA_DATA_DIR/storage holds storage/индекс state (locks). It must exist
# before supervisord starts php-fpm and the file-index worker (both as www-data),
# otherwise root-owned copies created by one-off CLI runs break them.
mkdir -p "$APPDATA_DATA_DIR" "$APPDATA_DATA_DIR/storage" "$UPLOADS_BASE_DIR/avatars" "$UPLOADS_BASE_DIR/support_chat" "$UPLOADS_BASE_DIR/avatar_cache" "$APPDATA_BASE_DIR" "$APPDATA_BASE_DIR/cloud_libreoffice_home" "$APPDATA_BASE_DIR/cloud_libreoffice_home/.cache" "$APPDATA_BASE_DIR/cloud_libreoffice_home/.config" "$APPDATA_BASE_DIR/cloud_libreoffice_home/tmp" /var/www/.cache /var/log/bot
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
# The code tree carries no symlinks: data lives only on the volumes
# ($APPDATA_DATA_DIR / $UPLOADS_BASE_DIR) and is resolved through
# storage_helper.php. Remove legacy links left by older images.
for _legacy_link in "$APP_ROOT/data" "$APP_ROOT/uploads"; do
  if [ -L "$_legacy_link" ]; then rm -f "$_legacy_link"; fi
done
chown -R www-data:www-data "$APPDATA_DATA_DIR" "$UPLOADS_BASE_DIR" "$APPDATA_BASE_DIR/cloud_libreoffice_home" /var/www/.cache /var/log/bot 2>/dev/null || true
chmod -R 0775 "$APPDATA_BASE_DIR/cloud_libreoffice_home" 2>/dev/null || true
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
    printf 'DB_PORT=%s\n' "${DB_PORT:-5432}"
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
  # Self-heal: host-gateway is the Docker host alias, never the Postgres
  # service. Older images baked it into db.env, which makes every PDO connect
  # fail with SQLSTATE[08006] "could not translate host name".
  if grep -q '^DB_HOST=host-gateway$' "$APPDATA_DATA_DIR/db.env" 2>/dev/null; then
    sed -i "s/^DB_HOST=host-gateway$/DB_HOST=${DB_HOST:-appdashboard-db}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  fi
  [ -n "${DB_HOST:-}" ] && sed -i "s/^DB_HOST=.*/DB_HOST=${DB_HOST}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  [ -n "${DB_PORT:-}" ] && sed -i "s/^DB_PORT=.*/DB_PORT=${DB_PORT}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  [ -n "${DB_NAME:-}" ] && sed -i "s/^DB_NAME=.*/DB_NAME=${DB_NAME}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  [ -n "${DB_USER:-}" ] && sed -i "s/^DB_USER=.*/DB_USER=${DB_USER}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  [ -n "${DB_PASSWORD:-}" ] && sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  [ -n "${REDIS_HOST:-}" ] && sed -i "s/^REDIS_HOST=.*/REDIS_HOST=${REDIS_HOST}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  [ -n "${REDIS_PORT:-}" ] && sed -i "s/^REDIS_PORT=.*/REDIS_PORT=${REDIS_PORT}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  [ -n "${REDIS_DATABASE:-}" ] && sed -i "s/^REDIS_DATABASE=.*/REDIS_DATABASE=${REDIS_DATABASE}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  [ -n "${REDIS_PREFIX:-}" ] && sed -i "s/^REDIS_PREFIX=.*/REDIS_PREFIX=${REDIS_PREFIX}/" "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
  grep -q '^REDIS_HOST=' "$APPDATA_DATA_DIR/db.env" || printf 'REDIS_HOST=%s\n' "${REDIS_HOST:-appdashboard-redis}" >> "$APPDATA_DATA_DIR/db.env"
  grep -q '^REDIS_PORT=' "$APPDATA_DATA_DIR/db.env" || printf 'REDIS_PORT=%s\n' "${REDIS_PORT:-6379}" >> "$APPDATA_DATA_DIR/db.env"
  grep -q '^REDIS_DATABASE=' "$APPDATA_DATA_DIR/db.env" || printf 'REDIS_DATABASE=%s\n' "${REDIS_DATABASE:-0}" >> "$APPDATA_DATA_DIR/db.env"
  grep -q '^REDIS_PREFIX=' "$APPDATA_DATA_DIR/db.env" || printf 'REDIS_PREFIX=%s\n' "${REDIS_PREFIX:-appdashboard:}" >> "$APPDATA_DATA_DIR/db.env"
  chown www-data:www-data "$APPDATA_DATA_DIR/db.env" 2>/dev/null || true
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
# MQTT: AppArmor on the host only allows mosquitto to read /etc/mosquitto and
# /var/lib/mosquitto/mosquitto.db. APPDATA keeps durable mirrors across rebuilds.
if [ -f /var/www/AppDashboard/deploy/mosquitto/smarthome.conf ]; then
  cp /var/www/AppDashboard/deploy/mosquitto/smarthome.conf /etc/mosquitto/conf.d/smarthome.conf
  chmod 0644 /etc/mosquitto/conf.d/smarthome.conf
fi
mkdir -p /etc/mosquitto /var/lib/mosquitto /var/log/mosquitto "$APPDATA_MOSQUITTO_DIR"
if [ -f /var/www/AppDashboard/deploy/scripts/ensure-apps-appdata.sh ]; then
  sh /var/www/AppDashboard/deploy/scripts/ensure-apps-appdata.sh /var/www/AppDashboard || true
elif [ -f /var/www/AppDashboard/deploy/scripts/ensure-spends-appdata.sh ]; then
  sh /var/www/AppDashboard/deploy/scripts/ensure-spends-appdata.sh /var/www/AppDashboard/apps/spends || true
fi
# ensure-apps-appdata chowns the whole APPDATA tree to www-data; restore the
# broker mirror dir, then seed runtime paths AppArmor actually allows.
mkdir -p "$APPDATA_MOSQUITTO_DIR"
chown -R mosquitto:mosquitto "$APPDATA_MOSQUITTO_DIR" 2>/dev/null || true
chmod 0700 "$APPDATA_MOSQUITTO_DIR" 2>/dev/null || true
for f in smarthome.passwd smarthome.acl; do
  if [ -s "$APPDATA_MOSQUITTO_DIR/$f" ]; then
    cp -a "$APPDATA_MOSQUITTO_DIR/$f" "/etc/mosquitto/$f"
  else
    touch "/etc/mosquitto/$f"
  fi
  chown mosquitto:mosquitto "/etc/mosquitto/$f" 2>/dev/null || true
  chmod 0600 "/etc/mosquitto/$f" 2>/dev/null || true
done
if [ -s "$APPDATA_MOSQUITTO_DIR/mosquitto.db" ]; then
  cp -a "$APPDATA_MOSQUITTO_DIR/mosquitto.db" /var/lib/mosquitto/mosquitto.db
fi
chown -R mosquitto:mosquitto /var/lib/mosquitto /var/log/mosquitto 2>/dev/null || true
chmod 0755 /var/lib/mosquitto /var/log/mosquitto 2>/dev/null || true
# Seed the bundled lopaka build only when the app directory is absent or
# empty. A bind-mounted checkout must never be wiped: the previous guard keyed
# off index.html, which the lopaka rewrite no longer ships, so it deleted the
# mounted tree on every container start.
if [ -d /usr/local/share/lopaka ]; then
  LOPAKA_TARGET=/var/www/AppDashboard/apps/lopaka
  if [ ! -d "$LOPAKA_TARGET" ] || [ -z "$(ls -A "$LOPAKA_TARGET" 2>/dev/null)" ]; then
    mkdir -p "$LOPAKA_TARGET"
    cp -a /usr/local/share/lopaka/. "$LOPAKA_TARGET/"
  fi
fi
git config --system --add safe.directory /var/www/AppDashboard 2>/dev/null || true
if [ "${RUN_MCP_PACKAGE_BUILD:-0}" = "1" ] && [ -f /var/www/AppDashboard/deploy/scripts/build-appdashboard-mcp.sh ]; then
  sh /var/www/AppDashboard/deploy/scripts/build-appdashboard-mcp.sh /var/www/AppDashboard >/var/log/supervisor/mcp-package-build.log 2>&1 &
fi
if [ "${RUN_STARTUP_DEPS:-1}" = "1" ]; then
  export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-$APPDATA_BASE_DIR/.composer-cache}"
  mkdir -p "$COMPOSER_CACHE_DIR"
  chown -R www-data:www-data "$COMPOSER_CACHE_DIR"
  install_composer_deps() {
    dir="$1"
    required_package="$2"
    [ -f "$dir/composer.json" ] || return 0
    needs_install=0
    if [ ! -f "$dir/vendor/autoload.php" ]; then
      needs_install=1
    elif [ -n "$required_package" ] && ! su -s /bin/bash www-data -c "cd $dir && composer show '$required_package' >/dev/null 2>&1"; then
      needs_install=1
    elif [ -f "$dir/composer.lock" ] && [ -f "$dir/vendor/composer/installed.php" ] && [ "$dir/composer.lock" -nt "$dir/vendor/composer/installed.php" ]; then
      needs_install=1
    fi
    [ "$needs_install" = "1" ] || return 0
    # Only fix ownership of the tree that is about to be rewritten. The old
    # unconditional chown -R over every vendor dir walked ~36k inodes on the
    # virtiofs bind mount at each container start, which is exactly the kind of
    # burst that pushes Docker Desktop's virtiofsd to its descriptor limit.
    chown -R www-data:www-data "$dir/vendor" "$dir/composer.lock" 2>/dev/null || true
    su -s /bin/bash www-data -c "cd $dir && composer install --no-interaction --no-progress --no-dev --prefer-dist --optimize-autoloader"
  }
  {
  mkdir -p /var/www/AppDashboard/vendor /var/www/AppDashboard/webssh/vendor /var/www/AppDashboard/apps/cloud/vendor
  install_composer_deps /var/www/AppDashboard sentry/sentry
  install_composer_deps /var/www/AppDashboard pimple/pimple
  install_composer_deps /var/www/AppDashboard/webssh cboden/ratchet
  install_composer_deps /var/www/AppDashboard/apps/cloud sentry/sentry
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
  BOT_VENV=/opt/ApplicationData/bot-venv
  if [ ! -d "$BOT_VENV" ]; then
    python3 -m venv "$BOT_VENV"
  fi
  "$BOT_VENV/bin/pip" install -q -r /var/www/AppDashboard/bot/requirements.txt || true
  } >/var/log/supervisor/startup-deps.log 2>&1 &
fi
if [ -f /var/www/AppDashboard/apps/smart_home/mqtt_auth_sync.php ]; then
  /usr/local/bin/php /var/www/AppDashboard/apps/smart_home/mqtt_auth_sync.php --once >/var/log/supervisor/smart-home-mqtt-auth-boot.log 2>&1 || true
fi
# Derive public service URLs from a single BASE_DOMAIN (override individually via env if needed).
BASE_DOMAIN="${BASE_DOMAIN:-localhost}"
case "$BASE_DOMAIN" in
  localhost|127.0.0.1|*.local) _ap_default_scheme=http ;;
  *) _ap_default_scheme=https ;;
esac
export BASE_DOMAIN
export APP_PUBLIC_SCHEME="${APP_PUBLIC_SCHEME:-$_ap_default_scheme}"
export APP_PUBLIC_BASE_URL="${APP_PUBLIC_BASE_URL:-${APP_PUBLIC_SCHEME}://${BASE_DOMAIN}}"
export AI_PUBLIC_HOST="${AI_PUBLIC_HOST:-ai.${BASE_DOMAIN}}"
export AI_PUBLIC_URL="${AI_PUBLIC_URL:-${APP_PUBLIC_SCHEME}://${AI_PUBLIC_HOST}}"
# OmniRoute process (Next.js) — not the public AI hostname (avoids proxy loop via ai.localhost→127.0.0.1).
export OMNIROUTE_UPSTREAM="${OMNIROUTE_UPSTREAM:-http://127.0.0.1:20128}"
export SEARCH_PUBLIC_URL="${SEARCH_PUBLIC_URL:-${APP_PUBLIC_SCHEME}://search.${BASE_DOMAIN}}"
export GITEA_PUBLIC_URL="${GITEA_PUBLIC_URL:-${APP_PUBLIC_SCHEME}://git.${BASE_DOMAIN}}"
export IMMICH_PUBLIC_URL="${IMMICH_PUBLIC_URL:-${APP_PUBLIC_SCHEME}://photo.${BASE_DOMAIN}}"
export DOCUMENT_PUBLIC_URL="${DOCUMENT_PUBLIC_URL:-${APP_PUBLIC_SCHEME}://document.${BASE_DOMAIN}}"
export DOCKER_PUBLIC_URL="${DOCKER_PUBLIC_URL:-${APP_PUBLIC_SCHEME}://docker.${BASE_DOMAIN}}"
export DAWARICH_PUBLIC_URL="${DAWARICH_PUBLIC_URL:-${APP_PUBLIC_SCHEME}://dawarich.${BASE_DOMAIN}}"
export DAWARICH_HOSTS="${DAWARICH_HOSTS:-localhost,127.0.0.1,::1,dawarich.${BASE_DOMAIN},${BASE_DOMAIN}}"
# Render nginx site config from template (no hardcoded production domain in the repo template).
NGINX_TEMPLATE="${NGINX_TEMPLATE:-/var/www/AppDashboard/nginx/appdashboard.conf.template}"
if [ ! -f "$NGINX_TEMPLATE" ] && [ -f /etc/nginx/templates/appdashboard.conf.template ]; then
  NGINX_TEMPLATE=/etc/nginx/templates/appdashboard.conf.template
fi
if [ -f "$NGINX_TEMPLATE" ] && [ -x /var/www/AppDashboard/deploy/scripts/render-nginx-conf.sh ]; then
NGINX_RENDERED_SITE=/tmp/appdashboard-nginx.conf
/var/www/AppDashboard/deploy/scripts/render-nginx-conf.sh "$NGINX_TEMPLATE" "$NGINX_RENDERED_SITE" \
>/var/log/supervisor/nginx-render.log 2>&1 || true
if [ -s "$NGINX_RENDERED_SITE" ]; then
ln -sfn "$NGINX_RENDERED_SITE" /etc/nginx/sites-enabled/default 2>/dev/null || true
fi
fi
# Ensure SSH WebSocket terminal is installable and will autostart under supervisord.
if [ -f /var/www/AppDashboard/deploy/scripts/ensure-ssh-ws.sh ]; then
  sh /var/www/AppDashboard/deploy/scripts/ensure-ssh-ws.sh /var/www/AppDashboard >/var/log/supervisor/ensure-ssh-ws.log 2>&1 || true
fi
# Seed empty public URL config keys from BASE_DOMAIN-derived env (never overwrite non-empty DB values).
if [ -f /var/www/AppDashboard/lib/app_domains.php ] && [ -f /var/www/AppDashboard/db.php ]; then
  /usr/local/bin/php -r '
require "/var/www/AppDashboard/lib/app_domains.php";
require "/var/www/AppDashboard/db.php";
if (!function_exists("setConfig") || !function_exists("getConfig")) { exit(0); }
$seed = [
  "app_public_base_url" => app_public_url(""),
  "tg_webapp_base_url" => app_public_url(""),
  "gitea_url" => app_public_url("git"),
  "immich_url" => app_public_url("photo"),
  "dawarich_url" => app_public_url("dawarich"),
  "docker_host" => app_public_url("docker"),
  "docker_domain" => app_subdomain_host("docker"),
  "search_engine_url" => app_public_url("search"),
];
$isLoopback = static function (string $url): bool {
  if ($url === "") return true;
  $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: $url));
  return $host === "localhost" || $host === "127.0.0.1" || $host === "::1" || str_ends_with($host, ".localhost");
};
foreach ($seed as $k => $v) {
  $cur = trim((string)getConfig($k, ""));
  if ($v === "") continue;
  if ($cur === "" || ($isLoopback($cur) && !$isLoopback($v))) {
    setConfig($k, $v);
  }
}
' >/var/log/supervisor/domain-seed.log 2>&1 || true
fi
if [ -f /var/www/AppDashboard/deploy/scripts/ensure-oauth-integrations.php ]; then
  /usr/local/bin/php /var/www/AppDashboard/deploy/scripts/ensure-oauth-integrations.php >/var/log/supervisor/oauth-integrations.log 2>&1 || true
fi
# --- Dynamic storage (MergerFS inside this container) -------------------------
STORAGE_ROOT="${STORAGE_ROOT:-/storage}"
STORAGE_DISKS_ROOT="${STORAGE_DISKS_ROOT:-/disks}"
STORAGE_INIT_LOG=/var/log/supervisor/storage-init.log
export STORAGE_ROOT STORAGE_DISKS_ROOT
mkdir -p "$STORAGE_ROOT" "$STORAGE_DISKS_ROOT" "$APPDATA_DATA_DIR/storage"
touch /etc/fuse.conf 2>/dev/null || true
if [ -f /etc/fuse.conf ] && ! grep -q '^user_allow_other' /etc/fuse.conf; then
  echo 'user_allow_other' >> /etc/fuse.conf
fi
storage_pool_mounted() {
  [ -d "$STORAGE_ROOT" ] || return 1
  awk -v mp="$STORAGE_ROOT" '$5==mp && ($0 ~ /fuse\.mergerfs/ || $0 ~ / mergerfs /) {found=1} END{exit !found}' /proc/self/mountinfo 2>/dev/null
}
# Virtual disks: sparse ext4 images stored in docker volumes, loop-mounted as /disks/<name>.
VDISKS_ROOT="${VDISKS_ROOT:-/vdisks}"
if [ -d "$VDISKS_ROOT" ]; then
  for _vd in "$VDISKS_ROOT"/*/; do
    [ -d "$_vd" ] || continue
    _vname="$(basename "$_vd")"
    _vimg="$_vd/disk.img"
    _vsizevar="VDISK_$(echo "$_vname" | tr 'a-z-' 'A-Z_')_SIZE"
    _vsize="$(eval "echo \${$_vsizevar:-${VDISK_DEFAULT_SIZE:-2G}}")"
    if [ ! -f "$_vimg" ]; then
      truncate -s "$_vsize" "$_vimg" && mkfs.ext4 -q -F "$_vimg" >>"$STORAGE_INIT_LOG" 2>&1 || { rm -f "$_vimg"; continue; }
    fi
    mkdir -p "$STORAGE_DISKS_ROOT/$_vname"
    if ! awk -v mp="$STORAGE_DISKS_ROOT/$_vname" '$5==mp{f=1} END{exit !f}' /proc/self/mountinfo; then
      mount -o loop "$_vimg" "$STORAGE_DISKS_ROOT/$_vname" >>"$STORAGE_INIT_LOG" 2>&1 || echo "[storage] cannot mount $_vimg" >>"$STORAGE_INIT_LOG"
    fi
  done
fi
if [ "${STORAGE_ENABLED:-1}" = "1" ]; then
  STORAGE_HAS_DISKS=0
  if [ -d "$STORAGE_DISKS_ROOT" ] && [ -n "$(ls -A "$STORAGE_DISKS_ROOT" 2>/dev/null)" ]; then STORAGE_HAS_DISKS=1; fi
  if [ "$STORAGE_HAS_DISKS" = "1" ] || [ -f "$APPDATA_DATA_DIR/.storage-unified-appdata" ]; then
    for _try in 1 2 3; do
      if /usr/local/bin/php /var/www/AppDashboard/project/storage/watcher.php --once >>"$STORAGE_INIT_LOG" 2>&1; then
        break
      fi
      sleep 3
    done
  fi
fi
if storage_pool_mounted; then
  mkdir -p "$STORAGE_ROOT/files" "$STORAGE_ROOT/documents" "$STORAGE_ROOT/uploads" "$STORAGE_ROOT/shared" \
           "$STORAGE_ROOT/applications" "$STORAGE_ROOT/appdashboard/data" "$STORAGE_ROOT/backup"
  chown www-data:www-data "$STORAGE_ROOT" 2>/dev/null || true
  chmod 0775 "$STORAGE_ROOT" 2>/dev/null || true
  # Let the app user write to the top of each branch; contents keep their
  # original ownership so host data stays untouched.
  for _branch in "$STORAGE_DISKS_ROOT"/*; do
    [ -d "$_branch" ] || continue
    chgrp www-data "$_branch" 2>/dev/null || true
    chmod g+rwx "$_branch" 2>/dev/null || true
  done
  seed_storage_dir() {
    _src="$1"; _dst="$2"; _marker="$3"; shift 3
    [ -f "$_marker" ] && return 0
    mkdir -p "$_dst" 2>/dev/null || return 1
    if [ "$(readlink -f "$_src" 2>/dev/null)" = "$(readlink -f "$_dst" 2>/dev/null)" ]; then
      touch "$_marker" 2>/dev/null || true
      return 0
    fi
    if [ -d "$_src" ] && [ -n "$(ls -A "$_src" 2>/dev/null)" ]; then
      echo "[storage] seeding $_src -> $_dst" >>"$STORAGE_INIT_LOG"
      if ! tar -C "$_src" -cf - "$@" . 2>>"$STORAGE_INIT_LOG" | tar -C "$_dst" -xf - 2>>"$STORAGE_INIT_LOG"; then
        echo "[storage] seeding failed; keeping legacy paths" >>"$STORAGE_INIT_LOG"
        return 1
      fi
    fi
    touch "$_marker" 2>/dev/null || true
    return 0
  }
  if [ "${STORAGE_UNIFY_APP_DATA:-1}" = "1" ]; then
    _appdata_marker="$APPDATA_DATA_DIR/.storage-unified-appdata"
    _uploads_marker="$APPDATA_DATA_DIR/.storage-unified-uploads"
    if seed_storage_dir "$APPDATA_BASE_DIR" "$STORAGE_ROOT/applications" "$_appdata_marker" \
        --exclude='./models' --exclude='./bot-venv' --exclude='./cloud_video_tmp' \
        --exclude='./cloud_libreoffice_home' --exclude='./latex-texmf' --exclude='./latex-pdf-cache' \
        --exclude='./.composer-cache' --exclude='./dashboard/uploads' \
        --exclude='./tmp' --exclude='./php-sessions' --exclude='./nginx' --exclude='./mosquitto' \
      && seed_storage_dir "$UPLOADS_BASE_DIR" "$STORAGE_ROOT/uploads" "$_uploads_marker"; then
      export APPDATA_BASE_DIR="$STORAGE_ROOT/applications"
      export UPLOADS_BASE_DIR="$STORAGE_ROOT/uploads"
      chown -R www-data:www-data "$STORAGE_ROOT/applications" "$STORAGE_ROOT/uploads" 2>/dev/null || true
    fi
  fi
  export STORAGE_STATE_DIR="$APPDATA_DATA_DIR/storage"
elif [ -f "$APPDATA_DATA_DIR/.storage-unified-appdata" ] && [ "${STORAGE_REQUIRE_POOL:-1}" = "1" ]; then
  # The pool was provisioned before, but no expected disk is mounted now.
  # Refusing to start is safer than silently writing to the legacy/system disk.
  echo "[storage] FATAL: merged storage is not mounted. Refusing to start to avoid writing outside the pool." >&2
  echo "[storage] Mount the disks, or set STORAGE_REQUIRE_POOL=0 to allow legacy paths." >&2
  exit 1
fi
if [ -f /var/www/AppDashboard/supervisord.conf ]; then
  cp /var/www/AppDashboard/supervisord.conf /etc/supervisor/conf.d/appdashboard.conf
fi
exec "$@"
