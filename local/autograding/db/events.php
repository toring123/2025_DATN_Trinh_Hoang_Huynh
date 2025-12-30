<?php
declare(strict_types=1);
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\local_autograding\observer::course_module_created',
        'priority' => 0,
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\local_autograding\observer::course_module_updated',
        'priority' => 0,
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\local_autograding\observer::course_module_deleted',
        'priority' => 0,
    ],
    [
        'eventname' => '\mod_assign\event\assessable_submitted',
        'callback' => '\local_autograding\observer::assessable_submitted',
        'priority' => 0,
    ],
];