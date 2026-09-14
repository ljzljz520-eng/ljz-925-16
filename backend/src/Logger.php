<?php

namespace App;

use App\Crypto;

/**
 * 日志记录类
 * 记录卡密使用日志和管理员操作日志
 */
class Logger
{
    /**
     * 记录卡密使用日志
     *
     * @param int|null $keyId 卡密ID
     * @param string $action 操作类型 (verify/ping/access)
     * @param string $result 结果 (success/failed)
     * @param string|null $reason 原因/说明
     * @param string|null $ip IP地址
     * @param string|null $userAgent User Agent
     * @return bool
     */
    public static function logKeyUsage(
        ?int $keyId,
        string $action,
        string $result,
        ?string $reason = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): bool {
        try {
            $ip = $ip ?? self::getClientIp();
            $userAgent = $userAgent ?? self::getUserAgent();

            Database::execute(
                "INSERT INTO key_usage_log (key_id, action, ip, user_agent, result, reason, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, datetime('now', 'localtime'))",
                [$keyId, $action, $ip, $userAgent, $result, $reason]
            );

            return true;
        } catch (\Exception $e) {
            error_log('记录卡密使用日志失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 记录管理员操作日志
     *
     * @param int $adminId 管理员ID
     * @param string $action 操作类型
     * @param string|null $details 详细信息
     * @param string|null $ip IP地址
     * @return bool
     */
    public static function logAdminOp(
        int $adminId,
        string $action,
        ?string $details = null,
        ?string $ip = null
    ): bool {
        try {
            $ip = $ip ?? self::getClientIp();

            Database::execute(
                "INSERT INTO admin_op_log (admin_id, action, details, ip, created_at)
                 VALUES (?, ?, ?, ?, datetime('now', 'localtime'))",
                [$adminId, $action, $details, $ip]
            );

            return true;
        } catch (\Exception $e) {
            error_log('记录管理员操作日志失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取卡密使用日志
     *
     * @param array $filters 筛选条件 ['key_id' => int, 'action' => string, 'result' => string, 'start_date' => string, 'end_date' => string]
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public static function getKeyUsageLogs(array $filters = [], int $page = 1, int $pageSize = 50): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['key_id'])) {
            $where[] = 'key_id = ?';
            $params[] = $filters['key_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['result'])) {
            $where[] = 'result = ?';
            $params[] = $filters['result'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['end_date'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // 过滤掉ping（心跳检测）日志
        if (!empty($whereClause)) {
            $whereClause .= " AND l.action != 'ping'";
        } else {
            $whereClause = "WHERE l.action != 'ping'";
        }

        // 获取总数
        $countSql = "SELECT COUNT(*) as total FROM key_usage_log l $whereClause";
        $totalResult = Database::queryOne($countSql, $params);
        $total = $totalResult['total'] ?? 0;

        // 获取分页数据
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT
                    l.*,
                    k.key_encrypted
                FROM key_usage_log l
                LEFT JOIN license_key k ON l.key_id = k.id
                $whereClause
                ORDER BY l.created_at DESC
                LIMIT ? OFFSET ?";
        $params[] = $pageSize;
        $params[] = $offset;

        $logs = Database::query($sql, $params);

        // 解密卡密
        foreach ($logs as &$log) {
            if (!empty($log['key_encrypted'])) {
                $log['key_plain'] = Crypto::decrypt($log['key_encrypted']);
            } else {
                $log['key_plain'] = null;
            }
            // 优化action描述
            $log['action_label'] = self::getActionLabel($log['action']);
        }

        return [
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'data' => $logs
        ];
    }

    /**
     * 获取管理员操作日志
     *
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public static function getAdminOpLogs(array $filters = [], int $page = 1, int $pageSize = 50): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['admin_id'])) {
            $where[] = 'admin_id = ?';
            $params[] = $filters['admin_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['end_date'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // 获取总数
        $countSql = "SELECT COUNT(*) as total FROM admin_op_log $whereClause";
        $totalResult = Database::queryOne($countSql, $params);
        $total = $totalResult['total'] ?? 0;

        // 获取分页数据
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT a.*, u.username
                FROM admin_op_log a
                LEFT JOIN admin_user u ON a.admin_id = u.id
                $whereClause
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?";
        $params[] = $pageSize;
        $params[] = $offset;

        $logs = Database::query($sql, $params);

        return [
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'data' => $logs
        ];
    }

    /**
     * 获取操作类型的中文标签
     *
     * @param string $action 操作类型
     * @return string
     */
    private static function getActionLabel(string $action): string
    {
        $labels = [
            'verify' => '卡密验证',
            'ping' => '状态检测（心跳）',
            'access' => '功能访问'
        ];

        return $labels[$action] ?? $action;
    }

    /**
     * 获取客户端IP地址
     *
     * @return string
     */
    private static function getClientIp(): string
    {
        $ip = '';

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return trim($ip);
    }

    /**
     * 获取User Agent
     *
     * @return string
     */
    private static function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * 计算IP哈希（用于隐私保护）
     *
     * @param string $ip IP地址
     * @return string
     */
    public static function hashIp(string $ip): string
    {
        return hash('sha256', $ip . Config::getServerSecret());
    }

    /**
     * 计算UA哈希
     *
     * @param string $ua User Agent
     * @return string
     */
    public static function hashUa(string $ua): string
    {
        return hash('sha256', $ua . Config::getServerSecret());
    }
}
