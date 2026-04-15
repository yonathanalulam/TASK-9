import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E configuration for Meridian platform.
 *
 * Targets the real Vite dev server (which proxies /api to nginx).
 * In Docker: E2E_BASE_URL=http://node:5173
 * Locally:   E2E_BASE_URL=http://localhost:5173 (default)
 *
 * No mocks — all API calls go to the real Symfony backend.
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  retries: process.env.CI ? 1 : 0,
  timeout: 45000,
  expect: { timeout: 10000 },
  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
  ],
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://localhost:5173',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
