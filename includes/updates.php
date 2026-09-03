<?php

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://bitbucket.org/paper-leaf/papertrail-agent',
    dirname(__DIR__) . '/papertrail-agent.php',
    'papertrail-agent'
);

$updateChecker->setAuthentication([
    'username'  => 'XWrYQ0NXHeGLCX4W0iTHY3IzMsf7K4dU',
    'api_token' => 'ATOACU9SQm3Pw1ON77ZCJQeZOtgLyu1nar_vYuwOvN6UjFwnDX0Cg3FJgvOmuivBwVzD71932309',
]);