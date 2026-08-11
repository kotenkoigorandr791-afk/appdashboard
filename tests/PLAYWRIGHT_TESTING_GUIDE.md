# Руководство по организации Playwright тестов для AppDashboard

## Обзор проекта

AppDashboard — это многоприложенная PHP-платформа (PHP 8.3) с архитектурой:
- **Основной роутер**: `index.php` → `project/index.php`
- **Ядро фреймворка**: `project/` (аутентификация, OAuth, UI shell, мониторинг)
- **Под-приложения**: `apps/cloud`, `apps/films`, `apps/map`, `apps/recipes`, `apps/spends`, `apps/steps`, `apps/viewer` и др.
- **Инфраструктура**: Docker (nginx + PHP-FPM + PostgreSQL)

---

## Рекомендуемая структура тестов

```
/home/ihor/appdashboard-code/
├── tests/
│   ├── e2e/                          # End-to-end тесты
│   │   ├── setup/                    # Настройка и утилиты тестов
│   │   │   ├── global-setup.ts       # Глобальная настройка (БД, авторизация)
│   │   │   ├── global-teardown.ts    # Очистка после тестов
│   │   │   ├── test-fixtures.ts      # Общие фикстуры
│   │   │   └── auth-helpers.ts       # Хелперы аутентификации
│   │   ├── apps/                     # Тесты по приложениям
│   │   │   ├── cloud/                # Cloud storage app
│   │   │   │   ├── file-operations.spec.ts
│   │   │   │   ├── editor.spec.ts
│   │   │   │   ├── ai-assistant.spec.ts
│   │   │   │   └── webdav.spec.ts
│   │   │   ├── films/                # Films app
│   │   │   │   ├── catalog.spec.ts
│   │   │   │   └── comments.spec.ts
│   │   │   ├── map/                  # Maps app
│   │   │   │   ├── locations.spec.ts
│   │   │   │   └── favorites.spec.ts
│   │   │   ├── recipes/              # Recipes app
│   │   │   │   ├── recipes.spec.ts
│   │   │   │   └── products.spec.ts
│   │   │   ├── spends/               # Expense tracking
│   │   │   │   ├── expenses.spec.ts
│   │   │   │   └── reports.spec.ts
│   │   │   ├── steps/                # Step counter
│   │   │   │   ├── counter.spec.ts
│   │   │   │   └── ocr.spec.ts
│   │   │   └── viewer/               # Network monitoring
│   │   │       ├── dashboard.spec.ts
│   │   │       └── devices.spec.ts
│   │   ├── core/                     # Тесты ядра фреймворка
│   │   │   ├── auth.spec.ts          # Login/OAuth флоу
│   │   │   ├── navigation.spec.ts    # Навигация и меню
│   │   │   ├── performance.spec.ts   # Performance monitoring
│   │   │   └── i18n.spec.ts          # Многоязычность
│   │   └── smoke/                    # Критические smoke-тесты
│   │       └── critical-flows.spec.ts
│   ├── config/                       # Конфигурация Playwright
│   │   ├── playwright.config.ts      # Основная конфигурация
│   │   ├── playwright.docker.config.ts # Docker-специфичная конфигурация
│   │   └── projects.ts               # Проекты браузеров/устройств
│   ├── docker/                       # Docker тестовое окружение
│   │   ├── Dockerfile.test           # Образ для тестов приложения
│   │   ├── Dockerfile.playwright     # Образ для Playwright runner
│   │   ├── docker-compose.test.yml   # Docker Compose для тестов
│   │   └── wait-for-services.sh      # Скрипт ожидания сервисов
│   ├── test-data/                    # Тестовые данные
│   │   ├── files/                    # Файлы для загрузки
│   │   ├── fixtures/                 # JSON фикстуры
│   │   └── sql/                      # SQL дампы для сидинга БД
│   └── package.json                  # Зависимости Playwright
```

---

## Конфигурационные файлы

### 1. `tests/package.json`

```json
{
  "name": "appdashboard-e2e-tests",
  "version": "1.0.0",
  "private": true,
  "description": "E2E tests for AppDashboard using Playwright",
  "scripts": {
    "test": "playwright test",
    "test:headed": "playwright test --headed",
    "test:debug": "playwright test --debug",
    "test:ui": "playwright test --ui",
    "test:docker": "docker compose -f docker/docker-compose.test.yml up --abort-on-container-exit --exit-code-from playwright",
    "test:ci": "playwright test --reporter=line,junit,html",
    "report": "playwright show-report",
    "install:browsers": "playwright install --with-deps chromium firefox webkit",
    "codegen": "playwright codegen http://localhost"
  },
  "devDependencies": {
    "@playwright/test": "^1.44.0",
    "typescript": "^5.4.0",
    "@types/node": "^20.12.0"
  },
  "engines": {
    "node": ">=18.0.0"
  }
}
```

### 2. `tests/config/playwright.config.ts`

