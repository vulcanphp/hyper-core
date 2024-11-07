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
     * Creates a hashed version of the provided plaintext string using the specified algorithm.
     *
     * @param string $plain The plaintext string to be hashed.
     * @param string $algo The algorithm to use for hashing (default is 'Blowfish').
     * @return string The resulting hashed string.
     */
    public static function make(string $plain, string $algo = 'Blowfish'): string
    {
        // Set the algorithm and cost parameters
        [$algo, $cost] = ['Blowfish' => ['2a', 7], 'SHA-256' => [5, 'rounds=5000'], 'SHA-512' => [6, 'rounds=5000']][$algo];

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
        return crypt($plain, sprintf('$%s$%s$%s$', $algo, $cost, $salt));
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
        return hash_equals(crypt($plain, $hash), $hash);
    }

    /**
     * Encrypts a string using AES-256-CBC symmetric encryption.
     *
     * @param string $value The plaintext string to encrypt.
     * @return string The encrypted string, base64 encoded, containing the cipherText, IV, and key.
     */
    public static function encrypt(string $value): string
    {
        // Random key for encryption
        $key = bin2hex(random_bytes(4));
        // IV length for AES-256-CBC
        $ivLen = openssl_cipher_iv_length('AES-256-CBC');
        // Random initialization vector
        $iv = random_bytes($ivLen);
        // Encrypt value using AES-256-CBC
        $cipherText = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        // Concatenate encrypted data, IV, and key, then encode in base64
        return base64_encode("$cipherText::$iv::$key");
    }

    /**
     * Decrypts a string that was encrypted using the encrypt method.
     *
     * @param string $cipherText The base64-encoded encrypted string containing cipherText, IV, and key.
     * @return string The decrypted plaintext string.
     */
    public static function decrypt(string $cipherText): string
    {
        // Split cipherText, IV, and key
        [$encryptedData, $iv, $key] = explode('::', base64_decode($cipherText));
        // Decrypt using AES-256-CBC and return plaintext
        return openssl_decrypt($encryptedData, 'AES-256-CBC', $key, 0, $iv);
    }
}
