<?php
/**
 * Hook callback definitions for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => core\hook\output\before_http_headers::class,
        'callback' => local_autograding\hook_callbacks::class . '::before_http_headers',
        'priority' => 500,
    ],
];
