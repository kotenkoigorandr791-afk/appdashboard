import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: '../e2e',
  outputDir: '../test-results/artifacts',
  fullyParallel: false,
  retries: 0,
  workers: 1,
  timeout: 180000,
  expect: { timeout: 15000 },
  reporter: [['line'], ['json', { outputFile: '../test-results/results.json' }]],
  use: {
    baseURL: 'http://localhost',
    actionTimeout: 70000,
    navigationTimeout: 70000,
    ignoreHTTPSErrors: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
