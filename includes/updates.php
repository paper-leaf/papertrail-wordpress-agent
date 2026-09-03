<?php

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://bitbucket.org/paperleaf/wordpress-agent',
    PAPERTRAIL_WP_AGENT_FILE,
    'papertrail-wordpress-agent'
);

$updateChecker->setAuthentication([
    'username' => 'info@paper-leaf.com',
    'api_token' => 'ATATT3xFfGF0lam36KthTDJi5LZkIX3m-sKguyMROT9Vv6pSkJciAHYPWM5iHIHWY2EBzg9zOZGspfw1RH4VugKC7vvjaH-gjeiv31ugkLbLNEDo-1VwhUe7ViN6LqK42m9cIbjPVFOdxgQdOBMAclZY11broxXhNkS-4Q1--mQoxEbhU5eg_i4=7E283E71',
]);