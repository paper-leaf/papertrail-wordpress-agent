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
