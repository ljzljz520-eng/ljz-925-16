<?php

namespace App;

/**
 * 认证核心类
 * 处理卡密验证、token验证和心跳检测
 */
class Auth
{
    /**
     * 验证卡密
     *
     * @param string $keyPlain 明文卡密
     * @param string|null $ip IP地址
     * @param string|null $ua User Agent
     * @return array Response格式
     */
    public static function verifyKey(string $keyPlain, ?string $ip = null, ?string $ua = null): array
    {
        // 输入验证
        $validation = Validator::validate(
            ['key' => $keyPlain],
            ['key' => 'required|length:12']
        );

        if (!$validation['valid']) {
            $error = reset($validation['errors']);
            Logger::logKeyUsage(null, 'verify', 'failed', $error, $ip, $ua);
            return Response::error(1001, $error);
        }

        // 防爆破检查
        $rateLimitResult = self::checkRateLimit($ip);
        if (!$rateLimitResult['allowed']) {
            Logger::logKeyUsage(null, 'verify', 'failed', '尝试次数过多', $ip, $ua);
            return Response::error(1004, '尝试次数过多，请稍后再试');
        }

        // 计算卡密哈希
        $keyHash = self::hashKey($keyPlain);

        // 查询数据库
        $keyInfo = Database::queryOne(
            'SELECT * FROM license_key WHERE key_hash = ?',
            [$keyHash]
        );

        if (!$keyInfo) {
            Logger::logKeyUsage(null, 'verify', 'failed', '卡密无效', $ip, $ua);
            return Response::error(1001, '卡密无效或已失效');
        }

        // 检查状态
        if ($keyInfo['status'] === 'banned') {
            Logger::logKeyUsage($keyInfo['id'], 'verify', 'failed', '卡密已被封禁', $ip, $ua);
            return Response::error(1003, '卡密已被封禁');
        }

        if ($keyInfo['status'] === 'deleted') {
            Logger::logKeyUsage($keyInfo['id'], 'verify', 'failed', '卡密已被删除', $ip, $ua);
            return Response::error(1001, '卡密无效或已失效');
        }

        // 检查过期时间
        if (strtotime($keyInfo['expire_at']) < time()) {
            // 自动封禁过期卡密
            Database::execute(
                "UPDATE license_key SET status = 'banned' WHERE id = ?",
                [$keyInfo['id']]
            );
            Logger::logKeyUsage($keyInfo['id'], 'verify', 'failed', '卡密已过期，已自动封禁', $ip, $ua);
            return Response::error(1001, '卡密已过期');
        }

        // 更新首次使用时间和最后使用时间
        if (!$keyInfo['first_used_at']) {
            Database::execute(
                "UPDATE license_key SET first_used_at = datetime('now', 'localtime'), last_used_at = datetime('now', 'localtime') WHERE id = ?",
                [$keyInfo['id']]
            );
        } else {
            Database::execute(
                "UPDATE license_key SET last_used_at = datetime('now', 'localtime') WHERE id = ?",
                [$keyInfo['id']]
            );
        }

        // 生成token
        $tokenData = TokenManager::generateToken($keyInfo['id'], $ip, $ua);

        // 记录成功日志
        Logger::logKeyUsage($keyInfo['id'], 'verify', 'success', '卡密验证成功', $ip, $ua);

        return Response::success([
            'token' => $tokenData['token'],
            'expire_at' => $tokenData['expire_at']
        ], '验证成功');
    }

    /**
     * 验证token
     *
     * @param string $token 令牌
     * @return array Response格式
     */
    public static function validateToken(string $token): array
    {
        if (empty($token)) {
            return Response::error(1002, '请先验证卡密');
        }

        $tokenInfo = TokenManager::validateToken($token);

        if (!$tokenInfo) {
            return Response::error(1002, '登录已过期，请重新验证');
        }

        return Response::success([
            'key_id' => $tokenInfo['key_id'],
            'expire_at' => $tokenInfo['expire_at']
        ]);
    }

