<?php
/**
 * Plugin Name:       PaperTrail WordPress Agent
 * Description:       Securely connects this WordPress site to PaperTrail, exposing read-only site and plugin data over an authenticated REST API.
 * Version:           1.2.1
 * Author:            Paper Leaf
 * Author URI:        https://paper-leaf.com
 * Text Domain:       papertrail-wordpress-agent
 */

defined('ABSPATH') || exit;

const PAPERTRAIL_WP_AGENT_FILE                      = __FILE__;
const PAPERTRAIL_WP_AGENT_TOKEN_OPTION              = 'papertrail_wordpress_agent_token';
const PAPERTRAIL_WP_AGENT_BITBUCKET_USERNAME_OPTION = 'papertrail_wordpress_agent_bitbucket_username';
const PAPERTRAIL_WP_AGENT_BITBUCKET_PASSWORD_OPTION = 'papertrail_wordpress_agent_bitbucket_password';
const PAPERTRAIL_WP_AGENT_SAVE_ACTION               = 'papertrail_wordpress_agent_save';
const PAPERTRAIL_WP_AGENT_RELEASE_ACTION            = 'papertrail_wordpress_agent_release';
const PAPERTRAIL_WP_AGENT_TEST_ACTION               = 'papertrail_wordpress_agent_test';
const PAPERTRAIL_WP_AGENT_REST_NAMESPACE            = 'papertrail/v1';

require plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
require plugin_dir_path(__FILE__) . 'includes/updates.php';
require plugin_dir_path(__FILE__) . 'includes/encryption.php';
require plugin_dir_path(__FILE__) . 'includes/rest.php';

/**
 * Register the plugin's own admin page.
 */
add_action('admin_menu', static function () {
    add_menu_page(
        __('PaperTrail WordPress Agent', 'papertrail-wordpress-agent'),
        __('PaperTrail Agent', 'papertrail-wordpress-agent'),
        'manage_options',
        'papertrail-wordpress-agent',
        'papertrail_wordpress_agent_render_page',
        'dashicons-rss'
    );
});

/**
 * Include the view partial.
 */
function papertrail_wordpress_agent_render_page()
{
    require plugin_dir_path(__FILE__) . 'views/admin-page.php';
}

/**
 * Handle the "save token" form submission.
 *
 * The token is hashed and saved.
 */
add_action('admin_post_' . PAPERTRAIL_WP_AGENT_SAVE_ACTION, 'papertrail_wordpress_agent_save_token');

function papertrail_wordpress_agent_save_token()
{
    // Check if the user has permissions to save the token
    if (! current_user_can('manage_options')) {
        wp_die(
            esc_html__('You do not have permission to do this.', 'papertrail-wordpress-agent'),
            '',
            ['response' => 403]
        );
    }

    // Check if it's coming from the admin side
    check_admin_referer(PAPERTRAIL_WP_AGENT_SAVE_ACTION);

    $token = '';
    // Retrieve the submitted token
    if (isset($_POST[PAPERTRAIL_WP_AGENT_TOKEN_OPTION]) && is_string($_POST[PAPERTRAIL_WP_AGENT_TOKEN_OPTION])) {
        $token = trim(sanitize_text_field(wp_unslash($_POST[PAPERTRAIL_WP_AGENT_TOKEN_OPTION])));
    }

    // Retrieve the BitBucket creds
    $bitbucket_username = '';
    $bitbucket_password = '';
    if (isset($_POST[PAPERTRAIL_WP_AGENT_BITBUCKET_USERNAME_OPTION]) && is_string($_POST[PAPERTRAIL_WP_AGENT_BITBUCKET_USERNAME_OPTION])) {
        $bitbucket_username = trim(sanitize_text_field(wp_unslash($_POST[PAPERTRAIL_WP_AGENT_BITBUCKET_USERNAME_OPTION])));
    }

    if (isset($_POST[PAPERTRAIL_WP_AGENT_BITBUCKET_PASSWORD_OPTION]) && is_string($_POST[PAPERTRAIL_WP_AGENT_BITBUCKET_PASSWORD_OPTION])) {
        $bitbucket_password = trim(sanitize_text_field(wp_unslash($_POST[PAPERTRAIL_WP_AGENT_BITBUCKET_PASSWORD_OPTION])));
    }

    // Redirect if no token provided
    if ($token === '') {
        papertrail_wordpress_agent_redirect_back('empty');
    }

    // Save token to database
    update_option(PAPERTRAIL_WP_AGENT_TOKEN_OPTION, hash('sha256', $token));
    update_option(PAPERTRAIL_WP_AGENT_BITBUCKET_USERNAME_OPTION, $bitbucket_username);
    update_option(PAPERTRAIL_WP_AGENT_BITBUCKET_PASSWORD_OPTION, $bitbucket_password);

    // Redirect after saving
    papertrail_wordpress_agent_redirect_back('saved');
}

