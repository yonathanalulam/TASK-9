# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: exports.spec.ts >> Export Lifecycle >> D2: requesting an export via API returns 201 with job ID
- Location: e2e/exports.spec.ts:37:3

# Error details

```
TypeError: Cannot read properties of null (reading 'token')
```

# Test source

```ts
  1  | import { type Page } from '@playwright/test';
  2  | 
  3  | /**
  4  |  * Seeded admin credentials from app:seed:demo.
  5  |  * Always present in a fresh Docker environment after the entrypoint runs.
  6  |  */
  7  | export const ADMIN = { username: 'admin', password: 'Demo#Password1!' };
  8  | export const MGR_NORTH = { username: 'mgr_north', password: 'Demo#Password1!' };
  9  | export const ANALYST = { username: 'analyst1', password: 'Demo#Password1!' };
  10 | 
  11 | /**
  12 |  * Return Bearer token header object for use with page.request.* calls.
  13 |  */
  14 | export function authHeader(token: string): Record<string, string> {
  15 |   return { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' };
  16 | }
  17 | 
  18 | /**
  19 |  * Log in via the UI login form and wait for redirect to the dashboard.
  20 |  */
  21 | export async function loginAs(
  22 |   page: Page,
  23 |   credentials: { username: string; password: string },
  24 | ): Promise<void> {
  25 |   await page.goto('/login');
  26 |   await page.fill('#username', credentials.username);
  27 |   await page.fill('#password', credentials.password);
  28 |   await page.click('button[type="submit"]');
  29 |   // Wait until navigated away from login page
  30 |   await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
  31 | }
  32 | 
  33 | /**
  34 |  * Log in via the API directly and inject the token into localStorage,
  35 |  * bypassing the UI — useful for setting up test state quickly.
  36 |  *
  37 |  * Returns the Bearer token so callers can pass it to page.request.*
  38 |  * calls via authHeader(token).
  39 |  */
  40 | export async function loginViaApi(
  41 |   page: Page,
  42 |   credentials: { username: string; password: string },
  43 | ): Promise<string> {
  44 |   const response = await page.request.post('/api/v1/auth/login', {
  45 |     data: { username: credentials.username, password: credentials.password },
  46 |     headers: { 'Content-Type': 'application/json' },
  47 |   });
  48 |   const body = await response.json();
> 49 |   const token: string = body.data.token;
     |                                   ^ TypeError: Cannot read properties of null (reading 'token')
  50 |   const user = body.data.user;
  51 | 
  52 |   // Inject into Zustand persist store that the frontend reads
  53 |   await page.goto('/login');
  54 |   await page.evaluate(
  55 |     ({ token, user }) => {
  56 |       localStorage.setItem(
  57 |         'meridian-auth',
  58 |         JSON.stringify({ state: { token, user, roles: user.roles, isAuthenticated: true }, version: 0 }),
  59 |       );
  60 |     },
  61 |     { token, user },
  62 |   );
  63 |   return token;
  64 | }
  65 | 
```