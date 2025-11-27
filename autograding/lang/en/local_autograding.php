<?php
declare(strict_types=1);

/**
 * English language strings for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Autograding';
$string['privacy:metadata'] = 'The Autograding plugin does not store any personal data.';

// Form elements.
$string['autograding_header'] = 'Autograding';
$string['autograding_label'] = 'Autograding';
$string['autograding_label_help'] = 'Select the autograding option for this assignment:
* **Not use**: Autograding is disabled for this assignment
* **Grading without answer**: Automatic grading without requiring student answers
* **Grading with text answer**: Automatic grading based on text submissions
* **Grading with file answer**: Automatic grading based on file submissions';

// Options.
$string['option_notuse'] = 'Not use';
$string['option_without_answer'] = 'Grading without answer';
$string['option_with_text'] = 'Grading with text answer';
$string['option_with_file'] = 'Grading with file answer';