```typescript
import { defineConfig, devices } from '@playwright/test';
import path from 'path';

/**
 * Основная конфигурация Playwright для AppDashboard
 * 
 * Поддерживает запуск:
 * - Локально: npm run test (использует существующий docker-compose.yml)
 * - В CI: npm run test:ci (изолированное окружение)
 * - В Docker: npm run test:docker (полностью изолированный стек)
 */
export default defineConfig({
  // Директория с тестами
  testDir: '../e2e',
  
  // Параллельный запуск тестов
  fullyParallel: true,
  
  // Запрет only-тестов в CI
  forbidOnly: !!process.env.CI,
  
  // Повторные попытки при падении
  retries: process.env.CI ? 2 : 0,
  
  // Количество воркеров (1 в CI для стабильности)
  workers: process.env.CI ? 1 : undefined,
  
  // Таймауты
  timeout: 60000,           // На тест
  expect: { timeout: 10000 }, // На assertions
  
  // Репортеры
  reporter: [
    ['html', { 
      outputFolder: 'test-results/html-report',
      open: 'never'
    }],
    ['json', { 
      outputFile: 'test-results/results.json' 
    }],
    ['junit', { 
      outputFile: 'test-results/junit.xml' 
    }],
    process.env.CI ? ['github'] : ['line']
  ],
  
  // Глобальные настройки для всех тестов
  use: {
    // Базовый URL (переопределяется через TEST_BASE_URL)
    baseURL: process.env.TEST_BASE_URL || 'http://localhost',
    
    // Трассировка при падении
    trace: 'on-first-retry',
    
    // Скриншоты при падении
    screenshot: 'only-on-failure',
    
    // Видео при падении
    video: 'retain-on-failure',
    
    // Контекст браузера
    viewport: { width: 1280, height: 720 },
    ignoreHTTPSErrors: true,
    
    // Локаль и таймзона
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow',
    
    // Дополнительные заголовки
    extraHTTPHeaders: {
      'Accept-Language': 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7'
    }
  },
  
  // Проекты (браузеры/устройства)
  projects: [
    {
      name: 'chromium',
      use: { 
        ...devices['Desktop Chrome'],
        // Включаем DevTools protocol для отладки
        launchOptions: {
          args: ['--disable-web-security', '--disable-features=IsolateOrigins']
        }
      }
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] }
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] }
    },
    // Мобильные устройства
    {
      name: 'mobile-chrome',
      use: { ...devices['Pixel 5'] }
    },
    {
      name: 'mobile-safari',
      use: { ...devices['iPhone 12'] }
    },
    // Планшеты
    {
      name: 'tablet',
      use: { ...devices['iPad Pro'] }
    }
  ],
  
  // Локальный веб-сервер (только для локального запуска)
  webServer: process.env.CI ? undefined : {
    command: 'docker compose up -d',
    url: 'http://localhost',
    reuseExistingServer: !process.env.CI,
    timeout: 120000,
    env: {
      APP_ENV: 'local',
      APP_DEBUG: '1'
    }
  },
  
  // Глобальные setup/teardown
  globalSetup: path.resolve(__dirname, '../e2e/setup/global-setup.ts'),
  globalTeardown: path.resolve(__dirname, '../e2e/setup/global-teardown.ts'),
  
  // Папка для артефактов
  outputDir: 'test-results/artifacts',
  
  // Метаданные для репортов
  metadata: {
    project: 'AppDashboard',
    version: process.env.APP_VERSION || 'dev',
    environment: process.env.APP_ENV || 'local'
  }
});
```

### 3. `tests/config/playwright.docker.config.ts`

```typescript
import { defineConfig } from '@playwright/test';
import baseConfig from './playwright.config';

/**
 * Docker-специфичная конфигурация
 * Используется при запуске в изолированном Docker окружении
 */
export default defineConfig({
  ...baseConfig,
  
  // В Docker базовый URL — имя сервиса
  use: {
    ...baseConfig.use,
    baseURL: process.env.TEST_BASE_URL || 'http://test-app'
  },
  
  // В Docker не нужен локальный веб-сервер
  webServer: undefined,
  
  // Увеличенные таймауты для Docker
  timeout: 120000,
  expect: { timeout: 30000 },
  
  // Только Chromium в Docker для скорости
  projects: baseConfig.projects.filter(p => p.name === 'chromium'),
  
  // Репортеры для CI
  reporter: [
    ['html', { outputFolder: 'test-results/html-report' }],
    ['json', { outputFile: 'test-results/results.json' }],
    ['junit', { outputFile: 'test-results/junit.xml' }],
    ['github']
  ]
});
```

### 4. `tests/config/projects.ts`

```typescript
import { devices } from '@playwright/test';

/**
 * Определение проектов (браузеры/устройства) для тестирования
 * Можно расширять под нужды проекта
 */

export const desktopProjects = [
  {
    name: 'chromium',
    use: { 
      ...devices['Desktop Chrome'],
      viewport: { width: 1920, height: 1080 }
    }
  },
  {
    name: 'firefox',
    use: { 
      ...devices['Desktop Firefox'],
      viewport: { width: 1920, height: 1080 }
    }
  },
  {
    name: 'webkit',
    use: { 
      ...devices['Desktop Safari'],
      viewport: { width: 1920, height: 1080 }
    }
  }
];

export const mobileProjects = [
  {
    name: 'mobile-chrome',
    use: { ...devices['Pixel 5'] }
  },
  {
    name: 'mobile-safari',
    use: { ...devices['iPhone 12'] }
  },
  {
    name: 'mobile-chrome-landscape',
    use: { 
      ...devices['Pixel 5'],
      viewport: { width: 896, height: 412 }
    }
  }
];

export const tabletProjects = [
  {
    name: 'tablet-portrait',
    use: { ...devices['iPad Pro'] }
  },
  {
    name: 'tablet-landscape',
    use: { 
      ...devices['iPad Pro'],
      viewport: { width: 1366, height: 1024 }
    }
  }
];

export const allProjects = [
  ...desktopProjects,
  ...mobileProjects,
  ...tabletProjects
];

// Для быстрого локального запуска — только Chromium
export const quickProjects = [
  desktopProjects[0]
];

// Для полного CI — все проекты
export const ciProjects = allProjects;
```

