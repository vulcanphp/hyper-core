<?php

namespace hyper\utils;

class hash
{
    protected const ENCRYPT_ALGO = [
        'Blowfish' => ['2a', 7],
        'SHA-256'  => [5, 'rounds=5000'],
        'SHA-512'  => [6, 'rounds=5000'],
    ];

    public static function make(string $plain, string $algo = 'Blowfish'): string
    {
        [$algo, $cost] = self::ENCRYPT_ALGO[$algo];

        if ($algo === '2a' && is_int($cost)) {
            $cost = sprintf('%02d', $cost);
        }

        $salt = '';
        for ($i = 0; $i < 8; ++$i) {
            $salt .= pack('S1', mt_rand(0, 0xffff));
        }

        $salt = strtr(rtrim(base64_encode($salt), '='), '+', '.');

        return crypt($plain . env('secret'), sprintf('$%s$%s$%s$', $algo, $cost, $salt));
    }

    public static function validate(string $plain, string $hash): bool
    {
        return hash_equals(crypt($plain . env('secret'), $hash), $hash);
    }

    public static function encrypt(string $value): string
    {
        $key        = bin2hex(random_bytes(4));
        $ivLen      = openssl_cipher_iv_length('AES-256-CBC');
        $iv         = random_bytes($ivLen);
        $cipherText = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);

        return base64_encode($cipherText . '::' . $iv . '::' . $key);
    }

    public static function decrypt(string $ciphertext): string
    {
        [$encryptedData, $iv, $key] = explode('::', base64_decode($ciphertext));
        return openssl_decrypt($encryptedData, 'AES-256-CBC', $key, 0, $iv);
    }
}
