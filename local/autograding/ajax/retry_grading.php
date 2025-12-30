<?php
declare(strict_types=1);

/**
 * AJAX endpoint for retrying a failed grading task.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

use local_autograding\grading_status;

// Require login and sesskey.
require_login();
require_sesskey();

// Get submission ID.
$submissionid = required_param('submissionid', PARAM_INT);

// Get the status record.
$statusrecord = grading_status::get_by_submission($submissionid);

if (!$statusrecord) {
    echo json_encode(['success' => false, 'error' => 'Status record not found']);
    exit;
}

// Get course module and check capability.
$cm = get_coursemodule_from_id('assign', $statusrecord->cmid, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_login($course, false, $cm);
require_capability('mod/assign:grade', $context);

// Only allow retry on failed status.
if ($statusrecord->status !== grading_status::STATUS_FAILED) {
    echo json_encode(['success' => false, 'error' => 'Can only retry failed submissions']);
    exit;
}

try {
    // Reset status to pending.
    grading_status::reset_for_retry($submissionid);

    // Queue a new grading task.
    $taskdata = new stdClass();
    $taskdata->cmid = $statusrecord->cmid;
    $taskdata->userid = $statusrecord->userid;
    $taskdata->contextid = $context->id;
    $taskdata->submissionid = $submissionid;

    $task = new \local_autograding\task\grade_submission_task();
    $task->set_custom_data($taskdata);
    $task->set_userid($statusrecord->userid);

    \core\task\manager::queue_adhoc_task($task);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
