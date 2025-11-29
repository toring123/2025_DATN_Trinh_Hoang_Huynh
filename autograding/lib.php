<?php
declare(strict_types=1);

/**
 * Library functions for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds autograding field to course module form for assignments.
 *
 * @param \moodleform_mod $formwrapper The course module form wrapper
 * @param \MoodleQuickForm $mform The form object
 * @return void
 */
function local_autograding_coursemodule_standard_elements(\moodleform_mod $formwrapper, \MoodleQuickForm $mform): void {
    global $DB;

    // Only add the field for assign modules.
    if ($formwrapper->get_current()->modulename !== 'assign') {
        return;
    }

    // Get the course module ID if editing.
    $cmid = $formwrapper->get_current()->coursemodule ?? null;
    $currentvalue = 0;
    $currentanswer = '';

    // Load existing value if editing.
    if ($cmid !== null && $cmid > 0) {
        $record = $DB->get_record('local_autograding', ['cmid' => $cmid], 'autograding_option, answer');
        if ($record !== false) {
            $currentvalue = (int)$record->autograding_option;
            $currentanswer = $record->answer ?? '';
        }
    }

    // Define the options.
    $options = [
        0 => get_string('option_notuse', 'local_autograding'),
        1 => get_string('option_without_answer', 'local_autograding'),
        2 => get_string('option_with_text', 'local_autograding'),
        3 => get_string('option_with_file', 'local_autograding'),
    ];

    // Add header for better organization.
    $mform->addElement('header', 'autograding_header', get_string('autograding_header', 'local_autograding'));

    // Add the select element.
    $mform->addElement(
        'select',
        'autograding_option',
        get_string('autograding_label', 'local_autograding'),
        $options
    );

    // Add help button.
    $mform->addHelpButton('autograding_option', 'autograding_label', 'local_autograding');

    // Set default value.
    $mform->setDefault('autograding_option', $currentvalue);

    // Set type.
    $mform->setType('autograding_option', PARAM_INT);

    // Add text answer field (conditional).
    $mform->addElement(
        'textarea',
        'autograding_text_answer',
        get_string('text_answer_label', 'local_autograding'),
        ['rows' => 5, 'cols' => 60]
    );

    // Add help button for text answer.
    $mform->addHelpButton('autograding_text_answer', 'text_answer_label', 'local_autograding');

    // Set type.
    $mform->setType('autograding_text_answer', PARAM_TEXT);

    // Set default value if editing.
    $mform->setDefault('autograding_text_answer', $currentanswer);

    // Hide this field unless option 2 is selected.
    $mform->hideIf('autograding_text_answer', 'autograding_option', 'neq', 2);

    // Disable autocomplete for better UX.
    $mform->disabledIf('autograding_text_answer', 'autograding_option', 'neq', 2);
}

/**
 * Validates the autograding form elements.
 *
 * This function is called by the course module form validation process.
 * Moodle passes different numbers of arguments in different contexts.
 *
 * @param mixed ...$args Variable number of arguments from Moodle
 * @return array Array of errors
 */
function local_autograding_coursemodule_validation(...$args): array {
    $errors = [];
    
    // Handle different calling conventions.
    // Sometimes Moodle passes: ($data, $files)
    // Sometimes Moodle passes: ($formobject, $data, $files)
    if (count($args) === 2) {
        // Called with ($data, $files).
        [$data, $files] = $args;
    } else if (count($args) === 3) {
        // Called with ($formobject, $data, $files).
        [, $data, $files] = $args;
    } else {
        // Unexpected number of arguments.
        debugging('Unexpected number of arguments in local_autograding_coursemodule_validation: ' . count($args), DEBUG_DEVELOPER);
        return $errors;
    }

    // Ensure $data is an array.
    if (is_object($data)) {
        $data = (array)$data;
    }

    // Check if option 2 is selected and text answer is required.
    if (isset($data['autograding_option']) && (int)$data['autograding_option'] === 2) {
        $textanswer = $data['autograding_text_answer'] ?? '';
        $textanswer = trim($textanswer);

        if (empty($textanswer)) {
            $errors['autograding_text_answer'] = get_string('text_answer_required', 'local_autograding');
        }
    }

    return $errors;
}

/**
 * Processes data before the course module form is saved.
 *
 * This function is called after the module is created/updated.
 * It should not modify the $data object, only use it to save additional data.
 *
 * @param object $data Form data (do not modify this object)
 * @param object $course Course object
 * @return object The unmodified $data object
 */
