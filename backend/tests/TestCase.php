<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use App\Database;

/**
 * 测试基类
 * 提供通用的测试辅助方法
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * 每个测试前执行
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 初始化测试数据库
        $this->initTestDatabase();
    }

    /**
     * 每个测试后执行
     */
    protected function tearDown(): void
    {
        // 清理数据库
        $this->cleanupDatabase();

        // 清除Config缓存
        \App\Config::clearCache();

        parent::tearDown();
    }

    /**
     * 初始化测试数据库
     */
    protected function initTestDatabase(): void
    {
        // 使用内存数据库
        Database::initialize();
    }

    /**
     * 清理数据库
     */
    protected function cleanupDatabase(): void
    {
        try {
            // 删除所有表数据
            $tables = [
                'key_usage_log',
                'admin_op_log',
                'access_token',
                'license_key',
                'key_batch',
                'admin_user',
                'system_config',
                'rate_limit',
                'problem_types',
                'templates'
            ];

            foreach ($tables as $table) {
                Database::execute("DELETE FROM $table");
            }
        } catch (\Exception $e) {
            // 忽略错误
        }
    }

    /**
     * 创建测试管理员
     */
    protected function createTestAdmin(?string $username = null, string $password = 'testpass'): int
    {
        // 如果没有指定用户名，生成唯一的用户名
        if ($username === null) {
            $username = 'testadmin_' . uniqid();
        }

        Database::execute(
            "INSERT INTO admin_user (username, password_hash, created_at, updated_at)
             VALUES (?, ?, datetime('now', 'localtime'), datetime('now', 'localtime'))",
            [$username, password_hash($password, PASSWORD_BCRYPT)]
        );

        return (int) Database::lastInsertId();
    }

    /**
     * 创建测试卡密
     */
    protected function createTestKey(
        string $keyPlain = 'TEST00000001',
        string $status = 'active',
        int $expireDays = 30
    ): array {
        // 创建批次
        $adminId = $this->createTestAdmin();

        Database::execute(
            "INSERT INTO key_batch (prefix, count, expire_days, created_by, created_at)
             VALUES (?, 1, ?, ?, datetime('now', 'localtime'))",
            ['TEST', $expireDays, $adminId]
        );

        $batchId = (int) Database::lastInsertId();

        // 创建卡密
        $keyHash = \App\Auth::hashKey($keyPlain);
        $keyEncrypted = \App\Crypto::encrypt($keyPlain);
        $expireAt = date('Y-m-d H:i:s', strtotime("+{$expireDays} days"));

        Database::execute(
            "INSERT INTO license_key (batch_id, key_hash, key_encrypted, status, expire_at, created_at)
             VALUES (?, ?, ?, ?, ?, datetime('now', 'localtime'))",
            [$batchId, $keyHash, $keyEncrypted, $status, $expireAt]
        );

        $keyId = (int) Database::lastInsertId();

        return [
            'id' => $keyId,
            'key_plain' => $keyPlain,
            'key_hash' => $keyHash,
            'batch_id' => $batchId,
            'status' => $status,
            'expire_at' => $expireAt
        ];
    }

    /**
     * 断言响应成功
     */
    protected function assertResponseSuccess(array $response): void
    {
        $this->assertEquals(0, $response['code'], 'Response should be successful');
        $this->assertArrayHasKey('data', $response);
    }

    /**
     * 断言响应失败
     */
    protected function assertResponseError(array $response, ?int $expectedCode = null): void
    {
        $this->assertNotEquals(0, $response['code'], 'Response should be error');

        if ($expectedCode !== null) {
            $this->assertEquals($expectedCode, $response['code']);
        }
    }
}
