<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\local_autorestrict\observer::course_module_created',
        'priority' => 200,
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\local_autorestrict\observer::course_module_updated',
        'priority' => 200,
    ],
    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => '\local_autorestrict\observer::course_module_completion_updated',
        'priority' => 9999,
    ],
];
