<?php

defined('ABSPATH') || exit;

/**
 * Get the decrypted GitHub API token.
 */
function papertrail_get_github_token(): string
{
    $encryptedToken = (string) get_option(
        PAPERTRAIL_WP_AGENT_GITHUB_TOKEN_OPTION,
        ''
    );

    if ($encryptedToken === '') {
        return '';
    }

    return papertrail_decrypt($encryptedToken);
}

/**
 * Save GitHub credentials.
 */
function papertrail_save_github_credentials(
    string $token
): void {
    update_option(
        PAPERTRAIL_WP_AGENT_GITHUB_TOKEN_OPTION,
        papertrail_encrypt($token)
    );
}