    /**
     * 心跳检测
     *
     * @param string $token 令牌
     * @return array Response格式
     */
    public static function ping(string $token): array
    {
        $tokenInfo = TokenManager::validateToken($token);

        if (!$tokenInfo) {
            Logger::logKeyUsage(null, 'ping', 'failed', 'Token无效或已过期');
            return Response::error(1002, '登录已过期，请重新验证');
        }

        // 检查卡密状态
        if ($tokenInfo['key_status'] !== 'active') {
            Logger::logKeyUsage($tokenInfo['key_id'], 'ping', 'failed', '卡密已被封禁或删除');
            return Response::error(1003, '卡密已被封禁或删除');
        }

        // 检查卡密是否过期
        if (strtotime($tokenInfo['key_expire_at']) < time()) {
            // 自动封禁过期卡密
            Database::execute(
                "UPDATE license_key SET status = 'banned' WHERE id = ?",
                [$tokenInfo['key_id']]
            );
            Logger::logKeyUsage($tokenInfo['key_id'], 'ping', 'failed', '卡密已过期，已自动封禁');
            return Response::error(1001, '卡密已过期');
        }

        // 记录成功日志
        Logger::logKeyUsage($tokenInfo['key_id'], 'ping', 'success', '心跳检测正常');

        return Response::success([
            'valid' => true,
            'key_status' => $tokenInfo['key_status']
        ]);
    }

    /**
     * 登出
     *
     * @param string $token 令牌
     * @return array Response格式
     */
    public static function logout(string $token): array
    {
        if (empty($token)) {
            return Response::error(1002, '无效的令牌');
        }

        $revoked = TokenManager::revokeToken($token);

        if ($revoked) {
            return Response::success(null, '已退出登录');
        } else {
            return Response::error(1002, '令牌不存在或已失效');
        }
    }

    /**
     * 计算卡密哈希
     *
     * @param string $keyPlain 明文卡密
     * @return string
     */
    public static function hashKey(string $keyPlain): string
    {
        $secret = Config::getServerSecret();
        return hash_hmac('sha256', $keyPlain, $secret);
    }

    /**
     * 防爆破检查
     *
     * @param string|null $ip IP地址
     * @return array ['allowed' => bool, 'attempts' => int, 'reset_at' => string]
     */
    private static function checkRateLimit(?string $ip): array
    {
        if (!$ip) {
            return ['allowed' => true, 'attempts' => 0];
        }

        $ipHash = Logger::hashIp($ip);
        $config = Config::getRateLimitConfig();

        // 查询当前IP的尝试记录
        $record = Database::queryOne(
            'SELECT * FROM rate_limit WHERE ip_hash = ?',
            [$ipHash]
        );

        if (!$record) {
            Database::execute(
                "INSERT OR IGNORE INTO rate_limit (ip_hash, attempt_count, first_attempt_at, last_attempt_at)
                 VALUES (?, 1, datetime('now', 'localtime'), datetime('now', 'localtime'))",
                [$ipHash]
            );

            $record = Database::queryOne(
                'SELECT * FROM rate_limit WHERE ip_hash = ?',
                [$ipHash]
            );

            return ['allowed' => true, 'attempts' => (int) ($record['attempt_count'] ?? 1)];
        }

        // 检查时间窗口
        $firstAttemptTime = strtotime($record['first_attempt_at']);
        $windowExpireTime = $firstAttemptTime + $config['window_seconds'];

        if (time() > $windowExpireTime) {
            // 时间窗口已过，重置计数
            Database::execute(
                "UPDATE rate_limit SET attempt_count = 1, first_attempt_at = datetime('now', 'localtime'), last_attempt_at = datetime('now', 'localtime') WHERE ip_hash = ?",
                [$ipHash]
            );
            return ['allowed' => true, 'attempts' => 1];
        }

        // 在时间窗口内，检查尝试次数
        if ($record['attempt_count'] >= $config['max_attempts']) {
            return [
                'allowed' => false,
                'attempts' => $record['attempt_count'],
                'reset_at' => date('Y-m-d H:i:s', $windowExpireTime)
            ];
        }

        // 增加尝试次数
        Database::execute(
            "UPDATE rate_limit SET attempt_count = attempt_count + 1, last_attempt_at = datetime('now', 'localtime') WHERE ip_hash = ?",
            [$ipHash]
        );

        return ['allowed' => true, 'attempts' => $record['attempt_count'] + 1];
    }

    /**
     * 中间件：验证token
     * 用于保护需要认证的API路由
     *
     * @return array|null 返回null表示验证通过，返回Response数组表示验证失败
     */
    public static function middleware(): ?array
    {
        $token = TokenManager::extractTokenFromRequest();

        if (!$token) {
            return Response::error(1002, '请先验证卡密');
        }

        $result = self::validateToken($token);

        if ($result['code'] !== 0) {
            return $result;
        }

        // 将key_id存储到全局变量，供后续使用
        $GLOBALS['auth_key_id'] = $result['data']['key_id'];

        return null; // 验证通过
    }

    /**
     * 获取当前认证的卡密ID
     *
     * @return int|null
     */
    public static function getKeyId(): ?int
    {
        return $GLOBALS['auth_key_id'] ?? null;
    }
}
