<?php
declare(strict_types=1);

/**
 * Event observer class for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

namespace local_autograding;

use core\event\course_module_created;
use core\event\course_module_updated;
use core\event\course_module_deleted;
use mod_assign\event\assessable_submitted;
use local_autograding\grading_status;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer class.
 */
class observer
{

    /**
     * Handle course module created event.
     *
     * @param course_module_created $event The event
     * @return void
     */
    public static function course_module_created(course_module_created $event): void
    {
        // Only process assign modules.
        if ($event->other['modulename'] !== 'assign') {
            return;
        }

        $cmid = (int) $event->objectid;
        if ($cmid <= 0) {
            return;
        }

        // The data should already be saved by local_autograding_coursemodule_edit_post_actions.
        // This is a backup in case that hook doesn't fire.
        self::save_from_request($cmid);
    }

    /**
     * Handle course module updated event.
     *
     * @param course_module_updated $event The event
     * @return void
     */
    public static function course_module_updated(course_module_updated $event): void
    {
        // Only process assign modules.
        if ($event->other['modulename'] !== 'assign') {
            return;
        }

        $cmid = (int) $event->objectid;
        if ($cmid <= 0) {
            return;
        }

        // The data should already be saved by local_autograding_coursemodule_edit_post_actions.
        // This is a backup in case that hook doesn't fire.
        self::save_from_request($cmid);
    }

    /**
     * Helper function to save data from request parameters.
     *
     * @param int $cmid Course module ID
     * @return void
     */
    private static function save_from_request(int $cmid): void
    {
        // Get the autograding option from the form data.
        $autogradingoption = optional_param('autograding_option', null, PARAM_INT);

        if ($autogradingoption === null) {
            return;
        }

        // Get the text answer if provided.
        $textanswer = null;
        if ($autogradingoption === 2) {
            // Handle both array format (from Moodle editor) and plain string format.
            $textanswerarray = optional_param_array('autograding_text_answer', null, PARAM_RAW);
            if ($textanswerarray !== null && is_array($textanswerarray)) {
                // Editor format: array with 'text' key.
                $textanswer = isset($textanswerarray['text']) ? clean_param($textanswerarray['text'], PARAM_TEXT) : '';
            } else {
                // Plain string format.
                $textanswer = optional_param('autograding_text_answer', '', PARAM_TEXT);
            }
            $textanswer = trim($textanswer);

            // Ensure it's not empty for option 2.
            if (empty($textanswer)) {
                $textanswer = null;
            }
        } else if ($autogradingoption === 3) {
            // Option 3 is handled by local_autograding_coursemodule_edit_post_actions
            // Don't save here to avoid overwriting the PDF-extracted answer with null
            return;
        }

        // Save the option with answer.
        local_autograding_save_option($cmid, $autogradingoption, $textanswer);
    }

    /**
     * Handle course module deleted event.
     *
     * @param course_module_deleted $event The event
     * @return void
     */
    public static function course_module_deleted(course_module_deleted $event): void
    {
        // Only process assign modules.
        if ($event->other['modulename'] !== 'assign') {
            return;
        }

        $cmid = $event->objectid;
        if ($cmid <= 0) {
            return;
        }

        // Delete the autograding option.
        local_autograding_delete_option($cmid);
    }

    /**
     * Handle assignment submission event for auto-grading.
     *
     * This method queues an adhoc task to process the grading asynchronously,
     * preventing browser hangs and managing API rate limits effectively.
     *
     * @param assessable_submitted $event The event
     * @return void
     */
    public static function assessable_submitted(assessable_submitted $event): void
    {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        error_log("[AUTOGRADING] ========================================");
        error_log("[AUTOGRADING] ASSESSABLE_SUBMITTED EVENT TRIGGERED");
        error_log("[AUTOGRADING] ========================================");

        try {
            $contextid = $event->contextid;
            $userid = (int) $event->userid;

            error_log("[AUTOGRADING] Context ID: " . $contextid);
            error_log("[AUTOGRADING] User ID: " . $userid);

            // Get context and course module.
            $context = \context::instance_by_id($contextid);
            if (!($context instanceof \context_module)) {
                error_log("[AUTOGRADING] ERROR: Context is not a module context, skipping");
                return;
            }

            $cmid = (int) $context->instanceid;
            $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);

            if (!$cm) {
                error_log("[AUTOGRADING] ERROR: Course module not found");
                return;
            }

            error_log("[AUTOGRADING] Course Module ID: " . $cmid);
            error_log("[AUTOGRADING] Assignment Instance ID: " . $cm->instance);

            // Quick check: Is autograding enabled for this assignment?
            $autogradingconfig = $DB->get_record('local_autograding', ['cmid' => $cmid]);
            if (!$autogradingconfig || (int) $autogradingconfig->autograding_option === 0) {
                error_log("[AUTOGRADING] Autograding not enabled for this assignment (cmid: $cmid)");
                return;
            }

            // Robust submission ID retrieval: Instantiate assign class and get submission from DB.
            $course = $DB->get_record('course', ['id' => $cm->course], '*', IGNORE_MISSING);
            if (!$course) {
                error_log("[AUTOGRADING] ERROR: Course not found for cmid: {$cmid}");
                return;
            }

            $assign = new \assign($context, $cm, $course);
            $submission = $assign->get_user_submission($userid, false); // false = do not create if missing

            if (!$submission || empty($submission->id)) {
                error_log("[AUTOGRADING] ERROR: No submission found for user {$userid} in assignment {$cmid}");
                return;
            }

            $submissionid = (int) $submission->id;
            error_log("[AUTOGRADING] Submission ID (from DB): " . $submissionid);

            // Deduplication: Check if a task for this submission already exists in the queue.
            $existingtasks = \core\task\manager::get_adhoc_tasks('\\local_autograding\\task\\grade_submission_task');
            foreach ($existingtasks as $existingtask) {
                $existingdata = $existingtask->get_custom_data();
                if (isset($existingdata->submissionid) && (int) $existingdata->submissionid === $submissionid) {
                    error_log("[AUTOGRADING] Task already queued for submission {$submissionid}, skipping duplicate");
                    return;
                }
            }

            error_log("[AUTOGRADING] Autograding is enabled, queueing adhoc task...");

            // Prepare the data for the adhoc task.
            $taskdata = new \stdClass();
            $taskdata->cmid = $cmid;
            $taskdata->userid = $userid;
            $taskdata->contextid = $contextid;
            $taskdata->submissionid = $submissionid;

            // Create status record for tracking.
            grading_status::create_or_update($cmid, $userid, $submissionid, grading_status::STATUS_PENDING);
            error_log("[AUTOGRADING] Status record created for submission {$submissionid}");

            // Create and queue the adhoc task.
            $task = new \local_autograding\task\grade_submission_task();
            $task->set_custom_data($taskdata);

            // Set the user who submitted (for context in task execution).
            $task->set_userid($userid);

            // Queue the task for asynchronous processing.
            \core\task\manager::queue_adhoc_task($task);

            error_log("[AUTOGRADING] Adhoc task queued successfully for submission {$submissionid}!");
            error_log("[AUTOGRADING] ========================================");

        } catch (\Exception $e) {
            error_log("[AUTOGRADING] EXCEPTION while queueing task: " . $e->getMessage());
            error_log("[AUTOGRADING] Stack trace: " . $e->getTraceAsString());
            debugging('Autograding error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}