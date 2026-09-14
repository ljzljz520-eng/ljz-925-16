<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Crypto;

/**
 * 加密解密测试
 */
class CryptoTest extends TestCase
{
    /**
     * 测试加密解密往返
     */
    public function testEncryptDecryptRoundTrip(): void
    {
        $plaintext = 'TEST00000001';

        $encrypted = Crypto::encrypt($plaintext);
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($plaintext, $encrypted);

        $decrypted = Crypto::decrypt($encrypted);
        $this->assertEquals($plaintext, $decrypted);
    }

    /**
     * 测试加密结果每次不同（因为IV随机）
     */
    public function testEncryptionIsRandom(): void
    {
        $plaintext = 'TEST00000001';

        $encrypted1 = Crypto::encrypt($plaintext);
        $encrypted2 = Crypto::encrypt($plaintext);

        $this->assertNotEquals($encrypted1, $encrypted2, 'Encryption should use random IV');

        // 但解密结果应该相同
        $this->assertEquals($plaintext, Crypto::decrypt($encrypted1));
        $this->assertEquals($plaintext, Crypto::decrypt($encrypted2));
    }

    /**
     * 测试解密无效数据
     */
    public function testDecryptInvalidData(): void
    {
        $result = Crypto::decrypt('invalid_base64_data');
        $this->assertNull($result);
    }

    /**
     * 测试解密空字符串
     */
    public function testDecryptEmptyString(): void
    {
        $result = Crypto::decrypt('');
        $this->assertNull($result);
    }

    /**
     * 测试加密长文本
     */
    public function testEncryptLongText(): void
    {
        $plaintext = str_repeat('A', 1000);

        $encrypted = Crypto::encrypt($plaintext);
        $decrypted = Crypto::decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    /**
     * 测试加密中文
     */
    public function testEncryptChineseText(): void
    {
        $plaintext = '测试卡密123';

        $encrypted = Crypto::encrypt($plaintext);
        $decrypted = Crypto::decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    /**
     * 测试加密特殊字符
     */
    public function testEncryptSpecialCharacters(): void
    {
        $plaintext = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        $encrypted = Crypto::encrypt($plaintext);
        $decrypted = Crypto::decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }
}
