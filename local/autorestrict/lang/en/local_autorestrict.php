<?php
/**
 * Language strings for auto restrict access
 *
 * @package    local_autorestrict
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Auto Restrict Access';

$string['enabled'] = 'Enable auto restrict';
$string['enabled_desc'] = 'Automatically add restrict access conditions when new activities are created.';

$string['hide_completely'] = 'Hide completely when restricted';
$string['hide_completely_desc'] = 'When enabled, activities will be completely hidden from students who do not meet the requirements. When disabled, activities will be shown greyed out with a message explaining the requirements.';

$string['require_previous_section'] = 'Require previous section completion';
$string['require_previous_section_desc'] = 'Require completion of activities in the previous section before accessing activities in the current section.';

$string['min_section_completions'] = 'Minimum section completions';
$string['min_section_completions_desc'] = 'Minimum number of activities that must be completed in the previous section.';

$string['require_previous_grade'] = 'Require previous section grade';
$string['require_previous_grade_desc'] = 'Require a minimum average grade in the previous section.';

$string['min_section_grade'] = 'Minimum section grade (%)';
$string['min_section_grade_desc'] = 'Minimum average grade required in the previous section.';

$string['require_difficulty_progression'] = 'Require difficulty progression';
$string['require_difficulty_progression_desc'] = 'Require completion of easier difficulty levels before accessing harder ones.';

$string['min_difficulty_completions'] = 'Minimum difficulty completions';
$string['min_difficulty_completions_desc'] = 'Minimum number of activities that must be completed at each difficulty level before progressing.';
