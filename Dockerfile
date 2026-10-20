# syntax=docker/dockerfile:1.7

# --- Build PHP extensions (build-essential / *-dev stay out of the runtime image)
FROM php:8.5-fpm-bookworm AS ext-builder
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS \
    libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libxml2-dev libonig-dev libicu-dev libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pgsql gd zip mbstring intl bcmath sockets curl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear /var/lib/apt/lists/*

# --- Composer vendor trees
FROM php:8.5-fpm-bookworm AS app-builder
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip \
    libpq5 libzip4 libpng16-16 libjpeg62-turbo libfreetype6 libicu72 libonig5 libxml2 \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=ext-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=ext-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
WORKDIR /var/www/AppDashboard
COPY . /var/www/AppDashboard/
RUN composer install --no-interaction --no-progress --no-dev --prefer-dist --optimize-autoloader \
    && composer install --no-interaction --no-progress --no-dev --prefer-dist --optimize-autoloader --working-dir=/var/www/AppDashboard/webssh \
    && composer install --no-interaction --no-progress --no-dev --prefer-dist --optimize-autoloader --working-dir=/var/www/AppDashboard/apps/cloud \
    && rm -rf /root/.composer /tmp/*

# --- Runtime: no compiler toolchain; TinyTeX lives on volume (ensure-tinytex.sh)
FROM php:8.5-fpm-bookworm
ENV DEBIAN_FRONTEND=noninteractive
ENV HYPERFRAMES_TELEMETRY_DISABLED=1
ENV ONNXRUNTIME_NODE_INSTALL_CUDA=skip
ENV ONNXRUNTIME_PREFER_CPU=1
ENV PIP_DEFAULT_TIMEOUT=300
ENV PIP_RETRIES=10
ENV PATH="/opt/TinyTeX/bin/x86_64-linux:/opt/TinyTeX/bin/aarch64-linux:${PATH}"

COPY --from=ext-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=ext-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx libnginx-mod-http-modsecurity modsecurity-crs supervisor git curl ca-certificates sudo unzip zip p7zip-full bzip2 mosquitto mosquitto-clients \
    libpq5 libzip4 libpng16-16 libjpeg62-turbo libfreetype6 \
    libxml2 libonig5 libicu72 libcurl4 \
    ffmpeg tesseract-ocr tesseract-ocr-eng tesseract-ocr-rus tesseract-ocr-bel tesseract-ocr-ukr \
    smartmontools util-linux openssh-client \
    python3 python3-pip python3-venv python3-psycopg2 docker.io \
    postgresql-client imagemagick poppler-utils libreoffice pandoc \
    fonts-liberation fonts-dejavu-core \
    libnss3 libatk-bridge2.0-0 libgtk-3-0 libx11-xcb1 libxcomposite1 libxdamage1 libxrandr2 libgbm1 libasound2 \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g hyperframes \
    && apt-get purge -y --auto-remove \
        build-essential autoconf pkg-config cpp gcc g++ make dpkg-dev \
        $PHPIZE_DEPS 2>/dev/null || true \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* /root/.npm /tmp/*

# TinyTeX is installed at runtime into TINYTEX_HOME (volume), see deploy/scripts/ensure-tinytex.sh
ENV TINYTEX_HOME=/opt/ApplicationData/tinytex
ENV HOME=/opt/ApplicationData/cloud_video_tmp/.hyperframes-home
ENV XDG_CACHE_HOME=/opt/ApplicationData/cloud_video_tmp/.hyperframes-cache
ENV npm_config_cache=/opt/ApplicationData/cloud_video_tmp/.hyperframes-cache/npm

RUN echo 'www-data ALL=(ALL) NOPASSWD: /usr/sbin/smartctl' > /etc/sudoers.d/www-data-smartctl \
    && chmod 440 /etc/sudoers.d/www-data-smartctl
RUN mkdir -p /run/php /var/log/supervisor /opt/AppDashboard/data /opt/ApplicationData/cloud_video_tmp/.hyperframes-home /opt/ApplicationData/cloud_video_tmp/.hyperframes-cache/npm /var/www/.hyperframes \
    && chown -R www-data:www-data /run/php /opt/AppDashboard /opt/ApplicationData /var/www/.hyperframes \
    && su -s /bin/sh www-data -c 'hyperframes telemetry disable || true'
RUN mkdir -p /etc/nginx/modsecurity /var/log/modsecurity \
    && cp /etc/nginx/modsecurity.conf /etc/modsecurity/modsecurity.conf \
    && cp /etc/nginx/unicode.mapping /etc/modsecurity/unicode.mapping \
    && sed -i 's/^SecRuleEngine .*/SecRuleEngine On/; s/^SecRequestBodyLimit .*/SecRequestBodyLimit 67108864/; s/^SecRequestBodyNoFilesLimit .*/SecRequestBodyNoFilesLimit 1048576/; s/^SecRequestBodyLimitAction .*/SecRequestBodyLimitAction Reject/; s|^SecAuditLog .*|SecAuditLog /var/log/modsecurity/audit.log|; s/^SecAuditLogType .*/SecAuditLogType Serial/; s/^SecAuditEngine .*/SecAuditEngine RelevantOnly/; s/^SecAuditLogRelevantStatus .*/SecAuditLogRelevantStatus \"^(?:5|4(?!04))\"/; s/^SecAuditLogParts .*/SecAuditLogParts ABIJDEFHZ/' /etc/modsecurity/modsecurity.conf \
    && if [ -f /usr/share/modsecurity-crs/crs-setup.conf.example ]; then cp /usr/share/modsecurity-crs/crs-setup.conf.example /etc/nginx/modsecurity/crs-setup.conf; elif [ -f /etc/modsecurity/crs/crs-setup.conf ]; then cp /etc/modsecurity/crs/crs-setup.conf /etc/nginx/modsecurity/crs-setup.conf; else touch /etc/nginx/modsecurity/crs-setup.conf; fi \
    && if [ -d /usr/share/modsecurity-crs/rules ]; then printf 'Include /usr/share/modsecurity-crs/rules/*.conf\n' > /etc/nginx/modsecurity/owasp-crs.load; else printf 'Include /usr/share/owasp-modsecurity-crs/rules/*.conf\n' > /etc/nginx/modsecurity/owasp-crs.load; fi \
    && chown -R www-data:adm /var/log/modsecurity \
    && chmod 0775 /var/log/modsecurity

