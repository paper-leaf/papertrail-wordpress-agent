<?php

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://bitbucket.org/paperleaf/wordpress-agent',
    PAPERTRAIL_WP_AGENT_FILE,
    'papertrail-wordpress-agent'
);

$bitbucket_username = get_option(PAPERTRAIL_WP_AGENT_BITBUCKET_USERNAME_OPTION, '');
$bitbucket_password = get_option(PAPERTRAIL_WP_AGENT_BITBUCKET_PASSWORD_OPTION, '');

$updateChecker->setAuthentication([
    'username' => $bitbucket_username,
    'api_token' => $bitbucket_password,
]);