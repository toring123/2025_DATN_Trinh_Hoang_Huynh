<?php
/**
 * Language strings for auto restrict access
 *
 * @package    local_autorestrict
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Auto Restrict Access';
$string['autorestrict:manage'] = 'Manage auto restrict settings';

$string['course_settings_desc'] = 'Configure automatic restriction settings for this course. When enabled, new activities will automatically have restriction conditions added based on the settings below.';
$string['settings_saved'] = 'Settings saved successfully.';

$string['section_settings'] = 'Section Progression Settings';

$string['enabled'] = 'Enable auto restrict';
$string['enabled_help'] = 'Automatically add restrict access conditions when new activities are created in this course.';

$string['hide_completely'] = 'Hide completely when restricted';
$string['hide_completely_help'] = 'When enabled, activities will be completely hidden from students who do not meet the requirements. When disabled, activities will be shown greyed out with a message explaining the requirements.';

$string['require_previous_section'] = 'Require previous section completion';
$string['require_previous_section_help'] = 'Require completion of activities in the previous section before accessing activities in the current section.';

$string['min_section_completions'] = 'Minimum section completions';
$string['min_section_completions_help'] = 'Minimum number of activities that must be completed in the previous section.';

$string['require_previous_grade'] = 'Require previous section grade';
$string['require_previous_grade_help'] = 'Require a minimum average grade in the previous section.';

$string['min_section_grade'] = 'Minimum section grade (%)';
$string['min_section_grade_help'] = 'Minimum average grade required in the previous section.';

$string['require_difficulty_progression'] = 'Require difficulty progression';
$string['require_difficulty_progression_help'] = 'Require completion of easier difficulty levels before accessing harder ones.';

$string['difficulty_settings'] = 'Difficulty Progression Settings';
$string['difficulty_settings_desc'] = 'Configure the number of completions required at each difficulty level to unlock the next level.';

$string['min_diff1_for_diff2'] = 'Diff1 completions to unlock Diff2';
$string['min_diff1_for_diff3'] = 'Diff1 completions to unlock Diff3';
$string['min_diff2_for_diff3'] = 'Diff2 completions to unlock Diff3';
$string['min_diff1_for_diff4'] = 'Diff1 completions to unlock Diff4';
$string['min_diff2_for_diff4'] = 'Diff2 completions to unlock Diff4';
$string['min_diff3_for_diff4'] = 'Diff3 completions to unlock Diff4';

$string['error_negative'] = 'Value cannot be negative.';
$string['error_grade_range'] = 'Grade must be between 0 and 100.';
