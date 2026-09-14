<?php

namespace App;

/**
 * 卡密管理类
 * 负责生成、封禁、解封、删除卡密
 */
class KeyManager
{
    /**
     * 批量生成卡密
     *
     * @param string $prefix 前缀
     * @param int $count 数量
     * @param int $expireDays 有效期（天）
     * @param int $adminId 管理员ID
     * @return array ['keys' => array, 'batch_id' => int]
     */
    public static function generateKeys(string $prefix, int $count, int $expireDays, int $adminId): array
    {
        $keys = [];

        try {
            Database::beginTransaction();

            // 创建批次记录
            Database::execute(
                "INSERT INTO key_batch (prefix, count, expire_days, created_by, created_at)
                 VALUES (?, ?, ?, ?, datetime('now', 'localtime'))",
                [$prefix, $count, $expireDays, $adminId]
            );

            $batchId = (int) Database::lastInsertId();

            // 计算过期时间
            $expireAt = date('Y-m-d H:i:s', strtotime("+{$expireDays} days"));

            // 生成卡密
            for ($i = 0; $i < $count; $i++) {
                $keyPlain = self::generateKey($prefix, 12);
                $keyHash = Auth::hashKey($keyPlain);
                $keyEncrypted = Crypto::encrypt($keyPlain);

                // 存储到数据库（同时存储哈希和加密值）
                Database::execute(
                    "INSERT INTO license_key (batch_id, key_hash, key_encrypted, status, expire_at, created_at)
                     VALUES (?, ?, ?, 'active', ?, datetime('now', 'localtime'))",
                    [$batchId, $keyHash, $keyEncrypted, $expireAt]
                );

                $keys[] = $keyPlain;
            }

            Database::commit();

            // 记录管理员操作日志
            Logger::logAdminOp(
                $adminId,
                'generate_keys',
                "生成{$count}个卡密，前缀：{$prefix}，有效期：{$expireDays}天"
            );

            return [
                'keys' => $keys,
                'batch_id' => $batchId
            ];

        } catch (\Exception $e) {
            Database::rollBack();
            error_log('生成卡密失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 生成单个卡密
     *
     * @param string $prefix 前缀
     * @param int $length 总长度
     * @return string
     */
    private static function generateKey(string $prefix, int $length): string
    {
        // 去除易混淆字符
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $prefixLen = strlen($prefix);
        $randomLen = $length - $prefixLen;

        $random = '';
        for ($i = 0; $i < $randomLen; $i++) {
            $random .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $prefix . $random;
    }

    /**
     * 批量封禁卡密
     *
     * @param array $keyIds 卡密ID数组
     * @param int $adminId 管理员ID
     * @return int 影响的行数
     */
    public static function banKeys(array $keyIds, int $adminId): int
    {
        if (empty($keyIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($keyIds), '?'));

        $affected = Database::execute(
            "UPDATE license_key SET status = 'banned' WHERE id IN ($placeholders)",
            $keyIds
        );

        // 撤销这些卡密的所有token
        foreach ($keyIds as $keyId) {
            TokenManager::revokeKeyTokens($keyId);
        }

        // 记录管理员操作日志
        Logger::logAdminOp(
            $adminId,
            'ban_keys',
            "封禁{$affected}个卡密，ID：" . implode(',', $keyIds)
        );

        return $affected;
    }

    /**
     * 批量解封卡密
     *
     * @param array $keyIds 卡密ID数组
     * @param int $adminId 管理员ID
     * @return int 影响的行数
     */
    public static function unbanKeys(array $keyIds, int $adminId): int
    {
        if (empty($keyIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($keyIds), '?'));

        $affected = Database::execute(
            "UPDATE license_key SET status = 'active' WHERE id IN ($placeholders) AND status = 'banned'",
            $keyIds
        );

        // 记录管理员操作日志
        Logger::logAdminOp(
            $adminId,
            'unban_keys',
            "解封{$affected}个卡密，ID：" . implode(',', $keyIds)
        );

        return $affected;
    }

    /**
     * 批量删除卡密（逻辑删除）
     *
     * @param array $keyIds 卡密ID数组
     * @param int $adminId 管理员ID
     * @return int 影响的行数
     */
    public static function deleteKeys(array $keyIds, int $adminId): int
    {
        if (empty($keyIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($keyIds), '?'));

        $affected = Database::execute(
            "UPDATE license_key SET status = 'deleted' WHERE id IN ($placeholders)",
            $keyIds
        );

        // 撤销这些卡密的所有token
        foreach ($keyIds as $keyId) {
            TokenManager::revokeKeyTokens($keyId);
        }

        // 记录管理员操作日志
        Logger::logAdminOp(
            $adminId,
            'delete_keys',
            "删除{$affected}个卡密，ID：" . implode(',', $keyIds)
        );

        return $affected;
    }

    /**
     * 修改卡密有效期
     *
     * @param array $keyIds 卡密ID数组
     * @param int $expireDays 有效期（天）
     * @param int $adminId 管理员ID
     * @return int 影响的行数
     */
    public static function updateExpire(array $keyIds, int $expireDays, int $adminId): int
    {
        if (empty($keyIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($keyIds), '?'));
        $expireAt = date('Y-m-d H:i:s', strtotime("+{$expireDays} days"));

        $params = array_merge([$expireAt], $keyIds);

        $affected = Database::execute(
            "UPDATE license_key SET expire_at = ? WHERE id IN ($placeholders)",
            $params
        );

        // 记录管理员操作日志
        Logger::logAdminOp(
            $adminId,
            'update_expire',
            "修改{$affected}个卡密的有效期为{$expireDays}天，ID：" . implode(',', $keyIds)
        );

        return $affected;
    }

    /**
     * 导出卡密（CSV格式）
     *
     * @param int $batchId 批次ID
     * @return string CSV内容
     */
    public static function exportKeys(int $batchId): string
    {
        $keys = Database::query(
            'SELECT id, key_encrypted, status, expire_at, created_at, first_used_at, last_used_at, nickname
             FROM license_key
             WHERE batch_id = ?
             ORDER BY id ASC',
            [$batchId]
        );

        $csv = "卡密,ID,状态,过期时间,创建时间,首次使用,最后使用,昵称\n";

        foreach ($keys as $key) {
            $keyPlain = !empty($key['key_encrypted']) ? Crypto::decrypt($key['key_encrypted']) : '无法解密';
            $csv .= sprintf(
                "%s,%d,%s,%s,%s,%s,%s,%s\n",
                $keyPlain,
                $key['id'],
                self::getStatusLabel($key['status']),
                $key['expire_at'],
                $key['created_at'],
                $key['first_used_at'] ?? '',
                $key['last_used_at'] ?? '',
                $key['nickname'] ?? ''
            );
        }

        return $csv;
    }

    /**
     * 导出所有卡密（CSV格式）
     *
     * @param array $filters 筛选条件
     * @return string CSV内容
     */
    public static function exportAllKeys(array $filters = []): string
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $keys = Database::query(
            "SELECT id, key_encrypted, batch_id, status, expire_at, created_at, first_used_at, last_used_at, nickname
             FROM license_key $whereClause
             ORDER BY created_at DESC",
            $params
        );

        $csv = "卡密,ID,批次ID,状态,过期时间,创建时间,首次使用,最后使用,昵称\n";

        foreach ($keys as $key) {
            $keyPlain = !empty($key['key_encrypted']) ? Crypto::decrypt($key['key_encrypted']) : '无法解密';
            $csv .= sprintf(
                "%s,%d,%d,%s,%s,%s,%s,%s,%s\n",
                $keyPlain,
                $key['id'],
                $key['batch_id'],
                self::getStatusLabel($key['status']),
                $key['expire_at'],
                $key['created_at'],
                $key['first_used_at'] ?? '',
                $key['last_used_at'] ?? '',
                $key['nickname'] ?? ''
            );
        }

        return $csv;
    }

    /**
     * 获取卡密统计信息
     *
     * @return array
     */
    public static function getKeyStats(): array
    {
        // 总数（包括deleted）
        $total = Database::queryOne('SELECT COUNT(*) as count FROM license_key');

        // 可用：status='active' 且未过期且未使用
        $active = Database::queryOne("SELECT COUNT(*) as count FROM license_key
            WHERE status = 'active'
            AND expire_at > datetime('now', 'localtime')
            AND first_used_at IS NULL");

        // 已使用：status='active' 且已使用过（不管是否过期）
        $used = Database::queryOne("SELECT COUNT(*) as count FROM license_key
            WHERE status = 'active'
            AND first_used_at IS NOT NULL");

        // 已封禁：status='banned'
        $banned = Database::queryOne("SELECT COUNT(*) as count FROM license_key WHERE status = 'banned'");

        // 已过期：status='active' 且已过期且未使用
        $expired = Database::queryOne("SELECT COUNT(*) as count FROM license_key
            WHERE status = 'active'
            AND expire_at <= datetime('now', 'localtime')
            AND first_used_at IS NULL");

        $todayNew = Database::queryOne("SELECT COUNT(*) as count FROM license_key WHERE DATE(created_at) = DATE('now', 'localtime')");
        $todayUsed = Database::queryOne("SELECT COUNT(*) as count FROM license_key WHERE DATE(first_used_at) = DATE('now', 'localtime')");

        return [
            'total' => $total['count'] ?? 0,
            'active' => $active['count'] ?? 0,
            'used' => $used['count'] ?? 0,
            'banned' => $banned['count'] ?? 0,
            'expired' => $expired['count'] ?? 0,
            'today_new' => $todayNew['count'] ?? 0,
            'today_used' => $todayUsed['count'] ?? 0
        ];
    }

    /**
     * 检查并封禁过期卡密
     *
     * @return int 封禁的数量
     */
    public static function checkExpiredKeys(): int
    {
        // 注意：这里不改变status，只是标记为过期
        // 实际验证时会检查expire_at字段
        return 0;
    }

    /**
     * 获取卡密列表
     *
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public static function getKeys(array $filters = [], int $page = 1, int $pageSize = 50): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['batch_id'])) {
            $where[] = 'batch_id = ?';
            $params[] = $filters['batch_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // 获取总数
        $countSql = "SELECT COUNT(*) as total FROM license_key $whereClause";
        $totalResult = Database::queryOne($countSql, $params);
        $total = $totalResult['total'] ?? 0;

        // 获取分页数据
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT * FROM license_key $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $pageSize;
        $params[] = $offset;

        $keys = Database::query($sql, $params);

        // 解密卡密
        foreach ($keys as &$key) {
            if (!empty($key['key_encrypted'])) {
                $key['key_plain'] = Crypto::decrypt($key['key_encrypted']);
            } else {
                $key['key_plain'] = null; // 旧数据没有加密字段
            }
        }

        return [
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'data' => $keys
        ];
    }

    /**
     * 获取状态标签
     *
     * @param string $status 状态
     * @return string
     */
    private static function getStatusLabel(string $status): string
    {
        $labels = [
            'active' => '可用',
            'banned' => '封禁',
            'deleted' => '已删除'
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * 更新卡密昵称
     *
     * @param int $keyId 卡密ID
     * @param string $nickname 昵称
     * @return bool
     */
    public static function updateNickname(int $keyId, string $nickname): bool
    {
        $affected = Database::execute(
            'UPDATE license_key SET nickname = ? WHERE id = ?',
            [$nickname, $keyId]
        );

        return $affected > 0;
    }

    /**
     * 导出所有卡密为Excel格式
     *
     * @param array $filters 筛选条件 ['status' => string, 'ids' => array]
     * @return string Excel文件路径
     */
    public static function exportAllKeysExcel(array $filters = []): string
    {
        // 获取所有卡密数据
        $where = [];
        $params = [];

        // 优先按ID筛选
        if (!empty($filters['ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['ids']), '?'));
            $where[] = "id IN ($placeholders)";
            $params = array_merge($params, $filters['ids']);
        } elseif (!empty($filters['status'])) {
            // 如果没有指定ID，则按状态筛选
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $keys = Database::query(
            "SELECT id, batch_id, key_encrypted, status, expire_at, created_at, first_used_at, last_used_at, nickname
             FROM license_key
             $whereClause
             ORDER BY id ASC",
            $params
        );

        // 创建PhpSpreadsheet对象
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 设置表头
        $headers = ['卡密', 'ID', '批次ID', '状态', '过期时间', '创建时间', '首次使用', '最后使用', '昵称'];
        $sheet->fromArray($headers, null, 'A1');

        // 设置表头样式
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0']
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

        // 填充数据
        $row = 2;
        foreach ($keys as $key) {
            $keyPlain = !empty($key['key_encrypted']) ? Crypto::decrypt($key['key_encrypted']) : '无法解密';
            $sheet->setCellValue('A' . $row, $keyPlain);
            $sheet->setCellValue('B' . $row, $key['id']);
            $sheet->setCellValue('C' . $row, $key['batch_id']);
            $sheet->setCellValue('D' . $row, self::getStatusLabel($key['status']));
            $sheet->setCellValue('E' . $row, $key['expire_at']);
            $sheet->setCellValue('F' . $row, $key['created_at']);
            $sheet->setCellValue('G' . $row, $key['first_used_at'] ?? '');
            $sheet->setCellValue('H' . $row, $key['last_used_at'] ?? '');
            $sheet->setCellValue('I' . $row, $key['nickname'] ?? '');
            $row++;
        }

        // 自动调整列宽
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 保存到临时文件
        $tempFile = sys_get_temp_dir() . '/keys_export_' . uniqid() . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }
}
