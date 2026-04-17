import { type Page } from '@playwright/test';

/**
 * Seeded admin credentials from app:seed:demo.
 * Always present in a fresh Docker environment after the entrypoint runs.
 */
export const ADMIN = { username: 'admin', password: 'Demo#Password1!' };
export const MGR_NORTH = { username: 'mgr_north', password: 'Demo#Password1!' };
export const ANALYST = { username: 'analyst1', password: 'Demo#Password1!' };

/**
 * Return Bearer token header object for use with page.request.* calls.
 */
export function authHeader(token: string): Record<string, string> {
  return { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' };
}

/**
 * Log in via the UI login form and wait for redirect to the dashboard.
 */
export async function loginAs(
  page: Page,
  credentials: { username: string; password: string },
): Promise<void> {
  await page.goto('/login');
  await page.fill('#username', credentials.username);
  await page.fill('#password', credentials.password);
  await page.click('button[type="submit"]');
  // Wait until navigated away from login page
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
}

/**
 * Log in via the API directly and inject the token into localStorage,
 * bypassing the UI — useful for setting up test state quickly.
 *
 * Returns the Bearer token so callers can pass it to page.request.*
 * calls via authHeader(token).
 */
export async function loginViaApi(
  page: Page,
  credentials: { username: string; password: string },
): Promise<string> {
  // Retry login up to 3 times to handle cold-start proxy/service latency.
  let lastError: unknown;
  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      const response = await page.request.post('/api/v1/auth/login', {
        data: { username: credentials.username, password: credentials.password },
        headers: { 'Content-Type': 'application/json' },
        timeout: 15000,
      });

      if (!response.ok()) {
        throw new Error(`Login API returned ${response.status()}: ${response.statusText()}`);
      }

      const body = await response.json();
      if (!body?.data?.token) {
        throw new Error(`Login response missing token: ${JSON.stringify(body).slice(0, 200)}`);
      }

      const token: string = body.data.token;
      const user = body.data.user;

      // Inject into Zustand persist store that the frontend reads
      await page.goto('/login');
      await page.evaluate(
        ({ token, user }) => {
          localStorage.setItem(
            'meridian-auth',
            JSON.stringify({ state: { token, user, roles: user.roles, isAuthenticated: true }, version: 0 }),
          );
        },
        { token, user },
      );
      return token;
    } catch (err) {
      lastError = err;
      // Wait before retry to let services warm up
      if (attempt < 2) await page.waitForTimeout(2000);
    }
  }
  throw lastError;
}