---

## Docker тестовое окружение

### 1. `tests/docker/Dockerfile.test`

```dockerfile
# Образ для запуска приложения в тестовом режиме
FROM php:8.3-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive
ENV APP_ENV=test
ENV APP_DEBUG=1

# Установка системных зависимостей
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor git curl ca-certificates sudo unzip zip \
    libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libxml2-dev libonig-dev libicu-dev ffmpeg tesseract-ocr tesseract-ocr-rus \
    tesseract-ocr-ukr smartmontools util-linux openssh-client \
    python3 python3-pip python3-venv python3-psycopg2 build-essential \
    postgresql-client imagemagick poppler-utils \
    libnss3 libatk-bridge2.0-0 libgtk-3-0 libx11-xcb1 libxcomposite1 \
    libxdamage1 libxrandr2 libgbm1 libasound2 fonts-dejavu-core \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g hyperframes \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pgsql gd zip mbstring intl bcmath opcache sockets \
    && rm -rf /var/lib/apt/lists/*

# Настройка пользователя www-data
RUN echo 'www-data ALL=(ALL) NOPASSWD: /usr/sbin/smartctl' > /etc/sudoers.d/www-data-smartctl \
    && chmod 440 /etc/sudoers.d/www-data-smartctl \
    && mkdir -p /run/php /var/log/supervisor /opt/AppDashboard/data /opt/ApplicationData \
    && chown -R www-data:www-data /run/php /opt/AppDashboard /opt/ApplicationData

# Копирование конфигураций
COPY ../../nginx/appdashboard.conf /etc/nginx/sites-available/appdashboard.conf
COPY ../../supervisord.conf /etc/supervisor/conf.d/appdashboard.conf
COPY ../../docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN ln -sf /etc/nginx/sites-available/appdashboard.conf /etc/nginx/sites-enabled/default \
    && sed -i 's|^listen = 9000|listen = /run/php/php8.3-fpm.sock|' /usr/local/etc/php-fpm.d/docker.conf \
    && sed -i 's|^;listen.owner = www-data|listen.owner = www-data|' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's|^;listen.group = www-data|listen.group = www-data|' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's|^;listen.mode = 0660|listen.mode = 0660|' /usr/local/etc/php-fpm.d/www.conf \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/AppDashboard

# Копирование кода приложения (read-only для безопасности)
COPY --chown=www-data:www-data ../../ /var/www/AppDashboard/

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
```

### 2. `tests/docker/Dockerfile.playwright`

```dockerfile
# Образ для запуска Playwright тестов
FROM mcr.microsoft.com/playwright:v1.44.0-noble

WORKDIR /app

# Копирование package.json для кэширования зависимостей
COPY package*.json ./

# Установка зависимостей
RUN npm ci

# Копирование конфигурации и тестов
COPY config/ ./config/
COPY e2e/ ./e2e/
COPY test-data/ ./test-data/

# Переменные окружения
ENV CI=true
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

# Точка входа
ENTRYPOINT ["npx", "playwright", "test"]
```

### 3. `tests/docker/docker-compose.test.yml`

```yaml
version: '3.8'

services:
  # Тестовая база данных PostgreSQL
  test-db:
    image: postgres:16-alpine
    container_name: appdashboard-test-db
    environment:
      POSTGRES_DB: appdashboard_test
      POSTGRES_USER: appdashboard
      POSTGRES_PASSWORD: test_password
      POSTGRES_INITDB_ARGS: "--auth-host=scram-sha-256"
    ports:
      - "15433:5432"
    volumes:
      - test-db-data:/var/lib/postgresql/data
      - ./test-data/sql/init-test-db.sql:/docker-entrypoint-initdb.d/init-test-db.sql:ro
    tmpfs:
      - /var/lib/postgresql/data:uid=999,gid=999
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U appdashboard -d appdashboard_test"]
      interval: 5s
      timeout: 5s
      retries: 10
    networks:
      - test-network

  # Тестовое приложение
  test-app:
    build:
      context: ../..
      dockerfile: tests/docker/Dockerfile.test
    container_name: appdashboard-test-app
    environment:
      APP_ENV: test
      APP_DEBUG: "1"
      APP_DB_CONFIG: /opt/AppDashboard/data/db.env
      APP_DB_SKIP_SESSION_INIT: "1"
      APPDATA_BASE_DIR: /opt/ApplicationData
      DB_HOST: test-db
      DB_PORT: "5432"
      DB_NAME: appdashboard_test
      DB_USER: appdashboard
      DB_PASSWORD: test_password
      RUN_STARTUP_DEPS: "1"
      SSH_WS_PORT: "8765"
      SSH_WS_BIND: "127.0.0.1"
      SMART_HOME_WS_PORT: "8766"
      SMART_HOME_WS_BIND: "127.0.0.1"
    ports:
      - "8080:80"
    volumes:
      - ../../:/var/www/AppDashboard:ro
      - test-app-data:/opt/ApplicationData
      - test-app-logs:/var/log/nginx
    depends_on:
      test-db:
        condition: service_healthy
    networks:
      - test-network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

  # Playwright runner
  playwright:
    build:
      context: .
      dockerfile: Dockerfile.playwright
    container_name: appdashboard-playwright
    environment:
      TEST_BASE_URL: http://test-app
      CI: "true"
      APP_VERSION: "${APP_VERSION:-test}"
      APP_ENV: test
    volumes:
      - ./test-results:/app/test-results
      - ./test-data:/app/test-data:ro
    depends_on:
      test-app:
        condition: service_healthy
    networks:
      - test-network
    # Команда переопределяется при запуске
    # command: npx playwright test --config=config/playwright.docker.config.ts

networks:
  test-network:
    driver: bridge

volumes:
  test-db-data:
  test-app-data:
  test-app-logs:
```

