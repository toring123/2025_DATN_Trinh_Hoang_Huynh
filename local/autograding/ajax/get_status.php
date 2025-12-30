<?php
declare(strict_types=1);

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

use local_autograding\grading_status;

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);

if (!has_capability('mod/assign:grade', $context)) {
    echo json_encode(['success' => false, 'error' => 'No permission']);
    exit;
}

$autograding_status = grading_status::get_all_for_assignment($cmid);
$summary = grading_status::get_summary($cmid);

$records = [];
foreach ($autograding_status as $record) {
    $records[] = [
        'submissionid' => (int) $record->submissionid,
        'userid' => (int) $record->userid,
        'studentname' => fullname($record),
        'status' => $record->status,
        'attempts' => (int) $record->attempts,
        'timemodified' => (int) $record->timemodified,
        'timemodified_formatted' => userdate($record->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
        'error_message' => $record->error_message ?? '',
    ];
}

echo json_encode([
    'success' => true,
    'summary' => [
        'pending' => $summary[grading_status::STATUS_PENDING],
        'processing' => $summary[grading_status::STATUS_PROCESSING],
        'success' => $summary[grading_status::STATUS_SUCCESS],
        'failed' => $summary[grading_status::STATUS_FAILED],
    ],
    'records' => $records,
]);