/**
 * Handle the "release token" form submission.
 *
 * Deletes the stored token so a new one can be applied.
 */
add_action('admin_post_' . PAPERTRAIL_WP_AGENT_RELEASE_ACTION, 'papertrail_wordpress_agent_release_token');

function papertrail_wordpress_agent_release_token()
{
    // Check if the user has permissions to release the token
    if (! current_user_can('manage_options')) {
        wp_die(
            esc_html__('You do not have permission to do this.', 'papertrail-wordpress-agent'),
            '',
            ['response' => 403]
        );
    }

    // Check if it's coming from the admin side
    check_admin_referer(PAPERTRAIL_WP_AGENT_RELEASE_ACTION);

    // Remove the stored token
    delete_option(PAPERTRAIL_WP_AGENT_TOKEN_OPTION);
    delete_option(PAPERTRAIL_WP_AGENT_BITBUCKET_USERNAME_OPTION);
    delete_option(PAPERTRAIL_WP_AGENT_BITBUCKET_PASSWORD_OPTION);

    // Redirect after releasing
    papertrail_wordpress_agent_redirect_back('released');
}

/**
 * Handle the "test connection" form submission
 */
add_action('admin_post_' . PAPERTRAIL_WP_AGENT_TEST_ACTION, 'papertrail_wordpress_agent_test_connection');

function papertrail_wordpress_agent_test_connection()
{
    // Check if the user has permissions to run the test
    if (! current_user_can('manage_options')) {
        wp_die(
            esc_html__('You do not have permission to do this.', 'papertrail-wordpress-agent'),
            '',
            ['response' => 403]
        );
    }

    // Check if it's coming from the admin side
    check_admin_referer(PAPERTRAIL_WP_AGENT_TEST_ACTION);

    // Request the status endpoint on the path PaperTrail uses, with no token
    $response = wp_remote_get(
        home_url('/api/v1/papertrail/agent/status'),
        [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
        ]
    );

    // Could not reach the endpoint at all
    if (is_wp_error($response)) {
        papertrail_wordpress_agent_redirect_back('test-unreachable');
    }

    $code       = (int) wp_remote_retrieve_response_code($response);
    $body       = json_decode(wp_remote_retrieve_body($response), true);
    $error_code = (is_array($body) && isset($body['code'])) ? $body['code'] : '';

    // 401 from our token check means the route resolved and the guard is running
    if ($code === 401 && $error_code === 'papertrail_invalid_token') {
        papertrail_wordpress_agent_redirect_back('test-ok');
    }

    if ($code === 401 && $error_code === 'papertrail_no_token') {
        papertrail_wordpress_agent_redirect_back('test-no-token');
    }

    papertrail_wordpress_agent_redirect_back('test-unreachable');
}

/**
 * Redirect back to the plugin page with a notice code, then stop.
 */
function papertrail_wordpress_agent_redirect_back($notice)
{
    wp_safe_redirect(add_query_arg(
        [
            'page'              => 'papertrail-wordpress-agent',
            'papertrail-notice' => $notice,
        ],
        admin_url('admin.php')
    ));
    exit;
}
