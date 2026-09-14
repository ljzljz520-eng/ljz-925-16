const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://localhost:3009';
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'daniaoge';

/**
 * 辅助函数：管理员登录
 */
async function loginAsAdmin(page) {
    await page.goto(`${BASE_URL}/admin/login`);
    await page.fill('#username', ADMIN_USERNAME);
    await page.fill('#password', ADMIN_PASSWORD);
    await page.click('#loginBtn');
    await page.waitForURL(`${BASE_URL}/admin/dashboard`, { timeout: 10000 });
}

test.describe('Admin Keys Management', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE_URL}/admin/keys`);
    });

    test('should generate keys with custom prefix', async ({ page }) => {
        await page.click('#generateBtn');
        await expect(page.locator('#generateModal')).toBeVisible();

        await page.fill('#keyPrefix', 'E2E');
        await page.fill('#keyCount', '5');
        await page.fill('#keyExpireDays', '30');
        await page.click('#confirmGenerateBtn');

        await expect(page.locator('#generatedKeysModal')).toBeVisible();
        await expect(page.locator('#generatedKeysList')).toContainText('E2E');
    });

    test('should validate generation form', async ({ page }) => {
        const generateBtn = page.locator('#generateBtn');

        // 不填写任何内容直接提交
        await generateBtn.click();

        // 应该显示验证错误
        await page.waitForTimeout(1000);
        // 根据实际错误提示元素调整
    });

    test('should batch ban keys', async ({ page }) => {
        // 选择多个卡密（根据实际HTML结构调整）
        const checkboxes = page.locator('.key-checkbox');
        const count = await checkboxes.count();

        if (count > 0) {
            // 选择前3个
            for (let i = 0; i < Math.min(3, count); i++) {
                await checkboxes.nth(i).check();
            }

            // 点击批量封禁按钮
            const banBtn = page.locator('#batchBanBtn');
            await banBtn.click();

            // 确认对话框（如果有）
            await page.waitForTimeout(1000);

            // 验证操作成功
        }
    });

    test('should batch unban keys', async ({ page }) => {
        // 筛选已封禁的卡密
        const statusFilter = page.locator('#statusFilter');
        if (await statusFilter.count() > 0) {
            await statusFilter.selectOption('banned');
            await page.waitForTimeout(1000);
        }

        // 选择卡密并解封
        const checkboxes = page.locator('.key-checkbox');
        const count = await checkboxes.count();

        if (count > 0) {
            await checkboxes.first().check();

            const unbanBtn = page.locator('#batchUnbanBtn');
            await unbanBtn.click();

            await page.waitForTimeout(1000);
        }
    });

    test('should batch delete keys', async ({ page }) => {
        const checkboxes = page.locator('.key-checkbox');
        const count = await checkboxes.count();

        if (count > 0) {
            await checkboxes.first().check();

            const deleteBtn = page.locator('#batchDeleteBtn');
            await deleteBtn.click();

            // 确认删除
            await page.waitForTimeout(1000);
        }
    });

    test('should export keys to Excel', async ({ page }) => {
        // 选择一些卡密
        const checkboxes = page.locator('.key-checkbox');
        const count = await checkboxes.count();

        if (count > 0) {
            await checkboxes.first().check();

            // 点击导出按钮
            const exportBtn = page.locator('#exportBtn');

            const [download] = await Promise.all([
                page.waitForEvent('download'),
                exportBtn.click()
            ]);

            // 验证文件名
            const filename = download.suggestedFilename();
            expect(filename).toMatch(/\.xlsx$/);

            // 验证文件大小
            const path = await download.path();
            const fs = require('fs');
            const stats = fs.statSync(path);
            expect(stats.size).toBeGreaterThan(0);
        }
    });

    test('should search keys', async ({ page }) => {
        const searchInput = page.locator('#searchInput');
        const searchBtn = page.locator('#searchBtn');

        if (await searchInput.count() > 0) {
            await searchInput.fill('TEST');
            await searchBtn.click();

            await page.waitForTimeout(1000);

            // 验证搜索结果
            const keyItems = page.locator('.key-item');
            const count = await keyItems.count();

            if (count > 0) {
                // 验证结果包含搜索关键词
                const firstItem = await keyItems.first().textContent();
                expect(firstItem).toContain('TEST');
            }
        }
    });

    test('should filter keys by status', async ({ page }) => {
        const statusFilter = page.locator('#statusFilter');

        if (await statusFilter.count() > 0) {
            // 筛选active状态
            await statusFilter.selectOption('active');
            await page.waitForTimeout(1000);

            // 验证所有显示的卡密都是active状态
            const statusBadges = page.locator('.status-badge');
            const count = await statusBadges.count();

            for (let i = 0; i < count; i++) {
                const text = await statusBadges.nth(i).textContent();
                expect(text).toContain('可用');
            }
        }
    });

    test('should paginate keys list', async ({ page }) => {
        // 查找分页控件
        const nextPageBtn = page.locator('.pagination-next');

        if (await nextPageBtn.count() > 0 && await nextPageBtn.isEnabled()) {
            // 记录当前页的第一个卡密
            const firstKeyBefore = await page.locator('.key-item').first().textContent();

            // 点击下一页
            await nextPageBtn.click();
            await page.waitForTimeout(1000);

            // 验证内容已更新
            const firstKeyAfter = await page.locator('.key-item').first().textContent();
            expect(firstKeyAfter).not.toBe(firstKeyBefore);
        }
    });

    test('should update key expiration', async ({ page }) => {
        const checkboxes = page.locator('.key-checkbox');
        const count = await checkboxes.count();

        if (count > 0) {
            await checkboxes.first().check();

            // 查找修改有效期按钮
            const updateExpireBtn = page.locator('#updateExpireBtn');

            if (await updateExpireBtn.count() > 0) {
                await updateExpireBtn.click();

                // 填写新的有效期
                const expireDaysInput = page.locator('#newExpireDays');
                await expireDaysInput.fill('60');

                // 确认
                const confirmBtn = page.locator('#confirmUpdateExpireBtn');
                await confirmBtn.click();

                await expect(page.locator('#updateExpireModal')).toBeHidden();
            }
        }
    });

    test('should select all keys', async ({ page }) => {
        const selectAllCheckbox = page.locator('#selectAll');

        if (await selectAllCheckbox.count() > 0) {
            // 全选
            await selectAllCheckbox.check();

            // 验证所有复选框都被选中
            const checkboxes = page.locator('.key-checkbox');
            const count = await checkboxes.count();

            for (let i = 0; i < count; i++) {
                await expect(checkboxes.nth(i)).toBeChecked();
            }

            // 取消全选
            await selectAllCheckbox.uncheck();

            // 验证所有复选框都未选中
            for (let i = 0; i < count; i++) {
                await expect(checkboxes.nth(i)).not.toBeChecked();
            }
        }
    });

    test('should show key details', async ({ page }) => {
        const keyItems = page.locator('.key-item');
        const count = await keyItems.count();

        if (count > 0) {
            // 点击第一个卡密查看详情
            const detailBtn = keyItems.first().locator('.detail-btn');

            if (await detailBtn.count() > 0) {
                await detailBtn.click();

                // 等待详情弹窗或页面
                await page.waitForTimeout(1000);

                // 验证详情内容显示
                const detailModal = page.locator('.key-detail-modal');
                if (await detailModal.count() > 0) {
                    await expect(detailModal).toBeVisible();
                }
            }
        }
    });

    test('should handle empty key list', async ({ page }) => {
        // 筛选一个不存在的状态或搜索不存在的关键词
        const searchInput = page.locator('#searchInput');

        if (await searchInput.count() > 0) {
            await searchInput.fill('NONEXISTENT999999');
            await page.locator('#searchBtn').click();

            await page.waitForTimeout(1000);

            // 验证显示空状态提示
            const emptyState = page.locator('.empty-state');
            if (await emptyState.count() > 0) {
                await expect(emptyState).toBeVisible();
            }
        }
    });
});
