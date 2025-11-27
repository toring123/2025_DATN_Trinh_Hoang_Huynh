<?php
declare(strict_types=1);

/**
 * Event observers for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
];