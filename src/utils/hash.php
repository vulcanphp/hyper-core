<?php

namespace hyper\utils;

/**
 * Class hash
 * 
 * Provides methods for creating and validating hashed values and encrypting/decrypting strings using different algorithms.
 * Includes support for password hashing with various algorithms and symmetric encryption for sensitive data.
 * 
 * @package hyper\utils
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class hash
{
    /**
     * @var array ENCRYPT_ALGO Supported encryption algorithms and their respective identifiers/cost parameters.
     */
    protected const ENCRYPT_ALGO = [
        'Blowfish' => ['2a', 7],
        'SHA-256' => [5, 'rounds=5000'],
        'SHA-512' => [6, 'rounds=5000'],
    ];

    /**
     * Creates a hashed version of the provided plaintext string using the specified algorithm.
     *
     * @param string $plain The plaintext string to be hashed.
     * @param string $algo The algorithm to use for hashing (default is 'Blowfish').
     * @return string The resulting hashed string.
     */
    public static function make(string $plain, string $algo = 'Blowfish'): string
    {
        [$algo, $cost] = self::ENCRYPT_ALGO[$algo];

        // Adjust cost formatting for Blowfish algorithm
        if ($algo === '2a' && is_int($cost)) {
            $cost = sprintf('%02d', $cost);
        }

        // Generate a random salt for hashing
        $salt = '';
        for ($i = 0; $i < 8; ++$i) {
            $salt .= pack('S1', mt_rand(0, 0xffff));
        }
        $salt = strtr(rtrim(base64_encode($salt), '='), '+', '.');

        // Hash the plaintext with the selected algorithm, salt, and cost
        return crypt($plain . env('secret'), sprintf('$%s$%s$%s$', $algo, $cost, $salt));
    }

    /**
     * Validates that a plaintext string matches a previously hashed value.
     *
     * @param string $plain The plaintext string to validate.
     * @param string $hash The hashed value to compare against.
     * @return bool True if the plaintext matches the hash; otherwise, false.
     */
    public static function validate(string $plain, string $hash): bool
    {
        return hash_equals(crypt($plain . env('secret'), $hash), $hash);
    }

    /**
     * Encrypts a string using AES-256-CBC symmetric encryption.
     *
     * @param string $value The plaintext string to encrypt.
     * @return string The encrypted string, base64 encoded, containing the ciphertext, IV, and key.
     */
    public static function encrypt(string $value): string
    {
        $key = bin2hex(random_bytes(4));  // Random key for encryption
        $ivLen = openssl_cipher_iv_length('AES-256-CBC');  // IV length for AES-256-CBC
        $iv = random_bytes($ivLen);  // Random initialization vector
        $cipherText = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);  // Encrypt value

        // Concatenate encrypted data, IV, and key, then encode in base64
        return base64_encode($cipherText . '::' . $iv . '::' . $key);
    }

    /**
     * Decrypts a string that was encrypted using the encrypt method.
     *
     * @param string $ciphertext The base64-encoded encrypted string containing ciphertext, IV, and key.
     * @return string The decrypted plaintext string.
     */
    public static function decrypt(string $ciphertext): string
    {
        [$encryptedData, $iv, $key] = explode('::', base64_decode($ciphertext));
        return openssl_decrypt($encryptedData, 'AES-256-CBC', $key, 0, $iv);  // Decrypt and return plaintext
    }
}
