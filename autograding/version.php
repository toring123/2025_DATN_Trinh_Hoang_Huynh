<?php
declare(strict_types=1);

/**
 * Version information for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_autograding';
$plugin->version = 2025120101; // Bumped version for auto-grading API feature.
$plugin->requires = 2024100700; // Moodle 5.0.
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.2.0';