### 4. `tests/docker/wait-for-services.sh`

```bash
#!/bin/bash
# Скрипт ожидания готовности сервисов перед запуском тестов

set -e

HOST="${1:-test-app}"
PORT="${2:-80}"
MAX_ATTEMPTS=60
ATTEMPT=0

echo "Waiting for $HOST:$PORT to be ready..."

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    if curl -sf "http://$HOST:$PORT/" > /dev/null 2>&1; then
        echo "Service is ready!"
        exit 0
    fi
    
    ATTEMPT=$((ATTEMPT + 1))
    echo "Attempt $ATTEMPT/$MAX_ATTEMPTS - waiting..."
    sleep 2
done

echo "Service did not become ready in time"
exit 1
```

---

## Setup и утилиты

### 1. `tests/e2e/setup/global-setup.ts`

```typescript
import { chromium, FullConfig } from '@playwright/test';
import fs from 'fs';
import path from 'path';

/**
 * Глобальная настройка перед запуском всех тестов
 * Выполняется один раз перед всеми тестами
 */
async function globalSetup(config: FullConfig) {
  console.log('🔧 Starting global setup...');
  
  const baseURL = config.projects[0].use?.baseURL || 'http://localhost';
  const authFile = path.resolve(__dirname, '../../test-results/auth-state.json');
  
  // Создаем директорию для результатов
  fs.mkdirSync(path.dirname(authFile), { recursive: true });
  
  // Запускаем браузер для настройки авторизации
  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();
  
  try {
    // Ждем готовности приложения
    await waitForAppReady(page, baseURL);
    
    // Создаем тестового пользователя (если нужно)
    await ensureTestUserExists(page, baseURL);
    
    // Логинимся и сохраняем состояние авторизации
    await loginAndSaveAuth(page, baseURL, authFile);
    
    console.log('✅ Global setup completed');
  } catch (error) {
    console.error('❌ Global setup failed:', error);
    throw error;
  } finally {
    await browser.close();
  }
}

/**
 * Ожидание готовности приложения
 */
async function waitForAppReady(page: any, baseURL: string) {
  const maxAttempts = 30;
  for (let i = 0; i < maxAttempts; i++) {
    try {
      const response = await page.goto(baseURL, { 
        waitUntil: 'networkidle',
        timeout: 10000 
      });
      if (response?.ok()) {
        console.log('Application is ready');
        return;
      }
    } catch (e) {
      // Игнорируем ошибки, ждем следующей попытки
    }
    console.log(`Waiting for app... attempt ${i + 1}/${maxAttempts}`);
    await page.waitForTimeout(2000);
  }
  throw new Error('Application did not become ready in time');
}

/**
 * Обеспечение существования тестового пользователя
 */
async function ensureTestUserExists(page: any, baseURL: string) {
  // Здесь можно добавить логику создания пользователя через API
  // или проверку в БД
  console.log('Ensuring test user exists...');
  
  // Пример: вызов API для создания пользователя
  // await page.request.post(`${baseURL}/api/test/setup-user`, {
  //   data: { email: 'test@example.com', password: 'testpassword' }
  // });
}

/**
 * Логин и сохранение состояния авторизации
 */
async function loginAndSaveAuth(page: any, baseURL: string, authFile: string) {
  console.log('Logging in and saving auth state...');
  
  await page.goto(`${baseURL}/login`);
  
  // Заполняем форму входа
  await page.fill('input[name="email"]', process.env.TEST_USER_EMAIL || 'test@example.com');
  await page.fill('input[name="password"]', process.env.TEST_USER_PASSWORD || 'testpassword');
  await page.click('button[type="submit"]');
  
  // Ждем редиректа
  await page.waitForURL(/\/(apps|cloud)/, { timeout: 10000 });
  
  // Сохраняем состояние (cookies, localStorage)
  await context.storageState({ path: authFile });
  
  console.log('Auth state saved to', authFile);
}

export default globalSetup;
```

### 2. `tests/e2e/setup/global-teardown.ts`

```typescript
import { FullConfig } from '@playwright/test';
import fs from 'fs';
import path from 'path';

/**
 * Глобальная очистка после завершения всех тестов
 */
async function globalTeardown(config: FullConfig) {
  console.log('🧹 Starting global teardown...');
  
  const authFile = path.resolve(__dirname, '../../test-results/auth-state.json');
  
  // Очистка временных файлов
  if (fs.existsSync(authFile)) {
    fs.unlinkSync(authFile);
    console.log('Removed auth state file');
  }
  
  // Можно добавить очистку тестовых данных в БД
  // await cleanupTestDatabase();
  
  console.log('✅ Global teardown completed');
}

export default globalTeardown;
```

### 3. `tests/e2e/setup/test-fixtures.ts`

