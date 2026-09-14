<?php

/**
 * 认证路由
 * 处理卡密验证、心跳检测、登出等认证相关请求
 */

use App\Auth;
use App\Response;

// 获取客户端IP和UA
$clientIp = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

// POST /api/auth/verify-key - 验证卡密
if ($path === 'api/auth/verify-key' && $method === 'POST') {
    // 获取POST数据
    $input = json_decode(file_get_contents('php://input'), true);
    $key = $input['key'] ?? '';

    $result = Auth::verifyKey($key, $clientIp, $userAgent);
    Response::json($result);
}

// GET /api/auth/ping - 心跳检测
elseif ($path === 'api/auth/ping' && $method === 'GET') {
    $token = App\TokenManager::extractTokenFromRequest();

    if (!$token) {
        Response::jsonError(1002, '请先验证卡密');
    }

    $result = Auth::ping($token);
    Response::json($result);
}

// POST /api/auth/logout - 登出
elseif ($path === 'api/auth/logout' && $method === 'POST') {
    $token = App\TokenManager::extractTokenFromRequest();

    if (!$token) {
        Response::jsonError(1002, '无效的令牌');
    }

    $result = Auth::logout($token);
    Response::json($result);
}

// 未匹配的认证路由
else {
    Response::jsonError(404, '接口不存在');
}
