const { test, expect } = require('@playwright/test');
const { resetTestDatabase } = require('./test-db');

const BASE_URL = 'http://localhost:3009';

test.describe('User Flow', () => {
    test.beforeEach(async ({ page }) => {
        resetTestDatabase();

        await page.goto(`${BASE_URL}/gate`);
        await page.evaluate(() => {
            localStorage.clear();
        });
    });

    test('should complete full user journey', async ({ page }) => {
        await page.goto(`${BASE_URL}/`);
        await page.waitForURL(`${BASE_URL}/gate`, { timeout: 5000 });

        await page.fill('#key', 'TEST00000001');
        await page.click('#submitBtn');
        await page.waitForURL(`${BASE_URL}/home`, { timeout: 10000 });

        await expect(page.locator('h1')).toBeVisible();

        const token = await page.evaluate(() => localStorage.getItem('token'));
        expect(token).toBeTruthy();

        await page.click('#logoutBtn');
        await page.waitForURL(`${BASE_URL}/gate`, { timeout: 5000 });

        const tokenAfterLogout = await page.evaluate(() => localStorage.getItem('token'));
        expect(tokenAfterLogout).toBeNull();
    });

    test('should show error for invalid key', async ({ page }) => {
        await page.goto(`${BASE_URL}/gate`);
        await page.fill('#key', 'INVALID00001');
        await page.click('#submitBtn');
        await page.waitForTimeout(2000);

        const errorElement = page.locator('#error');
        await expect(errorElement).toBeVisible();
        await expect(errorElement).toContainText('无效');
    });

    test('should validate key format', async ({ page }) => {
        await page.goto(`${BASE_URL}/gate`);
        await page.fill('#key', 'SHORT');
        await page.click('#submitBtn');
        await page.waitForTimeout(1000);

        const errorElement = page.locator('#error');
        await expect(errorElement).toBeVisible();
    });

    test('should auto-uppercase key input', async ({ page }) => {
        await page.goto(`${BASE_URL}/gate`);
        await page.fill('#key', 'test00000001');

        const value = await page.locator('#key').inputValue();
        expect(value).toBe('TEST00000001');
    });

    test('should handle network error gracefully', async ({ page }) => {
        await page.goto(`${BASE_URL}/gate`);

        await page.route('**/api/auth/verify-key', route => {
            route.abort('failed');
        });

        await page.fill('#key', 'TEST00000001');
        await page.click('#submitBtn');
        await page.waitForTimeout(2000);

        const errorElement = page.locator('#error');
        await expect(errorElement).toBeVisible();
    });

    test('should maintain session across page refresh', async ({ page }) => {
        await page.goto(`${BASE_URL}/gate`);
        await page.fill('#key', 'TEST00000002');
        await page.click('#submitBtn');
        await page.waitForURL(`${BASE_URL}/home`);

        await page.reload();
        await expect(page).toHaveURL(`${BASE_URL}/home`);
    });

    test('should redirect to gate if token expired', async ({ page }) => {
        await page.goto(`${BASE_URL}/gate`);
        await page.fill('#key', 'TEST00000003');
        await page.click('#submitBtn');
        await page.waitForURL(`${BASE_URL}/home`);

        await page.evaluate(() => {
            localStorage.removeItem('token');
        });

        await page.reload();
        await page.waitForURL(`${BASE_URL}/gate`, { timeout: 5000 });
    });

    test('should handle concurrent logins', async ({ browser }) => {
        const context1 = await browser.newContext();
        const context2 = await browser.newContext();
        const page1 = await context1.newPage();
        const page2 = await context2.newPage();

        await Promise.all([
            (async () => {
                await page1.goto(`${BASE_URL}/gate`);
                await page1.fill('#key', 'TEST00000004');
                await page1.click('#submitBtn');
                await page1.waitForURL(`${BASE_URL}/home`);
            })(),
            (async () => {
                await page2.goto(`${BASE_URL}/gate`);
                await page2.fill('#key', 'TEST00000004');
                await page2.click('#submitBtn');
                await page2.waitForURL(`${BASE_URL}/home`);
            })()
        ]);

        await expect(page1).toHaveURL(`${BASE_URL}/home`);
        await expect(page2).toHaveURL(`${BASE_URL}/home`);

        await context1.close();
        await context2.close();
    });

    test('should update nickname successfully', async ({ page }) => {
        await page.goto(`${BASE_URL}/gate`);
        await page.fill('#key', 'TEST00000005');
        await page.click('#submitBtn');
        await page.waitForURL(`${BASE_URL}/home`);

        const nicknameInput = page.locator('#nicknameInput');
        if (await nicknameInput.count() > 0) {
            await nicknameInput.fill('测试用户');

            const saveBtn = page.locator('#saveNicknameBtn');
            await saveBtn.click();
            await page.waitForTimeout(1000);

            await page.reload();
            const savedNickname = await nicknameInput.inputValue();
            expect(savedNickname).toBe('测试用户');
        }
    });
});
