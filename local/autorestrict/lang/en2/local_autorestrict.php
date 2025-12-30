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

$string['course_settings_desc'] = 'Configure automatic restriction settings for this course. When you save, restrictions will be automatically applied to all activities based on the settings below.';
$string['settings_saved'] = 'Settings saved successfully.';

$string['section_settings'] = 'Section Progression Settings';

$string['enabled'] = 'Enable auto restrict';
$string['enabled_help'] = 'When enabled, restrictions will be automatically applied to all activities. When disabled, all restrictions will be removed.';

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

$string['difficulty_settings'] = 'Course-wide Difficulty Progression';
$string['difficulty_settings_desc'] = 'Configure the number of completions required across the entire course to unlock each difficulty level.';

$string['min_diff1_for_diff2'] = 'Diff1 completions to unlock Diff2 (course)';
$string['min_diff1_for_diff3'] = 'Diff1 completions to unlock Diff3 (course)';
$string['min_diff2_for_diff3'] = 'Diff2 completions to unlock Diff3 (course)';
$string['min_diff1_for_diff4'] = 'Diff1 completions to unlock Diff4 (course)';
$string['min_diff2_for_diff4'] = 'Diff2 completions to unlock Diff4 (course)';
$string['min_diff3_for_diff4'] = 'Diff3 completions to unlock Diff4 (course)';

$string['section_difficulty_settings'] = 'Section-based Difficulty Progression';
$string['section_difficulty_settings_desc'] = 'Configure the number of completions required within the same section to unlock each difficulty level.';

$string['section_min_diff1_for_diff2'] = 'Diff1 completions to unlock Diff2 (section)';
$string['section_min_diff1_for_diff3'] = 'Diff1 completions to unlock Diff3 (section)';
$string['section_min_diff2_for_diff3'] = 'Diff2 completions to unlock Diff3 (section)';
$string['section_min_diff1_for_diff4'] = 'Diff1 completions to unlock Diff4 (section)';
$string['section_min_diff2_for_diff4'] = 'Diff2 completions to unlock Diff4 (section)';
$string['section_min_diff3_for_diff4'] = 'Diff3 completions to unlock Diff4 (section)';

$string['error_negative'] = 'Value cannot be negative.';
$string['error_grade_range'] = 'Grade must be between 0 and 100.';

// Auto apply/clear messages.
$string['auto_applied'] = 'Restrictions automatically applied: {$a->applied} activities updated, {$a->skipped} skipped.';
$string['auto_cleared'] = 'Restrictions automatically cleared: {$a->modules} activities, {$a->sections} sections.';

// Bulk difficulty page.
$string['bulk_difficulty'] = 'Bulk Set Difficulty';
$string['bulk_difficulty_desc'] = 'Select activities and assign difficulty levels. Activities with multiple difficulty tags will use the highest level.';
$string['filter_section'] = 'Filter by section';
$string['all_sections'] = 'All sections';
$string['selectall'] = 'Select all';
$string['deselectall'] = 'Deselect all';
$string['set_to'] = 'Set difficulty to';
$string['no_difficulty'] = 'No difficulty (remove tag)';
$string['diff1'] = 'Difficulty 1 (Easy)';
$string['diff2'] = 'Difficulty 2 (Medium)';
$string['diff3'] = 'Difficulty 3 (Hard)';
$string['diff4'] = 'Difficulty 4 (Expert)';
$string['apply'] = 'Apply';
$string['type'] = 'Type';
$string['current_difficulty'] = 'Current Difficulty';
$string['no_activities'] = 'No activities found in this course.';
$string['no_activities_selected'] = 'No activities selected.';
$string['bulk_diff_set'] = 'Difficulty set for {$a->success} activities.';
$string['bulk_diff_cleared'] = 'Difficulty cleared for {$a->success} activities.';
$string['manage_difficulty'] = 'Manage Difficulty Tags';
