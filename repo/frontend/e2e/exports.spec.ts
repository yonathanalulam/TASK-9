import { test, expect } from '@playwright/test';
import { ADMIN, loginViaApi } from './helpers/auth';

/**
 * E2E — Export / Compliance lifecycle flow.
 *
 * Tests D: export request, list, status, and compliance report generation.
 * Uses real browser ↔ Vite proxy ↔ Symfony backend.
 * No mocked API transport.
 */

test.describe('Export Lifecycle', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaApi(page, ADMIN);
  });

  test('D1: export list page loads and returns proper envelope from backend', async ({ page }) => {
    await page.goto('/exports');
    await expect(page).not.toHaveURL(/\/login/);

    const response = await page.waitForResponse(
      (resp) => resp.url().includes('/api/v1/exports'),
      { timeout: 15000 },
    );

    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('data');
    expect(body).toHaveProperty('meta');
    expect(body.error).toBeNull();
  });

  test('D2: requesting an export via API returns 201 with job ID', async ({ page }) => {
    const resp = await page.request.post('/api/v1/exports', {
      data: { dataset: 'content_items', format: 'CSV' },
      headers: { 'Content-Type': 'application/json' },
    });

    // 201 Created with job details
    expect(resp.status()).toBe(201);
    const body = await resp.json();
    expect(body.data).toHaveProperty('id');
    expect(body.data).toHaveProperty('dataset');
    expect(body.data).toHaveProperty('status');
    expect(body.data.dataset).toBe('content_items');
    expect(body.error).toBeNull();
  });

  test('D3: created export job is retrievable via GET and shows correct status', async ({
    page,
  }) => {
    // Create an export job
    const createResp = await page.request.post('/api/v1/exports', {
      data: { dataset: 'content_items', format: 'CSV' },
      headers: { 'Content-Type': 'application/json' },
    });
    expect(createResp.status()).toBe(201);
    const jobId = (await createResp.json()).data.id;

    // Retrieve the export job
    const getResp = await page.request.get(`/api/v1/exports/${jobId}`);
    expect(getResp.status()).toBe(200);
    const body = await getResp.json();
    expect(body.data.id).toBe(jobId);
    expect(body.data.dataset).toBe('content_items');
    expect(['PENDING', 'PROCESSING', 'SUCCEEDED', 'FAILED']).toContain(body.data.status);
    expect(body.error).toBeNull();
  });

  test('D4: downloading an unprocessed export returns 422 (not 500)', async ({ page }) => {
    // Create an export job
    const createResp = await page.request.post('/api/v1/exports', {
      data: { dataset: 'content_items', format: 'CSV' },
      headers: { 'Content-Type': 'application/json' },
    });
    expect(createResp.status()).toBe(201);
    const jobId = (await createResp.json()).data.id;

    // Immediately try to download — should be rejected with 422 (not succeeded yet)
    const dlResp = await page.request.get(`/api/v1/exports/${jobId}/download`);
    expect(dlResp.status()).toBe(422);
    const body = await dlResp.json();
    expect(body.error).toBeDefined();
    expect(body.error.code).not.toBe('INTERNAL_SERVER_ERROR');
  });

  test('D5: compliance reports page loads and lists reports', async ({ page }) => {
    await page.goto('/compliance-reports');
    await expect(page).not.toHaveURL(/\/login/);

    const response = await page.waitForResponse(
      (resp) => resp.url().includes('/api/v1/compliance-reports'),
      { timeout: 15000 },
    );

    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('data');
    expect(Array.isArray(body.data)).toBe(true);
    expect(body.meta).toHaveProperty('pagination');
  });

  test('D6: generating a compliance report returns 201 with tamper hash', async ({ page }) => {
    const resp = await page.request.post('/api/v1/compliance-reports', {
      data: { report_type: 'RETENTION_SUMMARY' },
      headers: { 'Content-Type': 'application/json' },
    });

    expect(resp.status()).toBe(201);
    const body = await resp.json();
    expect(body.data).toHaveProperty('id');
    expect(body.data).toHaveProperty('tamper_hash_sha256');
    expect(body.data.tamper_hash_sha256).toBeTruthy();
    expect(body.data).toHaveProperty('report_type');
    expect(body.data.report_type).toBe('RETENTION_SUMMARY');
  });
});
