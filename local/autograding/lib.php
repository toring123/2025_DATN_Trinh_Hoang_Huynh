<?php
declare(strict_types=1);

/**
 * Library functions for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds autograding field to course module form for assignments.
 *
 * @param \moodleform_mod $formwrapper The course module form wrapper
 * @param \MoodleQuickForm $mform The form object
 * @return void
 */
function local_autograding_coursemodule_standard_elements(\moodleform_mod $formwrapper, \MoodleQuickForm $mform): void
{
    global $DB;

    // Only add the field for assign modules.
    if ($formwrapper->get_current()->modulename !== 'assign') {
        return;
    }

    // Get the course module ID if editing.
    $cmid = $formwrapper->get_current()->coursemodule ?? null;
    $currentvalue = 0;
    $currentanswer = '';
    $draftitemid = 0;
    $textdraftitemid = 0;

    // Load existing value if editing.
    if ($cmid !== null && $cmid > 0) {
        $record = $DB->get_record('local_autograding', ['cmid' => $cmid], 'autograding_option, answer');
        if ($record !== false) {
            $currentvalue = (int) $record->autograding_option;
            $currentanswer = $record->answer ?? '';
        }

        // Prepare file area for text editor (if option 2).
        if ($currentvalue === 2) {
            $textdraftitemid = file_get_submitted_draft_itemid('autograding_text_answer');
            file_prepare_draft_area(
                $textdraftitemid,
                context_system::instance()->id,
                'local_autograding',
                'text_answer',
                $cmid,
                ['subdirs' => 0, 'maxfiles' => 10, 'maxbytes' => 10485760]
            );
        }

        // Prepare file area for editing (if option 3).
        if ($currentvalue === 3) {
            $draftitemid = file_get_submitted_draft_itemid('autograding_file_answer');
            file_prepare_draft_area(
                $draftitemid,
                context_system::instance()->id,
                'local_autograding',
                'answer_file',
                $cmid,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
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

    // Add text answer field (conditional - for option 2).
    $editoroptions = [
        'subdirs' => 0,
        'maxbytes' => 10485760, // 10MB max.
        'maxfiles' => 10,
        'context' => context_system::instance(),
    ];

    $mform->addElement(
        'editor',
        'autograding_text_answer',
        get_string('text_answer_label', 'local_autograding'),
        ['rows' => 10],
        $editoroptions
    );

    // Add help button for text answer.
    $mform->addHelpButton('autograding_text_answer', 'text_answer_label', 'local_autograding');

    // Set type for editor.
    $mform->setType('autograding_text_answer', PARAM_RAW);

    // Set default value if editing.
    if ($cmid !== null && $cmid > 0 && $currentvalue === 2) {
        $mform->setDefault('autograding_text_answer', [
            'text' => $currentanswer,
            'format' => FORMAT_HTML,
            'itemid' => $textdraftitemid
        ]);
    }

    // Hide this field unless option 2 is selected.
    $mform->hideIf('autograding_text_answer', 'autograding_option', 'neq', 2);

    // Disable autocomplete for better UX.
    $mform->disabledIf('autograding_text_answer', 'autograding_option', 'neq', 2);

    // Add file manager field (conditional - for option 3).
    $filemanageroptions = [
        'subdirs' => 0,
        'maxbytes' => 10485760, // 10MB max.
        'maxfiles' => 1,
        'accepted_types' => ['.pdf'],
        'return_types' => FILE_INTERNAL,
    ];

    $mform->addElement(
        'filemanager',
        'autograding_file_answer',
        get_string('file_answer_label', 'local_autograding'),
        null,
        $filemanageroptions
    );

    // Add help button for file answer.
    $mform->addHelpButton('autograding_file_answer', 'file_answer_label', 'local_autograding');

    // Set default draft item id if editing.
    if ($draftitemid > 0) {
        $mform->setDefault('autograding_file_answer', $draftitemid);
    }

    // Hide this field unless option 3 is selected.
    $mform->hideIf('autograding_file_answer', 'autograding_option', 'neq', 3);
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
function local_autograding_coursemodule_validation(...$args): array
{
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
        $data = (array) $data;
    }

    $autogradingoption = isset($data['autograding_option']) ? (int) $data['autograding_option'] : 0;

    // Validate option 2: Text answer required.
    if ($autogradingoption === 2) {
        $textanswer = '';

        // Editor field returns array with 'text' key.
        if (isset($data['autograding_text_answer'])) {
            if (is_array($data['autograding_text_answer'])) {
                $textanswer = $data['autograding_text_answer']['text'] ?? '';
            } else {
                $textanswer = $data['autograding_text_answer'];
            }
        }

        $textanswer = trim(strip_tags($textanswer));

        if (empty($textanswer)) {
            $errors['autograding_text_answer'] = get_string('text_answer_required', 'local_autograding');
        }
    }

    // Validate option 3: File required.
    if ($autogradingoption === 3) {
        $draftitemid = $data['autograding_file_answer'] ?? 0;

        // Check if files were uploaded.
        $fs = get_file_storage();
        $usercontext = context_user::instance($data['userid'] ?? 0);
        $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);

        if (empty($draftfiles)) {
            $errors['autograding_file_answer'] = get_string('file_answer_required', 'local_autograding');
        } else {
            // Validate file type is PDF.
            $validpdf = false;
            foreach ($draftfiles as $file) {
                $filename = $file->get_filename();
                if (pathinfo($filename, PATHINFO_EXTENSION) === 'pdf') {
                    $validpdf = true;
                    break;
                }
            }

            if (!$validpdf) {
                $errors['autograding_file_answer'] = get_string('file_answer_pdf_only', 'local_autograding');
            }
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
function local_autograding_coursemodule_edit_post_actions(object $data, object $course): object
{
    global $CFG, $USER;

    // error_log("[AUTOGRADING] ========================================");
    // error_log("[AUTOGRADING] POST_ACTIONS STARTED");
    // error_log("[AUTOGRADING] ========================================");
    // error_log("[AUTOGRADING] Module name: " . ($data->modulename ?? 'not set'));

    // Only process assign modules.
    if (!isset($data->modulename) || $data->modulename !== 'assign') {
        // error_log("[AUTOGRADING] Not an assign module, skipping");
        // error_log("[AUTOGRADING] POST_ACTIONS ENDED (not assign)");
        return $data;
    }

    // Get course module ID and ensure it's an integer.
    $cmid = isset($data->coursemodule) ? (int) $data->coursemodule : 0;
    // error_log("[AUTOGRADING] Course module ID: " . $cmid);

    if ($cmid <= 0) {
        // error_log("[AUTOGRADING] ERROR: Invalid cmid: " . ($data->coursemodule ?? 'null'));
        // error_log("[AUTOGRADING] POST_ACTIONS ENDED (invalid cmid)");
        return $data;
    }

    // Get autograding option.
    $autogradingoption = isset($data->autograding_option) ? (int) $data->autograding_option : 0;
    // error_log("[AUTOGRADING] Autograding option: " . $autogradingoption);

    // Process based on option.
    $answertext = null;

    if ($autogradingoption === 2) {
        // error_log("[AUTOGRADING] Processing option 2: text answer (editor)");
        // Option 2: Text answer from editor.
        if (isset($data->autograding_text_answer)) {
            $editordata = $data->autograding_text_answer;

            // Editor field returns array with 'text', 'format', and 'itemid'.
            if (is_array($editordata)) {
                $answertext = $editordata['text'] ?? '';
                $draftitemid = $editordata['itemid'] ?? 0;

                // Save files from editor to permanent storage.
                if ($draftitemid > 0) {
                    $context = context_system::instance();
                    file_save_draft_area_files(
                        $draftitemid,
                        $context->id,
                        'local_autograding',
                        'text_answer',
                        $cmid,
                        ['subdirs' => 0, 'maxfiles' => 10, 'maxbytes' => 10485760]
                    );
                }
            } else {
                // Fallback for non-editor format.
                $answertext = $editordata;
            }

            $answertext = trim($answertext);
            // error_log("[AUTOGRADING] Text answer length: " . strlen($answertext));
            if (empty($answertext)) {
                $answertext = null;
            }
        }
    } else if ($autogradingoption === 3) {
        // error_log("[AUTOGRADING] Processing option 3: file answer");
        // Option 3: File answer - extract text from PDF.
        if (isset($data->autograding_file_answer)) {
            $draftitemid = (int) $data->autograding_file_answer;
            // error_log("[AUTOGRADING] Draft item ID: " . $draftitemid);

            // Get user ID from global USER object.
            $userid = $USER->id;
            // error_log("[AUTOGRADING] Current user ID: " . $userid);

            // Check what's in the draft area before saving.
            $fs = get_file_storage();
            $usercontext = context_user::instance($userid);
            $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
            // error_log("[AUTOGRADING] Files in draft area: " . count($draftfiles));
            foreach ($draftfiles as $df) {
                // error_log("[AUTOGRADING]   Draft file: " . $df->get_filename() . " (" . $df->get_filesize() . " bytes)");
            }

            // Save files from draft area to permanent storage.
            $context = context_system::instance();
            // error_log("[AUTOGRADING] Saving files to permanent storage...");
            // error_log("[AUTOGRADING]   Context ID: " . $context->id);
            // error_log("[AUTOGRADING]   Component: local_autograding");
            // error_log("[AUTOGRADING]   Filearea: answer_file");
            // error_log("[AUTOGRADING]   Item ID: " . $cmid);

            file_save_draft_area_files(
                $draftitemid,
                $context->id,
                'local_autograding',
                'answer_file',
                $cmid,
                ['subdirs' => 0, 'maxfiles' => 1]
            );

            // error_log("[AUTOGRADING] Files saved to permanent storage");

            // Verify files were saved.
            $savedfiles = $fs->get_area_files($context->id, 'local_autograding', 'answer_file', $cmid, 'id', false);
            // error_log("[AUTOGRADING] Files in permanent storage: " . count($savedfiles));
            foreach ($savedfiles as $sf) {
                // error_log("[AUTOGRADING]   Saved file: " . $sf->get_filename() . " (id: " . $sf->get_id() . ", size: " . $sf->get_filesize() . " bytes)");
            }

            // Extract text from the uploaded PDF.
            // error_log("[AUTOGRADING] Calling PDF extraction function...");
            $answertext = local_autograding_extract_pdf_text($cmid);

            // error_log("[AUTOGRADING] PDF extraction returned: " . ($answertext === null ? 'NULL' : strlen($answertext) . ' characters'));

            if ($answertext === null) {
                // error_log("[AUTOGRADING] ERROR: Failed to extract text from PDF for cmid " . $cmid);
                $answertext = ''; // Save empty string on failure.
            } else if (!empty($answertext)) {
                // error_log("[AUTOGRADING] First 100 chars of extracted text: " . substr($answertext, 0, 100));
            }
        } else {
            // error_log("[AUTOGRADING] WARNING: autograding_file_answer not set in data object");
        }
    }

    // Save the data.
    // error_log("[AUTOGRADING] Calling save_option with: cmid=" . $cmid . ", option=" . $autogradingoption . ", answer length=" . ($answertext ? strlen($answertext) : 'NULL'));
    $result = local_autograding_save_option($cmid, $autogradingoption, $answertext);
    // error_log("[AUTOGRADING] save_option returned: " . ($result ? 'TRUE' : 'FALSE'));
    // error_log("[AUTOGRADING] ========================================");
    // error_log("[AUTOGRADING] POST_ACTIONS ENDED");
    // error_log("[AUTOGRADING] ========================================");

    // Return the unmodified data object.
    return $data;
}

/**
 * Saves the autograding option to the custom table.
 *
 * @param int $cmid Course module ID
 * @param int $autogradingoption The autograding option value
 * @param string|null $answer The text answer (for option 2 or extracted from PDF for option 3)
 * @return bool Success status
 */
function local_autograding_save_option(int $cmid, int $autogradingoption, ?string $answer = null): bool
{
    global $DB;

    // error_log("[AUTOGRADING] save_option called with: cmid=$cmid, option=$autogradingoption, answer=" . ($answer === null ? 'NULL' : strlen($answer) . ' chars'));

    if ($cmid <= 0) {
        // error_log("[AUTOGRADING] ERROR: Invalid cmid provided to save_option: " . $cmid);
        return false;
    }

    // Validate option value.
    if ($autogradingoption < 0 || $autogradingoption > 3) {
        // error_log("[AUTOGRADING] WARNING: Invalid option value, resetting to 0");
        $autogradingoption = 0;
    }

    // Store answer for options 2 and 3.
    // Option 2: text input, Option 3: extracted from PDF.
    if ($autogradingoption !== 2 && $autogradingoption !== 3) {
        // error_log("[AUTOGRADING] Option is not 2 or 3, clearing answer");
        $answer = null;
    } else {
        // error_log("[AUTOGRADING] Option is 2 or 3, keeping answer (length: " . ($answer ? strlen($answer) : 0) . ")");
    }

    try {
        // Check if record exists.
        $existing = $DB->get_record('local_autograding', ['cmid' => $cmid]);

        if ($existing !== false) {
            // Update existing record.
            // error_log("[AUTOGRADING] Updating existing record ID: " . $existing->id);
            $existing->autograding_option = $autogradingoption;
            $existing->answer = $answer;
            $existing->timemodified = time();

            // error_log("[AUTOGRADING] About to update: option={$existing->autograding_option}, answer length=" . ($existing->answer ? strlen($existing->answer) : 0));

            $result = $DB->update_record('local_autograding', $existing);

            if ($result) {
                // error_log("[AUTOGRADING] SUCCESS: Updated record for cmid $cmid");

                // Verify the update.
                $verify = $DB->get_record('local_autograding', ['cmid' => $cmid]);
                // error_log("[AUTOGRADING] Verification: answer in DB is " . ($verify->answer ? strlen($verify->answer) . ' chars' : 'NULL'));
            } else {
                // error_log("[AUTOGRADING] ERROR: Update returned false");
            }

            return $result;
        } else {
            // Insert new record.
            // error_log("[AUTOGRADING] Inserting new record");
            $record = new stdClass();
            $record->cmid = $cmid;
            $record->autograding_option = $autogradingoption;
            $record->answer = $answer;
            $record->timecreated = time();
            $record->timemodified = time();

            // error_log("[AUTOGRADING] About to insert: cmid={$record->cmid}, option={$record->autograding_option}, answer length=" . ($record->answer ? strlen($record->answer) : 0));

            $result = $DB->insert_record('local_autograding', $record);

            if ($result) {
                // error_log("[AUTOGRADING] SUCCESS: Inserted new record with ID $result");

                // Verify the insert.
                $verify = $DB->get_record('local_autograding', ['id' => $result]);
                // error_log("[AUTOGRADING] Verification: answer in DB is " . ($verify->answer ? strlen($verify->answer) . ' chars' : 'NULL'));
            } else {
                // error_log("[AUTOGRADING] ERROR: Insert failed");
            }

            return $result !== false;
        }
    } catch (\dml_exception $e) {
        // error_log("[AUTOGRADING] ERROR: Database exception while saving: " . $e->getMessage());
        // error_log("[AUTOGRADING] Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Gets the autograding option for a course module.
 *
 * @param int $cmid Course module ID
 * @return object|null Object with autograding_option and answer fields, or null if not found
 */
function local_autograding_get_option(int $cmid): ?object
{
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
function local_autograding_delete_option(int $cmid): bool
{
    global $DB;

    if ($cmid <= 0) {
        return false;
    }

    try {
        // Delete associated files first.
        $fs = get_file_storage();
        $context = context_system::instance();
        $fs->delete_area_files($context->id, 'local_autograding', 'answer_file', $cmid);

        // Delete database record.
        return $DB->delete_records('local_autograding', ['cmid' => $cmid]);
    } catch (\dml_exception $e) {
        debugging('Error deleting autograding option: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Extracts text content from uploaded PDF file using OCR server.
 *
 * @param int $cmid Course module ID
 * @return string|null Extracted text or null on failure
 */
function local_autograding_extract_pdf_text(int $cmid): ?string
{
    // Get OCR server URL from config.
    $ocrServerUrl = get_config('local_autograding', 'ocr_server_url');

    if (empty($ocrServerUrl)) {
        debugging('[AUTOGRADING] ERROR: OCR server URL not configured', DEBUG_DEVELOPER);
        return null;
    }

    $tempFile = null;
    try {
        // Step 1: Get the uploaded file.
        $fs = get_file_storage();
        $context = context_system::instance();

        $files = $fs->get_area_files($context->id, 'local_autograding', 'answer_file', $cmid, 'id', false);

        if (empty($files)) {
            debugging('[AUTOGRADING] ERROR: No files found in storage for cmid ' . $cmid, DEBUG_DEVELOPER);
            return null;
        }

        // Step 2: Get the first (and only) file.
        $file = reset($files);
        $filename = $file->get_filename();
        $filecontent = $file->get_content();

        if (empty($filecontent)) {
            debugging('[AUTOGRADING] ERROR: File content is empty for file: ' . $filename, DEBUG_DEVELOPER);
            return null;
        }

        // Step 3: Send file to OCR server.
        $url = rtrim($ocrServerUrl, '/') . '/ocr-pdf';

        // Create a temporary file for curl upload.
        $tempFile = tempnam(sys_get_temp_dir(), 'ocr_pdf_');
        file_put_contents($tempFile, $filecontent);

        // Use curl for multipart/form-data upload.
        $ch = curl_init();

        $postFields = [
            'file' => new \CURLFile($tempFile, $file->get_mimetype(), $filename),
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlError) {
            debugging('[AUTOGRADING] OCR API curl error: ' . $curlError, DEBUG_DEVELOPER);
            return null;
        }

        if ($httpCode !== 200) {
            debugging('[AUTOGRADING] OCR API returned HTTP ' . $httpCode . ': ' . substr($response, 0, 500), DEBUG_DEVELOPER);
            return null;
        }

        $responseData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging('[AUTOGRADING] OCR API JSON decode error: ' . json_last_error_msg(), DEBUG_DEVELOPER);
            return null;
        }

        $extractedText = $responseData['text'] ?? '';

        if (empty($extractedText)) {
            debugging('[AUTOGRADING] OCR returned empty text for cmid ' . $cmid, DEBUG_DEVELOPER);
            return '';
        }

        return trim($extractedText);

    } catch (\Exception $e) {
        debugging('[AUTOGRADING] ERROR: Exception during PDF extraction for cmid ' . $cmid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        return null;
    } finally {
        // Always clean up temp file.
        if ($tempFile !== null && file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }
}

/**
 * Extends the navigation for assignment modules to add grading progress link.
 *
 * @param navigation_node $navigation The navigation node
 * @param stdClass $course The course
 * @param stdClass $module The module
 * @param cm_info $cm Course module info
 */
function local_autograding_extend_navigation_course_module(
    navigation_node $navigation,
    stdClass $course,
    stdClass $module,
    cm_info $cm
): void {
    global $DB, $PAGE;

    // Only for assign modules.
    if ($cm->modname !== 'assign') {
        return;
    }

    // Check if autograding is enabled for this assignment.
    $autogradingconfig = $DB->get_record('local_autograding', ['cmid' => $cm->id]);
    if (!$autogradingconfig || (int) $autogradingconfig->autograding_option === 0) {
        return;
    }

    // Check capability to grade.
    $context = context_module::instance($cm->id);
    if (!has_capability('mod/assign:grade', $context)) {
        return;
    }

    // Add navigation node for grading progress.
    $url = new moodle_url('/local/autograding/grading_progress.php', ['cmid' => $cm->id]);
    $navigation->add(
        get_string('grading_progress_title', 'local_autograding'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'autograding_progress',
        new pix_icon('i/report', '')
    );
}

// NOTE: The before_http_headers callback has been migrated to the new Moodle 4.3+ hook system.
// See: classes/hook_callbacks.php and db/hooks.php
