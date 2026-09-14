<?php

namespace App;

/**
 * 加密解密工具类
 * 使用AES-256-CBC加密卡密
 */
class Crypto
{
    /**
     * 加密数据
     *
     * @param string $plaintext 明文
     * @return string 加密后的base64字符串
     */
    public static function encrypt(string $plaintext): string
    {
        $key = self::getEncryptionKey();
        $iv = random_bytes(16);

        $ciphertext = openssl_encrypt(
            $plaintext,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        // 将IV和密文组合后base64编码
        return base64_encode($iv . $ciphertext);
    }

    /**
     * 解密数据
     *
     * @param string $encrypted 加密的base64字符串
     * @return string|null 明文，失败返回null
     */
    public static function decrypt(string $encrypted): ?string
    {
        $key = self::getEncryptionKey();
        $data = base64_decode($encrypted);

        if ($data === false || strlen($data) < 16) {
            return null;
        }

        // 提取IV和密文
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $plaintext !== false ? $plaintext : null;
    }

    /**
     * 获取加密密钥
     *
     * @return string
     */
    private static function getEncryptionKey(): string
    {
        $secret = Config::getServerSecret();
        // 使用SHA256生成32字节密钥
        return hash('sha256', $secret, true);
    }
}
