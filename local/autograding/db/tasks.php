<?php
declare(strict_types=1);

/**
 * Scheduled task definitions for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

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
