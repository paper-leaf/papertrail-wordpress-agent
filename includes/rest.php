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

function papertrail_wordpress_agent_register_routes(): void
{
    register_rest_route(PAPERTRAIL_WP_AGENT_REST_NAMESPACE, '/status', [
        'methods'             => 'GET',
        'callback'            => 'papertrail_wordpress_agent_rest_status',
        'permission_callback' => 'papertrail_wordpress_agent_check_token',
    ]);

    register_rest_route(PAPERTRAIL_WP_AGENT_REST_NAMESPACE, '/overview', [
        'methods'             => 'GET',
        'callback'            => 'papertrail_wordpress_agent_rest_overview',
        'permission_callback' => 'papertrail_wordpress_agent_check_token',
    ]);
}

/**
 * Custom rewrite so the path PaperTrail calls
 * (/api/v1/papertrail/agent/{action}) forwards to the REST route.
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
register_activation_hook(PAPERTRAIL_WP_AGENT_FILE, 'papertrail_wordpress_agent_flush_rewrites');
register_deactivation_hook(PAPERTRAIL_WP_AGENT_FILE, 'flush_rewrite_rules');

function papertrail_wordpress_agent_flush_rewrites(): void
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
function papertrail_wordpress_agent_rest_status(): array
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
function papertrail_wordpress_agent_rest_overview(): array
{
    $theme      = wp_get_theme();
    $frameworks = [];

    if ($theme->exists()) {
        $frameworks[] = [
            'name'    => 'theme:' . $theme->get_stylesheet(),
            'version' => (string) $theme->get('Version'),
        ];

        $parent = $theme->parent();
        if ($parent && $parent->exists()) {
            $frameworks[] = [
                'name'    => 'theme-parent:' . $parent->get_stylesheet(),
                'version' => (string) $parent->get('Version'),
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
