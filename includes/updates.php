<?php

defined('ABSPATH') || exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

add_action('plugins_loaded', function () {

    $updateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/paper-leaf/papertrail-wordpress-agent',
        PAPERTRAIL_WP_AGENT_FILE,
        'papertrail-wordpress-agent'
    );

    // Set the access details
    $updateChecker->setBranch('main');
}, 20);