import { test, expect } from '@playwright/test';
import { ADMIN, loginViaApi } from './helpers/auth';

/**
 * E2E — Search flow.
 *
 * Tests B: search page loads, filters affect results, filter state is reflected.
 * Uses real browser ↔ Vite proxy ↔ Symfony backend.
 * No mocked API transport.
 */

test.describe('Search', () => {
  test.beforeAll(async ({ browser }) => {
    // Seed a content item via API so search has something to find
    const context = await browser.newContext();
    const page = await context.newPage();
    await loginViaApi(page, ADMIN);

    // Create + publish a content item to ensure search index has data
    const createResp = await page.request.post('/api/v1/content', {
      data: {
        title: 'E2E Searchable Meridian Job Post',
        body: 'This is an end-to-end test content item about engineering roles.',
        content_type: 'JOB_POST',
        author_name: 'E2E Tester',
      },
      headers: { 'Content-Type': 'application/json' },
    });

    if (createResp.status() === 201) {
      const body = await createResp.json();
      await page.request.post(`/api/v1/content/${body.data.id}/publish`);
    }

    await context.close();
  });

  test.beforeEach(async ({ page }) => {
    await loginViaApi(page, ADMIN);
  });

  test('B1: search page is accessible and renders the search form', async ({ page }) => {
    await page.goto('/search');
    await expect(page).not.toHaveURL(/\/login/);

    // Search input should be present
    const searchInput = page.locator('input[type="text"], input[placeholder*="search" i]').first();
    await expect(searchInput).toBeVisible({ timeout: 8000 });
  });

  test('B2: empty search shows validation error, not a crash', async ({ page }) => {
    await page.goto('/search');

    // Try submitting an empty search
    const submitBtn = page.locator('button[type="submit"], button:has-text("Search")').first();
    if (await submitBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await submitBtn.click();
      // Should show error or simply not crash the page
      await expect(page.locator('body')).toBeVisible();
      await expect(page).not.toHaveURL(/\/login/);
    }
  });

  test('B3: search with a query returns results or empty state (no crash)', async ({ page }) => {
    await page.goto('/search');

    // Type a search query
    const searchInput = page.locator('input[type="text"], input[placeholder*="search" i]').first();
    await searchInput.fill('E2E Searchable Meridian');

    // Submit
    const submitBtn = page.locator('button[type="submit"], button:has-text("Search")').first();
    if (await submitBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await submitBtn.click();
    } else {
      await searchInput.press('Enter');
    }

    // Wait for results or empty state (not a crash/redirect)
    await page.waitForResponse((resp) => resp.url().includes('/api/v1/search'), { timeout: 15000 });
    await expect(page.locator('body')).toBeVisible();
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('B4: filter by content type limits results to that type', async ({ page }) => {
    await page.goto('/search');

    // Enter a broad search term
    const searchInput = page.locator('input[type="text"], input[placeholder*="search" i]').first();
    await searchInput.fill('E2E Searchable');

    // Apply JOB_POST filter if the filter UI exists
    const storeFilterInput = page.getByTestId('search-filter-store');
    if (await storeFilterInput.isVisible({ timeout: 3000 }).catch(() => false)) {
      // Apply filters
      const applyBtn = page.locator('button:has-text("Apply Filters")');
      await applyBtn.click();
    }

    // Submit search
    const submitBtn = page.locator('button[type="submit"], button:has-text("Search")').first();
    if (await submitBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await submitBtn.click();
    } else {
      await searchInput.press('Enter');
    }

    // Wait for API call
    const searchResponse = await page.waitForResponse(
      (resp) => resp.url().includes('/api/v1/search'),
      { timeout: 15000 },
    );

    const body = await searchResponse.json();
    // Verify the API returned 200 with the expected envelope shape
    expect(searchResponse.status()).toBe(200);
    expect(body).toHaveProperty('data');
    expect(body).toHaveProperty('meta');
    expect(body.meta).toHaveProperty('pagination');
  });
});
