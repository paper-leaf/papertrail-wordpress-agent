<?php

defined('ABSPATH') || exit;

/**
 * Get the Bitbucket username.
 */
function papertrail_get_bitbucket_username(): string
{
    return (string) get_option(
        PAPERTRAIL_WP_AGENT_BITBUCKET_USERNAME_OPTION,
        ''
    );
}

/**
 * Get the decrypted Bitbucket API token.
 */
function papertrail_get_bitbucket_token(): string
{
    $encryptedToken = (string) get_option(
        PAPERTRAIL_WP_AGENT_BITBUCKET_PASSWORD_OPTION,
        ''
    );

    if ($encryptedToken === '') {
        return '';
    }

    return papertrail_decrypt($encryptedToken);
}

/**
 * Save Bitbucket credentials.
 */
function papertrail_save_bitbucket_credentials(
    string $username,
    string $token
): void {
    update_option(
        PAPERTRAIL_WP_AGENT_BITBUCKET_USERNAME_OPTION,
        sanitize_email($username)
    );

    update_option(
        PAPERTRAIL_WP_AGENT_BITBUCKET_PASSWORD_OPTION,
        papertrail_encrypt($token)
    );
}