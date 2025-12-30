<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_autorestrict_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025120200) {
        $table = new xmldb_table('local_autorestrict_course');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('hide_completely', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('require_previous_section', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('min_section_completions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('require_previous_grade', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('min_section_grade', XMLDB_TYPE_NUMBER, '10', null, XMLDB_NOTNULL, null, '50', 2);
        $table->add_field('require_difficulty_progression', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('min_diff1_for_diff2', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '2');
        $table->add_field('min_diff1_for_diff3', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '3');
        $table->add_field('min_diff2_for_diff3', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '2');
        $table->add_field('min_diff1_for_diff4', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '4');
        $table->add_field('min_diff2_for_diff4', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '3');
        $table->add_field('min_diff3_for_diff4', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '2');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_UNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025120200, 'local', 'autorestrict');
    }
    
    if ($oldversion < 2025120400) {
        $table = new xmldb_table('local_autorestrict_course');
        
        $field = new xmldb_field('section_min_diff1_for_diff2', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'min_diff3_for_diff4');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('section_min_diff1_for_diff3', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'section_min_diff1_for_diff2');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('section_min_diff2_for_diff3', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'section_min_diff1_for_diff3');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('section_min_diff1_for_diff4', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'section_min_diff2_for_diff3');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('section_min_diff2_for_diff4', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'section_min_diff1_for_diff4');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('section_min_diff3_for_diff4', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'section_min_diff2_for_diff4');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        upgrade_plugin_savepoint(true, 2025120400, 'local', 'autorestrict');
    }

    return true;
}
