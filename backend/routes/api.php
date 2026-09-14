<?php

/**
 * 业务API路由
 * 处理问题类型、模板、话术生成等业务请求
 * 所有路由都需要token认证
 */

use App\Auth;
use App\Database;
use App\Response;
use App\Validator;
use App\Logger;
use App\KeyManager;

// 认证中间件
$authResult = Auth::middleware();
if ($authResult !== null) {
    Response::json($authResult);
}

// 获取当前认证的卡密ID
$keyId = Auth::getKeyId();

// GET /api/problem-types - 获取问题类型列表
if ($path === 'api/problem-types' && $method === 'GET') {
    $types = Database::query(
        'SELECT id, name, description FROM problem_types ORDER BY sort_order ASC'
    );

    Response::jsonSuccess($types);
}

// GET /api/templates - 获取模板列表
elseif ($path === 'api/templates' && $method === 'GET') {
    $problemTypeId = $_GET['problemTypeId'] ?? '';
    $templateType = $_GET['templateType'] ?? 'SLOGAN';

    // 验证参数
    $validation = Validator::validate(
        ['problemTypeId' => $problemTypeId],
        ['problemTypeId' => 'required|integer']
    );

    if (!$validation['valid']) {
        $error = reset($validation['errors']);
        Response::jsonError(2002, $error);
    }

    $templates = Database::query(
        'SELECT id, title, content FROM templates WHERE problem_type_id = ? AND template_type = ? ORDER BY id ASC',
        [$problemTypeId, $templateType]
    );

    Response::jsonSuccess($templates);
}

// POST /api/generate/slogan - 生成话术
elseif ($path === 'api/generate/slogan' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $problemTypeId = $input['problemTypeId'] ?? '';
    $customInput = $input['customInput'] ?? '';

    // 验证参数
    $validation = Validator::validate(
        ['problemTypeId' => $problemTypeId],
        ['problemTypeId' => 'required|integer']
    );

    if (!$validation['valid']) {
        $error = reset($validation['errors']);
        Response::jsonError(2002, $error);
    }

    // 获取随机模板
    $template = Database::queryOne(
        'SELECT content FROM templates WHERE problem_type_id = ? AND template_type = ? ORDER BY RANDOM() LIMIT 1',
        [$problemTypeId, 'SLOGAN']
    );

    if (!$template) {
        Response::jsonError(2001, '未找到相关模板');
    }

    // 简单的话术生成逻辑（可以根据customInput进行定制）
    $content = $template['content'];

    // 如果有自定义输入，可以在这里进行处理
    if (!empty($customInput)) {
        $content .= "\n\n补充说明：" . $customInput;
    }

    // 记录使用日志
    Logger::logKeyUsage($keyId, 'access', 'success', '生成话术');

    Response::jsonSuccess(['content' => $content], '生成成功');
}

// POST /api/user/update-nickname - 更新昵称
elseif ($path === 'api/user/update-nickname' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nickname = $input['nickname'] ?? '';

    // 验证参数
    $validation = Validator::validate(
        ['nickname' => $nickname],
        ['nickname' => 'required|max:100']
    );

    if (!$validation['valid']) {
        $error = reset($validation['errors']);
        Response::jsonError(2002, $error);
    }

    $success = KeyManager::updateNickname($keyId, $nickname);

    if ($success) {
        Response::jsonSuccess(null, '昵称更新成功');
    } else {
        Response::jsonError(2001, '昵称更新失败');
    }
}

// GET /api/user/info - 获取用户信息
elseif ($path === 'api/user/info' && $method === 'GET') {
    $keyInfo = Database::queryOne(
        'SELECT id, nickname, expire_at, first_used_at, last_used_at FROM license_key WHERE id = ?',
        [$keyId]
    );

    if ($keyInfo) {
        Response::jsonSuccess($keyInfo);
    } else {
        Response::jsonError(2003, '用户信息不存在');
    }
}

// 未匹配的业务路由
else {
    Response::jsonError(404, '接口不存在');
}
