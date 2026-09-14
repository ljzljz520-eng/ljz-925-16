<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\KeyManager;
use App\Database;

/**
 * 卡密管理集成测试
 */
class KeyManagerTest extends TestCase
{
    /**
     * 测试批量生成卡密
     */
    public function testGenerateKeys(): void
    {
        $adminId = $this->createTestAdmin();

        $result = KeyManager::generateKeys('TEST', 5, 30, $adminId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('keys', $result);
        $this->assertArrayHasKey('batch_id', $result);
        $this->assertCount(5, $result['keys']);

        // 验证每个卡密格式
        foreach ($result['keys'] as $key) {
            $this->assertEquals(12, strlen($key));
            $this->assertStringStartsWith('TEST', $key);
            $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $key);
        }

        // 验证数据库记录
        $batchInfo = Database::queryOne(
            'SELECT * FROM key_batch WHERE id = ?',
            [$result['batch_id']]
        );

        $this->assertEquals('TEST', $batchInfo['prefix']);
        $this->assertEquals(5, $batchInfo['count']);
        $this->assertEquals(30, $batchInfo['expire_days']);
        $this->assertEquals($adminId, $batchInfo['created_by']);
    }

    /**
     * 测试生成的卡密唯一性
     */
    public function testGeneratedKeysAreUnique(): void
    {
        $adminId = $this->createTestAdmin();

        $result = KeyManager::generateKeys('UNIQ', 100, 30, $adminId);
        $keys = $result['keys'];

        // 检查没有重复
        $uniqueKeys = array_unique($keys);
        $this->assertCount(100, $uniqueKeys);
    }

    /**
     * 测试批量封禁卡密
     */
    public function testBanKeys(): void
    {
        $adminId = $this->createTestAdmin();

        // 生成卡密
        $result = KeyManager::generateKeys('BAN', 3, 30, $adminId);
        $keys = $result['keys'];

        // 获取卡密ID
        $keyIds = [];
        foreach ($keys as $key) {
            $keyHash = \App\Auth::hashKey($key);
            $keyInfo = Database::queryOne(
                'SELECT id FROM license_key WHERE key_hash = ?',
                [$keyHash]
            );
            $keyIds[] = $keyInfo['id'];
        }

        // 封禁
        $affected = KeyManager::banKeys($keyIds, $adminId);
        $this->assertEquals(3, $affected);

        // 验证状态
        foreach ($keyIds as $keyId) {
            $keyInfo = Database::queryOne(
                'SELECT status FROM license_key WHERE id = ?',
                [$keyId]
            );
            $this->assertEquals('banned', $keyInfo['status']);
        }
    }

    /**
     * 测试批量解封卡密
     */
    public function testUnbanKeys(): void
    {
        $adminId = $this->createTestAdmin();

        // 创建已封禁的卡密
        $keyData1 = $this->createTestKey('UNBAN0000001', 'banned', 30);
        $keyData2 = $this->createTestKey('UNBAN0000002', 'banned', 30);

        $keyIds = [$keyData1['id'], $keyData2['id']];

        // 解封
        $affected = KeyManager::unbanKeys($keyIds, $adminId);
        $this->assertEquals(2, $affected);

        // 验证状态
        foreach ($keyIds as $keyId) {
            $keyInfo = Database::queryOne(
                'SELECT status FROM license_key WHERE id = ?',
                [$keyId]
            );
            $this->assertEquals('active', $keyInfo['status']);
        }
    }

    /**
     * 测试批量删除卡密
     */
    public function testDeleteKeys(): void
    {
        $adminId = $this->createTestAdmin();

        $keyData1 = $this->createTestKey('DEL000000001', 'active', 30);
        $keyData2 = $this->createTestKey('DEL000000002', 'active', 30);

        $keyIds = [$keyData1['id'], $keyData2['id']];

        // 删除
        $affected = KeyManager::deleteKeys($keyIds, $adminId);
        $this->assertEquals(2, $affected);

        // 验证状态（软删除）
        foreach ($keyIds as $keyId) {
            $keyInfo = Database::queryOne(
                'SELECT status FROM license_key WHERE id = ?',
                [$keyId]
            );
            $this->assertEquals('deleted', $keyInfo['status']);
        }
    }

