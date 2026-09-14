<?php

/**
 * 管理后台API路由
 * 需要管理员认证
 */

use App\Auth;
use App\Database;
use App\Response;
use App\Validator;
use App\Logger;
use App\KeyManager;

// 管理员认证中间件
function requireAdminAuth() {
    // 从请求头获取token
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        Response::jsonError(4001, '请先登录');
    }

    $token = $matches[1];

    // 验证管理员token（使用session token）
    session_start();
    if (!isset($_SESSION['admin_token']) || $_SESSION['admin_token'] !== $token) {
        Response::jsonError(4001, '登录已过期，请重新登录');
    }

    if (!isset($_SESSION['admin_id'])) {
        Response::jsonError(4001, '请先登录');
    }

    return $_SESSION['admin_id'];
}

// 管理员认证中间件（支持URL参数token，用于文件下载）
function requireAdminAuthWithUrlToken() {
    // 启动session
    session_start();

    // 优先从URL参数获取token
    $token = $_GET['token'] ?? '';

    if (!$token) {
        // 如果没有URL token，尝试从请求头获取
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }

    // 如果有token，验证token
    if ($token) {
        if (!isset($_SESSION['admin_token']) || $_SESSION['admin_token'] !== $token) {
            Response::jsonError(4001, '登录已过期，请重新登录');
        }
    } else {
        // 如果没有token，检查session是否已登录（依赖浏览器cookie）
        if (!isset($_SESSION['admin_id'])) {
            Response::jsonError(4001, '请先登录');
        }
    }

    if (!isset($_SESSION['admin_id'])) {
        Response::jsonError(4001, '请先登录');
    }

    return $_SESSION['admin_id'];
}

// POST /api/admin/login - 管理员登录
if ($path === 'api/admin/login' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    // 验证输入
    $validation = Validator::validate(
        ['username' => $username, 'password' => $password],
        ['username' => 'required', 'password' => 'required']
    );

    if (!$validation['valid']) {
        Response::jsonError(4002, '用户名和密码不能为空');
    }

    // 查询管理员
    $admin = Database::queryOne(
        'SELECT * FROM admin_user WHERE username = ?',
        [$username]
    );

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        Response::jsonError(4002, '用户名或密码错误');
    }

    // 创建会话
    session_start();
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_token'] = bin2hex(random_bytes(32));

    // 记录登录日志
    Logger::logAdminOp($admin['id'], 'login', '管理员登录');

    Response::jsonSuccess([
        'token' => $_SESSION['admin_token'],
        'username' => $admin['username']
    ], '登录成功');
}

// POST /api/admin/logout - 管理员登出
elseif ($path === 'api/admin/logout' && $method === 'POST') {
    $adminId = requireAdminAuth();

    Logger::logAdminOp($adminId, 'logout', '管理员退出');

    session_start();
    session_destroy();

    Response::jsonSuccess(null, '已退出登录');
}

// GET /api/admin/stats - 获取统计数据
elseif ($path === 'api/admin/stats' && $method === 'GET') {
    requireAdminAuth();

    $stats = KeyManager::getKeyStats();

    // 获取今日使用次数
    $todayUsage = Database::queryOne(
        "SELECT COUNT(*) as count FROM key_usage_log
         WHERE DATE(created_at) = DATE('now', 'localtime') AND result = 'success'"
    );

    $stats['today_usage'] = $todayUsage['count'] ?? 0;

    Response::jsonSuccess($stats);
}

// GET /api/admin/keys - 获取卡密列表
elseif ($path === 'api/admin/keys' && $method === 'GET') {
    requireAdminAuth();

    $page = (int)($_GET['page'] ?? 1);
    $pageSize = (int)($_GET['pageSize'] ?? 20);
    $status = $_GET['status'] ?? '';

    $filters = [];
    if ($status) {
        $filters['status'] = $status;
    }

    $result = KeyManager::getKeys($filters, $page, $pageSize);
    Response::jsonSuccess($result);
}

// POST /api/admin/keys/generate - 批量生成卡密
elseif ($path === 'api/admin/keys/generate' && $method === 'POST') {
    $adminId = requireAdminAuth();

    $input = json_decode(file_get_contents('php://input'), true);
    $prefix = $input['prefix'] ?? '';
    $count = (int)($input['count'] ?? 0);
    $expireDays = (int)($input['expireDays'] ?? 30);

    // 验证
    if ($count < 1 || $count > 1000) {
        Response::jsonError(4003, '生成数量必须在1-1000之间');
    }

    if ($expireDays < 1 || $expireDays > 3650) {
        Response::jsonError(4003, '有效期必须在1-3650天之间');
    }

    if (strlen($prefix) > 4) {
        Response::jsonError(4003, '前缀长度不能超过4个字符');
    }

    try {
        $result = KeyManager::generateKeys($prefix, $count, $expireDays, $adminId);
        Response::jsonSuccess($result, "成功生成{$count}个卡密");
    } catch (Exception $e) {
        Response::jsonError(5000, '生成失败：' . $e->getMessage());
    }
}

