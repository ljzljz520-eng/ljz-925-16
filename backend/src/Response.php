<?php

namespace App;

/**
 * 统一响应格式类
 * 所有API接口统一返回HTTP 200，通过code字段区分成功/失败
 */
class Response
{
    /**
     * 成功响应
     *
     * @param mixed $data 业务数据
     * @param string $message 提示信息（中文）
     * @return array
     */
    public static function success($data = null, string $message = '操作成功'): array
    {
        return [
            'code' => 0,
            'message' => $message,
            'data' => $data
        ];
    }

    /**
     * 错误响应
     *
     * @param int $code 错误码（非0）
     * @param string $message 错误信息（中文）
     * @param mixed $data 附加数据（可选）
     * @return array
     */
    public static function error(int $code, string $message, $data = null): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'data' => $data
        ];
    }

    /**
     * 输出JSON响应并结束脚本
     *
     * @param array $response 响应数组
     * @param int $httpCode HTTP状态码（默认200）
     * @return void
     */
    public static function json(array $response, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 输出成功响应并结束脚本
     *
     * @param mixed $data 业务数据
     * @param string $message 提示信息
     * @return void
     */
    public static function jsonSuccess($data = null, string $message = '操作成功'): void
    {
        self::json(self::success($data, $message));
    }

    /**
     * 输出错误响应并结束脚本
     *
     * @param int $code 错误码
     * @param string $message 错误信息
     * @param mixed $data 附加数据
     * @return void
     */
    public static function jsonError(int $code, string $message, $data = null): void
    {
        self::json(self::error($code, $message, $data));
    }
}
