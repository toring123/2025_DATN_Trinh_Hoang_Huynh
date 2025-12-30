<?php
declare(strict_types=1);

/**
 * Database upgrade script for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the local_autograding plugin.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool Always returns true
 */
function xmldb_local_autograding_upgrade(int $oldversion): bool
{
    global $DB;

    $dbman = $DB->get_manager();

    // Add 'answer' field to store text answers for option 2.
    if ($oldversion < 2025112701) {
        $table = new xmldb_table('local_autograding');
        $field = new xmldb_field(
            'answer',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null,
            'autograding_option'
        );

        // Conditionally add field if it doesn't exist.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2025112701, 'local', 'autograding');
    }

    // Add new local_autograding_status table for tracking grading status.
    if ($oldversion < 2025121700) {
        // Define table local_autograding_status.
        $table = new xmldb_table('local_autograding_status');

        // Adding fields to table.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('submissionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timegraded', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('cmid_fk', XMLDB_KEY_FOREIGN, ['cmid'], 'course_modules', ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table.
        $table->add_index('cmid_userid_idx', XMLDB_INDEX_NOTUNIQUE, ['cmid', 'userid']);
        $table->add_index('submissionid_idx', XMLDB_INDEX_UNIQUE, ['submissionid']);
        $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);

        // Conditionally create table if it doesn't exist.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2025121700, 'local', 'autograding');
    }

    return true;
}