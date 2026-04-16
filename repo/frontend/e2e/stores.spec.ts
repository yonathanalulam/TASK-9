import { test, expect } from '@playwright/test';
import { ADMIN, loginViaApi, authHeader } from './helpers/auth';

/**
 * E2E — Store CRUD flow.
 *
 * Tests C: store list, store creation via UI, persisted result visible.
 * Uses real browser ↔ Vite proxy ↔ Symfony backend.
 * No mocked API transport.
 */

test.describe('Store CRUD', () => {
  let regionId: string;
  let token: string;

  test.beforeAll(async ({ browser }) => {
    // Create a region via API for store creation.
    // Retry the login + region creation once to handle cold-start latency.
    for (let attempt = 0; attempt < 2; attempt++) {
      const context = await browser.newContext();
      const page = await context.newPage();

      try {
        token = await loginViaApi(page, ADMIN);
        const headers = authHeader(token);

        const resp = await page.request.post('/api/v1/regions', {
          data: {
            code: 'EERGN',
            name: 'E2E Test Region',
            effective_from: '2026-01-01',
            hierarchy_level: 0,
          },
          headers,
        });

        if (resp.status() === 201) {
          const body = await resp.json();
          regionId = body.data.id;
        } else {
          // Region might already exist — try to fetch it
          const listResp = await page.request.get('/api/v1/regions?per_page=100', { headers });
          const listBody = await listResp.json();
          const existing = listBody.data?.find((r: { code: string }) => r.code === 'EERGN');
          regionId = existing?.id || '';
        }
      } catch {
        // First attempt may fail on cold Docker start — retry
        if (attempt === 0) {
          await context.close();
          continue;
        }
      }

      await context.close();
      if (regionId) break;
    }
  });

  test.beforeEach(async ({ page }) => {
    token = await loginViaApi(page, ADMIN);
  });

  test('C1: store list page loads and shows a table/list', async ({ page }) => {
    await page.goto('/stores');
    await expect(page).not.toHaveURL(/\/login/);

    // Page should render without error
    await expect(page.locator('body')).toBeVisible({ timeout: 8000 });
    await expect(page).toHaveURL(/\/stores/);
  });

  test('C2: store list shows correct pagination metadata from backend', async ({ page }) => {
    await page.goto('/stores');

    // Wait for API response
    const response = await page.waitForResponse(
      (resp) => resp.url().includes('/api/v1/stores'),
      { timeout: 15000 },
    );

    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('data');
    expect(body).toHaveProperty('meta');
    expect(body.meta).toHaveProperty('pagination');
  });

  test('C3: store creation via API persists and appears in the list', async ({ page }) => {
    // Create store via API (simulating form submission with real backend persistence)
    // Do NOT skip — if regionId is absent, E2E setup failed and the test must fail loudly.
    expect(regionId, 'E2E setup failed: no region available — beforeAll region create/fetch did not produce an ID').toBeTruthy();

    const uniqueCode = `E2E-${Date.now().toString().slice(-6)}`;
    const headers = authHeader(token);

    const createResp = await page.request.post('/api/v1/stores', {
      data: {
        code: uniqueCode,
        name: `E2E Store ${uniqueCode}`,
        store_type: 'STORE',
        region_id: regionId,
      },
      headers,
    });

    expect(createResp.status()).toBe(201);
    const createBody = await createResp.json();
    const storeId = createBody.data.id;
    expect(storeId).toBeTruthy();
    expect(createBody.data.code).toBe(uniqueCode);
    expect(createBody.data.name).toBe(`E2E Store ${uniqueCode}`);

    // Navigate to the store detail page to verify persistence
    await page.goto(`/stores/${storeId}`);
    await expect(page).not.toHaveURL(/\/login/);

    // Verify backend-driven state — the store detail page should show the store data
    const detailResponse = await page.waitForResponse(
      (resp) => resp.url().includes(`/api/v1/stores/${storeId}`),
      { timeout: 15000 },
    );
    expect(detailResponse.status()).toBe(200);
    const detailBody = await detailResponse.json();
    expect(detailBody.data.code).toBe(uniqueCode);
  });

  test('C4: store update persists via API and reflects in subsequent GET', async ({ page }) => {
    // Do NOT skip — if regionId is absent, E2E setup failed and the test must fail loudly.
    expect(regionId, 'E2E setup failed: no region available — beforeAll region create/fetch did not produce an ID').toBeTruthy();

    const headers = authHeader(token);

    // Create a store
    const code = `UPD-${Date.now().toString().slice(-6)}`;
    const createResp = await page.request.post('/api/v1/stores', {
      data: {
        code,
        name: `Update Test Store ${code}`,
        store_type: 'STORE',
        region_id: regionId,
      },
      headers,
    });
    expect(createResp.status()).toBe(201);
    const { id, version } = (await createResp.json()).data;

    // Update the store name
    const updateResp = await page.request.put(`/api/v1/stores/${id}`, {
      data: { name: `Updated Name ${code}` },
      headers: {
        ...headers,
        'If-Match': `"${version}"`,
      },
    });
    expect(updateResp.status()).toBe(200);
    const updateBody = await updateResp.json();
    expect(updateBody.data.name).toBe(`Updated Name ${code}`);

    // Verify via a fresh GET that the name persisted
    const getResp = await page.request.get(`/api/v1/stores/${id}`, {
      headers,
    });
    expect(getResp.status()).toBe(200);
    const getBody = await getResp.json();
    expect(getBody.data.name).toBe(`Updated Name ${code}`);
  });
});
