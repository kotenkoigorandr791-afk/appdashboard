# Миграция с Apache на Nginx

## Полное руководство по переводу проекта AppDashboard

---

## Содержание

1. [Обзор](#1-обзор)
2. [Установка Nginx и PHP-FPM](#2-установка-nginx-и-php-fpm)
3. [Базовая конфигурация Nginx](#3-базовая-конфигурация-nginx)
4. [Преобразование .htaccess — RewriteRules](#4-преобразование-htaccess--rewriterules)
5. [Преобразование .htaccess — Security Headers](#5-преобразование-htaccess--security-headers)
6. [Преобразование .htaccess — Блокировка файлов и каталогов](#6-преобразование-htaccess--блокировка-файлов-и-каталогов)
7. [Прокси: Portainer и Duplicati](#7-прокси-portainer-и-duplicati)
8. [WebSocket прокси](#8-websocket-прокси)
9. [Приложение Server Monitor (Performance)](#9-приложение-server-monitor-performance)
10. [PHP-FPM настройка](#10-php-fpm-настройка)
11. [SSL/TLS](#11-ssltls)
12. [Полный конфиг server block](#12-полный-конфиг-server-block)
13. [Проверка и тестирование](#13-проверка-и-тестирование)

---

## 1. Обзор

Проект AppDashboard использует Apache с `.htaccess` для:
- **Rewrite rules**: фронт-контроллер (`index.php`), красивые URL, API-роуты
- **Security headers**: `X-Content-Type-Options`, `X-Frame-Options`, HSTS и др.
- **Блокировки**: защита upload-директорий, `.git`, `vendor/`, SQLite-файлов
- **ProxyPass**: WebSocket-соединения SSH и Smart Home, Portainer, Duplicati

Nginx не поддерживает `.htaccess` — вся конфигурация выполняется в server/location блоках.

### Обозначения

| Путь | Описание |
|------|----------|
| `/var/www/AppDashboard` | Корень проекта (APP_ROOT) |
| `www-data` | Пользователь веб-сервера |
| `127.0.0.1:9000` | Portainer |
| `127.0.0.1:8200` | Duplicati |
| `127.0.0.1:8765` | SSH WebSocket |
| `127.0.0.1:8766` | Smart Home WebSocket |
| `127.0.0.1:8767` | Monaco Collab WebSocket |
| `127.0.0.1:8770` | Cloud Sync WebSocket |

---

## 2. Установка Nginx и PHP-FPM

```bash
# Установка Nginx + PHP-FPM
sudo apt update
sudo apt install -y nginx php-fpm php-cli php-curl php-gd php-mbstring \
    php-xml php-zip php-sqlite3 php-pgsql php-intl

# Отключаем Apache
sudo systemctl stop apache2
sudo systemctl disable apache2

# Запускаем Nginx
sudo systemctl enable --now nginx
sudo systemctl enable --now php8.3-fpm   # версия может отличаться
```

---

## 3. Базовая конфигурация Nginx

### 3.1. Структура

Вся конфигурация хранится в `/etc/nginx/`. Основные файлы:

| Файл | Назначение |
|------|------------|
| `/etc/nginx/nginx.conf` | Глобальная конфигурация |
| `/etc/nginx/sites-available/appdashboard` | Server block приложения |
| `/etc/nginx/sites-enabled/appdashboard` | Симлинк на sites-available |

### 3.2. Редактирование nginx.conf

Убедитесь, что в секции `http` присутствует `server_tokens off;` и корректный `sendfile`:

```nginx
http {
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    server_tokens off;

    # Лимиты
    client_max_body_size 0;        # без лимита (для загрузок)
    client_body_buffer_size 16M;

    # Keepalive
    keepalive_timeout 65;

    # MIME types
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    # Сжатие
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_types text/plain text/css application/json application/javascript
               text/xml application/xml text/javascript image/svg+xml;
    gzip_min_length 256;

    # Включаем server blocks
    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

### 3.3. Создание server block

```bash
sudo nano /etc/nginx/sites-available/appdashboard
sudo ln -s /etc/nginx/sites-available/appdashboard /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default   # удаляем дефолтный
```

---

## 4. Преобразование .htaccess — RewriteRules

### 4.1. Фронт-контроллер (все запросы → index.php)

**Apache (.htaccess):**
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L]
```

**Nginx:**
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 4.2. Удаление index.php из URL (редирект)

**Apache:**
```apache
RewriteCond %{THE_REQUEST} ^[A-Z]{3,9}\ /(.*)index\.(php|html?)?\ [NC]
RewriteRule ^(.*)index\.(php|html?)?$ /$1 [R=301,L]
```

**Nginx:**
```nginx
location ~* /(.*)index\.(php|html?)?$ {
    return 301 /$1;
}
```

### 4.3. Маршруты для server_monitor.php и эндпоинтов Performance

**Apache:**
```apache
RewriteRule ^server_monitor\.php$ - [L]
RewriteRule ^performance/(cpu|mem|memswap|diskio|network|uptime)/?$
    apps/performance/server_monitor.php?endpoint=$1 [L,QSA,NC]
```

**Nginx:**
```nginx
location = /server_monitor.php {
    try_files $uri =404;
}

location ~ ^/performance/(cpu|mem|memswap|diskio|network|uptime)/?$ {
    rewrite ^/performance/(cpu|mem|memswap|diskio|network|uptime)/?$
        /apps/performance/server_monitor.php?endpoint=$1 last;
}
```

> **Важно**: В маршрутах для Performance ("старые" эндпоинты API) убедитесь, что rewrite указывает на полный путь `/apps/performance/server_monitor.php`.

### 4.4. API-роуты для Map app

**Apache:**
```apache
RewriteRule ^map/api/token$ apps/map/index.php [L,QSA]
RewriteRule ^map/api/tokens$ apps/map/index.php [L,QSA]
RewriteRule ^map/api/location$ apps/map/index.php [L,QSA]
```

**Nginx:**
```nginx
location ~ ^/map/api/(token|tokens|location)$ {
    rewrite ^/map/api/(token|tokens|location)$ /apps/map/index.php last;
}
```

### 4.5. Статические страницы

**Apache:**
```apache
RewriteRule ^terms/?$ terms.html [L]
RewriteRule ^privacy/?$ privacy.html [L]
RewriteRule ^main/?$ index.php [L,QSA]
RewriteRule ^login/?$ index.php [L,QSA]
RewriteRule ^register/?$ index.php [L,QSA]
RewriteRule ^api/client-auth/?$ index.php?api=client_auth [L,QSA]
```

**Nginx:**
```nginx
location = /terms { rewrite ^ /terms.html last; }
location = /privacy { rewrite ^ /privacy.html last; }
location ~ ^/(main|login|register)/?$ { rewrite ^ /index.php last; }
location ~ ^/api/client-auth/?$ { rewrite ^ /index.php?api=client_auth last; }
```

### 4.6. Разрешение прямого доступа к определённым PHP-файлам

**Apache:**
```apache
RewriteRule ^file_viewer\.php$ - [L]
RewriteRule ^cloud/zip_viewer\.php$ - [L]
RewriteRule ^apps/cloud/api\.php$ - [L]
RewriteRule ^proxy\.php(/.*)?$ - [L]
RewriteRule ^proxy_backup\.php(/.*)?$ - [L]
```

**Nginx:**

Эти файлы уже будут доступны через `try_files` — никаких дополнительных правил не требуется, если они лежат в корне.

### 4.7. Разрешение `sharedFile` в query string

**Apache:**
```apache
RewriteCond %{QUERY_STRING} (sharedFile|download|display) [NC]
RewriteRule . - [L]
```

**Nginx:**

Фильтрация по query string в Nginx делается через location + `if`:
```nginx
location / {
    if ($arg_sharedFile) {
        try_files $uri $uri/ =404;
    }
    try_files $uri $uri/ /index.php?$query_string;
}
```

---

## 5. Преобразование .htaccess — Security Headers

**Apache:**
```apache
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(self), microphone=(), camera=(self), usb=()"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains" env=HTTPS
    Header always unset X-Powered-By
</IfModule>
```

**Nginx:**
```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(self), microphone=(), camera=(self), usb=()" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
proxy_hide_header X-Powered-By;
fastcgi_hide_header X-Powered-By;

# Удаление X-Powered-By из ответов PHP
fastcgi_param PHP_VALUE "expose_php = Off";
```

> **Примечание**: HSTS (`Strict-Transport-Security`) следует включать только после настройки HTTPS. Для HTTP-сервера HSTS не добавляйте.

---

## 6. Преобразование .htaccess — Блокировка файлов и каталогов

### 6.1. Блокировка исполняемых файлов в uploads и app-директориях

**Apache:**
```apache
RewriteRule ^(uploads|apps/cloud/storage|apps/cloud/temp|...)/.*\.(php|php3|...)$ - [F,L,NC]
```

**Nginx:**

Используем несколько `location` блоков с `deny all`:

```nginx
# Блокировка исполняемых файлов в upload/app-директориях
location ~* ^/(uploads|apps/cloud/storage|apps/cloud/temp|apps/cloud/temp_convert|
                apps/cloud/transcription_uploads|apps/cloud/tts_output|
                apps/cloud/upload_sessions|apps/cloud/data|apps/map/uploads|
                apps/map/location_photos|apps/map/user_data|apps/films/comments|
                apps/spends/backups|apps/steps/users|apps/spends/users)/
                .*\.(php|php3|php4|php5|php7|php8|phtml|phar|phps|pht|phtm|
                     inc|cgi|pl|py|rb|jsp|asp|aspx)$ {
    deny all;
    return 403;
}
```

### 6.2. Блокировка чувствительных каталогов

**Apache:**
```apache
RewriteRule ^(\.git|\.svn|\.hg|\.cursor|\.venv|\.Trash-1000|\.security_backups)(/|$) - [F,L,NC]
RewriteRule ^(.*/)?vendor/.* - [F,L,NC]
RewriteRule ^(.*/)?node_modules/.* - [F,L,NC]
```

**Nginx:**
```nginx
# Блокировка скрытых каталогов и VCS
location ~ /(\.|vendor/|node_modules/) {
    deny all;
    return 403;
}

location ~ /(\.git|\.svn|\.hg|\.cursor|\.venv|\.Trash-1000|\.security_backups) {
    deny all;
    return 403;
}

location ~ ^/(vendor|node_modules)/ {
    deny all;
    return 403;
}
```

### 6.3. Блокировка SQLite-файлов

**Apache:**
```apache
<FilesMatch "\.(sqlite|sqlite3|db|db3|sqlitedb)$">
    Require all denied
</FilesMatch>
RewriteRule ^data/.*\.sqlite3$ - [F,L]
RewriteRule ^db/.*\.sqlite3$ - [F,L]
RewriteRule ^database/.*\.sqlite3$ - [F,L]
```

**Nginx:**
```nginx
location ~* \.(sqlite|sqlite3|db|db3|sqlitedb)$ {
    deny all;
    return 403;
}
```

### 6.4. Блокировка служебных и конфигурационных файлов

**Apache:**
```apache
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
<FilesMatch "\.(htaccess|htpasswd|ini|log|sh|bak|sql|env|zip|tar|gz|tgz|7z|bz2|rar|swp|swo|orig|backup|old|dist|lock|yml|yaml)$">
    Require all denied
</FilesMatch>
```

**Nginx:**
```nginx
# Блокировка служебных расширений
location ~* \.(htaccess|htpasswd|ini|log|sh|bak|sql|env|tar|gz|tgz|7z|bz2|rar|
               swp|swo|orig|backup|old|dist|lock|yml|yaml)$ {
    deny all;
    return 403;
}

# Разрешить только конкретные zip-файлы
location = /cpp_solver.zip { alias /var/www/AppDashboard/cpp_solver.zip; }
location = /v13.54.34.z1.zip { alias /var/www/AppDashboard/v13.54.34.z1.zip; }
```

### 6.5. Блокировка защищённых папок apps/

**Apache:**
```apache
RewriteRule ^apps/films/comments/ - [F,L]
RewriteRule ^apps/map/uploads/ - [F,L]
RewriteRule ^apps/performance/chache/ - [F,L]
RewriteRule ^apps/steps/users/[^/]+/.*\.csv$ - [F,L]
RewriteRule ^apps/steps/users/[^/]+/.*\.json$ - [F,L]
RewriteRule ^apps/spends/users/[^/]+/.*\.csv$ - [F,L]
RewriteRule ^apps/spends/backups/ - [F,L]
```

**Nginx:**
```nginx
location /apps/films/comments/ { deny all; return 403; }
location /apps/map/uploads/ { deny all; return 403; }
location /apps/performance/chache/ { deny all; return 403; }

location ~* /apps/steps/users/[^/]+/.*\.(csv|json)$ { deny all; return 403; }
location ~* /apps/spends/users/[^/]+/.*\.csv$ { deny all; return 403; }
location /apps/spends/backups/ { deny all; return 403; }
```

---

## 7. Прокси: Portainer и Duplicati

**Apache:**
```apache
RewriteRule ^portainer/(.*)$ http://localhost:9000/$1 [P,L,QSA]
RewriteRule ^duplicati/(.*)$ http://localhost:8200/$1 [P,L,QSA]
```

**Nginx:**
```nginx
location /portainer/ {
    proxy_pass http://127.0.0.1:9000/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_read_timeout 120s;
    proxy_buffering off;
}

location /duplicati/ {
    proxy_pass http://127.0.0.1:8200/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_read_timeout 120s;
    proxy_buffering off;
}
```

---

## 8. WebSocket прокси

Проект использует несколько WebSocket-сервисов за systemd.

### 8.1. Карта WebSocket-сервисов

| Сервис | systemd unit | Внутренний адрес | Публичный путь |
|--------|-------------|------------------|----------------|
| SSH Terminal | `ap-ssh-ws.service` | `ws://127.0.0.1:8765` | `/ssh-ws` |
| Smart Home | `smart-home-ws.service` | `ws://127.0.0.1:8766` | `/smart-home-ws` |
| Monaco Collab | `cloud-monaco-collab-ws.service` | `ws://127.0.0.1:8767` | `/monaco-collab-ws` |
| Cloud Sync | `cloud-sync-ws.service` | `ws://127.0.0.1:8770` | `/cloud-sync-ws` |

### 8.2. Конфигурация Nginx для WebSocket

**Apache (было):**
```apache
# smart-home-ws — deploy/apache-smart-home-ws.conf.snippet
ProxyPreserveHost On
ProxyPass /smart-home-ws ws://127.0.0.1:8766/
ProxyPassReverse /smart-home-ws ws://127.0.0.1:8766/

# ssh-ws — deploy/apache-ssh-ws.conf.snippet
ProxyPreserveHost On
ProxyPass /ssh-ws ws://127.0.0.1:8765/
ProxyPassReverse /ssh-ws ws://127.0.0.1:8765/
```

**Nginx:**

```nginx
# SSH WebSocket
location /ssh-ws {
    proxy_pass http://127.0.0.1:8765;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 86400s;        # Долгие SSH-сессии
    proxy_send_timeout 86400s;
    proxy_buffering off;
}

# Smart Home WebSocket
location /smart-home-ws {
    proxy_pass http://127.0.0.1:8766;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 86400s;
    proxy_send_timeout 86400s;
    proxy_buffering off;
}

# Monaco Collaboration WebSocket
location /monaco-collab-ws {
    proxy_pass http://127.0.0.1:8767;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 86400s;
    proxy_send_timeout 86400s;
    proxy_buffering off;
}

# Cloud Sync WebSocket
location /cloud-sync-ws {
    proxy_pass http://127.0.0.1:8770;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 300s;
    proxy_send_timeout 300s;
    proxy_buffering off;
}
```

> **Важно**: Для WebSocket необходимо передавать заголовки `Upgrade` и `Connection`. Все WebSocket-сервисы слушают на `127.0.0.1` (локальная петля).

---

## 9. Приложение Server Monitor (Performance)

### 9.1. Как работает Performance

- **`/apps/performance/server_monitor.php`** — JSON API для мониторинга
  - Эндпоинты: `cpu`, `mem`, `memswap`, `diskio`, `network`, `uptime` (через `?endpoint=`)
- **`/apps/performance/index.php`** — Веб-интерфейс монитора
- **`/apps/performance/monitor_stats.php`** — Графики исторических данных
- **`/apps/performance/bin/monitor_collector.php`** — CLI-коллектор метрик (запускается через systemd)

### 9.2. Прямые маршруты для Performance

```nginx
# Основная страница монитора
location /apps/performance/ {
    try_files $uri $uri/ /apps/performance/index.php?$query_string;
}

# Страница исторических графиков
location /apps/performance/monitor_stats.php {
    try_files $uri =404;
}

# JSON API — эндпоинты (используется index.php из performance)
location ~ ^/performance/(cpu|mem|memswap|diskio|network|uptime)/?$ {
    rewrite ^/performance/(cpu|mem|memswap|diskio|network|uptime)/?$
        /apps/performance/server_monitor.php?endpoint=$1 last;
}

# Отдельный файл server_monitor.php (используется для прямых запросов)
location /apps/performance/server_monitor.php {
    try_files $uri =404;
}

# Статические файлы performance (CSS, изображения)
location /apps/performance/ {
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 7d;
        add_header Cache-Control "public, immutable";
    }
}
```

### 9.3. Установка Performance-коллектора

Коллектор метрик запускается через systemd и не зависит от веб-сервера:

```bash
sudo cp /var/www/AppDashboard/apps/performance/deploy/performance-monitor-collector.service \
        /etc/systemd/system/performance-monitor-collector.service
sudo systemctl daemon-reload
sudo systemctl enable --now performance-monitor-collector.service
```

---

## 10. PHP-FPM настройка

### 10.1. Базовые параметры

```nginx
location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;  # или unix:/run/php/php8.3-fpm.sock
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;

    # Параметры для AppDashboard
    fastcgi_read_timeout 120;
    fastcgi_buffers 16 16k;
    fastcgi_buffer_size 32k;
    fastcgi_busy_buffers_size 64k;

    # Отключаем expose_php
    fastcgi_param PHP_VALUE "expose_php = Off\nmax_execution_time = 120\nmemory_limit = 256M\nupload_max_filesize = 0\npost_max_size = 0";

    # X-Forwarded-* для корректного определения HTTPS/IP
    fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;
    fastcgi_param HTTP_X_REAL_IP $remote_addr;
    fastcgi_param HTTPS $https if_not_empty;
}
```

### 10.2. Защита от выполнения PHP в upload-директориях

```nginx
# Блокируем PHP в upload/app-папках через fastcgi
location ~* ^/(uploads|apps/cloud/storage|apps/cloud/temp|apps/cloud/temp_convert|
                apps/cloud/transcription_uploads|apps/cloud/tts_output|
                apps/cloud/upload_sessions|apps/cloud/data|apps/map/uploads|
                apps/map/location_photos|apps/map/user_data|apps/films/comments|
                apps/spends/backups|apps/steps/users|apps/spends/users)/ {
    location ~ \.php$ { deny all; return 403; }
}
```

---

## 11. SSL/TLS

### 11.1. Установка certbot и получение сертификата

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d serverkherson.xyz -d www.serverkherson.xyz
```

### 11.2. Редирект HTTP → HTTPS

```nginx
server {
    listen 80;
    server_name serverkherson.xyz www.serverkherson.xyz;
    return 301 https://$server_name$request_uri;
}
```

### 11.3. HTTPS server block

Добавьте в HTTPS-сервер:

```nginx
# HSTS (включать только после проверки работы HTTPS)
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

---

## 12. Полный конфиг server block

### Файл: `/etc/nginx/sites-available/appdashboard`

```nginx
upstream php-fpm {
    server unix:/var/run/php/php8.3-fpm.sock;
}

server {
    listen 80;
    server_name serverkherson.xyz www.serverkherson.xyz;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name serverkherson.xyz www.serverkherson.xyz;

    root /var/www/AppDashboard;
    index index.php;

    # ── SSL ────────────────────────────────────────────────────────────────
    ssl_certificate     /etc/letsencrypt/live/serverkherson.xyz/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/serverkherson.xyz/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # ── Logs ────────────────────────────────────────────────────────────────
    access_log  /var/log/nginx/appdashboard_access.log;
    error_log   /var/log/nginx/appdashboard_error.log;

    # ── Security Headers ────────────────────────────────────────────────────
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(self), microphone=(), camera=(self), usb=()" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    proxy_hide_header X-Powered-By;
    fastcgi_hide_header X-Powered-By;

    # ── Gzip ────────────────────────────────────────────────────────────────
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css application/json application/javascript
               text/xml application/xml image/svg+xml;

    # ── Блокировка служебных файлов ─────────────────────────────────────────
    location ~* \.(htaccess|htpasswd|ini|log|sh|bak|sql|env|tar|gz|tgz|7z|bz2|
                   rar|swp|swo|orig|backup|old|dist|lock|yml|yaml)$ {
        deny all;
        return 403;
    }

    location ~ /(\.git|\.svn|\.hg|\.cursor|\.venv|\.Trash-1000|\.security_backups|
                 vendor|node_modules)/ {
        deny all;
        return 403;
    }

    location ~* \.(sqlite|sqlite3|db|db3|sqlitedb)$ {
        deny all;
        return 403;
    }

    # ── Блокировка исполняемых файлов в upload/app-папках ──────────────────
    location ~* ^/(uploads|apps/cloud/storage|apps/cloud/temp|apps/cloud/temp_convert|
                    apps/cloud/transcription_uploads|apps/cloud/tts_output|
                    apps/cloud/upload_sessions|apps/cloud/data|apps/map/uploads|
                    apps/map/location_photos|apps/map/user_data|apps/films/comments|
                    apps/spends/backups|apps/steps/users|apps/spends/users)/ {
        location ~ \.php$ { deny all; return 403; }
    }

    # ── Защищённые папки apps ──────────────────────────────────────────────
    location /apps/films/comments/     { deny all; return 403; }
    location /apps/map/uploads/        { deny all; return 403; }
    location /apps/performance/chache/ { deny all; return 403; }
    location /apps/spends/backups/     { deny all; return 403; }
    location ~* /apps/steps/users/[^/]+/.*\.(csv|json)$ { deny all; return 403; }
    location ~* /apps/spends/users/[^/]+/.*\.csv$       { deny all; return 403; }

    # ── Статические страницы ────────────────────────────────────────────────
    location = /terms  { rewrite ^ /terms.html last; }
    location = /privacy { rewrite ^ /privacy.html last; }

    # ── Performance app ─────────────────────────────────────────────────────
    # API-эндпоинты (для обратной совместимости)
    location ~ ^/performance/(cpu|mem|memswap|diskio|network|uptime)/?$ {
        rewrite ^/performance/(cpu|mem|memswap|diskio|network|uptime)/?$
            /apps/performance/server_monitor.php?endpoint=$1 last;
    }

    # ── Map app API ───────────────────────────────────────────────────────────
    location ~ ^/map/api/(token|tokens|location)$ {
        rewrite ^/map/api/(token|tokens|location)$ /apps/map/index.php last;
    }

    # ── WebSocket прокси ──────────────────────────────────────────────────────
    location /ssh-ws {
        proxy_pass http://127.0.0.1:8765;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
        proxy_buffering off;
    }

    location /smart-home-ws {
        proxy_pass http://127.0.0.1:8766;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
        proxy_buffering off;
    }

    location /monaco-collab-ws {
        proxy_pass http://127.0.0.1:8767;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
        proxy_buffering off;
    }

    location /cloud-sync-ws {
        proxy_pass http://127.0.0.1:8770;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300s;
        proxy_send_timeout 300s;
        proxy_buffering off;
    }

    # ── Прокси Portainer / Duplicati ──────────────────────────────────────────
    location /portainer/ {
        proxy_pass http://127.0.0.1:9000/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_http_version 1.1;
        proxy_buffering off;
    }

    location /duplicati/ {
        proxy_pass http://127.0.0.1:8200/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_http_version 1.1;
        proxy_buffering off;
    }

    # ── PHP-FPM ───────────────────────────────────────────────────────────────
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        fastcgi_read_timeout 120;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        fastcgi_busy_buffers_size 64k;

        fastcgi_param PHP_VALUE "expose_php = Off
            max_execution_time = 120
            memory_limit = 256M
            upload_max_filesize = 0
            post_max_size = 0";
    }

    # ── Статические файлы (кеширование) ──────────────────────────────────────
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot|webp)$ {
        expires 7d;
        add_header Cache-Control "public, immutable";
    }

    # ── Фронт-контроллер (всё остальное → index.php) ─────────────────────────
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

---

## 13. Проверка и тестирование

### 13.1. Проверка конфигурации Nginx

```bash
sudo nginx -t
```

### 13.2. Перезагрузка Nginx

```bash
sudo systemctl reload nginx
# или
sudo nginx -s reload
```

### 13.3. Проверка критических URL

| URL | Ожидаемый результат |
|-----|-------------------|
| `https://serverkherson.xyz/` | HTML-страница home.html |
| `https://serverkherson.xyz/main` | Панель управления (index.php) |
| `https://serverkherson.xyz/login` | Страница входа |
| `https://serverkherson.xyz/privacy` | privacy.html |
| `https://serverkherson.xyz/performance/` | Монитор сервера |
| `https://serverkherson.xyz/performance/cpu/` | JSON с CPU (Server Monitor API) |
| `https://serverkherson.xyz/.git/config` | 403 Forbidden |
| `https://serverkherson.xyz/vendor/autoload.php` | 403 Forbidden |
| `https://serverkherson.xyz/uploads/evil.php` | 403 Forbidden |
| `https://serverkherson.xyz/ssh-ws` | WebSocket handshake (101 Switching Protocols) |
| `https://serverkherson.xyz/portainer/` | Portainer login |
| `https://serverkherson.xyz/duplicati/` | Duplicati |

### 13.4. Проверка заголовков

```bash
curl -sI https://serverkherson.xyz/ | grep -i 'X-Content-Type-Options\|X-Frame-Options\|Strict-Transport-Security\|X-Powered-By\|Server'
```

### 13.5. Просмотр логов

```bash
# Ошибки Nginx
sudo tail -f /var/log/nginx/error.log

# Ошибки PHP
sudo tail -f /var/log/php8.3-fpm.log
```

### 13.6. Восстановление (Rollback)

Если что-то пошло не так:

```bash
# Отключаем Nginx
sudo systemctl stop nginx
sudo systemctl disable nginx

# Включаем Apache обратно
sudo systemctl enable --now apache2
```

---

## Приложение A: Сравнение директив

| Apache | Nginx |
|--------|-------|
| `.htaccess` | server / location блоки в `/etc/nginx/sites-available/` |
| `RewriteRule ... [L]` | `rewrite ... last` |
| `RewriteCond %{REQUEST_FILENAME} !-f` | `try_files $uri $uri/ /index.php` |
| `Header always set ...` | `add_header ... always` |
| `Require all denied` / `Deny from all` | `deny all; return 403;` |
| `<FilesMatch "\.php$">` | `location ~ \.php$` |
| `ProxyPass /path http://backend` | `proxy_pass http://backend; location /path {}` |
| `ProxyPass /path ws://backend` | `proxy_pass http://backend;` + заголовки Upgrade |
| `RewriteRule ... [F,L]` | `deny all; return 403;` |
| `ErrorDocument 404 /index.php` | `try_files $uri $uri/ /index.php` |
| `<IfModule mod_headers.c>` | не требуется (всегда доступно) |

---

## Приложение B: Список systemd-сервисов

```bash
# Установка всех сервисов
sudo bash /var/www/AppDashboard/scripts/install-systemd-services.sh

# Или по одному:
sudo systemctl enable --now ap-ssh-ws
sudo systemctl enable --now smart-home-ws
sudo systemctl enable --now cloud-sync-ws
sudo systemctl enable --now cloud-monaco-collab-ws
sudo systemctl enable --now performance-monitor-collector
sudo systemctl enable --now appdashboard-bot
```

---

## Приложение C: Рекомендации по производительности

1. **FastCGI cache** (опционально): для статических страниц можно включить кеширование
2. **PHP-FPM pool** (`/etc/php/8.3/fpm/pool.d/www.conf`):
   ```
   pm = dynamic
   pm.max_children = 50
   pm.start_servers = 5
   pm.min_spare_servers = 5
   pm.max_spare_servers = 15
   ```
3. **Nginx worker_processes**: `auto` (обычно равно числу ядер CPU)
4. **sendfile on**: уже включено в базовой конфигурации
5. **open_file_cache**: ускоряет отдачу статики

```nginx
open_file_cache max=1000 inactive=20s;
open_file_cache_valid 30s;
open_file_cache_min_uses 2;
open_file_cache_errors on;
```
