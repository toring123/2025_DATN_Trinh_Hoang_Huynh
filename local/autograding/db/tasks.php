<?php
declare(strict_types=1);
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_autograding\task\send_failure_digest',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '8',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