```typescript
import { test as base, Page, BrowserContext } from '@playwright/test';
import path from 'path';

// Типы для фикстур
interface TestFixtures {
  authenticatedPage: Page;
  adminPage: Page;
  testData: TestDataHelper;
}

interface TestDataHelper {
  createTestFile: (name: string, content: string) => Promise<string>;
  cleanupTestFiles: () => Promise<void>;
}

/**
 * Расширенные фикстуры для тестов
 */
export const test = base.extend<TestFixtures>({
  // Страница с уже выполненным логином
  authenticatedPage: async ({ page, context }, use) => {
    const authFile = path.resolve(__dirname, '../../../test-results/auth-state.json');
    
    if (require('fs').existsSync(authFile)) {
      // Загружаем сохраненное состояние авторизации
      await context.addCookies(JSON.parse(require('fs').readFileSync(authFile, 'utf-8')).cookies);
    }
    
    await use(page);
  },
  
  // Страница с админскими правами
  adminPage: async ({ browser }, use) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    
    // Логин под админом
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'adminpassword');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/(apps|cloud)/);
    
    await use(page);
    await context.close();
  },
  
  // Хелпер для тестовых данных
  testData: async ({ page }, use) => {
    const helper: TestDataHelper = {
      createdFiles: [],
      
      async createTestFile(name: string, content: string) {
        const fs = require('fs');
        const testDir = path.resolve(__dirname, '../../../test-data/files');
        fs.mkdirSync(testDir, { recursive: true });
        
        const filePath = path.join(testDir, name);
        fs.writeFileSync(filePath, content);
        this.createdFiles.push(filePath);
        return filePath;
      },
      
      async cleanupTestFiles() {
        const fs = require('fs');
        for (const file of this.createdFiles) {
          if (fs.existsSync(file)) {
            fs.unlinkSync(file);
          }
        }
        this.createdFiles = [];
      }
    };
    
    await use(helper);
    await helper.cleanupTestFiles();
  }
});

export { expect } from '@playwright/test';
```

### 4. `tests/e2e/setup/auth-helpers.ts`

```typescript
import { Page, BrowserContext } from '@playwright/test';

/**
 * Хелперы для работы с аутентификацией
 */

export interface UserCredentials {
  email: string;
  password: string;
  name?: string;
}

export const TEST_USERS: Record<string, UserCredentials> = {
  standard: {
    email: process.env.TEST_USER_EMAIL || 'test@example.com',
    password: process.env.TEST_USER_PASSWORD || 'testpassword',
    name: 'Test User'
  },
  admin: {
    email: process.env.TEST_ADMIN_EMAIL || 'admin@example.com',
    password: process.env.TEST_ADMIN_PASSWORD || 'adminpassword',
    name: 'Admin User'
  },
  readonly: {
    email: process.env.TEST_READONLY_EMAIL || 'readonly@example.com',
    password: process.env.TEST_READONLY_PASSWORD || 'readonlypassword',
    name: 'Readonly User'
  }
};

/**
 * Выполняет логин пользователя
 */
export async function login(page: Page, user: UserCredentials = TEST_USERS.standard): Promise<void> {
  await page.goto('/login');
  
  // Ждем загрузки формы
  await page.waitForSelector('input[name="email"]', { state: 'visible' });
  
  // Заполняем форму
  await page.fill('input[name="email"]', user.email);
  await page.fill('input[name="password"]', user.password);
  
  // Нажимаем кнопку входа
  await page.click('button[type="submit"]');
  
  // Ждем редиректа на главную страницу приложений
  await page.waitForURL(/\/(apps|cloud)/, { timeout: 15000 });
  
  // Проверяем успешный вход
  await expect(page.locator('body')).not.toContainText('Неверный email или пароль');
}

/**
 * Выполняет логаут
 */
export async function logout(page: Page): Promise<void> {
  // Ищем кнопку выхода (может быть в меню пользователя)
  const userMenu = page.locator('[data-testid="user-menu"], .user-menu, .dropdown-toggle').first();
  
  if (await userMenu.isVisible({ timeout: 5000 })) {
    await userMenu.click();
    await page.click('text=Выход, text=Logout, [data-action="logout"]');
    await page.waitForURL('/login');
  }
}

/**
 * Логин через OAuth (GitHub, Google, etc.)
 */
export async function loginViaOAuth(page: Page, provider: 'github' | 'google' | 'gitea'): Promise<void> {
  await page.goto('/login');
  
  // Нажимаем кнопку OAuth провайдера
  const providerButton = page.locator(`button:has-text("${provider.charAt(0).toUpperCase() + provider.slice(1)}")`);
  await providerButton.click();
  
  // В тестовом окружении OAuth может быть замокан
  // Ждем редиректа обратно
  await page.waitForURL(/\/(apps|cloud)/, { timeout: 30000 });
}

/**
 * Создает контекст с предварительно залогиненным пользователем
 */
export async function createAuthenticatedContext(
  browser: any, 
  user: UserCredentials = TEST_USERS.standard
): Promise<BrowserContext> {
  const context = await browser.newContext();
  const page = await context.newPage();
  
  await login(page, user);
  
  // Сохраняем cookies для переиспользования
  const cookies = await context.cookies();
  
  return context;
}

/**
 * Проверяет, авторизован ли пользователь
 */
export async function isAuthenticated(page: Page): Promise<boolean> {
  try {
    // Проверяем наличие индикатора авторизованного пользователя
    await page.waitForSelector('[data-testid="user-avatar"], .user-avatar, .logged-in', { timeout: 5000 });
    return true;
  } catch {
    return false;
  }
}

/**
 * Ожидает завершения загрузки приложения после логина
 */
export async function waitForAppLoad(page: Page): Promise<void> {
  // Ждем загрузки основного контента
  await page.waitForSelector('#app-content, .main-content, [role="main"]', { timeout: 10000 });
  
  // Ждем завершения сетевых запросов
  await page.waitForLoadState('networkidle');
}
```