// POST /api/admin/keys/ban - 批量封禁卡密
elseif ($path === 'api/admin/keys/ban' && $method === 'POST') {
    $adminId = requireAdminAuth();

    $input = json_decode(file_get_contents('php://input'), true);
    $keyIds = $input['keyIds'] ?? [];

    if (empty($keyIds)) {
        Response::jsonError(4003, '请选择要封禁的卡密');
    }

    $affected = KeyManager::banKeys($keyIds, $adminId);
    Response::jsonSuccess(['affected' => $affected], "成功封禁{$affected}个卡密");
}

// POST /api/admin/keys/unban - 批量解封卡密
elseif ($path === 'api/admin/keys/unban' && $method === 'POST') {
    $adminId = requireAdminAuth();

    $input = json_decode(file_get_contents('php://input'), true);
    $keyIds = $input['keyIds'] ?? [];

    if (empty($keyIds)) {
        Response::jsonError(4003, '请选择要解封的卡密');
    }

    $affected = KeyManager::unbanKeys($keyIds, $adminId);
    Response::jsonSuccess(['affected' => $affected], "成功解封{$affected}个卡密");
}

// POST /api/admin/keys/delete - 批量删除卡密
elseif ($path === 'api/admin/keys/delete' && $method === 'POST') {
    $adminId = requireAdminAuth();

    $input = json_decode(file_get_contents('php://input'), true);
    $keyIds = $input['keyIds'] ?? [];

    if (empty($keyIds)) {
        Response::jsonError(4003, '请选择要删除的卡密');
    }

    $affected = KeyManager::deleteKeys($keyIds, $adminId);
    Response::jsonSuccess(['affected' => $affected], "成功删除{$affected}个卡密");
}

// POST /api/admin/keys/update-expire - 修改卡密有效期
elseif ($path === 'api/admin/keys/update-expire' && $method === 'POST') {
    $adminId = requireAdminAuth();

    $input = json_decode(file_get_contents('php://input'), true);
    $keyIds = $input['keyIds'] ?? [];
    $expireDays = (int)($input['expireDays'] ?? 0);

    if (empty($keyIds)) {
        Response::jsonError(4003, '请选择要修改的卡密');
    }

    if ($expireDays < 1 || $expireDays > 3650) {
        Response::jsonError(4003, '有效期必须在1-3650天之间');
    }

    $affected = KeyManager::updateExpire($keyIds, $expireDays, $adminId);
    Response::jsonSuccess(['affected' => $affected], "成功修改{$affected}个卡密的有效期");
}

// GET /api/admin/keys/export - 导出卡密
elseif ($path === 'api/admin/keys/export' && $method === 'GET') {
    $adminId = requireAdminAuth();

    $batchId = (int)($_GET['batchId'] ?? 0);

    if (!$batchId) {
        Response::jsonError(4003, '请指定批次ID');
    }

    $csv = KeyManager::exportKeys($batchId);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="keys_batch_' . $batchId . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo $csv;
    exit;
}

// GET /api/admin/keys/export-all - 导出所有卡密
elseif ($path === 'api/admin/keys/export-all' && $method === 'GET') {
    $adminId = requireAdminAuthWithUrlToken();

    $filters = [];

    // 优先处理ids参数（导出选中的卡密）
    if (!empty($_GET['ids'])) {
        $idsStr = $_GET['ids'];
        $ids = array_map('intval', explode(',', $idsStr));
        $filters['ids'] = array_filter($ids); // 过滤掉无效的ID
    } elseif (!empty($_GET['status'])) {
        // 如果没有ids，则按状态筛选
        $filters['status'] = $_GET['status'];
    }

    // 生成Excel文件
    $tempFile = KeyManager::exportAllKeysExcel($filters);

    // 设置下载响应头
    $filename = 'keys_' . (!empty($filters['ids']) ? 'selected_' : 'all_') . date('YmdHis') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // 输出文件内容
    readfile($tempFile);

    // 删除临时文件
    unlink($tempFile);
    exit;
}

// GET /api/admin/logs - 获取日志列表
elseif ($path === 'api/admin/logs' && $method === 'GET') {
    requireAdminAuth();

    $page = (int)($_GET['page'] ?? 1);
    $pageSize = (int)($_GET['pageSize'] ?? 50);
    $type = $_GET['type'] ?? 'usage'; // usage or admin

    if ($type === 'admin') {
        $result = Logger::getAdminOpLogs([], $page, $pageSize);
    } else {
        $result = Logger::getKeyUsageLogs([], $page, $pageSize);
    }

    Response::jsonSuccess($result);
}

// 未匹配的管理路由
else {
    Response::jsonError(404, '接口不存在');
}
