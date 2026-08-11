import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.TEST_BASE_URL || 'http://localhost';

export default defineConfig({
  testDir: '../e2e',
  outputDir: '../test-results/artifacts',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 2 : undefined,
  timeout: 60000,
  expect: { timeout: 10000 },
  reporter: [
    ['line'],
    ['html', { outputFolder: '../test-results/html-report', open: 'never' }],
    ['json', { outputFile: '../test-results/results.json' }]
  ],
  use: {
    baseURL,
    actionTimeout: 15000,
    navigationTimeout: 30000,
    ignoreHTTPSErrors: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    locale: 'en-US',
    timezoneId: 'Europe/Warsaw',
    extraHTTPHeaders: {
      'Accept-Language': 'en-US,en;q=0.9'
    }
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'], viewport: { width: 1440, height: 900 } } },
    { name: 'webkit', use: { ...devices['Desktop Safari'], viewport: { width: 1440, height: 900 } } },
    { name: 'mobile-chrome-pixel-5', use: { ...devices['Pixel 5'] } },
    { name: 'mobile-safari-iphone-12', use: { ...devices['iPhone 12'] } },
    { name: 'mobile-safari-iphone-se', use: { ...devices['iPhone SE'] } },
    { name: 'tablet-ipad-pro', use: { ...devices['iPad Pro 11'] } }
  ],
  webServer: process.env.PLAYWRIGHT_START_SERVER === '1'
    ? {
        command: 'docker compose up -d',
        url: baseURL,
        reuseExistingServer: true,
        timeout: 120000
      }
    : undefined
});
