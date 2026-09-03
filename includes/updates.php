<?php

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'http://papertrail.test/api/agent/wordpress/update-request',
    __FILE__,
    'papertrail-wordpress-agent'
);