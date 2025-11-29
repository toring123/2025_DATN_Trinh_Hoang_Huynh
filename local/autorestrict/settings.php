<?php
/**
 * Settings for auto restrict access
 *
 * @package    local_autorestrict
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_autorestrict', get_string('pluginname', 'local_autorestrict'));
    
    // Enable/disable auto restrict
    $settings->add(new admin_setting_configcheckbox(
        'local_autorestrict/enabled',
        get_string('enabled', 'local_autorestrict'),
        get_string('enabled_desc', 'local_autorestrict'),
        0
    ));
    
    // Hide completely when not met
    $settings->add(new admin_setting_configcheckbox(
        'local_autorestrict/hide_completely',
        get_string('hide_completely', 'local_autorestrict'),
        get_string('hide_completely_desc', 'local_autorestrict'),
        1
    ));
    
    // Require previous section completion
    $settings->add(new admin_setting_configcheckbox(
        'local_autorestrict/require_previous_section',
        get_string('require_previous_section', 'local_autorestrict'),
        get_string('require_previous_section_desc', 'local_autorestrict'),
        1
    ));
    
    // Minimum section completions
    $settings->add(new admin_setting_configtext(
        'local_autorestrict/min_section_completions',
        get_string('min_section_completions', 'local_autorestrict'),
        get_string('min_section_completions_desc', 'local_autorestrict'),
        1,
        PARAM_INT
    ));
    
    // Require previous section grade
    $settings->add(new admin_setting_configcheckbox(
        'local_autorestrict/require_previous_grade',
        get_string('require_previous_grade', 'local_autorestrict'),
        get_string('require_previous_grade_desc', 'local_autorestrict'),
        0
    ));
    
    // Minimum section grade
    $settings->add(new admin_setting_configtext(
        'local_autorestrict/min_section_grade',
        get_string('min_section_grade', 'local_autorestrict'),
        get_string('min_section_grade_desc', 'local_autorestrict'),
        50,
        PARAM_FLOAT
    ));
    
    // Require difficulty progression
    $settings->add(new admin_setting_configcheckbox(
        'local_autorestrict/require_difficulty_progression',
        get_string('require_difficulty_progression', 'local_autorestrict'),
        get_string('require_difficulty_progression_desc', 'local_autorestrict'),
        1
    ));
    
    // Minimum difficulty completions
    $settings->add(new admin_setting_configtext(
        'local_autorestrict/min_difficulty_completions',
        get_string('min_difficulty_completions', 'local_autorestrict'),
        get_string('min_difficulty_completions_desc', 'local_autorestrict'),
        1,
        PARAM_INT
    ));
    
    $ADMIN->add('localplugins', $settings);
}
