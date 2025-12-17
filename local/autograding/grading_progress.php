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

// Require login and check capability with graceful handling.
require_login($course, false, $cm);

// Check capability - redirect instead of throwing exception with stack trace.
if (!has_capability('mod/assign:grade', $context)) {
    $assignurl = new moodle_url('/mod/assign/view.php', ['id' => $cmid]);
    redirect($assignurl, get_string('nopermissions', 'error', get_string('grading_progress_title', 'local_autograding')), null, \core\output\notification::NOTIFY_ERROR);
}


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

// Auto-refresh info.
echo '<p class="text-muted small">' . get_string('auto_refresh_info', 'local_autograding') . '</p>';

// Status table.
if (empty($statusrecords)) {
    echo $OUTPUT->notification(get_string('no_submissions_yet', 'local_autograding'), 'info');
} else {
    // Build table manually for better control over data attributes.
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

        // Row with data attribute.
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

// Back button.
$backurl = new moodle_url('/mod/assign/view.php', ['id' => $cmid, 'action' => 'grading']);
echo '<p><a href="' . $backurl . '" class="btn btn-secondary">' . get_string('back_to_grading', 'local_autograding') . '</a></p>';

// JavaScript for retry button and smooth AJAX polling.
$PAGE->requires->js_amd_inline("
    require(['jquery'], function($) {
        var cmid = " . $cmid . ";
        var pollIntervalFast = 3000; // 3 seconds when active
        var pollIntervalSlow = 30000; // 30 seconds when idle

        // Status badge classes mapping.
        var statusBadges = {
            'pending': '<span class=\"badge badge-warning\">" . get_string('status_pending', 'local_autograding') . "</span>',
            'processing': '<span class=\"badge badge-info\">" . get_string('status_processing', 'local_autograding') . "</span>',
            'success': '<span class=\"badge badge-success\">" . get_string('status_success', 'local_autograding') . "</span>',
            'failed': '<span class=\"badge badge-danger\">" . get_string('status_failed', 'local_autograding') . "</span>'
        };

        // Update summary cards.
        function updateSummary(summary) {
            $('#summary-pending').text(summary.pending);
            $('#summary-processing').text(summary.processing);
            $('#summary-success').text(summary.success);
            $('#summary-failed').text(summary.failed);
        }

        // Update table row.
        function updateRow(record) {
            var row = $('tr[data-submissionid=\"' + record.submissionid + '\"]');
            if (row.length === 0) {
                // New record - reload page to add it.
                location.reload();
                return;
            }

            // Update status badge.
            row.find('.status-cell').html(statusBadges[record.status] || record.status);

            // Update attempts.
            row.find('.attempts-cell').text(record.attempts);

            // Update time.
            row.find('.time-cell').text(record.timemodified_formatted);

            // Update error message.
            var errormsg = record.error_message || '-';
            if (errormsg.length > 50) {
                errormsg = '<span title=\"' + errormsg + '\">' + errormsg.substring(0, 50) + '...</span>';
            }
            row.find('.error-cell').html(errormsg);

            // Update actions based on status.
            var actionsCell = row.find('.actions-cell');
            if (record.status === 'failed') {
                if (actionsCell.find('.retry-btn').length === 0) {
                    // Add retry and grade manually buttons.
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

        // Bind retry handler to button.
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
                            // Trigger immediate poll to update UI.
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

        // Poll for status updates.
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

                        // Adaptive polling: fast when active, slow when idle.
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
                    // Schedule next poll with current interval.
                    setTimeout(pollStatus, currentInterval);
                }
            });
        }

        // Bind retry handlers to existing buttons.
        $('.retry-btn').each(function() {
            bindRetryHandler($(this));
        });

        // Check if there are active tasks initially.
        var hasInitialActive = " . ($summary[grading_status::STATUS_PENDING] + $summary[grading_status::STATUS_PROCESSING]) . " > 0;
        var currentInterval = hasInitialActive ? pollIntervalFast : pollIntervalSlow;

        // Start polling after initial delay.
        setTimeout(pollStatus, currentInterval);
    });
");

echo $OUTPUT->footer();
