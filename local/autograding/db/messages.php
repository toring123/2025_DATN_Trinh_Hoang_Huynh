<?php
declare(strict_types=1);
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'grading_failure' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED,
        ],
    ],
];
