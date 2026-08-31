<?php
/**
 * REST API: routes, path rewrite, token check, and endpoint callbacks.
 *
 * Loaded from papertrail-wordpress-agent.php.
 */

defined('ABSPATH') || exit;

/**
 * Register the plugin's REST routes.
 */
add_action('rest_api_init', 'papertrail_wordpress_agent_register_routes');

function papertrail_wordpress_agent_register_routes()
{
    // Status route - returns the status of the API connection
    register_rest_route(PAPERTRAIL_WP_AGENT_REST_NAMESPACE, '/status', [
        'methods'             => 'GET',
        'callback'            => 'papertrail_wordpress_agent_rest_status',
        'permission_callback' => 'papertrail_wordpress_agent_check_token',
    ]);

    // Overview route - returns the basic WP information
    register_rest_route(PAPERTRAIL_WP_AGENT_REST_NAMESPACE, '/overview', [
        'methods'             => 'GET',
        'callback'            => 'papertrail_wordpress_agent_rest_overview',
        'permission_callback' => 'papertrail_wordpress_agent_check_token',
    ]);

    // Dependencies route - returns all plugin data
    register_rest_route(PAPERTRAIL_WP_AGENT_REST_NAMESPACE, '/dependencies', [
        'methods'             => 'GET',
        'callback'            => 'papertrail_wordpress_agent_rest_dependencies',
        'permission_callback' => 'papertrail_wordpress_agent_check_token',
    ]);
}

/**
 * Custom rewrite so the path PaperTrail calls
 * (/api/v1/papertrail/agent/{action}) forwards to the REST route.
 */
add_action('init', 'papertrail_wordpress_agent_add_rewrite');

function papertrail_wordpress_agent_add_rewrite()
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
register_activation_hook(PAPERTRAIL_WP_AGENT_FILE, 'papertrail_wordpress_agent_flush_rewrites');
register_deactivation_hook(PAPERTRAIL_WP_AGENT_FILE, 'flush_rewrite_rules');

function papertrail_wordpress_agent_flush_rewrites()
{
    papertrail_wordpress_agent_add_rewrite();
    flush_rewrite_rules();
}

/**
 * Permission callback: require a valid PaperTrail token.
 *
 * Compares the hash of the presented token against the stored hash.
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
function papertrail_wordpress_agent_rest_status()
{
    return [
        'status' => 'ok',
        'agent'  => 'papertrail-wordpress',
    ];
}

/**
 * GET /papertrail/v1/overview
 *
 * Site and runtime summary.
 */
function papertrail_wordpress_agent_rest_overview()
{
    $theme      = wp_get_theme();
    $frameworks = [];

    if ($theme->exists()) {
        $frameworks[] = [
            'name'    => 'theme:' . $theme->get_stylesheet(),
            'version' => $theme->get('Version'),
        ];

        $parent = $theme->parent();
        if ($parent && $parent->exists()) {
            $frameworks[] = [
                'name'    => 'theme-parent:' . $parent->get_stylesheet(),
                'version' => $parent->get('Version'),
            ];
        }
    }

    return [
        'agent' => 'papertrail-wordpress',
        'site'  => [
            'name' => get_bloginfo('name'),
            'type' => 'wordpress',
            'url'  => home_url(),
        ],
        'runtime' => [
            'php' => [
                'version' => sprintf('v%s', PHP_VERSION),
            ],
        ],
        'platform' => [
            'name'    => 'wordpress',
            'version' => get_bloginfo('version'),
        ],
        'frameworks' => $frameworks,
    ];
}

/**
 * GET /papertrail/v1/dependencies
 *
 * Every installed plugin
 */
function papertrail_wordpress_agent_rest_dependencies()
{
    // If WP function to get plugins exists
    if (! function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // Make sure the "updates available" data exists before we read it.
    if (!get_site_transient('update_plugins')) {
        // If not, fetch it
        wp_update_plugins();
    }

    // Get current plugins added to site
    $installed = get_plugins();
    // Get all active plugin information
    $active    = papertrail_wordpress_agent_active_plugin_files();
    // Retrieve the transient data of pinged plugins that need updating
    $updates   = get_site_transient('update_plugins');
    // Get the response from the data
    $available = (isset($updates->response) && is_array($updates->response)) ? $updates->response : [];

    $packages = [];

    foreach ($installed as $file => $data) {
        $slug        = papertrail_wordpress_agent_plugin_slug($file);
        $new_version = '';

        // Check the pinged update data with the plugin to see if there is an update available
        if (isset($available[$file])) {
            $update      = get_object_vars($available[$file]);
            $new_version = isset($update['new_version']) ? $update['new_version'] : '';
        }

        // Gather the plugin data
        $packages[] = [
            'name'             => $slug,
            'title'            => (isset($data['Name']) && $data['Name'] !== '') ? $data['Name'] : $slug,
            'version'          => isset($data['Version']) ? $data['Version'] : '',
            'author'           => isset($data['AuthorName']) ? wp_strip_all_tags($data['AuthorName']) : '',
            'author_url'       => isset($data['AuthorURI']) ? $data['AuthorURI'] : '',
            'plugin_url'       => isset($data['PluginURI']) ? $data['PluginURI'] : '',
            'active'           => in_array($file, $active, true),
            'update_available' => $new_version !== '',
            'new_version'      => $new_version,
        ];
    }

    return [
        'type'     => 'wordpress-plugins',
        'packages' => $packages,
    ];
}

/**
 * Plugin files that are active (site-level plus network-activated).
 */
function papertrail_wordpress_agent_active_plugin_files()
{
    $active = get_option('active_plugins', []);

    if (is_multisite()) {
        $network = get_site_option('active_sitewide_plugins', []);
        $active  = array_merge($active, array_keys($network));
    }

    return array_map('strval', $active);
}

/**
 * Slug from a plugin file as that is typically the plugin slug
 *   "wordpress-seo/wp-seo.php" => "wordpress-seo"
 *   "hello.php"                => "hello"
 */
function papertrail_wordpress_agent_plugin_slug($file)
{
    if (strpos($file, '/') !== false) {
        return dirname($file);
    }

    return basename($file, '.php');
}