    /**
     * 测试修改卡密有效期
     */
    public function testUpdateExpire(): void
    {
        $adminId = $this->createTestAdmin();

        $keyData = $this->createTestKey('EXP000000001', 'active', 30);

        // 修改有效期为60天
        $affected = KeyManager::updateExpire([$keyData['id']], 60, $adminId);
        $this->assertEquals(1, $affected);

        // 验证新的过期时间
        $keyInfo = Database::queryOne(
            'SELECT expire_at FROM license_key WHERE id = ?',
            [$keyData['id']]
        );

        $expectedExpireAt = date('Y-m-d H:i:s', strtotime('+60 days'));
        $actualExpireAt = $keyInfo['expire_at'];

        // 允许1分钟的误差
        $diff = abs(strtotime($expectedExpireAt) - strtotime($actualExpireAt));
        $this->assertLessThan(60, $diff);
    }

    /**
     * 测试修改有效期后，已过期卡密恢复可用
     */
    public function testUpdateExpireReactivatesExpiredKeys(): void
    {
        $adminId = $this->createTestAdmin();

        // 创建已过期且状态为expired的卡密
        $keyData = $this->createTestKey('EXP000000010', 'expired', -1);

        // 修改有效期为30天
        $affected = KeyManager::updateExpire([$keyData['id']], 30, $adminId);
        $this->assertEquals(1, $affected);

        // 状态应恢复为active，且过期时间在未来
        $keyInfo = Database::queryOne(
            'SELECT status, expire_at FROM license_key WHERE id = ?',
            [$keyData['id']]
        );
        $this->assertEquals('active', $keyInfo['status']);
        $this->assertGreaterThan(time(), strtotime($keyInfo['expire_at']));
    }

    /**
     * 测试扫描处理过期卡密
     */
    public function testCheckExpiredKeys(): void
    {
        // 准备数据：2个已过期的active卡密、1个未过期的active卡密、1个已封禁卡密
        $expired1 = $this->createTestKey('SCAN00000001', 'active', -1);
        $expired2 = $this->createTestKey('SCAN00000002', 'active', -3);
        $valid = $this->createTestKey('SCAN00000003', 'active', 30);
        $banned = $this->createTestKey('SCAN00000004', 'banned', -1);

        // 为过期卡密创建token，验证扫描后token被撤销
        $tokenData = \App\TokenManager::generateToken($expired1['id']);

        // 执行扫描
        $affected = KeyManager::checkExpiredKeys();
        $this->assertEquals(2, $affected);

        // 过期卡密状态应更新为expired
        foreach ([$expired1, $expired2] as $keyData) {
            $keyInfo = Database::queryOne('SELECT status FROM license_key WHERE id = ?', [$keyData['id']]);
            $this->assertEquals('expired', $keyInfo['status']);
        }

        // 未过期卡密不受影响
        $keyInfo = Database::queryOne('SELECT status FROM license_key WHERE id = ?', [$valid['id']]);
        $this->assertEquals('active', $keyInfo['status']);

        // 已封禁卡密不受影响
        $keyInfo = Database::queryOne('SELECT status FROM license_key WHERE id = ?', [$banned['id']]);
        $this->assertEquals('banned', $keyInfo['status']);

        // 过期卡密的token应被撤销
        $this->assertNull(\App\TokenManager::getTokenKeyInfo($tokenData['token']));

        // 再次扫描，没有可处理的卡密
        $this->assertEquals(0, KeyManager::checkExpiredKeys());
    }

    /**
     * 测试将单个卡密标记为过期
     */
    public function testMarkKeyExpired(): void
    {
        $keyData = $this->createTestKey('MARK00000001', 'active', -1);
        $tokenData = \App\TokenManager::generateToken($keyData['id']);

        $result = KeyManager::markKeyExpired($keyData['id']);
        $this->assertTrue($result);

        // 状态更新为expired
        $keyInfo = Database::queryOne('SELECT status FROM license_key WHERE id = ?', [$keyData['id']]);
        $this->assertEquals('expired', $keyInfo['status']);

        // token被撤销
        $this->assertNull(\App\TokenManager::getTokenKeyInfo($tokenData['token']));

        // 重复标记返回false（状态已不是active）
        $this->assertFalse(KeyManager::markKeyExpired($keyData['id']));

        // 已封禁卡密不会被标记为过期
        $bannedKey = $this->createTestKey('MARK00000002', 'banned', -1);
        $this->assertFalse(KeyManager::markKeyExpired($bannedKey['id']));
        $keyInfo = Database::queryOne('SELECT status FROM license_key WHERE id = ?', [$bannedKey['id']]);
        $this->assertEquals('banned', $keyInfo['status']);
    }

