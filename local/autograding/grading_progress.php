<?php
declare(strict_types=1);

/**
 * Grading progress page for teachers to view AI grading status.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

require_once(__DIR__ . '/../../config.php');

use local_autograding\grading_status;

// Get course module ID.
$cmid = required_param('cmid', PARAM_INT);

// Get the course module and context.
$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// Require login and capability.
require_login($course, false, $cm);
require_capability('mod/assign:grade', $context);

// Check if autograding is enabled.
$autogradingconfig = $DB->get_record('local_autograding', ['cmid' => $cmid]);
if (!$autogradingconfig || (int) $autogradingconfig->autograding_option === 0) {
    throw new moodle_exception('autograding_disabled', 'local_autograding');
}

// Set up page.
$PAGE->set_url('/local/autograding/grading_progress.php', ['cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->set_title(get_string('grading_progress_title', 'local_autograding'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('grading_progress_title', 'local_autograding'));

// Get assignment name.
$assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);

// Get all status records for this assignment.
$statusrecords = grading_status::get_all_for_assignment($cmid);
$summary = grading_status::get_summary($cmid);

// Output page.
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('grading_progress_title', 'local_autograding') . ': ' . $assign->name);

// Summary cards.
echo '<div class="row mb-4">';
echo '<div class="col-md-3"><div class="card bg-warning text-white"><div class="card-body text-center">';
echo '<h3>' . $summary[grading_status::STATUS_PENDING] . '</h3>';
echo '<p class="mb-0">' . get_string('status_pending', 'local_autograding') . '</p>';
echo '</div></div></div>';

echo '<div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center">';
echo '<h3>' . $summary[grading_status::STATUS_PROCESSING] . '</h3>';
echo '<p class="mb-0">' . get_string('status_processing', 'local_autograding') . '</p>';
echo '</div></div></div>';

echo '<div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center">';
echo '<h3>' . $summary[grading_status::STATUS_SUCCESS] . '</h3>';
echo '<p class="mb-0">' . get_string('status_success', 'local_autograding') . '</p>';
echo '</div></div></div>';

echo '<div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body text-center">';
echo '<h3>' . $summary[grading_status::STATUS_FAILED] . '</h3>';
echo '<p class="mb-0">' . get_string('status_failed', 'local_autograding') . '</p>';
echo '</div></div></div>';
echo '</div>';

// Auto-refresh info.
echo '<p class="text-muted small">' . get_string('auto_refresh_info', 'local_autograding') . '</p>';

// Status table.
if (empty($statusrecords)) {
    echo $OUTPUT->notification(get_string('no_submissions_yet', 'local_autograding'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('student', 'local_autograding'),
        get_string('status', 'local_autograding'),
        get_string('attempts', 'local_autograding'),
        get_string('last_updated', 'local_autograding'),
        get_string('error_message', 'local_autograding'),
        get_string('actions', 'local_autograding'),
    ];
    $table->attributes['class'] = 'table table-striped table-hover';

    foreach ($statusrecords as $record) {
        $studentname = fullname($record);

        // Status badge.
        switch ($record->status) {
            case grading_status::STATUS_PENDING:
                $statusbadge = '<span class="badge badge-warning">' . get_string('status_pending', 'local_autograding') . '</span>';
                break;
            case grading_status::STATUS_PROCESSING:
                $statusbadge = '<span class="badge badge-info">' . get_string('status_processing', 'local_autograding') . '</span>';
                break;
            case grading_status::STATUS_SUCCESS:
                $statusbadge = '<span class="badge badge-success">' . get_string('status_success', 'local_autograding') . '</span>';
                break;
            case grading_status::STATUS_FAILED:
                $statusbadge = '<span class="badge badge-danger">' . get_string('status_failed', 'local_autograding') . '</span>';
                break;
            default:
                $statusbadge = '<span class="badge badge-secondary">' . $record->status . '</span>';
        }

        // Time.
        $timestr = userdate($record->timemodified, get_string('strftimedatetimeshort', 'langconfig'));

        // Error message (truncated).
        $errormsg = $record->error_message ?? '-';
        if (strlen($errormsg) > 50) {
            $errormsg = '<span title="' . s($record->error_message) . '">' . s(substr($errormsg, 0, 50)) . '...</span>';
        } else {
            $errormsg = s($errormsg);
        }

        // Actions.
        $actions = [];

        if ($record->status === grading_status::STATUS_FAILED) {
            // Retry button.
            $retryurl = new moodle_url('/local/autograding/ajax/retry_grading.php', [
                'submissionid' => $record->submissionid,
                'sesskey' => sesskey(),
            ]);
            $actions[] = '<button class="btn btn-sm btn-primary retry-btn" data-submissionid="' . $record->submissionid . '">' .
                get_string('retry', 'local_autograding') . '</button>';

            // Grade manually link.
            $gradeurl = new moodle_url('/mod/assign/view.php', [
                'id' => $cmid,
                'action' => 'grader',
                'userid' => $record->userid,
            ]);
            $actions[] = '<a href="' . $gradeurl . '" class="btn btn-sm btn-secondary">' .
                get_string('grade_manually', 'local_autograding') . '</a>';
        } elseif ($record->status === grading_status::STATUS_SUCCESS) {
            // View grade link.
            $gradeurl = new moodle_url('/mod/assign/view.php', [
                'id' => $cmid,
                'action' => 'grader',
                'userid' => $record->userid,
            ]);
            $actions[] = '<a href="' . $gradeurl . '" class="btn btn-sm btn-outline-secondary">' .
                get_string('view_grade', 'local_autograding') . '</a>';
        }

        $table->data[] = [
            $studentname,
            $statusbadge,
            $record->attempts,
            $timestr,
            $errormsg,
            implode(' ', $actions),
        ];
    }

    echo html_writer::table($table);
}

// Back button.
$backurl = new moodle_url('/mod/assign/view.php', ['id' => $cmid, 'action' => 'grading']);
echo '<p><a href="' . $backurl . '" class="btn btn-secondary">' . get_string('back_to_grading', 'local_autograding') . '</a></p>';

// JavaScript for retry button and auto-refresh.
$PAGE->requires->js_amd_inline("
    require(['jquery'], function($) {
        // Retry button handler.
        $('.retry-btn').on('click', function() {
            var btn = $(this);
            var submissionid = btn.data('submissionid');
            btn.prop('disabled', true).text('" . get_string('retrying', 'local_autograding') . "');

            $.ajax({
                url: M.cfg.wwwroot + '/local/autograding/ajax/retry_grading.php',
                method: 'POST',
                data: {
                    submissionid: submissionid,
                    sesskey: M.cfg.sesskey
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.error || 'Retry failed');
                        btn.prop('disabled', false).text('" . get_string('retry', 'local_autograding') . "');
                    }
                },
                error: function() {
                    alert('Request failed');
                    btn.prop('disabled', false).text('" . get_string('retry', 'local_autograding') . "');
                }
            });
        });

        // Auto-refresh every 10 seconds.
        setTimeout(function() {
            location.reload();
        }, 10000);
    });
");

echo $OUTPUT->footer();
