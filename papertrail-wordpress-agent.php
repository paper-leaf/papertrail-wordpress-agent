<?php
/**
 * Plugin Name:       PaperTrail WordPress Agent
 * Description:       Securely connects this WordPress site to PaperTrail, exposing read-only site and plugin data over an authenticated REST API.
 * Version:           1.0
 * Author:            Paper Leaf
 * Author URI:        https://paper-leaf.com
 * Text Domain:       papertrail-wordpress-agent
 */

defined('ABSPATH') || exit;

const PAPERTRAIL_WP_AGENT_TOKEN_OPTION   = 'papertrail_wordpress_agent_token';
const PAPERTRAIL_WP_AGENT_SAVE_ACTION    = 'papertrail_wordpress_agent_save';
const PAPERTRAIL_WP_AGENT_RELEASE_ACTION = 'papertrail_wordpress_agent_release';
const PAPERTRAIL_WP_AGENT_REST_NAMESPACE = 'papertrail/v1';

/**
 * Register the plugin's own admin page.
 */
add_action('admin_menu', static function (): void {
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
function papertrail_wordpress_agent_render_page(): void
{
    require plugin_dir_path(__FILE__) . 'views/admin-page.php';
}

/**
 * Handle the "save token" form submission.
 *
 * The token is hashed and saved.
 */
add_action('admin_post_' . PAPERTRAIL_WP_AGENT_SAVE_ACTION, 'papertrail_wordpress_agent_save_token');

function papertrail_wordpress_agent_save_token(): void
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

    // Redirect if no token provided
    if ($token === '') {
        papertrail_wordpress_agent_redirect_back('empty');
    }

    // Save token to database
    update_option(PAPERTRAIL_WP_AGENT_TOKEN_OPTION, hash('sha256', $token));

    // Redirect after saving
    papertrail_wordpress_agent_redirect_back('saved');
}

/**
 * Handle the "release token" form submission.
 *
 * Deletes the stored token so a new one can be applied.
 */
add_action('admin_post_' . PAPERTRAIL_WP_AGENT_RELEASE_ACTION, 'papertrail_wordpress_agent_release_token');

function papertrail_wordpress_agent_release_token(): void
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

    // Redirect after releasing
    papertrail_wordpress_agent_redirect_back('released');
}

/**
 * Redirect back to the plugin page with a notice code, then stop.
 */
function papertrail_wordpress_agent_redirect_back(string $notice): void
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

/**
 * Register the plugin's REST routes.
 */
add_action('rest_api_init', 'papertrail_wordpress_agent_register_routes');

function papertrail_wordpress_agent_register_routes(): void
{
    register_rest_route(PAPERTRAIL_WP_AGENT_REST_NAMESPACE, '/status', [
        'methods'             => 'GET',
        'callback'            => 'papertrail_wordpress_agent_rest_status',
        'permission_callback' => 'papertrail_wordpress_agent_check_token',
    ]);
}

/**
 * Custom rewrite to match the API path
 */
add_action('init', 'papertrail_wordpress_agent_add_rewrite');

function papertrail_wordpress_agent_add_rewrite(): void
{
    add_rewrite_rule(
        '^api/v1/papertrail/agent/(.+?)/?$',
        'index.php?rest_route=/' . PAPERTRAIL_WP_AGENT_REST_NAMESPACE . '/$matches[1]',
        'top'
    );
}

/**
 * Flush rewrite rules on activation and deactivation so the mapping above
 * is added and removed cleanly.
 */
register_activation_hook(__FILE__, 'papertrail_wordpress_agent_flush_rewrites');
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

function papertrail_wordpress_agent_flush_rewrites(): void
{
    papertrail_wordpress_agent_add_rewrite();
    flush_rewrite_rules();
}

/**
 * Permission callback: require a valid PaperTrail token.
 *
 * Compares the hash of the token against the stored hash.
 *
 */
function papertrail_wordpress_agent_check_token(WP_REST_Request $request)
{
    $stored = get_option(PAPERTRAIL_WP_AGENT_TOKEN_OPTION, '');

    if ($stored === '') {
        return new WP_Error(
            'papertrail_no_token',
            __('No connection token is configured.', 'papertrail-wordpress-agent'),
            ['status' => 401]
        );
    }

    $header = $request->get_header('authorization');
    $token  = '';

    if (is_string($header) && stripos($header, 'Bearer ') === 0) {
        $token = trim(substr($header, 7));
    }

    if ($token === '' || ! hash_equals($stored, hash('sha256', $token))) {
        return new WP_Error(
            'papertrail_invalid_token',
            __('Invalid connection token.', 'papertrail-wordpress-agent'),
            ['status' => 401]
        );
    }

    return true;
}

/**
 * GET /papertrail/v1/status
 */
function papertrail_wordpress_agent_rest_status(): array
{
    return [
        'status' => 'ok',
        'agent'  => 'papertrail-wordpress',
    ];
}
