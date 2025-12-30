<?php
declare(strict_types=1);
require_once(__DIR__ . '/../../config.php');

use local_autograding\grading_status;

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);

if (!has_capability('mod/assign:grade', $context)) {
    $assignurl = new moodle_url('/mod/assign/view.php', ['id' => $cmid]);
    redirect($assignurl, get_string('nopermissions', 'error', get_string('grading_progress_title', 'local_autograding')), null, \core\output\notification::NOTIFY_ERROR);
}

$autogradingconfig = $DB->get_record('local_autograding', ['cmid' => $cmid]);
if (!$autogradingconfig || (int) $autogradingconfig->autograding_option === 0) {
    throw new moodle_exception('autograding_disabled', 'local_autograding');
}

$PAGE->set_url('/local/autograding/grading_progress.php', ['cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->set_title(get_string('grading_progress_title', 'local_autograding'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('grading_progress_title', 'local_autograding'));

$assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);

$autograding_status = grading_status::get_all_for_assignment($cmid);

$summary = grading_status::get_summary($cmid);

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('grading_progress_title', 'local_autograding') . ': ' . $assign->name);

echo '<div class="row mb-4">';
echo '<div class="col-md-3"><div class="card bg-warning text-white"><div class="card-body text-center">';
echo '<h3 id="summary-pending">' . $summary[grading_status::STATUS_PENDING] . '</h3>';
echo '<p class="mb-0">' . get_string('status_pending', 'local_autograding') . '</p>';
echo '</div></div></div>';

echo '<div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center">';
echo '<h3 id="summary-processing">' . $summary[grading_status::STATUS_PROCESSING] . '</h3>';
echo '<p class="mb-0">' . get_string('status_processing', 'local_autograding') . '</p>';
echo '</div></div></div>';

echo '<div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center">';
echo '<h3 id="summary-success">' . $summary[grading_status::STATUS_SUCCESS] . '</h3>';
echo '<p class="mb-0">' . get_string('status_success', 'local_autograding') . '</p>';
echo '</div></div></div>';

echo '<div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body text-center">';
echo '<h3 id="summary-failed">' . $summary[grading_status::STATUS_FAILED] . '</h3>';
echo '<p class="mb-0">' . get_string('status_failed', 'local_autograding') . '</p>';
echo '</div></div></div>';
echo '</div>';

echo '<p class="text-muted small">' . get_string('auto_refresh_info', 'local_autograding') . '</p>';

