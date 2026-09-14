<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Config;
use App\Database;

/**
 * 配置管理测试
 */
class ConfigTest extends TestCase
{
    /**
     * 测试获取配置 - 存在的配置
     */
    public function testGetExistingConfig(): void
    {
        // 插入测试配置
        Database::execute(
            "INSERT INTO system_config (key, value, updated_at) VALUES (?, ?, datetime('now', 'localtime'))",
            ['test_key', 'test_value']
        );

        $value = Config::get('test_key');
        $this->assertEquals('test_value', $value);
    }

    /**
     * 测试获取配置 - 不存在的配置返回默认值
     */
    public function testGetNonExistingConfigReturnsDefault(): void
    {
        $value = Config::get('non_existing_key', 'default_value');
        $this->assertEquals('default_value', $value);
    }

    /**
     * 测试设置配置 - 新配置
     */
    public function testSetNewConfig(): void
    {
        $result = Config::set('new_key', 'new_value');
        $this->assertTrue($result);

        $value = Config::get('new_key');
        $this->assertEquals('new_value', $value);
    }

    /**
     * 测试设置配置 - 更新现有配置
     */
    public function testSetExistingConfig(): void
    {
        Config::set('update_key', 'old_value');
        Config::set('update_key', 'new_value');

        $value = Config::get('update_key');
        $this->assertEquals('new_value', $value);
    }

    /**
     * 测试配置缓存
     */
    public function testConfigCaching(): void
    {
        // 第一次获取会从数据库加载
        Config::set('cache_test', 'value1');
        $value1 = Config::get('cache_test');

        // 直接修改数据库（绕过Config类）
        Database::execute(
            "UPDATE system_config SET value = ? WHERE key = ?",
            ['value2', 'cache_test']
        );

        // 第二次获取应该从缓存读取，仍然是旧值
        $value2 = Config::get('cache_test');
        $this->assertEquals($value1, $value2);

        // 清除缓存后应该读取新值
        Config::clearCache();
        $value3 = Config::get('cache_test');
        $this->assertEquals('value2', $value3);
    }

    /**
     * 测试获取服务器密钥
     */
    public function testGetServerSecret(): void
    {
        $secret = Config::getServerSecret();

        $this->assertNotEmpty($secret);
        // 密钥应该是32字节的hex编码（64个字符）或UUID格式（36个字符）
        $this->assertGreaterThanOrEqual(32, strlen($secret));

        // 再次获取应该返回相同的密钥
        $secret2 = Config::getServerSecret();
        $this->assertEquals($secret, $secret2);
    }

    /**
     * 测试获取Token过期时间
     */
    public function testGetTokenExpireHours(): void
    {
        // 默认值
        $hours = Config::getTokenExpireHours();
        $this->assertEquals(12, $hours);

        // 设置自定义值
        Config::set('token_expire_hours', '24');
        Config::clearCache();

        $hours = Config::getTokenExpireHours();
        $this->assertEquals(24, $hours);
    }

    /**
     * 测试获取速率限制配置
     */
    public function testGetRateLimitConfig(): void
    {
        $config = Config::getRateLimitConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('max_attempts', $config);
        $this->assertArrayHasKey('window_seconds', $config);

        // 默认值
        $this->assertEquals(10, $config['max_attempts']);
        $this->assertEquals(300, $config['window_seconds']);
    }

    /**
     * 测试获取所有配置
     */
    public function testGetAllConfigs(): void
    {
        Config::set('key1', 'value1');
        Config::set('key2', 'value2');
        Config::clearCache();

        $all = Config::getAll();

        $this->assertIsArray($all);
        $this->assertArrayHasKey('key1', $all);
        $this->assertArrayHasKey('key2', $all);
        $this->assertEquals('value1', $all['key1']);
        $this->assertEquals('value2', $all['key2']);
    }
}
