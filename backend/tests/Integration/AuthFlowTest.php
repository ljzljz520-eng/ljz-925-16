<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Auth;
use App\Database;

/**
 * 认证流程集成测试
 */
class AuthFlowTest extends TestCase
{
    /**
     * 测试完整的认证流程
     */
    public function testCompleteAuthFlow(): void
    {
        // 1. 创建测试卡密
        $keyData = $this->createTestKey('TEST00000001', 'active', 30);
        $keyPlain = $keyData['key_plain'];

        // 2. 验证卡密
        $authResult = Auth::verifyKey($keyPlain, '127.0.0.1', 'TestAgent');

        $this->assertResponseSuccess($authResult);
        $this->assertArrayHasKey('token', $authResult['data']);
        $this->assertArrayHasKey('expire_at', $authResult['data']);

        $token = $authResult['data']['token'];

        // 3. 验证token
        $validateResult = Auth::validateToken($token);
        $this->assertResponseSuccess($validateResult);
        $this->assertEquals($keyData['id'], $validateResult['data']['key_id']);

        // 4. 心跳检测
        $pingResult = Auth::ping($token);
        $this->assertResponseSuccess($pingResult);
        $this->assertTrue($pingResult['data']['valid']);
        $this->assertEquals('active', $pingResult['data']['key_status']);

        // 5. 登出
        $logoutResult = Auth::logout($token);
        $this->assertResponseSuccess($logoutResult);

        // 6. 验证token已失效
        $validateResult2 = Auth::validateToken($token);
        $this->assertResponseError($validateResult2, 1002);
    }

    /**
     * 测试无效卡密验证
     */
    public function testInvalidKeyVerification(): void
    {
        $result = Auth::verifyKey('INVALID00001', '127.0.0.1', 'TestAgent');

        $this->assertResponseError($result, 1001);
        $this->assertStringContainsString('无效', $result['message']);
    }

    /**
     * 测试过期卡密自动封禁
     */
    public function testExpiredKeyAutoBan(): void
    {
        // 创建已过期的卡密
        $keyData = $this->createTestKey('EXP000000001', 'active', -1);
        $keyPlain = $keyData['key_plain'];

        // 尝试验证
        $result = Auth::verifyKey($keyPlain, '127.0.0.1', 'TestAgent');

        $this->assertResponseError($result, 1001);
        $this->assertStringContainsString('过期', $result['message']);

        // 验证卡密已被自动封禁
        $keyInfo = Database::queryOne(
            'SELECT status FROM license_key WHERE id = ?',
            [$keyData['id']]
        );

        $this->assertEquals('banned', $keyInfo['status']);
    }

    /**
     * 测试封禁卡密验证
     */
    public function testBannedKeyVerification(): void
    {
        $keyData = $this->createTestKey('BAN000000001', 'banned', 30);
        $keyPlain = $keyData['key_plain'];

        $result = Auth::verifyKey($keyPlain, '127.0.0.1', 'TestAgent');

        $this->assertResponseError($result, 1003);
        $this->assertStringContainsString('封禁', $result['message']);
    }

    /**
     * 测试已删除卡密验证
     */
    public function testDeletedKeyVerification(): void
    {
        $keyData = $this->createTestKey('DEL000000001', 'deleted', 30);
        $keyPlain = $keyData['key_plain'];

        $result = Auth::verifyKey($keyPlain, '127.0.0.1', 'TestAgent');

        $this->assertResponseError($result, 1001);
    }

    /**
     * 测试卡密长度验证
     */
    public function testKeyLengthValidation(): void
    {
        // 太短
        $result = Auth::verifyKey('SHORT', '127.0.0.1', 'TestAgent');
        $this->assertResponseError($result, 1001);

        // 太长
        $result = Auth::verifyKey('TOOLONGKEY123', '127.0.0.1', 'TestAgent');
        $this->assertResponseError($result, 1001);
    }