---

## Примеры тестов

### 1. `tests/e2e/core/auth.spec.ts`

```typescript
import { test, expect } from '../setup/test-fixtures';
import { login, logout, TEST_USERS, isAuthenticated } from '../setup/auth-helpers';

test.describe('Authentication', () => {
  test.beforeEach(async ({ page }) => {
    // Очищаем состояние перед каждым тестом
    await page.context().clearCookies();
  });

  test('should login with valid credentials', async ({ page }) => {
    await login(page, TEST_USERS.standard);
    await expect(page).toHaveURL(/\/(apps|cloud)/);
    await expect(await isAuthenticated(page)).toBeTruthy();
  });

  test('should show error for invalid credentials', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'wrong@example.com');
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');
    
    await expect(page.locator('.error, .alert-danger, [role="alert"]')).toContainText(
      /неверн|invalid|ошибк/i
    );
  });

  test('should logout successfully', async ({ authenticatedPage }) => {
    await logout(authenticatedPage);
    await expect(authenticatedPage).toHaveURL('/login');
    await expect(await isAuthenticated(authenticatedPage)).toBeFalsy();
  });

  test('should redirect to login when accessing protected page', async ({ page }) => {
    await page.goto('/apps/cloud/');
    await expect(page).toHaveURL('/login');
  });

  test('should remember login across page reloads', async ({ page }) => {
    await login(page, TEST_USERS.standard);
    await page.reload();
    await expect(await isAuthenticated(page)).toBeTruthy();
  });
});
```

### 2. `tests/e2e/apps/cloud/file-operations.spec.ts`

```typescript
import { test, expect } from '../setup/test-fixtures';
import { login, waitForAppLoad, TEST_USERS } from '../setup/auth-helpers';
import path from 'path';

test.describe('Cloud App - File Operations', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, TEST_USERS.standard);
    await page.goto('/apps/cloud/');
    await waitForAppLoad(page);
  });

  test('should upload a file via drag and drop', async ({ page, testData }) => {
    // Создаем тестовый файл
    const testFile = await testData.createTestFile('test-upload.txt', 'Hello World!');
    
    // Загружаем через input
    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(testFile);
    
    // Ждем появления файла в списке
    await expect(page.locator('.file-list, .file-grid, [data-testid="file-list"]'))
      .toContainText('test-upload.txt', { timeout: 10000 });
  });

  test('should create a new folder', async ({ page }) => {
    // Нажимаем кнопку создания папки
    await page.click('button:has-text("Новая папка"), button:has-text("New Folder"), [data-action="create-folder"]');
    
    // Заполняем имя папки
    await page.fill('input[name="folderName"], input[placeholder*="папк" i], input[placeholder*="folder" i]', 'Test Folder');
    
    // Подтверждаем создание
    await page.click('button:has-text("Создать"), button:has-text("Create"), [data-action="confirm-create"]');
    
    // Проверяем появление папки
    await expect(page.locator('.folder-item, .file-item.folder, [data-type="folder"]'))
      .toContainText('Test Folder');
  });

  test('should download a file', async ({ page }) => {
    // Сначала загружаем файл
    const testFile = await testData.createTestFile('download-test.txt', 'Download me!');
    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(testFile);
    await expect(page.locator('.file-list')).toContainText('download-test.txt');
    
    // Скачиваем файл
    const downloadPromise = page.waitForEvent('download');
    await page.click('[data-action="download"] >> nth=0'); // Или правый клик -> скачать
    const download = await downloadPromise;
    
    expect(download.suggestedFilename()).toBe('download-test.txt');
    
    // Проверяем содержимое
    const content = await download.createReadStream();
    // В реальном тесте можно проверить содержимое файла
  });

  test('should rename a file', async ({ page }) => {
    // Загружаем файл
    const testFile = await testData.createTestFile('rename-me.txt', 'Content');
    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(testFile);
    await expect(page.locator('.file-list')).toContainText('rename-me.txt');
    
    // Открываем контекстное меню (правый клик)
    const fileItem = page.locator('.file-item:has-text("rename-me.txt")').first();
    await fileItem.click({ button: 'right' });
    
    // Выбираем "Переименовать"
    await page.click('[data-action="rename"], text=Переименовать, text=Rename');
    
    // Вводим новое имя
    await page.fill('input[name="newName"], input.rename-input', 'renamed.txt');
    await page.keyboard.press('Enter');
    
    // Проверяем новое имя
    await expect(page.locator('.file-list')).toContainText('renamed.txt');
    await expect(page.locator('.file-list')).not.toContainText('rename-me.txt');
  });

  test('should delete a file', async ({ page }) => {
    // Загружаем файл
    const testFile = await testData.createTestFile('delete-me.txt', 'Delete me');
    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.setInputFiles(testFile);
    await expect(page.locator('.file-list')).toContainText('delete-me.txt');
    
    // Удаляем файл
    const fileItem = page.locator('.file-item:has-text("delete-me.txt")').first();
    await fileItem.click({ button: 'right' });
    await page.click('[data-action="delete"], text=Удалить, text=Delete');
    
    // Подтверждаем удаление в модальном окне
    await page.click('button:has-text("Подтвердить"), button:has-text("Confirm"), [data-action="confirm-delete"]');
    
    // Проверяем, что файл удален
    await expect(page.locator('.file-list')).not.toContainText('delete-me.txt');
  });
});
```

