<?php
declare(strict_types=1);

/**
 * Scheduled task to send daily digest of failed grading attempts.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

namespace local_autograding\task;

use local_autograding\grading_status;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task that sends a daily digest of failed grading attempts to teachers.
 */
class send_failure_digest extends \core\task\scheduled_task
{
    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name(): string
    {
        return get_string('task_send_failure_digest', 'local_autograding');
    }

    /**
     * Execute the task.
     */
    public function execute(): void
    {
        global $DB;

        mtrace("[FAILURE DIGEST] Starting failure digest task...");

        // Get failures from last 24 hours.
        $since = time() - (24 * 60 * 60);
        $failedrecords = grading_status::get_failed_for_digest($since);

        if (empty($failedrecords)) {
            mtrace("[FAILURE DIGEST] No failed submissions in the last 24 hours.");
            return;
        }

        mtrace("[FAILURE DIGEST] Found " . count($failedrecords) . " failed submissions.");

        // Group by course and assignment.
        $grouped = [];
        foreach ($failedrecords as $record) {
            $key = $record->course . '_' . $record->cmid;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'courseid' => $record->course,
                    'cmid' => $record->cmid,
                    'failures' => [],
                ];
            }
            $grouped[$key]['failures'][] = $record;
        }

        // Send notifications to course teachers.
        foreach ($grouped as $group) {
            $this->send_notification_to_teachers($group);
        }

        mtrace("[FAILURE DIGEST] Digest notifications sent.");
    }

    /**
     * Send notification to teachers of a course about failed grading.
     *
     * @param array $group Group data with courseid, cmid, and failures
     */
    private function send_notification_to_teachers(array $group): void
    {
        global $DB;

        $courseid = $group['courseid'];
        $cmid = $group['cmid'];
        $failures = $group['failures'];

        // Get course and assignment info.
        $course = $DB->get_record('course', ['id' => $courseid]);
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
        if (!$course || !$cm) {
            return;
        }

        $assign = $DB->get_record('assign', ['id' => $cm->instance]);
        if (!$assign) {
            return;
        }

        // Get teachers enrolled in the course.
        $context = \context_course::instance($courseid);
        $teachers = get_enrolled_users($context, 'mod/assign:grade');

        if (empty($teachers)) {
            mtrace("[FAILURE DIGEST] No teachers found for course {$courseid}");
            return;
        }

        // Build message.
        $failurecount = count($failures);
        $studentnames = [];
        foreach ($failures as $failure) {
            $studentnames[] = $failure->firstname . ' ' . $failure->lastname;
        }

        $progressurl = new \moodle_url('/local/autograding/grading_progress.php', ['cmid' => $cmid]);

        $messagetext = get_string('digest_message', 'local_autograding', [
            'count' => $failurecount,
            'assignmentname' => $assign->name,
            'coursename' => $course->fullname,
            'students' => implode(', ', array_slice($studentnames, 0, 5)),
            'more' => $failurecount > 5 ? get_string('and_more', 'local_autograding', $failurecount - 5) : '',
            'url' => $progressurl->out(false),
        ]);

        // Send to each teacher.
        foreach ($teachers as $teacher) {
            $message = new \core\message\message();
            $message->component = 'local_autograding';
            $message->name = 'grading_failure';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $teacher;
            $message->subject = get_string('digest_subject', 'local_autograding', $assign->name);
            $message->fullmessage = $messagetext;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = nl2br(s($messagetext));
            $message->smallmessage = get_string('digest_small', 'local_autograding', $failurecount);
            $message->notification = 1;
            $message->contexturl = $progressurl;
            $message->contexturlname = get_string('grading_progress_title', 'local_autograding');

            message_send($message);
            mtrace("[FAILURE DIGEST] Notification sent to teacher {$teacher->id}");
        }
    }
}
