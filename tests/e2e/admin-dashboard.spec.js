const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://localhost:3009';
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'daniaoge';

test.describe('Admin Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('#username', ADMIN_USERNAME);
    await page.fill('#password', ADMIN_PASSWORD);
    await page.click('#loginBtn');
    await page.waitForURL(`${BASE_URL}/admin/dashboard`);
  });

  test('should display dashboard correctly', async ({ page }) => {
    // Wait for dashboard content to load
    await page.waitForTimeout(2000);

    // Check page title - match actual title
    await expect(page).toHaveTitle('数据统计 - 卡密管理系统');
    await expect(page.locator('.page-title')).toContainText('数据统计');

    // Check sidebar
    await expect(page.locator('.sidebar-title')).toContainText('卡密管理系统');
    await expect(page.locator('.sidebar-subtitle')).toContainText('管理后台');

    // Check navigation items
    await expect(page.locator('a[href="/admin/dashboard"]')).toHaveClass(/active/);
    await expect(page.locator('a[href="/admin/keys"]')).toBeVisible();
    await expect(page.locator('a[href="/admin/logs"]')).toBeVisible();

    // Check username display
    await expect(page.locator('#username')).toBeVisible();

    // Check logout button
    await expect(page.locator('#logoutBtn')).toBeVisible();
  });

  test('should display all 8 statistics cards with valid data', async ({ page }) => {
    // Setup API response listener
    const statsResponsePromise = page.waitForResponse(
      response => response.url().includes('/api/admin/stats') && response.status() === 200
    );

    // Wait for stats API to be called
    const statsResponse = await statsResponsePromise;
    const statsData = await statsResponse.json();

    // Verify API response structure: { code: 0, message: "...", data: {...} }
    expect(statsData.code).toBe(0);
    expect(statsData.data).toBeTruthy();
    const data = statsData.data;

    // Verify all required fields exist and are numbers
    expect(typeof data.total).toBe('number');
    expect(typeof data.active).toBe('number');
    expect(typeof data.used).toBe('number');
    expect(typeof data.banned).toBe('number');
    expect(typeof data.expired).toBe('number');
    expect(typeof data.today_new).toBe('number');
    expect(typeof data.today_used).toBe('number');
    expect(typeof data.today_usage).toBe('number');

    // Verify data logic: total should equal or be greater than sum of active, used, banned, expired
    const calculatedTotal = data.active + data.used + data.banned + data.expired;
    expect(data.total).toBeGreaterThanOrEqual(calculatedTotal);

    // Verify all values are non-negative
    expect(data.total).toBeGreaterThanOrEqual(0);
    expect(data.active).toBeGreaterThanOrEqual(0);
    expect(data.used).toBeGreaterThanOrEqual(0);
    expect(data.banned).toBeGreaterThanOrEqual(0);
    expect(data.expired).toBeGreaterThanOrEqual(0);
    expect(data.today_new).toBeGreaterThanOrEqual(0);
    expect(data.today_used).toBeGreaterThanOrEqual(0);
    expect(data.today_usage).toBeGreaterThanOrEqual(0);

    // Wait for dashboard content to be visible
    await page.waitForSelector('#dashboardContent:not(.hidden)', { timeout: 15000 });

    // Verify UI displays the correct data from API
    const statCards = [
      { id: 'stat-total', apiKey: 'total' },
      { id: 'stat-active', apiKey: 'active' },
      { id: 'stat-used', apiKey: 'used' },
      { id: 'stat-banned', apiKey: 'banned' },
      { id: 'stat-expired', apiKey: 'expired' },
      { id: 'stat-today_new', apiKey: 'today_new' },
      { id: 'stat-today_used', apiKey: 'today_used' },
      { id: 'stat-today_usage', apiKey: 'today_usage' }
    ];

    for (const card of statCards) {
      const element = page.locator(`#${card.id}`);
      await expect(element).toBeVisible({ timeout: 10000 });

      // Verify displayed value matches API data
      const displayedValue = await element.textContent();
      expect(displayedValue).toBe(String(data[card.apiKey]));
    }
  });

  test('should display system information', async ({ page }) => {
    // Wait for dashboard content to be visible
    await page.waitForSelector('#dashboardContent:not(.hidden)', { timeout: 15000 });
    await page.waitForTimeout(1000);

    // Check system info card
    await expect(page.locator('.card-title')).toContainText('系统信息');

    // Check system info items
    await expect(page.getByText('数据库：')).toBeVisible();
    await expect(page.getByText('SQLite3')).toBeVisible();
    await expect(page.getByText('后端：')).toBeVisible();
    await expect(page.getByText(/^PHP \d+\.\d+$/)).toBeVisible();
  });

  test('should navigate to keys management', async ({ page }) => {
    await page.click('a[href="/admin/keys"]');
    await page.waitForURL(`${BASE_URL}/admin/keys`);

    await expect(page).toHaveURL(`${BASE_URL}/admin/keys`);
    await expect(page.locator('.page-title')).toContainText('卡密管理');
  });

  test('should navigate to logs page', async ({ page }) => {
    await page.click('a[href="/admin/logs"]');
    await page.waitForURL(`${BASE_URL}/admin/logs`);

    await expect(page).toHaveURL(`${BASE_URL}/admin/logs`);
    await expect(page.locator('.page-title')).toContainText('使用日志');
  });

  test('should logout successfully', async ({ page }) => {
    // Click logout button
    await page.click('#logoutBtn');

    // Wait for confirmation modal with longer timeout
    await page.waitForSelector('.modal-container:not(.hidden)', { timeout: 10000 });
    await expect(page.locator('.modal-title')).toContainText('退出确认');

    // Confirm logout
    await page.click('.modal-ok');

    // Wait for redirect to login page
    await page.waitForURL(`${BASE_URL}/admin/login`, { timeout: 10000 });

    // Check if token is cleared
    const token = await page.evaluate(() => localStorage.getItem('admin_token'));
    expect(token).toBeNull();
  });

  test('should cancel logout', async ({ page }) => {
    // Click logout button
    await page.click('#logoutBtn');

    // Wait for confirmation modal with longer timeout
    await page.waitForSelector('.modal-container:not(.hidden)', { timeout: 10000 });

    // Cancel logout
    await page.click('.modal-cancel');

    // Should stay on dashboard
    await expect(page).toHaveURL(`${BASE_URL}/admin/dashboard`);

    // Token should still exist
    const token = await page.evaluate(() => localStorage.getItem('admin_token'));
    expect(token).toBeTruthy();
  });
});
