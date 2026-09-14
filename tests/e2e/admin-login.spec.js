const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://localhost:3009';
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'daniaoge';

test.describe('Admin Login', () => {
  test.beforeEach(async ({ page }) => {
    // Clear localStorage before each test
    await page.goto(`${BASE_URL}/admin/login`);
    await page.evaluate(() => {
      localStorage.clear();
    });
  });

  test('should display login page correctly', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);

    // Verify page loaded correctly
    await expect(page).toHaveTitle('管理后台登录 - 卡密管理系统');

    // Verify form is functional (can input data)
    await page.fill('#username', 'test');
    await expect(page.locator('#username')).toHaveValue('test');

    await page.fill('#password', 'test');
    await expect(page.locator('#password')).toHaveValue('test');
  });

  test('should show error for empty username', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);

    // Leave username empty, fill password
    await page.fill('#password', 'test123');
    await page.click('#loginBtn');

    const errorBox = page.locator('#loginError');
    await expect(errorBox).toBeVisible({ timeout: 10000 });
    await expect(errorBox).toContainText('请输入用户名');
  });

  test('should show error for empty password', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);

    // Fill username, leave password empty
    await page.fill('#username', 'admin');
    await page.click('#loginBtn');

    const errorBox = page.locator('#loginError');
    await expect(errorBox).toBeVisible({ timeout: 10000 });
    await expect(errorBox).toContainText('请输入密码');
  });

  test('should show error for wrong credentials', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);

    // Fill wrong credentials
    await page.fill('#username', 'wronguser');
    await page.fill('#password', 'wrongpass');
    await page.click('#loginBtn');

    // Wait for error message
    await page.waitForTimeout(2000);

    // Check for error
    const errorBox = page.locator('#loginError');
    await expect(errorBox).toBeVisible({ timeout: 10000 });
  });

  test('should login successfully with correct credentials', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/login`);

    // Fill correct credentials
    await page.fill('#username', ADMIN_USERNAME);
    await page.fill('#password', ADMIN_PASSWORD);
    await page.click('#loginBtn');

    // Wait for redirect to dashboard
    await page.waitForURL(`${BASE_URL}/admin/dashboard`, { timeout: 10000 });

    // Verify URL changed
    await expect(page).toHaveURL(`${BASE_URL}/admin/dashboard`);

    // Verify token and username are saved in localStorage
    const token = await page.evaluate(() => localStorage.getItem('admin_token'));
    const username = await page.evaluate(() => localStorage.getItem('admin_username'));

    expect(token).toBeTruthy();
    expect(username).toBe(ADMIN_USERNAME);
  });

  test('should redirect to dashboard if already logged in', async ({ page }) => {
    // First login
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('#username', ADMIN_USERNAME);
    await page.fill('#password', ADMIN_PASSWORD);
    await page.click('#loginBtn');
    await page.waitForURL(`${BASE_URL}/admin/dashboard`);

    // Try to access login page again
    await page.goto(`${BASE_URL}/admin`);

    // Should redirect to dashboard
    await page.waitForURL(`${BASE_URL}/admin/dashboard`, { timeout: 5000 });
    await expect(page).toHaveURL(`${BASE_URL}/admin/dashboard`);
  });
});
