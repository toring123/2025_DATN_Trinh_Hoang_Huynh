<?php
declare(strict_types=1);
defined('MOODLE_INTERNAL') || die();

function get_local_autograding(?int $cmid): array
{
    global $DB;

    $data = [
        'currentvalue' => 0,
        'currentanswer' => '',
        'draftitemid' => 0,
        'textdraftitemid' => 0,
    ];

    if ($cmid === null || $cmid <= 0) {
        return $data;
    }

    $record = $DB->get_record('local_autograding', ['cmid' => $cmid], 'autograding_option, answer');
    if ($record !== false) {
        $data['currentvalue'] = (int) $record->autograding_option;
        $data['currentanswer'] = $record->answer ?? '';
    }

    if ($data['currentvalue'] === 2) {
        $data['textdraftitemid'] = file_get_submitted_draft_itemid('autograding_text_answer');
        file_prepare_draft_area(
            $data['textdraftitemid'],
            context_system::instance()->id,
            'local_autograding',
            'text_answer',
            $cmid,
            ['subdirs' => 0, 'maxfiles' => 10, 'maxbytes' => 10485760]
        );
    }

    if ($data['currentvalue'] === 3) {
        $data['draftitemid'] = file_get_submitted_draft_itemid('autograding_file_answer');
        file_prepare_draft_area(
            $data['draftitemid'],
            context_system::instance()->id,
            'local_autograding',
            'answer_file',
            $cmid,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
    }

    return $data;
}

function local_autograding_coursemodule_standard_elements(\moodleform_mod $formwrapper, \MoodleQuickForm $mform): void
{
    if ($formwrapper->get_current()->modulename !== 'assign') {
        return;
    }

    $cmid = $formwrapper->get_current()->coursemodule ?? null;

    $local_autograding = get_local_autograding($cmid !== null ? (int) $cmid : null);
    $currentvalue = $local_autograding['currentvalue'];
    $currentanswer = $local_autograding['currentanswer'];
    $draftitemid = $local_autograding['draftitemid'];
    $textdraftitemid = $local_autograding['textdraftitemid'];

    $options = [
        0 => get_string('option_notuse', 'local_autograding'),
        1 => get_string('option_without_answer', 'local_autograding'),
        2 => get_string('option_with_text', 'local_autograding'),
        3 => get_string('option_with_file', 'local_autograding'),
    ];

    $mform->addElement('header', 'autograding_header', get_string('autograding_header', 'local_autograding'));

    $mform->addElement(
        'select',
        'autograding_option',
        get_string('autograding_label', 'local_autograding'),
        $options
    );

    $mform->addHelpButton('autograding_option', 'autograding_label', 'local_autograding');

    $mform->setDefault('autograding_option', $currentvalue);

    $mform->setType('autograding_option', PARAM_INT);

    $editoroptions = [
        'subdirs' => 0,
        'maxbytes' => 10485760,
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

    $mform->addHelpButton('autograding_text_answer', 'text_answer_label', 'local_autograding');

    $mform->setType('autograding_text_answer', PARAM_RAW);

    if ($cmid !== null && $cmid > 0 && $currentvalue === 2) {
        $mform->setDefault('autograding_text_answer', [
            'text' => $currentanswer,
            'format' => FORMAT_HTML,
            'itemid' => $textdraftitemid
        ]);
    }

    $mform->hideIf('autograding_text_answer', 'autograding_option', 'neq', 2);

    $mform->disabledIf('autograding_text_answer', 'autograding_option', 'neq', 2);

    $filemanageroptions = [
        'subdirs' => 0,
        'maxbytes' => 10485760,
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

    $mform->addHelpButton('autograding_file_answer', 'file_answer_label', 'local_autograding');

    if ($draftitemid > 0) {
        $mform->setDefault('autograding_file_answer', $draftitemid);
    }

    $mform->hideIf('autograding_file_answer', 'autograding_option', 'neq', 3);
}

function local_autograding_coursemodule_validation(...$args): array
{
    $errors = [];

    if (count($args) === 2) {
        [$data, $files] = $args;
    } else if (count($args) === 3) {
        [, $data, $files] = $args;
    } else {
        debugging('Unexpected number of arguments in local_autograding_coursemodule_validation: ' . count($args), DEBUG_DEVELOPER);
        return $errors;
    }

    if (is_object($data)) {
        $data = (array) $data;
    }

    $autogradingoption = isset($data['autograding_option']) ? (int) $data['autograding_option'] : 0;

    if ($autogradingoption === 2) {
        $textanswer = '';

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

    if ($autogradingoption === 3) {
        $draftitemid = $data['autograding_file_answer'] ?? 0;

        $fs = get_file_storage();
        $usercontext = context_user::instance($data['userid'] ?? 0);
        $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);

        if (empty($draftfiles)) {
            $errors['autograding_file_answer'] = get_string('file_answer_required', 'local_autograding');
        } else {
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

function local_autograding_coursemodule_edit_post_actions(object $data, object $course): object
{
    global $CFG, $USER;

    if (!isset($data->modulename) || $data->modulename !== 'assign') {
        return $data;
    }

    $cmid = isset($data->coursemodule) ? (int) $data->coursemodule : 0;

    if ($cmid <= 0) {
        return $data;
    }

    $autogradingoption = isset($data->autograding_option) ? (int) $data->autograding_option : 0;

    $answertext = null;

    if ($autogradingoption === 2) {
        if (isset($data->autograding_text_answer)) {
            $editordata = $data->autograding_text_answer;

            if (is_array($editordata)) {
                $answertext = $editordata['text'] ?? '';
                $draftitemid = $editordata['itemid'] ?? 0;

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
                $answertext = $editordata;
            }

            $answertext = trim($answertext);
            if (empty($answertext)) {
                $answertext = null;
            }
        }
    } else if ($autogradingoption === 3) {
        if (isset($data->autograding_file_answer)) {
            $draftitemid = (int) $data->autograding_file_answer;

            $userid = $USER->id;

            $fs = get_file_storage();
            $usercontext = context_user::instance($userid);
            $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);

            $context = context_system::instance();

            file_save_draft_area_files(
                $draftitemid,
                $context->id,
                'local_autograding',
                'answer_file',
                $cmid,
                ['subdirs' => 0, 'maxfiles' => 1]
            );

            $savedfiles = $fs->get_area_files($context->id, 'local_autograding', 'answer_file', $cmid, 'id', false);
            $answertext = local_autograding_extract_pdf_text($cmid);

            if ($answertext === null) {
                $answertext = '';
            } else if (!empty($answertext)) {
            }
        } else {
        }
    }

    $result = local_autograding_save_option($cmid, $autogradingoption, $answertext);

    return $data;
}

function local_autograding_save_option(int $cmid, int $autogradingoption, ?string $answer = null): bool
{
    global $DB;

    if ($cmid <= 0) {
        return false;
    }

    if ($autogradingoption < 0 || $autogradingoption > 3) {
        $autogradingoption = 0;
    }

    if ($autogradingoption !== 2 && $autogradingoption !== 3) {
        $answer = null;
    } else {
    }

    try {
        $existing = $DB->get_record('local_autograding', ['cmid' => $cmid]);

        if ($existing !== false) {
            $existing->autograding_option = $autogradingoption;
            $existing->answer = $answer;
            $existing->timemodified = time();

            $result = $DB->update_record('local_autograding', $existing);

            if ($result) {
                $verify = $DB->get_record('local_autograding', ['cmid' => $cmid]);
            } else {
            }

            return $result;
        } else {
            $record = new stdClass();
            $record->cmid = $cmid;
            $record->autograding_option = $autogradingoption;
            $record->answer = $answer;
            $record->timecreated = time();
            $record->timemodified = time();

            $result = $DB->insert_record('local_autograding', $record);

            if ($result) {
                $verify = $DB->get_record('local_autograding', ['id' => $result]);
            } else {
            }

            return $result !== false;
        }
    } catch (\dml_exception $e) {
        return false;
    }
}

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

function local_autograding_delete_option(int $cmid): bool
{
    global $DB;

    if ($cmid <= 0) {
        return false;
    }

    try {
        $fs = get_file_storage();
        $context = context_system::instance();
        $fs->delete_area_files($context->id, 'local_autograding', 'answer_file', $cmid);

        return $DB->delete_records('local_autograding', ['cmid' => $cmid]);
    } catch (\dml_exception $e) {
        debugging('Error deleting autograding option: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

function local_autograding_extract_pdf_text(int $cmid): ?string
{
    return \local_autograding\ocr_service::extract_pdf_text_by_cmid($cmid);
}

function local_autograding_extend_navigation_course_module(
    navigation_node $navigation,
    stdClass $course,
    stdClass $module,
    cm_info $cm
): void {
    global $DB, $PAGE;

    if ($cm->modname !== 'assign') {
        return;
    }

    $autogradingconfig = $DB->get_record('local_autograding', ['cmid' => $cm->id]);
    if (!$autogradingconfig || (int) $autogradingconfig->autograding_option === 0) {
        return;
    }

    $context = context_module::instance($cm->id);
    if (!has_capability('mod/assign:grade', $context)) {
        return;
    }

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
