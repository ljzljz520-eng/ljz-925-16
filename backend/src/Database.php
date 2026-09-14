<?php

namespace App;

use PDO;
use PDOException;

/**
 * 数据库连接管理类
 * 使用单例模式管理SQLite连接
 */
class Database
{
    private static ?PDO $instance = null;
    private static string $dbPath;

    /**
     * 私有构造函数，防止外部实例化
     */
    private function __construct()
    {
    }

    /**
     * 获取数据库连接实例
     *
     * @return PDO
     * @throws PDOException
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$dbPath = self::getDbPath();

            try {
                self::$instance = new PDO('sqlite:' . self::$dbPath);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                // 启用外键约束
                self::$instance->exec('PRAGMA foreign_keys = ON');

                // 提高SQLite并发读写稳定性，避免高并发下出现 database is locked
                self::$instance->exec('PRAGMA journal_mode = WAL');
                self::$instance->exec('PRAGMA busy_timeout = 5000');
                self::$instance->exec('PRAGMA synchronous = NORMAL');

                // 设置UTF-8编码
                self::$instance->exec('PRAGMA encoding = "UTF-8"');

            } catch (PDOException $e) {
                throw new PDOException('数据库连接失败: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * 获取数据库文件路径
     *
     * @return string
     */
    private static function getDbPath(): string
    {
        // 优先使用环境变量
        $dbPath = getenv('DB_PATH');

        if ($dbPath && file_exists(dirname($dbPath))) {
            return $dbPath;
        }

        // 默认路径
        $defaultPath = dirname(__DIR__) . '/data/app.db';

        // 确保data目录存在
        $dataDir = dirname($defaultPath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        return $defaultPath;
    }

    /**
     * 执行查询并返回所有结果
     *
     * @param string $sql SQL语句
     * @param array $params 参数
     * @return array
     */
    public static function query(string $sql, array $params = []): array
    {
        $db = self::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 执行查询并返回单行结果
     *
     * @param string $sql SQL语句
     * @param array $params 参数
     * @return array|null
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $db = self::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 执行插入/更新/删除操作
     *
     * @param string $sql SQL语句
     * @param array $params 参数
     * @return int 影响的行数
     */
    public static function execute(string $sql, array $params = []): int
    {
        $db = self::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 获取最后插入的ID
     *
     * @return string
     */
    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }

    /**
     * 开始事务
     *
     * @return bool
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    /**
     * 提交事务
     *
     * @return bool
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    /**
     * 回滚事务
     *
     * @return bool
     */
    public static function rollBack(): bool
    {
        return self::getInstance()->rollBack();
    }

    /**
     * 初始化数据库（执行迁移脚本）
     *
     * @return bool
     */
    public static function initialize(): bool
    {
        try {
            $migrationsDir = dirname(__DIR__) . '/migrations';

            // 执行init.sql
            $initSql = file_get_contents($migrationsDir . '/init.sql');
            if ($initSql) {
                self::getInstance()->exec($initSql);
            }

            // 兼容旧库：早期版本未包含 license_key.key_encrypted 字段
            $licenseKeyColumns = self::query("PRAGMA table_info(license_key)");
            $hasEncrypted = false;
            foreach ($licenseKeyColumns as $col) {
                if (($col['name'] ?? null) === 'key_encrypted') {
                    $hasEncrypted = true;
                    break;
                }
            }
            if (!$hasEncrypted) {
                self::getInstance()->exec('ALTER TABLE license_key ADD COLUMN key_encrypted TEXT');
            }

            // 检查是否需要执行seed.sql（如果admin_user表为空且不是测试环境）
            $isTestEnv = getenv('APP_ENV') === 'testing';
            if (!$isTestEnv) {
                $adminCount = self::queryOne('SELECT COUNT(*) as count FROM admin_user');
                if ($adminCount['count'] == 0) {
                    $seedSql = file_get_contents($migrationsDir . '/seed.sql');
                    if ($seedSql) {
                        self::getInstance()->exec($seedSql);
                    }
                }
            }

            return true;
        } catch (PDOException $e) {
            error_log('数据库初始化失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 清理过期数据
     *
     * @return void
     */
    public static function cleanup(): void
    {
        try {
            // 清理过期token
            self::execute(
                "DELETE FROM access_token WHERE expire_at < datetime('now', 'localtime')"
            );

            // 清理过期的防爆破记录（超过1小时）
            self::execute(
                "DELETE FROM rate_limit WHERE last_attempt_at < datetime('now', '-1 hours', 'localtime')"
            );

        } catch (PDOException $e) {
            error_log('数据清理失败: ' . $e->getMessage());
        }
    }
}
