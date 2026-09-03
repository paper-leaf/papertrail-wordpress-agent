<?php

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://bitbucket.org/paperleaf/wordpress-agent',
    PAPERTRAIL_WP_AGENT_FILE,
    'papertrail-wordpress-agent'
);

