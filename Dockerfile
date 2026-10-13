FROM php:8.5-fpm-bookworm
ENV DEBIAN_FRONTEND=noninteractive
ENV HYPERFRAMES_TELEMETRY_DISABLED=1
ENV ONNXRUNTIME_NODE_INSTALL_CUDA=skip
ENV ONNXRUNTIME_PREFER_CPU=1
ENV PIP_DEFAULT_TIMEOUT=300
ENV PIP_RETRIES=10
ENV HOME=/opt/ApplicationData/cloud_video_tmp/.hyperframes-home
ENV XDG_CACHE_HOME=/opt/ApplicationData/cloud_video_tmp/.hyperframes-cache
ENV npm_config_cache=/opt/ApplicationData/cloud_video_tmp/.hyperframes-cache/npm
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx libnginx-mod-http-modsecurity modsecurity-crs supervisor git curl ca-certificates sudo unzip zip p7zip-full mosquitto mosquitto-clients \
    libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libxml2-dev libonig-dev libicu-dev libcurl4-openssl-dev ffmpeg tesseract-ocr tesseract-ocr-eng tesseract-ocr-rus tesseract-ocr-bel \
    tesseract-ocr-ukr smartmontools util-linux openssh-client \
    default-jdk-headless \
    python3 python3-pip python3-venv python3-dev python3-psycopg2 build-essential autoconf pkg-config docker.io \
    postgresql-client imagemagick poppler-utils libreoffice pandoc \
    fonts-liberation fonts-dejavu-core \
    libnss3 libatk-bridge2.0-0 libgtk-3-0 libx11-xcb1 libxcomposite1 libxdamage1 libxrandr2 libgbm1 libasound2 \
    texlive-latex-base texlive-latex-recommended texlive-latex-extra \
    texlive-fonts-recommended texlive-lang-cyrillic texlive-xetex texlive-plain-generic latexmk \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g hyperframes \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && python3 -m pip install --break-system-packages --no-cache-dir --extra-index-url https://download.pytorch.org/whl/cpu torch \
    && python3 -m pip install --break-system-packages --no-cache-dir gTTS openai-whisper pillow numpy scipy opencv-python-headless rembg onnxruntime psycopg2-binary \
    && python3 -m pip install --break-system-packages --no-cache-dir 'bankstatementparser[excel]' \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pgsql gd zip mbstring intl bcmath sockets curl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
ENV ANDROID_HOME=/opt/android-sdk
ENV ANDROID_SDK_ROOT=/opt/android-sdk
ENV PATH=/opt/android-sdk/cmdline-tools/latest/bin:/opt/android-sdk/platform-tools:$PATH
RUN mkdir -p /opt/android-sdk/cmdline-tools \
    && curl -fsSL -o /tmp/android-commandlinetools.zip https://dl.google.com/android/repository/commandlinetools-linux-13114758_latest.zip \
    && unzip -q /tmp/android-commandlinetools.zip -d /opt/android-sdk/cmdline-tools \
    && mv /opt/android-sdk/cmdline-tools/cmdline-tools /opt/android-sdk/cmdline-tools/latest \
    && rm /tmp/android-commandlinetools.zip \
    && yes | sdkmanager --licenses >/dev/null \
    && sdkmanager "platform-tools" "platforms;android-35" "build-tools;35.0.0" \
    && chown -R www-data:www-data /opt/android-sdk
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
COPY . /var/www/AppDashboard/
RUN rm -rf /var/www/AppDashboard/apps/tft-generator \
    && mkdir -p /var/www/AppDashboard/apps/lopaka
WORKDIR /var/www/AppDashboard
RUN cd /var/www/AppDashboard && composer install --no-interaction --no-progress --no-dev --prefer-dist --optimize-autoloader \
    && cd /var/www/AppDashboard/webssh && composer install --no-interaction --no-progress --no-dev --prefer-dist --optimize-autoloader \
    && cd /var/www/AppDashboard/apps/cloud && composer install --no-interaction --no-progress --no-dev --prefer-dist --optimize-autoloader
COPY apps/lopaka/ /var/www/AppDashboard/apps/lopaka/
RUN cp -a /var/www/AppDashboard/apps/lopaka /usr/local/share/lopaka
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
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
