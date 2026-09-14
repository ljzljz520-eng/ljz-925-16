<?php

namespace App;

/**
 * 配置管理类
 * 从system_config表读取配置，带缓存机制
 */
class Config
{
    private static array $cache = [];
    private static bool $loaded = false;

    /**
     * 获取配置值
     *
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        if (!self::$loaded) {
            self::loadAll();
        }

        return self::$cache[$key] ?? $default;
    }

    /**
     * 设置配置值
     *
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @return bool
     */
    public static function set(string $key, $value): bool
    {
        try {
            $exists = Database::queryOne(
                'SELECT key FROM system_config WHERE key = ?',
                [$key]
            );

            if ($exists) {
                Database::execute(
                    "UPDATE system_config SET value = ?, updated_at = datetime('now', 'localtime') WHERE key = ?",
                    [$value, $key]
                );
            } else {
                Database::execute(
                    "INSERT INTO system_config (key, value, updated_at) VALUES (?, ?, datetime('now', 'localtime'))",
                    [$key, $value]
                );
            }

            // 更新缓存
            self::$cache[$key] = $value;

            return true;
        } catch (\Exception $e) {
            error_log('配置设置失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 加载所有配置到缓存
     *
     * @return void
     */
    private static function loadAll(): void
    {
        try {
            $configs = Database::query('SELECT key, value FROM system_config');

            foreach ($configs as $config) {
                self::$cache[$config['key']] = $config['value'];
            }

            self::$loaded = true;
        } catch (\Exception $e) {
            error_log('配置加载失败: ' . $e->getMessage());
            self::$loaded = true; // 标记为已加载，避免重复尝试
        }
    }

    /**
     * 清除缓存
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$loaded = false;
    }

    /**
     * 获取服务器密钥（用于HMAC）
     *
     * @return string
     */
    public static function getServerSecret(): string
    {
        $secret = self::get('server_secret');

        if (!$secret) {
            // 如果不存在，生成一个新的
            $secret = bin2hex(random_bytes(32));
            self::set('server_secret', $secret);
        }

        return $secret;
    }

    /**
     * 获取Token过期时间（小时）
     *
     * @return int
     */
    public static function getTokenExpireHours(): int
    {
        return (int) self::get('token_expire_hours', 12);
    }

    /**
     * 获取防爆破配置
     *
     * @return array ['max_attempts' => int, 'window_seconds' => int]
     */
    public static function getRateLimitConfig(): array
    {
        return [
            'max_attempts' => (int) self::get('max_attempts_per_ip', 10),
            'window_seconds' => (int) self::get('rate_limit_window_seconds', 300)
        ];
    }

    /**
     * 获取所有配置
     *
     * @return array
     */
    public static function getAll(): array
    {
        if (!self::$loaded) {
            self::loadAll();
        }

        return self::$cache;
    }
}
