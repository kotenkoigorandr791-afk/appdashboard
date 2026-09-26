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
APPDATA_BASE_DIR="${APPDATA_BASE_DIR:-/opt/ApplicationData}"
APPDATA_DATA_DIR="${APPDATA_DATA_DIR:-/opt/AppDashboard/data}"
UPLOADS_BASE_DIR="${UPLOADS_BASE_DIR:-${APPDATA_BASE_DIR%/}/dashboard/uploads}"
mkdir -p "$APPDATA_BASE_DIR/cloud_runtime/temp" "$APPDATA_BASE_DIR/cloud_runtime/temp_convert" "$APPDATA_BASE_DIR/cloud_runtime/upload_sessions" "$APPDATA_BASE_DIR/cloud_runtime/transcription_uploads" "$APPDATA_BASE_DIR/cloud_runtime/tts_output" "$APPDATA_BASE_DIR/cloud"
sed -i 's|^listen = .*php[0-9.]*-fpm.sock|listen = /run/php/php-fpm.sock|' /usr/local/etc/php-fpm.d/docker.conf 2>/dev/null || true
[ -d /var/www/AppDashboard/apps/cloud/temp_convert ] || rm -f /var/www/AppDashboard/apps/cloud/temp_convert
if [ -f /usr/local/etc/php-fpm.d/www.conf ]; then
  sed -i 's/^pm = .*/pm = dynamic/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  sed -i 's/^pm.max_children = .*/pm.max_children = '"${PHP_FPM_MAX_CHILDREN:-25}"'/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  sed -i 's/^pm.start_servers = .*/pm.start_servers = '"${PHP_FPM_START_SERVERS:-6}"'/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = '"${PHP_FPM_MIN_SPARE:-3}"'/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = '"${PHP_FPM_MAX_SPARE:-10}"'/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  if grep -q '^;request_terminate_timeout' /usr/local/etc/php-fpm.d/www.conf; then
    sed -i 's/^;request_terminate_timeout = .*/request_terminate_timeout = '"${PHP_FPM_REQUEST_TERMINATE_TIMEOUT:-30}"'/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  elif grep -q '^request_terminate_timeout' /usr/local/etc/php-fpm.d/www.conf; then
    sed -i 's/^request_terminate_timeout = .*/request_terminate_timeout = '"${PHP_FPM_REQUEST_TERMINATE_TIMEOUT:-30}"'/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
  else
    printf '\nrequest_terminate_timeout = %s\n' "${PHP_FPM_REQUEST_TERMINATE_TIMEOUT:-30}" >> /usr/local/etc/php-fpm.d/www.conf
  fi
fi
{
  printf 'max_execution_time=%s\n' "${PHP_MAX_EXECUTION_TIME:-30}"
  printf 'default_socket_timeout=%s\n' "${PHP_DEFAULT_SOCKET_TIMEOUT:-15}"
} > /usr/local/etc/php/conf.d/appdashboard-timeouts.ini
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
[ -e "$APP_ROOT/data" ] || [ -L "$APP_ROOT/data" ] || ln -s "$APPDATA_DATA_DIR" "$APP_ROOT/data"
[ -e "$APP_ROOT/uploads" ] || [ -L "$APP_ROOT/uploads" ] || ln -s "$UPLOADS_BASE_DIR" "$APP_ROOT/uploads"
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
    su -s /bin/bash www-data -c "cd $dir && composer install --no-interaction --no-progress --no-dev --prefer-dist --optimize-autoloader"
  }
  {
  mkdir -p /var/www/AppDashboard/vendor /var/www/AppDashboard/webssh/vendor /var/www/AppDashboard/apps/cloud/vendor
  chown -R www-data:www-data /var/www/AppDashboard/vendor /var/www/AppDashboard/webssh/vendor /var/www/AppDashboard/apps/cloud/vendor
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
  SPENDS_VENV=/var/www/AppDashboard/apps/spends/utils/.venv
  if [ ! -d "$SPENDS_VENV" ]; then
    python3 -m venv "$SPENDS_VENV"
  fi
  if [ ! -x "$SPENDS_VENV/bin/pip" ]; then
    python3 -m venv --clear "$SPENDS_VENV"
  fi
  "$SPENDS_VENV/bin/python3" -m ensurepip --upgrade >/dev/null 2>&1 || true
  if [ -f /var/www/AppDashboard/apps/spends/utils/requirements-ocr.txt ] && ! "$SPENDS_VENV/bin/python3" -c 'import torch, transformers, qwen_vl_utils, PIL, numpy, psycopg2' 2>/dev/null; then
    "$SPENDS_VENV/bin/python3" -m pip install -q -r /var/www/AppDashboard/apps/spends/utils/requirements-ocr.txt || true
  fi
  export HF_HOME="${HF_HOME:-/opt/ApplicationData/huggingface}"
  mkdir -p "$HF_HOME"
  chown -R www-data:www-data "$HF_HOME" 2>/dev/null || true
  chmod -R ug+rwX "$HF_HOME" 2>/dev/null || true
  if "$SPENDS_VENV/bin/python3" -c 'import transformers, qwen_vl_utils, huggingface_hub' 2>/dev/null; then
    su -s /bin/sh www-data -c "HF_HOME='$HF_HOME' '$SPENDS_VENV/bin/python3' -" <<'PY' || true
import os
from huggingface_hub import snapshot_download
model=os.environ.get("SPENDS_OCR_VLM_MODEL","Qwen/Qwen2.5-VL-3B-Instruct")
snapshot_download(model,ignore_patterns=["*.msgpack","*.h5","*.ot","*.onnx"])
PY
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
  /var/www/AppDashboard/deploy/scripts/render-nginx-conf.sh "$NGINX_TEMPLATE" /etc/nginx/sites-available/appdashboard.conf \
    >/var/log/supervisor/nginx-render.log 2>&1 || true
  ln -sfn /etc/nginx/sites-available/appdashboard.conf /etc/nginx/sites-enabled/default 2>/dev/null || true
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
if [ -f /var/www/AppDashboard/supervisord.conf ]; then
  cp /var/www/AppDashboard/supervisord.conf /etc/supervisor/conf.d/appdashboard.conf
fi
exec "$@"
