<?php

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'http://papertrail.test/api/agent/wordpress/update-request',
    __DIR__ . '/papertrail-wordpress-agent.php',
    'papertrail-wordpress-agent'
);

$updateChecker->debugMode = true;