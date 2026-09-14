<?php

namespace App;

/**
 * 表单验证类
 * 提供常用的验证规则，返回中文错误信息
 */
class Validator
{
    /**
     * 验证数据
     *
     * @param array $data 待验证数据
     * @param array $rules 验证规则 ['field' => 'required|length:12|email']
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $ruleList = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                $error = self::applyRule($field, $value, $rule);
                if ($error) {
                    $errors[$field] = $error;
                    break; // 一个字段只返回第一个错误
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 应用单个验证规则
     *
     * @param string $field 字段名
     * @param mixed $value 字段值
     * @param string $rule 规则字符串
     * @return string|null 错误信息，无错误返回null
     */
    private static function applyRule(string $field, $value, string $rule): ?string
    {
        // 解析规则和参数
        if (strpos($rule, ':') !== false) {
            [$ruleName, $param] = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $param = null;
        }

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    return self::getFieldLabel($field) . '不能为空';
                }
                break;

            case 'length':
                if ($value !== null && mb_strlen($value) != $param) {
                    return self::getFieldLabel($field) . '长度必须为' . $param . '个字符';
                }
                break;

            case 'min':
                if ($value !== null && mb_strlen($value) < $param) {
                    return self::getFieldLabel($field) . '长度不能少于' . $param . '个字符';
                }
                break;

            case 'max':
                if ($value !== null && mb_strlen($value) > $param) {
                    return self::getFieldLabel($field) . '长度不能超过' . $param . '个字符';
                }
                break;

            case 'email':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return self::getFieldLabel($field) . '格式不正确';
                }
                break;

            case 'integer':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_INT)) {
                    return self::getFieldLabel($field) . '必须为整数';
                }
                break;

            case 'numeric':
                if ($value !== null && !is_numeric($value)) {
                    return self::getFieldLabel($field) . '必须为数字';
                }
                break;

            case 'regex':
                if ($value !== null && !preg_match($param, $value)) {
                    return self::getFieldLabel($field) . '格式不正确';
                }
                break;

            case 'in':
                $allowedValues = explode(',', $param);
                if ($value !== null && !in_array($value, $allowedValues)) {
                    return self::getFieldLabel($field) . '值不在允许范围内';
                }
                break;

            case 'alpha':
                if ($value !== null && !ctype_alpha($value)) {
                    return self::getFieldLabel($field) . '只能包含字母';
                }
                break;

            case 'alphanumeric':
                if ($value !== null && !ctype_alnum($value)) {
                    return self::getFieldLabel($field) . '只能包含字母和数字';
                }
                break;
        }

        return null;
    }

    /**
     * 获取字段的中文标签
     *
     * @param string $field 字段名
     * @return string 中文标签
     */
    private static function getFieldLabel(string $field): string
    {
        $labels = [
            'key' => '卡密',
            'token' => '令牌',
            'username' => '用户名',
            'password' => '密码',
            'nickname' => '昵称',
            'email' => '邮箱',
            'phone' => '手机号',
            'problemTypeId' => '问题类型',
            'templateType' => '模板类型',
            'customInput' => '自定义输入',
            'prefix' => '前缀',
            'count' => '数量',
            'expireDays' => '有效期',
        ];

        return $labels[$field] ?? $field;
    }

    /**
     * 快速验证必填字段
     *
     * @param array $data 数据
     * @param array $requiredFields 必填字段列表
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function required(array $data, array $requiredFields): array
    {
        $rules = [];
        foreach ($requiredFields as $field) {
            $rules[$field] = 'required';
        }
        return self::validate($data, $rules);
    }
}
