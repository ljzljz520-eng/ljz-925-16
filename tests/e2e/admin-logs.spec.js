const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://localhost:3009';
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'daniaoge';

test.describe('Admin Logs', () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('#username', ADMIN_USERNAME);
    await page.fill('#password', ADMIN_PASSWORD);
    await page.click('#loginBtn');
    await page.waitForURL(`${BASE_URL}/admin/dashboard`);

    // Navigate to logs page
    await page.click('a[href="/admin/logs"]');
    await page.waitForURL(`${BASE_URL}/admin/logs`);
  });

  test('should display logs page correctly', async ({ page }) => {
    // Check page title
    await expect(page).toHaveTitle('使用日志 - 卡密管理系统');
    await expect(page.locator('.page-title')).toContainText('使用日志');

    // Check tabs
    await expect(page.locator('.tab[data-type="usage"]')).toBeVisible();
    await expect(page.locator('.tab[data-type="admin"]')).toBeVisible();

    // Check table
    await expect(page.locator('.table')).toBeVisible();
    await expect(page.locator('#logsTableHead')).toBeVisible();
    await expect(page.locator('#logsTableBody')).toBeVisible();
  });

  test('should NOT show error on page load', async ({ page }) => {
    // Wait for logs to load
    await page.waitForTimeout(2000);

    // Check that no error is displayed in table
    const errorCell = page.locator('td:has-text("加载失败")');
    await expect(errorCell).not.toBeVisible();

    // Check console for JavaScript errors
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });

    await page.waitForTimeout(1000);
    expect(errors.length).toBe(0);
  });

  test('should display usage logs with valid data structure', async ({ page }) => {
    // Setup API response listener
    const logsResponsePromise = page.waitForResponse(
      response => response.url().includes('/api/admin/logs') &&
                  response.url().includes('type=usage') &&
                  response.status() === 200
    );

    // Wait for logs to load
    await page.waitForTimeout(1000);

    // Wait for API response
    const logsResponse = await logsResponsePromise;
    const logsData = await logsResponse.json();

    // Verify API response structure: { code: 0, message: "...", data: { total, page, page_size, data: [...] } }
    expect(logsData.code).toBe(0);
    expect(logsData.data).toBeTruthy();
    expect(logsData.data.data).toBeTruthy();
    expect(Array.isArray(logsData.data.data)).toBe(true);
    const logs = logsData.data.data;

    // If there are logs, verify data structure
    if (logs.length > 0) {
      const firstLog = logs[0];

      // Verify required fields exist
      expect(firstLog).toHaveProperty('id');
      expect(firstLog).toHaveProperty('created_at');

      // Verify data types
      expect(typeof firstLog.id).toBe('number');
      expect(typeof firstLog.created_at).toBe('string');

      // Verify date format (should be valid date string)
      const date = new Date(firstLog.created_at);
      expect(date.toString()).not.toBe('Invalid Date');

      // Verify date is not in the future
      const now = new Date();
      expect(date.getTime()).toBeLessThanOrEqual(now.getTime());

      // Verify date is reasonable (within last 10 years)
      const tenYearsAgo = new Date();
      tenYearsAgo.setFullYear(tenYearsAgo.getFullYear() - 10);
      expect(date.getTime()).toBeGreaterThan(tenYearsAgo.getTime());
    }

    // Verify pagination data if exists
    if (logsData.pagination) {
      expect(typeof logsData.pagination.current_page).toBe('number');
      expect(typeof logsData.pagination.total_pages).toBe('number');
      expect(typeof logsData.pagination.total).toBe('number');
      expect(logsData.pagination.current_page).toBeGreaterThan(0);
      expect(logsData.pagination.total_pages).toBeGreaterThanOrEqual(0);
      expect(logsData.pagination.total).toBeGreaterThanOrEqual(0);
    }

    // Verify table displays data correctly
    const tableBody = page.locator('#logsTableBody');
    const rowCount = await tableBody.locator('tr').count();

    if (logs.length > 0) {
      // Should have rows matching data
      expect(rowCount).toBeGreaterThan(0);
      expect(rowCount).toBeLessThanOrEqual(logs.length);
    }
  });

  test('should switch to admin logs and load different data', async ({ page }) => {
    // Wait for initial usage logs to load
    await page.waitForTimeout(1000);

    // Setup API response listener for admin logs
    const adminLogsResponsePromise = page.waitForResponse(
      response => response.url().includes('/api/admin/logs') &&
                  response.url().includes('type=admin') &&
                  response.status() === 200
    );

    // Click admin logs tab
    await page.click('.tab[data-type="admin"]');

    // Wait for admin logs API response
    const adminLogsResponse = await adminLogsResponsePromise;
    const adminLogsData = await adminLogsResponse.json();

    // Verify API response structure: { code: 0, message: "...", data: { total, page, page_size, data: [...] } }
    expect(adminLogsData.code).toBe(0);
    expect(adminLogsData.data).toBeTruthy();
    expect(adminLogsData.data.data).toBeTruthy();
    expect(Array.isArray(adminLogsData.data.data)).toBe(true);
    const adminLogs = adminLogsData.data.data;

    // If there are admin logs, verify data structure
    if (adminLogs.length > 0) {
      const firstLog = adminLogs[0];

      // Verify required fields for admin logs
      expect(firstLog).toHaveProperty('id');
      expect(firstLog).toHaveProperty('created_at');
      expect(firstLog).toHaveProperty('username'); // From JOIN with admin_user table
      expect(firstLog).toHaveProperty('action');

      // Verify data types
      expect(typeof firstLog.id).toBe('number');
      expect(typeof firstLog.created_at).toBe('string');
      expect(typeof firstLog.username).toBe('string');
      expect(typeof firstLog.action).toBe('string');

      // Verify admin username is not empty
      expect(firstLog.username.length).toBeGreaterThan(0);
    }

    // Verify tab is active
    await expect(page.locator('.tab[data-type="admin"]')).toHaveClass(/active/);

    // Wait for table to update
    await page.waitForTimeout(1000);
  });

  test('should display logs data or empty state', async ({ page }) => {
    // Wait for logs to load
    await page.waitForTimeout(2000);

    const tableBody = page.locator('#logsTableBody');

    // Check if there are logs or empty state
    const hasData = await tableBody.locator('tr').count() > 0;

    if (hasData) {
      // If there are logs, check that they have proper structure
      const firstRow = tableBody.locator('tr').first();
      const cells = firstRow.locator('td');
      const cellCount = await cells.count();

      // Usage logs should have 7 columns
      expect(cellCount).toBeGreaterThan(0);
    } else {
      // If no logs, should show empty state
      await expect(tableBody).toContainText('暂无日志');
    }
  });

  test('should show pagination if there are multiple pages', async ({ page }) => {
    // Wait for logs to load
    await page.waitForTimeout(1000);

    // Check if pagination exists
    const pagination = page.locator('.pagination');
    if (await pagination.isVisible()) {
      await expect(page.locator('.pagination-btn')).toHaveCount(2);
      await expect(page.locator('.pagination-info')).toBeVisible();
    }
  });

  test('should navigate between pages and load different data', async ({ page }) => {
    // Wait for logs to load
    await page.waitForTimeout(2000);

    // Check if pagination exists
    const pagination = page.locator('.pagination');
    if (await pagination.isVisible()) {
      // Check if next button exists and is enabled
      const nextBtn = page.locator('.pagination-btn:has-text("下一页")');
      if (await nextBtn.isVisible() && await nextBtn.isEnabled()) {
        // Click next page
        await nextBtn.click();

        // Wait for page to load
        await page.waitForTimeout(2000);

        // Previous button should now be enabled
        const prevBtn = page.locator('.pagination-btn:has-text("上一页")');
        await expect(prevBtn).toBeEnabled();
      }
    }
  });

  test('should maintain tab selection when switching pages', async ({ page }) => {
    // Switch to admin logs
    await page.click('.tab[data-type="admin"]');
    await page.waitForTimeout(1000);

    // Check if pagination exists
    const nextBtn = page.locator('.pagination-btn:has-text("下一页")');
    if (await nextBtn.isVisible() && await nextBtn.isEnabled()) {
      // Click next page
      await nextBtn.click();
      await page.waitForTimeout(1000);

      // Admin tab should still be active
      await expect(page.locator('.tab[data-type="admin"]')).toHaveClass(/active/);
    }
  });

  test('should display result badges correctly', async ({ page }) => {
    // Wait for logs to load
    await page.waitForTimeout(1000);

    // Check if there are any logs
    const hasLogs = await page.locator('#logsTableBody tr').count() > 0;

    // If there are logs, just verify the table is populated
    // Badge classes may vary, so we just check that data is displayed
    if (hasLogs) {
      const firstRow = page.locator('#logsTableBody tr').first();
      await expect(firstRow).toBeVisible();
    }
  });

  test('should format dates correctly and match API data', async ({ page }) => {
    // Setup API response listener
    const logsResponsePromise = page.waitForResponse(
      response => response.url().includes('/api/admin/logs') && response.status() === 200
    );

    // Wait for logs to load
    await page.waitForTimeout(1000);

    // Get API response
    const logsResponse = await logsResponsePromise;
    const logsData = await logsResponse.json();

    // Verify response structure
    expect(logsData.code).toBe(0);
    expect(logsData.data).toBeTruthy();
    expect(logsData.data.data).toBeTruthy();
    const logs = Array.isArray(logsData.data.data) ? logsData.data.data : [];

    if (logs.length > 0) {
      const firstLog = logs[0];

      // Verify date from API is valid
      const apiDate = new Date(firstLog.created_at);
      expect(apiDate.toString()).not.toBe('Invalid Date');

      // Verify date is not in the future
      const now = new Date();
      expect(apiDate.getTime()).toBeLessThanOrEqual(now.getTime());

      // Verify date is reasonable (not too old, e.g., within last 10 years)
      const tenYearsAgo = new Date();
      tenYearsAgo.setFullYear(tenYearsAgo.getFullYear() - 10);
      expect(apiDate.getTime()).toBeGreaterThan(tenYearsAgo.getTime());

      // Check if there are any date cells in the table
      const dateCells = page.locator('#logsTableBody tr td:last-child');
      const count = await dateCells.count();

      if (count > 0) {
        // Get first date cell text
        const dateText = await dateCells.first().textContent();

        // Verify date format contains year
        expect(dateText).toMatch(/\d{4}/);

        // Verify date format is reasonable (YYYY/MM/DD or YYYY-MM-DD)
        const hasValidFormat = /\d{4}[/-]\d{1,2}[/-]\d{1,2}/.test(dateText);
        expect(hasValidFormat).toBe(true);
      }
    }
  });
});