function local_autograding_coursemodule_edit_post_actions(object $data, object $course): object {
    // Only process assign modules.
    if (!isset($data->modulename) || $data->modulename !== 'assign') {
        return $data;
    }

    // Get course module ID and ensure it's an integer.
    $cmid = isset($data->coursemodule) ? (int)$data->coursemodule : 0;
    if ($cmid <= 0) {
        debugging('Invalid cmid in local_autograding_coursemodule_edit_post_actions: ' . ($data->coursemodule ?? 'null'), DEBUG_DEVELOPER);
        return $data;
    }

    // Get autograding option.
    $autogradingoption = isset($data->autograding_option) ? (int)$data->autograding_option : 0;

    // Get text answer if provided.
    $textanswer = null;
    if ($autogradingoption === 2 && isset($data->autograding_text_answer)) {
        $textanswer = trim($data->autograding_text_answer);
        if (empty($textanswer)) {
            $textanswer = null;
        }
    }

    // Save the data.
    debugging('Saving autograding data: cmid=' . $cmid . ', option=' . $autogradingoption . ', answer=' . ($textanswer ? 'yes' : 'no'), DEBUG_DEVELOPER);
    local_autograding_save_option($cmid, $autogradingoption, $textanswer);
    
    // Return the unmodified data object.
    return $data;
}

/**
 * Saves the autograding option to the custom table.
 *
 * @param int $cmid Course module ID
 * @param int $autogradingoption The autograding option value
 * @param string|null $answer The text answer (for option 2)
 * @return bool Success status
 */
function local_autograding_save_option(int $cmid, int $autogradingoption, ?string $answer = null): bool {
    global $DB;

    if ($cmid <= 0) {
        debugging('Invalid cmid provided to local_autograding_save_option: ' . $cmid, DEBUG_DEVELOPER);
        return false;
    }

    // Validate option value.
    if ($autogradingoption < 0 || $autogradingoption > 3) {
        $autogradingoption = 0;
    }

    // Only store answer if option 2 is selected.
    if ($autogradingoption !== 2) {
        $answer = null;
    }

    try {
        // Check if record exists.
        $existing = $DB->get_record('local_autograding', ['cmid' => $cmid]);

        if ($existing !== false) {
            // Update existing record.
            $existing->autograding_option = $autogradingoption;
            $existing->answer = $answer;
            $existing->timemodified = time();
            $result = $DB->update_record('local_autograding', $existing);
            debugging('Updated autograding record for cmid ' . $cmid . ', option: ' . $autogradingoption . ', answer: ' . ($answer ? 'yes' : 'no'), DEBUG_DEVELOPER);
            return $result;
        } else {
            // Insert new record.
            $record = new stdClass();
            $record->cmid = $cmid;
            $record->autograding_option = $autogradingoption;
            $record->answer = $answer;
            $record->timecreated = time();
            $record->timemodified = time();
            $result = $DB->insert_record('local_autograding', $record);
            debugging('Inserted new autograding record for cmid ' . $cmid . ', option: ' . $autogradingoption . ', answer: ' . ($answer ? 'yes' : 'no'), DEBUG_DEVELOPER);
            return $result !== false;
        }
    } catch (\dml_exception $e) {
        debugging('Error saving autograding option: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Gets the autograding option for a course module.
 *
 * @param int $cmid Course module ID
 * @return object|null Object with autograding_option and answer fields, or null if not found
 */
function local_autograding_get_option(int $cmid): ?object {
    global $DB;

    if ($cmid <= 0) {
        return null;
    }

    try {
        $record = $DB->get_record('local_autograding', ['cmid' => $cmid], 'autograding_option, answer');
        if ($record !== false) {
            return $record;
        }
    } catch (\dml_exception $e) {
        debugging('Error retrieving autograding option: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    return null;
}

/**
 * Deletes autograding option when a course module is deleted.
 *
 * @param int $cmid Course module ID
 * @return bool Success status
 */
function local_autograding_delete_option(int $cmid): bool {
    global $DB;

    if ($cmid <= 0) {
        return false;
    }

    try {
        return $DB->delete_records('local_autograding', ['cmid' => $cmid]);
    } catch (\dml_exception $e) {
        debugging('Error deleting autograding option: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}