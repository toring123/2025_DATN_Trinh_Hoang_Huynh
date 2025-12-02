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
$string['autograding_header'] = 'Autograding Settings';
$string['autograding_label'] = 'Autograding';
$string['autograding_label_help'] = 'Select the autograding option for this assignment:
* **Not use**: Autograding is disabled for this assignment
* **Grading without answer**: Automatic grading without requiring student answers
* **Grading with text answer**: Automatic grading based on text submissions (requires answer text)
* **Grading with file answer**: Automatic grading based on PDF file submissions (requires PDF upload)';

// Text answer field.
$string['text_answer_label'] = 'Text Answer';
$string['text_answer_label_help'] = 'Enter the expected text answer for automatic grading. This field is required when "Grading with text answer" is selected. The text entered here will be used as the reference answer for comparing student submissions.';
$string['text_answer_required'] = 'Text answer is required when "Grading with text answer" is selected.';

// File answer field.
$string['file_answer_label'] = 'PDF Answer File';
$string['file_answer_label_help'] = 'Upload a PDF file containing the reference answer for automatic grading. This field is required when "Grading with file answer" is selected. The text will be automatically extracted from the PDF and stored for comparison with student submissions. Only PDF files are accepted (max 10MB).';
$string['file_answer_required'] = 'PDF file is required when "Grading with file answer" is selected.';
$string['file_answer_pdf_only'] = 'Only PDF files are accepted for the answer file.';

// Options.
$string['option_notuse'] = 'Not use';
$string['option_without_answer'] = 'Grading without answer';
$string['option_with_text'] = 'Grading with text answer';
$string['option_with_file'] = 'Grading with file answer';

// Gemini API Settings.
$string['gemini_settings_header'] = 'Google Gemini AI Settings';
$string['gemini_settings_desc'] = 'Configure the Google Gemini API for automatic grading. You need a valid API key from Google AI Studio (https://aistudio.google.com/).';
$string['gemini_api_key'] = 'Gemini API Key';
$string['gemini_api_key_desc'] = 'Enter your Google Gemini API key. This key is used to authenticate requests to the Gemini AI service for automatic grading.';
$string['gemini_model'] = 'Gemini Model';
$string['gemini_model_desc'] = 'Select the Gemini model to use for grading. Gemini 2.0 Flash is recommended for balance of speed and quality.';

// Auto-grading messages.
$string['autograding_disabled'] = 'Auto-grading is not enabled for this assignment.';
$string['autograding_no_api_key'] = 'Gemini API key is not configured. Please contact the administrator.';
$string['autograding_api_error'] = 'Error communicating with Gemini API: {$a}';
$string['autograding_invalid_response'] = 'Invalid response from Gemini API. Could not parse grading result.';
$string['autograding_success'] = 'Assignment automatically graded by AI.';
$string['autograding_no_submission'] = 'No submission content found to grade.';
$string['autograding_no_reference'] = 'No reference answer configured for this assignment.';
$string['autograding_feedback_prefix'] = '[AI Auto-Grading]';

// Adhoc task strings.
$string['task_grade_submission'] = 'Process auto-grading for assignment submission';
$string['ratelimited'] = 'API rate limit exceeded';
$string['locktimeout'] = 'Could not acquire lock for API call';