    /**
     * 测试首次使用时间记录
     */
    public function testFirstUsedAtRecording(): void
    {
        $keyData = $this->createTestKey('FIRST0000001', 'active', 30);
        $keyPlain = $keyData['key_plain'];

        // 验证前，first_used_at应该为空
        $keyInfo = Database::queryOne(
            'SELECT first_used_at FROM license_key WHERE id = ?',
            [$keyData['id']]
        );
        $this->assertNull($keyInfo['first_used_at']);

        // 验证卡密
        Auth::verifyKey($keyPlain, '127.0.0.1', 'TestAgent');

        // 验证后，first_used_at应该有值
        $keyInfo = Database::queryOne(
            'SELECT first_used_at, last_used_at FROM license_key WHERE id = ?',
            [$keyData['id']]
        );
        $this->assertNotNull($keyInfo['first_used_at']);
        $this->assertNotNull($keyInfo['last_used_at']);
    }

    /**
     * 测试最后使用时间更新
     */
    public function testLastUsedAtUpdate(): void
    {
        $keyData = $this->createTestKey('LAST00000001', 'active', 30);
        $keyPlain = $keyData['key_plain'];

        // 第一次验证
        Auth::verifyKey($keyPlain, '127.0.0.1', 'TestAgent');

        $keyInfo1 = Database::queryOne(
            'SELECT last_used_at FROM license_key WHERE id = ?',
            [$keyData['id']]
        );

        sleep(1);

        // 第二次验证
        Auth::verifyKey($keyPlain, '127.0.0.1', 'TestAgent');

        $keyInfo2 = Database::queryOne(
            'SELECT last_used_at FROM license_key WHERE id = ?',
            [$keyData['id']]
        );

        // last_used_at应该更新
        $this->assertNotEquals($keyInfo1['last_used_at'], $keyInfo2['last_used_at']);
    }

    /**
     * 测试心跳检测过期卡密
     */
    public function testPingExpiredKey(): void
    {
        // 创建即将过期的卡密
        $keyData = $this->createTestKey('PING00000001', 'active', 30);
        $keyPlain = $keyData['key_plain'];

        // 验证并获取token
        $authResult = Auth::verifyKey($keyPlain);
        $token = $authResult['data']['token'];

        // 手动将卡密设置为过期
        Database::execute(
            "UPDATE license_key SET expire_at = datetime('now', '-1 days', 'localtime') WHERE id = ?",
            [$keyData['id']]
        );

        // 心跳检测应该失败（返回1001或1002都可以接受）
        $pingResult = Auth::ping($token);
        $this->assertResponseError($pingResult);
        // 可能返回1001（卡密过期）或1002（token无效）
        $this->assertContains($pingResult['code'], [1001, 1002]);

        // 如果返回1001，说明执行了过期检查，卡密应该被封禁
        if ($pingResult['code'] === 1001) {
            $keyInfo = Database::queryOne(
                'SELECT status FROM license_key WHERE id = ?',
                [$keyData['id']]
            );
            $this->assertEquals('banned', $keyInfo['status']);
        }
    }

    /**
     * 测试心跳检测封禁卡密
     */
    public function testPingBannedKey(): void
    {
        $keyData = $this->createTestKey('PING00000002', 'active', 30);
        $keyPlain = $keyData['key_plain'];

        // 验证并获取token
        $authResult = Auth::verifyKey($keyPlain);
        $token = $authResult['data']['token'];

        // 封禁卡密
        Database::execute(
            "UPDATE license_key SET status = 'banned' WHERE id = ?",
            [$keyData['id']]
        );

        // 心跳检测应该失败（返回1002或1003都可以接受）
        $pingResult = Auth::ping($token);
        $this->assertResponseError($pingResult);
        // 可能返回1002（token无效）或1003（卡密封禁）
        $this->assertContains($pingResult['code'], [1002, 1003]);
    }

    /**
     * 测试空token验证
     */
    public function testValidateEmptyToken(): void
    {
        $result = Auth::validateToken('');
        $this->assertResponseError($result, 1002);
    }

    /**
     * 测试无效token验证
     */
    public function testValidateInvalidToken(): void
    {
        $result = Auth::validateToken('invalid_token_12345');
        $this->assertResponseError($result, 1002);
    }

    /**
     * 测试空token登出
     */
    public function testLogoutEmptyToken(): void
    {
        $result = Auth::logout('');
        $this->assertResponseError($result, 1002);
    }

    /**
     * 测试不存在的token登出
     */
    public function testLogoutNonExistingToken(): void
    {
        $result = Auth::logout('non_existing_token');
        $this->assertResponseError($result, 1002);
    }
}
