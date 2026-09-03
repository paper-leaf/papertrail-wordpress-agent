<?php

/**
 * 1. Helper: Encrypt the API Token using a site-specific WP salt
 */
function wp_encrypt_api_token(string $plaintext): string 
{
    if (empty($plaintext)) {
        return '';
    }

    // Uses the unique SECURE_AUTH_SALT from wp-config.php
    $key = wp_salt('secure_auth');
    $cipher = 'AES-256-CTR';
    
    $iv_length = openssl_cipher_iv_length($cipher);
    $iv = random_bytes($iv_length);
    
    $ciphertext = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    
    // Prefix with a unique identifier so we can recognize it's encrypted
    return 'encrypted_v1:' . base64_encode($iv . $ciphertext);
}

/**
 * 2. Helper: Decrypt the API Token
 */
function wp_decrypt_api_token(string $encrypted_text): string 
{
    // Check if the string is actually encrypted by our function
    if (strpos($encrypted_text, 'encrypted_v1:') !== 0) {
        return $encrypted_text; // Return as-is if it's already plain text
    }

    // Strip the prefix
    $raw_data = substr($encrypted_text, 13); 
    
    $key = wp_salt('secure_auth');
    $cipher = 'AES-256-CTR';
    
    $decoded = base64_decode($raw_data);
    $iv_length = openssl_cipher_iv_length($cipher);
    
    $iv = substr($decoded, 0, $iv_length);
    $ciphertext = substr($decoded, $iv_length);
    
    $decrypted = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    
    return $decrypted !== false ? $decrypted : '';
}

/**
 * 3. Hook into WordPress to automate Encryption before saving
 */
add_filter('pre_update_option_papertrail_wordpress_agent_bitbucket_password', function($new_value, $old_value) {
    // If the value hasn't changed or is empty, don't re-encrypt
    if ($new_value === $old_value || empty($new_value)) {
        return $new_value;
    }
    
    return wp_encrypt_api_token($new_value);
}, 10, 2);

/**
 * 4. Hook into WordPress to automate Decryption after fetching
 */
add_filter('option_papertrail_wordpress_agent_bitbucket_password', function($value) {
    return wp_decrypt_api_token($value);
});