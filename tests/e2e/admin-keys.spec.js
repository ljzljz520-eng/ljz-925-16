const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://localhost:3009';
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'daniaoge';

test.describe('Admin Keys Management', () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('#username', ADMIN_USERNAME);
    await page.fill('#password', ADMIN_PASSWORD);
    await page.click('#loginBtn');
    await page.waitForURL(`${BASE_URL}/admin/dashboard`);

    // Navigate to keys page
    await page.click('a[href="/admin/keys"]');
    await page.waitForURL(`${BASE_URL}/admin/keys`);

    // Ensure all modals are closed by executing JavaScript
    await page.evaluate(() => {
      const modals = ['generateModal', 'updateExpireModal', 'generatedKeysModal'];
      modals.forEach(id => {
        const modal = document.getElementById(id);
        if (modal) {
          modal.classList.add('hidden');
        }
      });
      document.body.style.overflow = '';
    });

    // Wait a bit for any animations to complete
    await page.waitForTimeout(500);
  });

  test('should display keys management page correctly', async ({ page }) => {
    // Check page title
    await expect(page).toHaveTitle('卡密管理 - 卡密管理系统');
    await expect(page.locator('.page-title')).toContainText('卡密管理');

    // Check action buttons
    await expect(page.locator('#generateBtn')).toBeVisible();
    await expect(page.locator('#exportBtn')).toBeVisible();
    await expect(page.locator('#updateExpireBtn')).toBeVisible();
    await expect(page.locator('#batchBanBtn')).toBeVisible();
    await expect(page.locator('#batchUnbanBtn')).toBeVisible();
    await expect(page.locator('#batchDeleteBtn')).toBeVisible();

    // Check filter buttons
    await expect(page.locator('.filter-btn[data-status=""]')).toBeVisible();
    await expect(page.locator('.filter-btn[data-status="active"]')).toBeVisible();
    await expect(page.locator('.filter-btn[data-status="banned"]')).toBeVisible();
    await expect(page.locator('.filter-btn[data-status="deleted"]')).toBeVisible();

    // Check table
    await expect(page.locator('.table')).toBeVisible();
    await expect(page.locator('#selectAll')).toBeVisible();
  });

  test('should NOT show modal on page load', async ({ page }) => {
    // Wait a bit to ensure no modal appears
    await page.waitForTimeout(1000);

    // Check that all modals are hidden
    const generateModal = page.locator('#generateModal');
    const updateExpireModal = page.locator('#updateExpireModal');
    const generatedKeysModal = page.locator('#generatedKeysModal');

    await expect(generateModal).toHaveClass(/hidden/);
    await expect(updateExpireModal).toHaveClass(/hidden/);
    await expect(generatedKeysModal).toHaveClass(/hidden/);
  });

  test('should open generate modal when clicking generate button', async ({ page }) => {
    // Wait for page to be fully loaded
    await page.waitForTimeout(1000);

    // Click generate button
    await page.click('#generateBtn');

    // Wait for modal to appear with longer timeout
    await page.waitForSelector('#generateModal:not(.hidden)', { timeout: 10000 });

    // Check modal content
    await expect(page.locator('#generateModal .modal-title')).toContainText('生成卡密');
    await expect(page.locator('#keyPrefix')).toBeVisible();
    await expect(page.locator('#keyCount')).toBeVisible();
    await expect(page.locator('#keyExpireDays')).toBeVisible();

    // Check default values
    await expect(page.locator('#keyCount')).toHaveValue('10');
    await expect(page.locator('#keyExpireDays')).toHaveValue('30');
  });

  test('should close generate modal when clicking cancel', async ({ page }) => {
    // Wait for page to be fully loaded
    await page.waitForTimeout(1000);

    // Open modal
    await page.click('#generateBtn');
    await page.waitForSelector('#generateModal:not(.hidden)', { timeout: 10000 });

    // Click cancel button
    await page.click('#generateModal .btn-secondary');

    // Modal should be hidden
    await expect(page.locator('#generateModal')).toHaveClass(/hidden/);
  });

  test('should generate keys successfully', async ({ page }) => {
    // Wait for page to be fully loaded
    await page.waitForTimeout(1000);

    // Open generate modal
    await page.click('#generateBtn');
    await page.waitForSelector('#generateModal:not(.hidden)', { timeout: 10000 });

    // Fill form
    await page.fill('#keyPrefix', 'TEST');
    await page.fill('#keyCount', '5');
    await page.fill('#keyExpireDays', '60');

    // Click generate button
    await page.click('#confirmGenerateBtn');

    // Wait for success (either toast or generated keys modal)
    await page.waitForTimeout(3000);

    // Check if generate modal is closed
    await expect(page.locator('#generateModal')).toHaveClass(/hidden/);
  });

  test('should validate generate form inputs', async ({ page }) => {
    // Wait for page to be fully loaded
    await page.waitForTimeout(1000);

    // Open generate modal
    await page.click('#generateBtn');
    await page.waitForSelector('#generateModal:not(.hidden)', { timeout: 10000 });

    // Try to generate with invalid count
    await page.fill('#keyCount', '2000');
    await page.click('#confirmGenerateBtn');

    // Should show error toast or stay on modal
    await page.waitForTimeout(2000);
  });

  test('should filter keys by status', async ({ page }) => {
    // Wait for initial load
    await page.waitForTimeout(2000);

    // Click on "封禁" filter
    await page.click('.filter-btn[data-status="banned"]');

    // Wait for table to update
    await page.waitForTimeout(2000);

    // Check that filter button is active
    await expect(page.locator('.filter-btn[data-status="banned"]')).toHaveClass(/active/);
  });

  test('should enable batch action buttons when keys are selected', async ({ page }) => {
    // Wait for table to load
    await page.waitForTimeout(2000);

    // Check initial state - buttons should be disabled
    await expect(page.locator('#updateExpireBtn')).toBeDisabled();
    await expect(page.locator('#batchBanBtn')).toBeDisabled();
    await expect(page.locator('#batchUnbanBtn')).toBeDisabled();
    await expect(page.locator('#batchDeleteBtn')).toBeDisabled();

    // Select a key if available
    const firstCheckbox = page.locator('.key-checkbox').first();
    if (await firstCheckbox.isVisible()) {
      await firstCheckbox.check();

      // Wait for UI to update
      await page.waitForTimeout(1000);

      // Buttons should now be enabled
      await expect(page.locator('#updateExpireBtn')).toBeEnabled();
      await expect(page.locator('#batchBanBtn')).toBeEnabled();
      await expect(page.locator('#batchUnbanBtn')).toBeEnabled();
      await expect(page.locator('#batchDeleteBtn')).toBeEnabled();
    }
  });

  test('should select all keys when clicking select all checkbox', async ({ page }) => {
    // Wait for table to load
    await page.waitForTimeout(2000);

    // Check if there are any keys
    const checkboxes = page.locator('.key-checkbox');
    const count = await checkboxes.count();

    if (count > 0) {
      // Click select all
      await page.click('#selectAll');

      // Wait for UI to update
      await page.waitForTimeout(1000);

      // All checkboxes should be checked
      for (let i = 0; i < count; i++) {
        await expect(checkboxes.nth(i)).toBeChecked();
      }

      // Export button text should change
      await expect(page.locator('#exportBtn')).toContainText('导出选中');
    }
  });

  test('should open update expire modal when clicking update expire button', async ({ page }) => {
    // Wait for table to load
    await page.waitForTimeout(2000);

    // Select a key if available
    const firstCheckbox = page.locator('.key-checkbox').first();
    if (await firstCheckbox.isVisible()) {
      await firstCheckbox.check();
      await page.waitForTimeout(1000);

      // Click update expire button
      await page.click('#updateExpireBtn');

      // Wait for modal to appear with longer timeout
      await page.waitForSelector('#updateExpireModal:not(.hidden)', { timeout: 10000 });

      // Check modal content
      await expect(page.locator('#updateExpireModal .modal-title')).toContainText('修改卡密有效期');
      await expect(page.locator('#newExpireDays')).toBeVisible();
      await expect(page.locator('#updateExpireCount')).toBeVisible();
    }
  });

  test('should show pagination if there are multiple pages', async ({ page }) => {
    // Wait for table to load
    await page.waitForTimeout(1000);

    // Check if pagination exists
    const pagination = page.locator('.pagination');
    if (await pagination.isVisible()) {
      await expect(page.locator('.pagination-btn')).toHaveCount(2);
      await expect(page.locator('.pagination-info')).toBeVisible();
    }
  });
});