COPY nginx/appdashboard.conf /etc/nginx/sites-available/appdashboard.conf
COPY deploy/waf/appdashboard-modsecurity.conf /etc/nginx/modsecurity/appdashboard.conf
COPY deploy/waf/appdashboard-crs-exclusions.conf /etc/nginx/modsecurity/appdashboard-crs-exclusions.conf
COPY deploy/mosquitto/smarthome.conf /etc/mosquitto/conf.d/smarthome.conf
COPY supervisord.conf /etc/supervisor/conf.d/appdashboard.conf
COPY --from=app-builder /var/www/AppDashboard/ /var/www/AppDashboard/
RUN mkdir -p /var/www/AppDashboard/apps/lopaka
WORKDIR /var/www/AppDashboard
COPY apps/lopaka/ /var/www/AppDashboard/apps/lopaka/
RUN cp -a /var/www/AppDashboard/apps/lopaka /usr/local/share/lopaka
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# restic powers the integrated backup manager (replaces the Duplicati service).
ARG RESTIC_VERSION=0.18.1
RUN set -eux; \
    arch="$(dpkg --print-architecture)"; \
    case "$arch" in \
        amd64) rarch=amd64 ;; \
        arm64) rarch=arm64 ;; \
        armhf) rarch=arm ;; \
        *) echo "Unsupported architecture for restic: $arch" >&2; exit 1 ;; \
    esac; \
    curl -fsSL -o /tmp/restic.bz2 "https://github.com/restic/restic/releases/download/v${RESTIC_VERSION}/restic_${RESTIC_VERSION}_linux_${rarch}.bz2"; \
    bunzip2 -c /tmp/restic.bz2 > /usr/local/bin/restic; \
    chmod 0755 /usr/local/bin/restic; \
    rm -f /tmp/restic.bz2; \
    restic version

RUN ln -sf /etc/nginx/sites-available/appdashboard.conf /etc/nginx/sites-enabled/default \
    && rm -f /etc/nginx/sites-enabled/default.bak \
    && sed -i 's|^listen = 9000|listen = /run/php/php-fpm.sock|' /usr/local/etc/php-fpm.d/docker.conf \
    && sed -i 's|^;listen.owner = www-data|listen.owner = www-data|' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's|^;listen.group = www-data|listen.group = www-data|' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's|^;listen.mode = 0660|listen.mode = 0660|' /usr/local/etc/php-fpm.d/www.conf \
    && chmod +x /usr/local/bin/docker-entrypoint.sh
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