if (empty($autograding_status)) {
    echo $OUTPUT->notification(get_string('no_submissions_yet', 'local_autograding'), 'info');
} else {
    echo '<table class="table table-striped table-hover" id="grading-status-table">';
    echo '<thead><tr>';
    echo '<th>' . get_string('student', 'local_autograding') . '</th>';
    echo '<th>' . get_string('status', 'local_autograding') . '</th>';
    echo '<th>' . get_string('attempts', 'local_autograding') . '</th>';
    echo '<th>' . get_string('last_updated', 'local_autograding') . '</th>';
    echo '<th>' . get_string('error_message', 'local_autograding') . '</th>';
    echo '<th>' . get_string('actions', 'local_autograding') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($autograding_status as $record) {
        $studentname = fullname($record);

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

        $timestr = userdate($record->timemodified, get_string('strftimedatetimeshort', 'langconfig'));

        $errormsg = $record->error_message ?? '-';
        if (strlen($errormsg) > 50) {
            $errormsg = '<span title="' . s($record->error_message) . '">' . s(substr($errormsg, 0, 50)) . '...</span>';
        } else {
            $errormsg = s($errormsg);
        }

        $actions = '';

        if ($record->status === grading_status::STATUS_FAILED) {
            $actions = '<button class="btn btn-sm btn-primary retry-btn" data-submissionid="' . $record->submissionid . '">' .
                get_string('retry', 'local_autograding') . '</button> ';
            $gradeurl = new moodle_url('/mod/assign/view.php', [
                'id' => $cmid,
                'action' => 'grader',
                'userid' => $record->userid,
            ]);
            $actions .= '<a href="' . $gradeurl . '" class="btn btn-sm btn-secondary">' .
                get_string('grade_manually', 'local_autograding') . '</a>';
        } elseif ($record->status === grading_status::STATUS_SUCCESS) {
            $gradeurl = new moodle_url('/mod/assign/view.php', [
                'id' => $cmid,
                'action' => 'grader',
                'userid' => $record->userid,
            ]);
            $actions = '<a href="' . $gradeurl . '" class="btn btn-sm btn-outline-secondary">' .
                get_string('view_grade', 'local_autograding') . '</a>';
        }

        echo '<tr data-submissionid="' . $record->submissionid . '" data-userid="' . $record->userid . '">';
        echo '<td class="student-cell">' . $studentname . '</td>';
        echo '<td class="status-cell">' . $statusbadge . '</td>';
        echo '<td class="attempts-cell">' . $record->attempts . '</td>';
        echo '<td class="time-cell">' . $timestr . '</td>';
        echo '<td class="error-cell">' . $errormsg . '</td>';
        echo '<td class="actions-cell">' . $actions . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

$backurl = new moodle_url('/mod/assign/view.php', ['id' => $cmid, 'action' => 'grading']);
echo '<p><a href="' . $backurl . '" class="btn btn-secondary">' . get_string('back_to_grading', 'local_autograding') . '</a></p>';

$PAGE->requires->js_amd_inline("
    require(['jquery'], function($) {
        var cmid = " . $cmid . ";
        var pollIntervalFast = 3000;
        var pollIntervalSlow = 30000;

        var statusBadges = {
            'pending': '<span class=\"badge badge-warning\">" . get_string('status_pending', 'local_autograding') . "</span>',
            'processing': '<span class=\"badge badge-info\">" . get_string('status_processing', 'local_autograding') . "</span>',
            'success': '<span class=\"badge badge-success\">" . get_string('status_success', 'local_autograding') . "</span>',
            'failed': '<span class=\"badge badge-danger\">" . get_string('status_failed', 'local_autograding') . "</span>'
        };

        function updateSummary(summary) {
            $('#summary-pending').text(summary.pending);
            $('#summary-processing').text(summary.processing);
            $('#summary-success').text(summary.success);
            $('#summary-failed').text(summary.failed);
        }

        function updateRow(record) {
            var row = $('tr[data-submissionid=\"' + record.submissionid + '\"]');
            if (row.length === 0) {
                location.reload();
                return;
            }

            row.find('.status-cell').html(statusBadges[record.status] || record.status);

            row.find('.attempts-cell').text(record.attempts);

            row.find('.time-cell').text(record.timemodified_formatted);

            var errormsg = record.error_message || '-';
            if (errormsg.length > 50) {
                errormsg = '<span title=\"' + errormsg + '\">' + errormsg.substring(0, 50) + '...</span>';
            }
            row.find('.error-cell').html(errormsg);

            var actionsCell = row.find('.actions-cell');
            if (record.status === 'failed') {
                if (actionsCell.find('.retry-btn').length === 0) {
                    actionsCell.html(
                        '<button class=\"btn btn-sm btn-primary retry-btn\" data-submissionid=\"' + record.submissionid + '\">" . get_string('retry', 'local_autograding') . "</button> ' +
                        '<a href=\"' + M.cfg.wwwroot + '/mod/assign/view.php?id=' + cmid + '&action=grader&userid=' + record.userid + '\" class=\"btn btn-sm btn-secondary\">" . get_string('grade_manually', 'local_autograding') . "</a>'
                    );
                    bindRetryHandler(actionsCell.find('.retry-btn'));
                }
            } else if (record.status === 'success') {
                actionsCell.html(
                    '<a href=\"' + M.cfg.wwwroot + '/mod/assign/view.php?id=' + cmid + '&action=grader&userid=' + record.userid + '\" class=\"btn btn-sm btn-outline-secondary\">" . get_string('view_grade', 'local_autograding') . "</a>'
                );
            } else {
                actionsCell.html('');
            }
        }

        function bindRetryHandler(btn) {
            btn.on('click', function() {
                var button = $(this);
                var submissionid = button.data('submissionid');
                button.prop('disabled', true).text('" . get_string('retrying', 'local_autograding') . "');

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
                            pollStatus();
                        } else {
                            alert(response.error || 'Retry failed');
                            button.prop('disabled', false).text('" . get_string('retry', 'local_autograding') . "');
                        }
                    },
                    error: function() {
                        alert('Request failed');
                        button.prop('disabled', false).text('" . get_string('retry', 'local_autograding') . "');
                    }
                });
            });
        }

        function pollStatus() {
            $.ajax({
                url: M.cfg.wwwroot + '/local/autograding/ajax/get_status.php',
                method: 'GET',
                data: { cmid: cmid },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        updateSummary(response.summary);
                        response.records.forEach(function(record) {
                            updateRow(record);
                        });

                        var hasActiveTasks = response.summary.pending > 0 || response.summary.processing > 0;
                        if (hasActiveTasks) {
                            currentInterval = pollIntervalFast;
                        } else {
                            currentInterval = pollIntervalSlow;
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[Autograding] AJAX error:', status, error);
                },
                complete: function() {
                    setTimeout(pollStatus, currentInterval);
                }
            });
        }

        $('.retry-btn').each(function() {
            bindRetryHandler($(this));
        });

        var hasInitialActive = " . ($summary[grading_status::STATUS_PENDING] + $summary[grading_status::STATUS_PROCESSING]) . " > 0;
        var currentInterval = hasInitialActive ? pollIntervalFast : pollIntervalSlow;

        setTimeout(pollStatus, currentInterval);
    });
");

echo $OUTPUT->footer();
