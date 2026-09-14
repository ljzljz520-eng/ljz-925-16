<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Validator;

/**
 * 验证器测试
 */
class ValidatorTest extends TestCase
{
    /**
     * 测试必填验证 - 成功
     */
    public function testRequiredValidationSuccess(): void
    {
        $result = Validator::validate(
            ['key' => 'TEST00000001'],
            ['key' => 'required']
        );

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * 测试必填验证 - 失败
     */
    public function testRequiredValidationFailure(): void
    {
        $result = Validator::validate(
            ['key' => ''],
            ['key' => 'required']
        );

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('key', $result['errors']);
        $this->assertStringContainsString('不能为空', $result['errors']['key']);
    }

    /**
     * 测试长度验证 - 成功
     */
    public function testLengthValidationSuccess(): void
    {
        $result = Validator::validate(
            ['key' => 'TEST00000001'],
            ['key' => 'length:12']
        );

        $this->assertTrue($result['valid']);
    }

    /**
     * 测试长度验证 - 失败
     */
    public function testLengthValidationFailure(): void
    {
        $result = Validator::validate(
            ['key' => 'TEST'],
            ['key' => 'length:12']
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('长度必须为12个字符', $result['errors']['key']);
    }

    /**
     * 测试最小长度验证
     */
    public function testMinLengthValidation(): void
    {
        // 成功
        $result = Validator::validate(
            ['password' => '12345678'],
            ['password' => 'min:8']
        );
        $this->assertTrue($result['valid']);

        // 失败
        $result = Validator::validate(
            ['password' => '123'],
            ['password' => 'min:8']
        );
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('不能少于8个字符', $result['errors']['password']);
    }

    /**
     * 测试最大长度验证
     */
    public function testMaxLengthValidation(): void
    {
        // 成功
        $result = Validator::validate(
            ['nickname' => '测试用户'],
            ['nickname' => 'max:20']
        );
        $this->assertTrue($result['valid']);

        // 失败
        $result = Validator::validate(
            ['nickname' => str_repeat('A', 21)],
            ['nickname' => 'max:20']
        );
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('不能超过20个字符', $result['errors']['nickname']);
    }

    /**
     * 测试邮箱验证
     */
    public function testEmailValidation(): void
    {
        // 成功
        $result = Validator::validate(
            ['email' => 'test@example.com'],
            ['email' => 'email']
        );
        $this->assertTrue($result['valid']);

        // 失败
        $result = Validator::validate(
            ['email' => 'invalid-email'],
            ['email' => 'email']
        );
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('格式不正确', $result['errors']['email']);
    }

    /**
     * 测试整数验证
     */
    public function testIntegerValidation(): void
    {
        // 成功
        $result = Validator::validate(
            ['count' => '10'],
            ['count' => 'integer']
        );
        $this->assertTrue($result['valid']);

        // 失败
        $result = Validator::validate(
            ['count' => 'abc'],
            ['count' => 'integer']
        );
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('必须为整数', $result['errors']['count']);
    }

    /**
     * 测试数字验证
     */
    public function testNumericValidation(): void
    {
        // 成功 - 整数
        $result = Validator::validate(
            ['price' => '100'],
            ['price' => 'numeric']
        );
        $this->assertTrue($result['valid']);

        // 成功 - 小数
        $result = Validator::validate(
            ['price' => '99.99'],
            ['price' => 'numeric']
        );
        $this->assertTrue($result['valid']);

        // 失败
        $result = Validator::validate(
            ['price' => 'abc'],
            ['price' => 'numeric']
        );
        $this->assertFalse($result['valid']);
    }

    /**
     * 测试in验证
     */
    public function testInValidation(): void
    {
        // 成功
        $result = Validator::validate(
            ['status' => 'active'],
            ['status' => 'in:active,banned,deleted']
        );
        $this->assertTrue($result['valid']);

        // 失败
        $result = Validator::validate(
            ['status' => 'invalid'],
            ['status' => 'in:active,banned,deleted']
        );
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('不在允许范围内', $result['errors']['status']);
    }

    /**
     * 测试字母验证
     */
    public function testAlphaValidation(): void
    {
        // 成功
        $result = Validator::validate(
            ['code' => 'ABC'],
            ['code' => 'alpha']
        );
        $this->assertTrue($result['valid']);

        // 失败
        $result = Validator::validate(
            ['code' => 'ABC123'],
            ['code' => 'alpha']
        );
        $this->assertFalse($result['valid']);
    }

    /**
     * 测试字母数字验证
     */
    public function testAlphanumericValidation(): void
    {
        // 成功
        $result = Validator::validate(
            ['code' => 'ABC123'],
            ['code' => 'alphanumeric']
        );
        $this->assertTrue($result['valid']);

        // 失败
        $result = Validator::validate(
            ['code' => 'ABC-123'],
            ['code' => 'alphanumeric']
        );
        $this->assertFalse($result['valid']);
    }

    /**
     * 测试组合验证规则
     */
    public function testMultipleRules(): void
    {
        $result = Validator::validate(
            ['key' => 'TEST00000001'],
            ['key' => 'required|length:12|alphanumeric']
        );

        $this->assertTrue($result['valid']);
    }

    /**
     * 测试多字段验证
     */
    public function testMultipleFields(): void
    {
        $result = Validator::validate(
            [
                'username' => 'admin',
                'password' => '12345678',
                'email' => 'admin@example.com'
            ],
            [
                'username' => 'required|min:3',
                'password' => 'required|min:8',
                'email' => 'required|email'
            ]
        );

        $this->assertTrue($result['valid']);
    }

    /**
     * 测试快速必填验证
     */
    public function testQuickRequiredValidation(): void
    {
        $result = Validator::required(
            ['username' => 'admin', 'password' => ''],
            ['username', 'password']
        );

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    /**
     * 测试中文字段名
     */
    public function testChineseFieldLabels(): void
    {
        $result = Validator::validate(
            ['key' => ''],
            ['key' => 'required']
        );

        $this->assertStringContainsString('卡密', $result['errors']['key']);
    }
}