    /**
     * 测试获取卡密统计
     */
    public function testGetKeyStats(): void
    {
        $adminId = $this->createTestAdmin();

        // 创建不同状态的卡密
        $this->createTestKey('STAT00000001', 'active', 30);  // 可用
        $this->createTestKey('STAT00000002', 'active', 30);  // 可用
        $this->createTestKey('STAT00000003', 'banned', 30);  // 封禁
        $this->createTestKey('STAT00000004', 'active', -1);  // 过期

        // 标记一个为已使用
        $keyData = $this->createTestKey('STAT00000005', 'active', 30);
        Database::execute(
            "UPDATE license_key SET first_used_at = datetime('now', 'localtime') WHERE id = ?",
            [$keyData['id']]
        );

        $stats = KeyManager::getKeyStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('used', $stats);
        $this->assertArrayHasKey('banned', $stats);
        $this->assertArrayHasKey('expired', $stats);

        $this->assertGreaterThanOrEqual(5, $stats['total']);
        $this->assertGreaterThanOrEqual(2, $stats['active']);
        $this->assertGreaterThanOrEqual(1, $stats['used']);
        $this->assertGreaterThanOrEqual(1, $stats['banned']);
        $this->assertGreaterThanOrEqual(1, $stats['expired']);
    }

    /**
     * 测试获取卡密列表
     */
    public function testGetKeys(): void
    {
        $adminId = $this->createTestAdmin();

        // 创建测试卡密
        for ($i = 1; $i <= 15; $i++) {
            $this->createTestKey(sprintf('LIST%08d', $i), 'active', 30);
        }

        // 获取第一页
        $result = KeyManager::getKeys([], 1, 10);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('page_size', $result);
        $this->assertArrayHasKey('data', $result);

        $this->assertGreaterThanOrEqual(15, $result['total']);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(10, $result['page_size']);
        $this->assertCount(10, $result['data']);
    }

    /**
     * 测试按状态筛选卡密
     */
    public function testGetKeysByStatus(): void
    {
        $adminId = $this->createTestAdmin();

        $this->createTestKey('FILT00000001', 'active', 30);
        $this->createTestKey('FILT00000002', 'active', 30);
        $this->createTestKey('FILT00000003', 'banned', 30);

        // 只获取active状态
        $result = KeyManager::getKeys(['status' => 'active'], 1, 50);

        foreach ($result['data'] as $key) {
            $this->assertEquals('active', $key['status']);
        }
    }

    /**
     * 测试更新卡密昵称
     */
    public function testUpdateNickname(): void
    {
        $keyData = $this->createTestKey('NICK00000001', 'active', 30);

        $result = KeyManager::updateNickname($keyData['id'], '测试用户');
        $this->assertTrue($result);

        // 验证昵称
        $keyInfo = Database::queryOne(
            'SELECT nickname FROM license_key WHERE id = ?',
            [$keyData['id']]
        );

        $this->assertEquals('测试用户', $keyInfo['nickname']);
    }

    /**
     * 测试导出CSV
     */
    public function testExportKeys(): void
    {
        $adminId = $this->createTestAdmin();

        $result = KeyManager::generateKeys('EXPORT', 3, 30, $adminId);
        $batchId = $result['batch_id'];

        $csv = KeyManager::exportKeys($batchId);

        $this->assertIsString($csv);
        $this->assertStringContainsString('卡密,ID,状态', $csv);
        $this->assertStringContainsString('EXPORT', $csv);

        // 验证CSV行数（标题行 + 3个卡密）
        $lines = explode("\n", trim($csv));
        $this->assertCount(4, $lines);
    }

    /**
     * 测试空数组操作
     */
    public function testEmptyArrayOperations(): void
    {
        $adminId = $this->createTestAdmin();

        // 空数组封禁
        $affected = KeyManager::banKeys([], $adminId);
        $this->assertEquals(0, $affected);

        // 空数组解封
        $affected = KeyManager::unbanKeys([], $adminId);
        $this->assertEquals(0, $affected);

        // 空数组删除
        $affected = KeyManager::deleteKeys([], $adminId);
        $this->assertEquals(0, $affected);
    }
}
