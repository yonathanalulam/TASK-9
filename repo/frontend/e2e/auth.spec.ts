import { test, expect } from '@playwright/test';
import { ADMIN, loginViaApi } from './helpers/auth';

/**
 * E2E — Authentication flows.
 *
 * Tests A: valid login, invalid login, protected route enforcement.
 * All tests use real browser ↔ Vite proxy ↔ Symfony backend.
 * No mocked API transport.
 */

test.describe('Authentication', () => {
  test.beforeEach(async ({ page }) => {
    // Clear auth state before each test
    await page.goto('/login');
    await page.evaluate(() => localStorage.removeItem('meridian-auth'));
  });

  test('A1: valid login succeeds and redirects to dashboard', async ({ page }) => {
    await page.goto('/login');

    // Fill and submit the login form
    await page.fill('#username', ADMIN.username);
    await page.fill('#password', ADMIN.password);
    await page.click('button[type="submit"]');

    // Should redirect away from /login — give extra time for Vite HMR + API round-trip in Docker
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 });

    // Dashboard should be visible (the root route renders DashboardPage)
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('A2: invalid login shows visible error message and stays on login page', async ({ page }) => {
    await page.goto('/login');

    await page.fill('#username', ADMIN.username);
    await page.fill('#password', 'definitively-wrong-password-123!');
    await page.click('button[type="submit"]');

    // Wait for the API round-trip to complete before checking for error text
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

    // Error message should appear — backend returns "Invalid credentials"
    // and LoginPage extracts it into a visible error div
    await expect(page.locator('text=/failed|invalid|incorrect|credentials/i').first()).toBeVisible({
      timeout: 15000,
    });

    // Must remain on the login page
    await expect(page).toHaveURL(/\/login/);
  });

  test('A3: nonexistent user gets auth error, not a server error', async ({ page }) => {
    await page.goto('/login');

    await page.fill('#username', 'definitely_does_not_exist_xyz123');
    await page.fill('#password', 'SomePassword!');
    await page.click('button[type="submit"]');

    // Wait for the API round-trip to complete
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

    // Should show user-visible error (not a blank page or 500 error)
    await expect(page.locator('text=/failed|invalid|incorrect|credentials|error/i').first()).toBeVisible({
      timeout: 15000,
    });
    await expect(page).toHaveURL(/\/login/);
  });

  test('A4: protected page redirects to login when unauthenticated', async ({ page }) => {
    // Navigate directly to a protected page without being logged in
    await page.goto('/stores');

    // Should be redirected to /login
    await expect(page).toHaveURL(/\/login/, { timeout: 5000 });
  });

  test('A5: protected dashboard becomes accessible after login', async ({ page }) => {
    // First confirm the route is blocked
    await page.goto('/');
    await expect(page).toHaveURL(/\/login/);

    // Now log in via API and inject state
    await loginViaApi(page, ADMIN);

    // Navigate to protected route
    await page.goto('/');
    await expect(page).not.toHaveURL(/\/login/);
  });
});
