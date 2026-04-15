import { test, expect } from '@playwright/test';
import { ADMIN, loginViaApi } from './helpers/auth';

/**
 * E2E — Content create journey (real UI form).
 *
 * Tests F: filling the ContentCreatePage form with a real browser, submitting
 * to the real Symfony backend, and verifying the created content persists.
 *
 * Uses real browser ↔ Vite proxy ↔ Symfony backend.
 * No mocked API transport. No page.request shortcuts for the submit action.
 */

test.describe('Content Create (UI form journey)', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaApi(page, ADMIN);
  });

  test('F1: content create form renders all required fields', async ({ page }) => {
    await page.goto('/content/new');
    await expect(page).not.toHaveURL(/\/login/);

    // All three required form fields must be present
    await expect(page.locator('input[placeholder="Enter content title"]')).toBeVisible({
      timeout: 8000,
    });
    await expect(page.locator('textarea[placeholder="Write your content here..."]')).toBeVisible();
    await expect(page.locator('input[placeholder="Author name"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('F2: submitting the form with valid data redirects to the content detail page', async ({
    page,
  }) => {
    await page.goto('/content/new');

    const uniqueTitle = `E2E Create ${Date.now()}`;

    // Fill the form using real browser interactions — no API shortcuts
    await page.fill('input[placeholder="Enter content title"]', uniqueTitle);
    await page.fill(
      'textarea[placeholder="Write your content here..."]',
      'End-to-end test body. This content was created through the real browser UI.',
    );
    await page.fill('input[placeholder="Author name"]', 'E2E Test Author');

    // Submit via the real button
    await page.click('button[type="submit"]');

    // On success, ContentCreatePage navigates to /content/:id
    await page.waitForURL(
      (url) => /\/content\/[0-9a-f-]{36}/.test(url.pathname),
      { timeout: 15000 },
    );

    await expect(page).not.toHaveURL(/\/login/);
    await expect(page).not.toHaveURL(/\/content\/new/);
  });

  test('F3: content created via form persists and is retrievable from the backend', async ({
    page,
  }) => {
    await page.goto('/content/new');

    const uniqueTitle = `E2E Persist ${Date.now()}`;

    // Fill and submit the form
    await page.fill('input[placeholder="Enter content title"]', uniqueTitle);
    await page.fill(
      'textarea[placeholder="Write your content here..."]',
      'Persistence verification body — this was submitted through the live UI form.',
    );
    await page.fill('input[placeholder="Author name"]', 'E2E Persist Author');

    // Wait for the POST response while clicking submit — both happen simultaneously
    const [createResponse] = await Promise.all([
      page.waitForResponse(
        (resp) =>
          resp.url().includes('/api/v1/content') && resp.request().method() === 'POST',
        { timeout: 15000 },
      ),
      page.click('button[type="submit"]'),
    ]);

    // The backend must return 201
    expect(createResponse.status()).toBe(201);
    const body = await createResponse.json();
    expect(body.data.title).toBe(uniqueTitle);
    expect(body.data.id).toBeTruthy();

    const contentId = body.data.id;

    // Verify the content is retrievable from the backend independently of the UI state
    const getResp = await page.request.get(`/api/v1/content/${contentId}`);
    expect(getResp.status()).toBe(200);
    const getBody = await getResp.json();
    expect(getBody.data.title).toBe(uniqueTitle);
    expect(getBody.data.author_name).toBe('E2E Persist Author');
  });

  test('F4: submitting with missing required fields shows a visible validation error', async ({
    page,
  }) => {
    await page.goto('/content/new');

    // Leave title empty, fill the rest
    await page.fill(
      'textarea[placeholder="Write your content here..."]',
      'Some body text.',
    );
    await page.fill('input[placeholder="Author name"]', 'E2E Author');

    await page.click('button[type="submit"]');

    // Client-side validation: form should show an error and NOT navigate away
    await expect(page.locator('text=/title.*required|required.*title/i').first()).toBeVisible({
      timeout: 5000,
    });
    await expect(page).toHaveURL(/\/content\/new/);
  });
});
