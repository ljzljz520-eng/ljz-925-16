<?php

namespace App;

/**
 * Token管理类
 * 负责生成、验证和撤销访问令牌
 */
class TokenManager
{
    /**
     * 生成访问令牌
     *
     * @param int $keyId 卡密ID
     * @param string|null $ip IP地址
     * @param string|null $ua User Agent
     * @return array ['token' => string, 'expire_at' => string]
     */
    public static function generateToken(int $keyId, ?string $ip = null, ?string $ua = null): array
    {
        // 生成随机token（64字符）
        $token = bin2hex(random_bytes(32));

        // 计算token哈希
        $tokenHash = self::hashToken($token);

        // 计算过期时间
        $expireHours = Config::getTokenExpireHours();
        $expireAt = date('Y-m-d H:i:s', strtotime("+{$expireHours} hours"));

        // 可选：计算IP和UA哈希
        $ipHash = $ip ? Logger::hashIp($ip) : null;
        $uaHash = $ua ? Logger::hashUa($ua) : null;

        // 存储到数据库
        Database::execute(
            "INSERT INTO access_token (token_hash, key_id, expire_at, created_at, last_seen_at, ip_hash, ua_hash)
             VALUES (?, ?, ?, datetime('now', 'localtime'), datetime('now', 'localtime'), ?, ?)",
            [$tokenHash, $keyId, $expireAt, $ipHash, $uaHash]
        );

        return [
            'token' => $token,
            'expire_at' => $expireAt
        ];
    }

    /**
     * 验证令牌
     *
     * @param string $token 令牌
     * @return array|null 返回token信息，失败返回null
     */
    public static function validateToken(string $token): ?array
    {
        $tokenHash = self::hashToken($token);

        // 查询token
        $tokenInfo = Database::queryOne(
            "SELECT t.*, k.status as key_status, k.expire_at as key_expire_at
             FROM access_token t
             LEFT JOIN license_key k ON t.key_id = k.id
             WHERE t.token_hash = ?",
            [$tokenHash]
        );

        if (!$tokenInfo) {
            return null;
        }

        // 检查token是否过期
        if (strtotime($tokenInfo['expire_at']) < time()) {
            return null;
        }

        // 检查关联的卡密状态
        if ($tokenInfo['key_status'] !== 'active') {
            return null;
        }

        // 检查卡密是否过期
        if (strtotime($tokenInfo['key_expire_at']) < time()) {
            return null;
        }

        // 更新最后访问时间
        Database::execute(
            "UPDATE access_token SET last_seen_at = datetime('now', 'localtime') WHERE token_hash = ?",
            [$tokenHash]
        );

        return $tokenInfo;
    }

    /**
     * 撤销令牌
     *
     * @param string $token 令牌
     * @return bool
     */
    public static function revokeToken(string $token): bool
    {
        $tokenHash = self::hashToken($token);

        $affected = Database::execute(
            'DELETE FROM access_token WHERE token_hash = ?',
            [$tokenHash]
        );

        return $affected > 0;
    }

    /**
     * 撤销指定卡密的所有令牌
     *
     * @param int $keyId 卡密ID
     * @return int 撤销的令牌数量
     */
    public static function revokeKeyTokens(int $keyId): int
    {
        return Database::execute(
            'DELETE FROM access_token WHERE key_id = ?',
            [$keyId]
        );
    }

    /**
     * 清理过期令牌
     *
     * @return int 清理的令牌数量
     */
    public static function cleanExpiredTokens(): int
    {
        return Database::execute(
            "DELETE FROM access_token WHERE expire_at < datetime('now', 'localtime')"
        );
    }

    /**
     * 获取指定卡密的活跃令牌数量
     *
     * @param int $keyId 卡密ID
     * @return int
     */
    public static function getActiveTokenCount(int $keyId): int
    {
        $result = Database::queryOne(
            "SELECT COUNT(*) as count FROM access_token
             WHERE key_id = ? AND expire_at > datetime('now', 'localtime')",
            [$keyId]
        );

        return $result['count'] ?? 0;
    }

    /**
     * 计算token哈希
     *
     * @param string $token 原始token
     * @return string
     */
    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * 从请求头中提取token
     *
     * @return string|null
     */
    public static function extractTokenFromRequest(): ?string
    {
        // 从Authorization头提取
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // 也可以从查询参数提取（不推荐，但作为备选）
        return $_GET['token'] ?? null;
    }

    /**
     * 获取token统计信息
     *
     * @return array
     */
    public static function getStats(): array
    {
        $total = Database::queryOne('SELECT COUNT(*) as count FROM access_token');
        $active = Database::queryOne(
            "SELECT COUNT(*) as count FROM access_token WHERE expire_at > datetime('now', 'localtime')"
        );
        $expired = Database::queryOne(
            "SELECT COUNT(*) as count FROM access_token WHERE expire_at <= datetime('now', 'localtime')"
        );

        return [
            'total' => $total['count'] ?? 0,
            'active' => $active['count'] ?? 0,
            'expired' => $expired['count'] ?? 0
        ];
    }
}
