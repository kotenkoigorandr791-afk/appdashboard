FROM node:22.3.0-alpine AS lopaka-build
WORKDIR /src
RUN apk add --no-cache git \
    && npm install -g pnpm@10.30.3 \
    && git init \
    && git remote add origin https://github.com/sbrin/lopaka.git \
    && git fetch --depth 1 origin dacfa70936a0f1eab456b09938eec36945cfd0cd \
    && git checkout FETCH_HEAD \
    && HUSKY=0 pnpm install --frozen-lockfile \
    && sed -i "s|base: '/',|base: '/apps/lopaka/',|; s|sourcemap: true,|sourcemap: false,|; s|assetsInlineLimit: 0,|assetsInlineLimit: 0,\n        reportCompressedSize: false,|" vite.config.mts \
    && NODE_OPTIONS="--max-old-space-size=16384" pnpm build \
    && find dist -name '*.map' -type f -delete

FROM php:8.3-fpm-bookworm
ENV DEBIAN_FRONTEND=noninteractive
ENV HYPERFRAMES_TELEMETRY_DISABLED=1
ENV HOME=/opt/ApplicationData/cloud_video_tmp/.hyperframes-home
ENV XDG_CACHE_HOME=/opt/ApplicationData/cloud_video_tmp/.hyperframes-cache
ENV npm_config_cache=/opt/ApplicationData/cloud_video_tmp/.hyperframes-cache/npm
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor git curl ca-certificates sudo unzip zip p7zip-full \
    libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libxml2-dev libonig-dev libicu-dev ffmpeg tesseract-ocr tesseract-ocr-rus \
    tesseract-ocr-ukr smartmontools util-linux openssh-client \
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
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pgsql gd zip mbstring intl bcmath opcache sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
RUN echo 'www-data ALL=(ALL) NOPASSWD: /usr/sbin/smartctl' > /etc/sudoers.d/www-data-smartctl \
    && chmod 440 /etc/sudoers.d/www-data-smartctl
RUN mkdir -p /run/php /var/log/supervisor /opt/AppDashboard/data /opt/ApplicationData/cloud_video_tmp/.hyperframes-home /opt/ApplicationData/cloud_video_tmp/.hyperframes-cache/npm /var/www/.hyperframes \
    && chown -R www-data:www-data /run/php /opt/AppDashboard /opt/ApplicationData /var/www/.hyperframes \
    && su -s /bin/sh www-data -c 'hyperframes telemetry disable || true'
COPY nginx/appdashboard.conf /etc/nginx/sites-available/appdashboard.conf
COPY supervisord.conf /etc/supervisor/conf.d/appdashboard.conf
COPY . /var/www/AppDashboard/
RUN rm -rf /var/www/AppDashboard/apps/tft-generator \
    && mkdir -p /var/www/AppDashboard/apps/lopaka
COPY --from=lopaka-build /src/dist/ /var/www/AppDashboard/apps/lopaka/
COPY apps/lopaka/ /var/www/AppDashboard/apps/lopaka/
RUN cp -a /var/www/AppDashboard/apps/lopaka /usr/local/share/lopaka
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN ln -sf /etc/nginx/sites-available/appdashboard.conf /etc/nginx/sites-enabled/default \
    && rm -f /etc/nginx/sites-enabled/default.bak \
    && sed -i 's|^listen = 9000|listen = /run/php/php8.3-fpm.sock|' /usr/local/etc/php-fpm.d/docker.conf \
    && sed -i 's|^;listen.owner = www-data|listen.owner = www-data|' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's|^;listen.group = www-data|listen.group = www-data|' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's|^;listen.mode = 0660|listen.mode = 0660|' /usr/local/etc/php-fpm.d/www.conf \
    && chmod +x /usr/local/bin/docker-entrypoint.sh
WORKDIR /var/www/AppDashboard
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
