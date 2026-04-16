# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: auth.spec.ts >> Authentication >> A1: valid login succeeds and redirects to dashboard
- Location: e2e/auth.spec.ts:19:3

# Error details

```
TimeoutError: page.waitForURL: Timeout 30000ms exceeded.
=========================== logs ===========================
waiting for navigation until "load"
============================================================
```

# Page snapshot

```yaml
- generic [ref=e4]:
  - heading "Meridian" [level=1] [ref=e5]
  - paragraph [ref=e6]: Sign in to your account
  - generic [ref=e7]: The optimistic lock on an entity failed.
  - generic [ref=e8]:
    - generic [ref=e9]:
      - generic [ref=e10]: Username
      - textbox "Username" [ref=e11]: admin
    - generic [ref=e12]:
      - generic [ref=e13]: Password
      - textbox "Password" [ref=e14]: Demo#Password1!
    - button "Sign in" [ref=e15] [cursor=pointer]
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | import { ADMIN, loginViaApi } from './helpers/auth';
  3  | 
  4  | /**
  5  |  * E2E — Authentication flows.
  6  |  *
  7  |  * Tests A: valid login, invalid login, protected route enforcement.
  8  |  * All tests use real browser ↔ Vite proxy ↔ Symfony backend.
  9  |  * No mocked API transport.
  10 |  */
  11 | 
  12 | test.describe('Authentication', () => {
  13 |   test.beforeEach(async ({ page }) => {
  14 |     // Clear auth state before each test
  15 |     await page.goto('/login');
  16 |     await page.evaluate(() => localStorage.removeItem('meridian-auth'));
  17 |   });
  18 | 
  19 |   test('A1: valid login succeeds and redirects to dashboard', async ({ page }) => {
  20 |     await page.goto('/login');
  21 | 
  22 |     // Fill and submit the login form
  23 |     await page.fill('#username', ADMIN.username);
  24 |     await page.fill('#password', ADMIN.password);
  25 |     await page.click('button[type="submit"]');
  26 | 
  27 |     // Should redirect away from /login — give extra time for Vite HMR + API round-trip in Docker
> 28 |     await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 });
     |                ^ TimeoutError: page.waitForURL: Timeout 30000ms exceeded.
  29 | 
  30 |     // Dashboard should be visible (the root route renders DashboardPage)
  31 |     await expect(page).not.toHaveURL(/\/login/);
  32 |   });
  33 | 
  34 |   test('A2: invalid login shows visible error message and stays on login page', async ({ page }) => {
  35 |     await page.goto('/login');
  36 | 
  37 |     await page.fill('#username', ADMIN.username);
  38 |     await page.fill('#password', 'definitively-wrong-password-123!');
  39 |     await page.click('button[type="submit"]');
  40 | 
  41 |     // Wait for the API round-trip to complete before checking for error text
  42 |     await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  43 | 
  44 |     // Error message should appear — backend returns "Invalid credentials"
  45 |     // and LoginPage extracts it into a visible error div
  46 |     await expect(page.locator('text=/failed|invalid|incorrect|credentials/i').first()).toBeVisible({
  47 |       timeout: 15000,
  48 |     });
  49 | 
  50 |     // Must remain on the login page
  51 |     await expect(page).toHaveURL(/\/login/);
  52 |   });
  53 | 
  54 |   test('A3: nonexistent user gets auth error, not a server error', async ({ page }) => {
  55 |     await page.goto('/login');
  56 | 
  57 |     await page.fill('#username', 'definitely_does_not_exist_xyz123');
  58 |     await page.fill('#password', 'SomePassword!');
  59 |     await page.click('button[type="submit"]');
  60 | 
  61 |     // Wait for the API round-trip to complete
  62 |     await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  63 | 
  64 |     // Should show user-visible error (not a blank page or 500 error)
  65 |     await expect(page.locator('text=/failed|invalid|incorrect|credentials|error/i').first()).toBeVisible({
  66 |       timeout: 15000,
  67 |     });
  68 |     await expect(page).toHaveURL(/\/login/);
  69 |   });
  70 | 
  71 |   test('A4: protected page redirects to login when unauthenticated', async ({ page }) => {
  72 |     // Navigate directly to a protected page without being logged in
  73 |     await page.goto('/stores');
  74 | 
  75 |     // Should be redirected to /login
  76 |     await expect(page).toHaveURL(/\/login/, { timeout: 5000 });
  77 |   });
  78 | 
  79 |   test('A5: protected dashboard becomes accessible after login', async ({ page }) => {
  80 |     // First confirm the route is blocked
  81 |     await page.goto('/');
  82 |     await expect(page).toHaveURL(/\/login/);
  83 | 
  84 |     // Now log in via API and inject state
  85 |     await loginViaApi(page, ADMIN);
  86 | 
  87 |     // Navigate to protected route
  88 |     await page.goto('/');
  89 |     await expect(page).not.toHaveURL(/\/login/);
  90 |   });
  91 | });
  92 | 
```