<?php

defined('ABSPATH') || exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

add_action('plugins_loaded', function () {

    $updateChecker = PucFactory::buildUpdateChecker(
        'https://bitbucket.org/paperleaf/wordpress-agent',
        PAPERTRAIL_WP_AGENT_FILE,
        'papertrail-wordpress-agent'
    );

    $username = papertrail_get_bitbucket_username();
    $token = papertrail_get_bitbucket_token();

    if ($username === '' || $token === '') {
        return;
    }

    $updateChecker->setAuthentication([
        'username' => $username,
        'api_token' => $token,
    ]);

}, 20);