<?php
declare(strict_types=1);

/**
 * Version information for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_autograding';
$plugin->version = 2025121800; // Migrated to new hook system for before_http_headers.
$plugin->requires = 2024100700; // Moodle 5.0.
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.3.1';