### 3. `tests/e2e/smoke/critical-flows.spec.ts`

```typescript
import { test, expect } from '../setup/test-fixtures';
import { login, waitForAppLoad, TEST_USERS } from '../setup/auth-helpers';

test.describe('Critical User Flows (Smoke Tests)', () => {
  test('complete user journey: login -> cloud -> upload -> logout', async ({ page }) => {
    // 1. Логин
    await login(page, TEST_USERS.standard);
    
    // 2. Переход в Cloud
    await page.goto('/apps/cloud/');
    await waitForAppLoad(page);
    await expect(page.locator('h1, .page-title')).toContainText(/cloud|облако/i);
    
    // 3. Загрузка файла
    const testFile = await testData.createTestFile('smoke-test.txt', 'Smoke test');
    await page.setInputFiles('input[type="file"]', testFile);
    await expect(page.locator('.file-list')).toContainText('smoke-test.txt');
    
    // 4. Проверка навигации
    await page.click('text=Файлы, text=Files, [href*="cloud"]');
    await expect(page).toHaveURL(/\/apps\/cloud/);
    
    // 5. Логаут
    await logout(page);
    await expect(page).toHaveURL('/login');
  });

  test('main navigation works', async ({ authenticatedPage }) => {
    const page = authenticatedPage;
    
    // Проверяем основные пункты меню
    const navItems = [
      { selector: 'a[href="/apps/cloud/"]', text: /cloud|облако/i },
      { selector: 'a[href="/apps/films/"]', text: /films|фильмы/i },
      { selector: 'a[href="/apps/map/"]', text: /map|карта/i },
      { selector: 'a[href="/apps/recipes/"]', text: /recipes|рецепты/i },
      { selector: 'a[href="/apps/spends/"]', text: /spends|расходы/i },
      { selector: 'a[href="/apps/steps/"]', text: /steps|шаги/i },
    ];
    
    for (const item of navItems) {
      const link = page.locator(item.selector).first();
      if (await link.isVisible({ timeout: 3000 })) {
        await link.click();
        await page.waitForLoadState('networkidle');
        // Просто проверяем, что страница загрузилась без ошибок
        await expect(page.locator('body')).not.toContainText(/500|error|ошибка/i);
      }
    }
  });

  test('language switching works', async ({ authenticatedPage }) => {
    const page = authenticatedPage;
    
    // Открываем селектор языка
    await page.click('[data-testid="language-selector"], .lang-selector, button:has-text("RU")');
    
    // Переключаем на английский
    await page.click('text=English, text=EN, [data-lang="en"]');
    
    // Проверяем смену языка
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  });

  test('responsive layout works on mobile', async ({ page, isMobile }) => {
    if (!isMobile) {
      test.skip('Mobile only test');
    }
    
    await login(page, TEST_USERS.standard);
    await page.goto('/apps/cloud/');
    
    // Проверяем, что мобильное меню работает
    await expect(page.locator('.mobile-menu-toggle, .hamburger, [aria-label="menu"]')).toBeVisible();
  });
});
```

---

## Тестовые данные

### 1. `tests/test-data/sql/init-test-db.sql`

```sql
-- Инициализация тестовой базы данных
-- Выполняется при запуске тестового контейнера PostgreSQL

-- Создание тестового пользователя
INSERT INTO users (email, password_hash, name, role, created_at)
VALUES (
    'test@example.com',
    '$2y$10$testhash', -- bcrypt hash для 'testpassword'
    'Test User',
    'user',
    NOW()
) ON CONFLICT (email) DO NOTHING;

-- Администратор
INSERT INTO users (email, password_hash, name, role, created_at)
VALUES (
    'admin@example.com',
    '$2y$10$adminhash',
    'Admin User',
    'admin',
    NOW()
) ON CONFLICT (email) DO NOTHING;

-- Тестовые данные для Cloud
INSERT INTO cloud_files (user_id, name, path, size, mime_type, created_at)
SELECT id, 'welcome.txt', '/welcome.txt', 12, 'text/plain', NOW()
FROM users WHERE email = 'test@example.com'
ON CONFLICT DO NOTHING;

-- Настройки по умолчанию
INSERT INTO settings (user_id, key, value)
SELECT id, 'language', 'ru' FROM users WHERE email = 'test@example.com'
ON CONFLICT (user_id, key) DO NOTHING;
```

---

## Запуск тестов

### Локальная разработка

```bash
# 1. Установка зависимостей
cd tests
npm install
npx playwright install --with-deps

# 2. Запуск основного приложения (в отдельном терминале)
docker compose up -d

# 3. Запуск тестов
npm run test                    # Все тесты
npm run test -- --project=chromium  # Только Chromium
npm run test -- tests/e2e/apps/cloud/  # Только Cloud app
npm run test:headed             # С открытым браузером
npm run test:debug              # Режим отладки
npm run test:ui                 # Playwright UI
```

### В Docker (полная изоляция)

```bash
# Из корня проекта
cd tests

# Запуск полного стека в Docker
npm run test:docker

# Или вручную:
docker compose -f docker/docker-compose.test.yml up --build --abort-on-container-exit --exit-code-from playwright
```

