<?php

defined('ABSPATH') || exit;

/**
 * Encrypt a value using a key derived from the WordPress salts.
 */
function papertrail_encrypt(string $plaintext): string
{
    if ($plaintext === '') {
        return '';
    }

    $cipher = 'aes-256-gcm';
    $key = hash('sha256', wp_salt('secure_auth'), true);

    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = random_bytes($ivLength);
    $tag = '';

    $ciphertext = openssl_encrypt(
        $plaintext,
        $cipher,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($ciphertext === false) {
        throw new RuntimeException('Unable to encrypt PaperTrail credential.');
    }

    return 'encrypted_v2:' . base64_encode(
        $iv . $tag . $ciphertext
    );
}

/**
 * Decrypt a value encrypted by papertrail_encrypt().
 */
function papertrail_decrypt(string $encrypted): string
{
    if ($encrypted === '') {
        return '';
    }

    // Support existing plaintext values during migration.
    if (strpos($encrypted, 'encrypted_v2:') !== 0) {
        return $encrypted;
    }

    $data = base64_decode(
        substr($encrypted, strlen('encrypted_v2:')),
        true
    );

    if ($data === false) {
        return '';
    }

    $cipher = 'aes-256-gcm';
    $key = hash('sha256', wp_salt('secure_auth'), true);

    $ivLength = openssl_cipher_iv_length($cipher);
    $tagLength = 16;

    if (strlen($data) <= $ivLength + $tagLength) {
        return '';
    }

    $iv = substr($data, 0, $ivLength);
    $tag = substr($data, $ivLength, $tagLength);
    $ciphertext = substr($data, $ivLength + $tagLength);

    $plaintext = openssl_decrypt(
        $ciphertext,
        $cipher,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    return $plaintext !== false ? $plaintext : '';
}