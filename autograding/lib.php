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

    // Load existing value if editing.
    if ($cmid !== null && $cmid > 0) {
        $record = $DB->get_record('local_autograding', ['cmid' => $cmid], 'autograding_option');
        if ($record !== false) {
            $currentvalue = (int)$record->autograding_option;
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
}

/**
 * Saves the autograding option to the custom table.
 *
 * @param int $cmid Course module ID
 * @param int $autogradingoption The autograding option value
 * @return bool Success status
 */
function local_autograding_save_option(int $cmid, int $autogradingoption): bool {
    global $DB;

    if ($cmid <= 0) {
        return false;
    }

    // Validate option value.
    if ($autogradingoption < 0 || $autogradingoption > 3) {
        $autogradingoption = 0;
    }

    try {
        // Check if record exists.
        $existing = $DB->get_record('local_autograding', ['cmid' => $cmid]);

        if ($existing !== false) {
            // Update existing record.
            $existing->autograding_option = $autogradingoption;
            $existing->timemodified = time();
            return $DB->update_record('local_autograding', $existing);
        } else {
            // Insert new record.
            $record = new stdClass();
            $record->cmid = $cmid;
            $record->autograding_option = $autogradingoption;
            $record->timecreated = time();
            $record->timemodified = time();
            return $DB->insert_record('local_autograding', $record) !== false;
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
 * @return int The autograding option value (0 if not found)
 */
function local_autograding_get_option(int $cmid): int {
    global $DB;

    if ($cmid <= 0) {
        return 0;
    }

    try {
        $record = $DB->get_record('local_autograding', ['cmid' => $cmid], 'autograding_option');
        if ($record !== false) {
            return (int)$record->autograding_option;
        }
    } catch (\dml_exception $e) {
        debugging('Error retrieving autograding option: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    return 0;
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