### CI/CD (GitHub Actions пример)

```yaml
# .github/workflows/e2e-tests.yml
name: E2E Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  e2e-tests:
    runs-on: ubuntu-latest
    timeout-minutes: 30
    
    services:
      # Можно использовать внешний PostgreSQL или встроенный
      postgres:
        image: postgres:16-alpine
        env:
          POSTGRES_DB: appdashboard_test
          POSTGRES_USER: appdashboard
          POSTGRES_PASSWORD: test_password
        ports: ["5432:5432"]
        options: >-
          --health-cmd "pg_isready -U appdashboard -d appdashboard_test"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
          cache-dependency-path: tests/package-lock.json
      
      - name: Install dependencies
        run: cd tests && npm ci
      
      - name: Install Playwright browsers
        run: cd tests && npx playwright install --with-deps chromium
      
      - name: Start application
        run: |
          cd tests
          docker compose -f docker/docker-compose.test.yml up -d test-db test-app
          # Ждем готовности
          sleep 30
      
      - name: Run E2E tests
        run: cd tests && npm run test:ci
        env:
          TEST_BASE_URL: http://localhost:8080
          CI: true
      
      - name: Upload test results
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: playwright-results
          path: tests/test-results/
          retention-days: 7
      
      - name: Upload HTML report
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: playwright-report
          path: tests/test-results/html-report/
          retention-days: 7
```

---

## Лучшие практики

### 1. Селекторы
```typescript
// ✅ Хорошо: используем data-testid
await page.click('[data-testid="login-button"]');

// ✅ Хорошо: семантические селекторы
await page.click('button:has-text("Войти")');

// ❌ Плохо: хрупкие CSS селекторы
await page.click('.btn.btn-primary.mt-3:nth-child(2)');
```

### 2. Ожидания
```typescript
// ✅ Хорошо: явные ожидания
await expect(page.locator('.file-list')).toContainText('test.txt');
await page.waitForURL(/\/(apps|cloud)/);

// ❌ Плохо: произвольные задержки
await page.waitForTimeout(3000);
```

### 3. Изоляция тестов
```typescript
// ✅ Каждый тест независим
test('upload file', async ({ page }) => {
  await login(page);
  // ... тест
});

test('delete file', async ({ page }) => {
  await login(page);
  // ... тест (не зависит от предыдущего)
});
```

### 4. Page Object Model (для сложных страниц)
```typescript
// tests/e2e/apps/cloud/pages/CloudPage.ts
export class CloudPage {
  constructor(private page: Page) {}
  
  get fileList() { return this.page.locator('[data-testid="file-list"]'); }
  get uploadInput() { return this.page.locator('input[type="file"]'); }
  get newFolderButton() { return this.page.locator('[data-action="create-folder"]'); }
  
  async goto() {
    await this.page.goto('/apps/cloud/');
    await this.page.waitForLoadState('networkidle');
  }
  
  async uploadFile(filePath: string) {
    await this.uploadInput.setInputFiles(filePath);
    await this.page.waitForLoadState('networkidle');
  }
  
  async createFolder(name: string) {
    await this.newFolderButton.click();
    await this.page.fill('[data-testid="folder-name-input"]', name);
    await this.page.click('[data-action="confirm-create"]');
  }
}
```

---

## Отладка

### 1. Локальная отладка
```bash
# Запуск в headed режиме
npm run test:headed

# Режим отладки с инспектором
npm run test:debug

# Playwright UI
npm run test:ui

# Генерация тестов (codegen)
npm run codegen
```

### 2. Анализ падений
- **HTML отчет**: `tests/test-results/html-report/index.html`
- **Трассировки**: `tests/test-results/artifacts/` (`.trace.zip` файлы)
- **Скриншоты**: `tests/test-results/artifacts/` (при падении)
- **Видео**: `tests/test-results/artifacts/` (при падении)

### 3. Открытие трассировки
```bash
npx playwright show-trace tests/test-results/artifacts/trace-xxx.zip
```

---

## Расширение тестов

### Добавление нового приложения
1. Создайте директорию: `tests/e2e/apps/new-app/`
2. Добавьте тесты: `feature.spec.ts`
3. При необходимости создайте Page Object: `pages/NewAppPage.ts`
4. Добавьте тестовые данные в `tests/test-data/`

### Параллельный запуск
Тесты запускаются параллельно по умолчанию. Для принудительного последовательного запуска:
```typescript
// В тестовом файле
test.describe.configure({ retries: 0, workers: 1 });
```

### Кастомные репортеры
Можно добавить свои репортеры в `playwright.config.ts`:
- Slack уведомления
- Allure отчеты
- Пользовательские метрики

---

## Полезные ссылки

- [Playwright Documentation](https://playwright.dev/)
- [Playwright Test API](https://playwright.dev/docs/api/class-test)
- [Best Practices](https://playwright.dev/docs/best-practices)
- [CI/CD Integration](https://playwright.dev/docs/ci)
- [Debugging](https://playwright.dev/docs/debug)
- [Trace Viewer](https://playwright.dev/docs/trace-viewer)

---

## Поддержка

При возникновении проблем:
1. Проверьте логи: `docker compose logs test-app`
2. Убедитесь, что порты свободны (8080, 15433)
3. Проверьте переменные окружения в `.env.test`
4. Очистите кэш: `docker compose down -v && npm run test:docker`