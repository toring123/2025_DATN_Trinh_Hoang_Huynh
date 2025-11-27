<?php
declare(strict_types=1);

/**
 * Database upgrade script for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the local_autograding plugin.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool Always returns true
 */
function xmldb_local_autograding_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // Add future upgrade steps here.
    // Example:
    // if ($oldversion < 2025112701) {
    //     // Upgrade code here.
    //     upgrade_plugin_savepoint(true, 2025112701, 'local', 'autograding');
    // }

    return true;
}