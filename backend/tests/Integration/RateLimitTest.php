<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Auth;
use App\Database;

/**
 * 速率限制测试
 */
class RateLimitTest extends TestCase
{
    /**
     * 测试速率限制阻止
     */
    public function testRateLimitBlocking(): void
    {
        $ip = '192.168.1.100';

        // 尝试11次（超过默认限制10次）
        for ($i = 0; $i < 11; $i++) {
            $result = Auth::verifyKey('INVALID00001', $ip, 'TestAgent');
        }

        // 第11次应该被限流
        $this->assertResponseError($result, 1004);
        $this->assertStringContainsString('尝试次数过多', $result['message']);
    }

    /**
     * 测试速率限制重置
     */
    public function testRateLimitReset(): void
    {
        $ip = '192.168.1.101';

        // 第一次尝试
        $result1 = Auth::verifyKey('INVALID00002', $ip, 'TestAgent');
        $this->assertResponseError($result1, 1001);

        // 手动重置时间窗口（模拟5分钟后）
        Database::execute(
            "UPDATE rate_limit SET first_attempt_at = datetime('now', '-6 minutes', 'localtime') WHERE ip_hash = ?",
            [hash('sha256', $ip)]
        );

        // 应该可以再次尝试
        $result2 = Auth::verifyKey('INVALID00002', $ip, 'TestAgent');
        $this->assertResponseError($result2, 1001);
        $this->assertNotEquals(1004, $result2['code']); // 不应该是限流错误
    }

    /**
     * 测试不同IP独立限流
     */
    public function testRateLimitPerIP(): void
    {
        $ip1 = '192.168.1.102';
        $ip2 = '192.168.1.103';

        // IP1尝试11次
        for ($i = 0; $i < 11; $i++) {
            Auth::verifyKey('INVALID00003', $ip1, 'TestAgent');
        }

        // IP1应该被限流
        $result1 = Auth::verifyKey('INVALID00003', $ip1, 'TestAgent');
        $this->assertResponseError($result1, 1004);

        // IP2应该不受影响
        $result2 = Auth::verifyKey('INVALID00003', $ip2, 'TestAgent');
        $this->assertResponseError($result2, 1001); // 卡密无效，不是限流
    }

    /**
     * 测试成功验证后的限流计数
     */
    public function testRateLimitAfterSuccessfulAuth(): void
    {
        $ip = '192.168.1.104';

        // 创建有效卡密
        $keyData = $this->createTestKey('VALID0000001', 'active', 30);

        // 失败几次
        for ($i = 0; $i < 5; $i++) {
            Auth::verifyKey('INVALID00004', $ip, 'TestAgent');
        }

        // 成功验证
        $result = Auth::verifyKey($keyData['key_plain'], $ip, 'TestAgent');
        $this->assertResponseSuccess($result);

        // 限流计数应该继续累加（不会因为成功而重置）
        for ($i = 0; $i < 6; $i++) {
            $result = Auth::verifyKey('INVALID00004', $ip, 'TestAgent');
        }

        // 应该被限流
        $this->assertResponseError($result, 1004);
    }

    /**
     * 测试无IP时不限流
     */
    public function testNoRateLimitWithoutIP(): void
    {
        // 不传IP参数
        for ($i = 0; $i < 15; $i++) {
            $result = Auth::verifyKey('INVALID00005', null, 'TestAgent');
        }

        // 不应该被限流，只是卡密无效
        $this->assertResponseError($result, 1001);
        $this->assertNotEquals(1004, $result['code']);
    }
}
