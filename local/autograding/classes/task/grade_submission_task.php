<?php
declare(strict_types=1);

/**
 * Adhoc task for asynchronous auto-grading of assignment submissions.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

namespace local_autograding\task;

use local_autograding\llm_service;
use local_autograding\ocr_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task that processes a single assignment submission for auto-grading.
 *
 * This task is queued by the event observer when a student submits an assignment.
 * It handles the heavy lifting of calling the AI API and saving the grade
 * asynchronously, preventing browser hangs and managing API rate limits.
 */
class grade_submission_task extends \core\task\adhoc_task
{

    /**
     * Get the name of the task.
     *
     * @return string
     */
    public function get_name(): string
    {
        return get_string('task_grade_submission', 'local_autograding');
    }

    /**
     * Execute the task.
     *
     * This method retrieves submission data, calls the AI API for grading,
     * and saves the resulting grade. It implements concurrency locking to prevent
     * API rate limit errors.
     *
     * @return void
     * @throws \moodle_exception If the task should be retried later.
     */
    public function execute(): void
    {
        global $DB, $CFG;

        $data = $this->get_custom_data();

        // Validate required data - cast to int first, then validate they're positive.
        $cmid = (int) ($data->cmid ?? 0);
        $userid = (int) ($data->userid ?? 0);
        $contextid = (int) ($data->contextid ?? 0);

        if ($cmid <= 0 || $userid <= 0 || $contextid <= 0) {
            mtrace("[AUTOGRADING TASK] ERROR: Missing or invalid required data (cmid: {$cmid}, userid: {$userid}, contextid: {$contextid})");
            return; // Don't retry - data is invalid.
        }

        $submissionid = isset($data->submissionid) ? (int) $data->submissionid : null;
        // If submissionid is provided but invalid (0), treat as null.
        if ($submissionid !== null && $submissionid <= 0) {
            $submissionid = null;
        }

        mtrace("[AUTOGRADING TASK] Starting grading task for cmid: {$cmid}, userid: {$userid}");

        try {
            // Step 1: Verify the course module still exists.
            $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                mtrace("[AUTOGRADING TASK] Course module {$cmid} no longer exists, skipping");
                return; // Don't retry - assignment was deleted.
            }

            // Step 2: Check if autograding is still enabled for this assignment.
            $autogradingconfig = $DB->get_record('local_autograding', ['cmid' => $cmid]);
            if (!$autogradingconfig || (int) $autogradingconfig->autograding_option === 0) {
                mtrace("[AUTOGRADING TASK] Autograding not enabled for cmid: {$cmid}");
                return; // Don't retry - autograding was disabled.
            }

            // Step 3: Get the assignment instance.
            $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', IGNORE_MISSING);
            if (!$assign) {
                mtrace("[AUTOGRADING TASK] Assignment instance not found for cmid: {$cmid}");
                return; // Don't retry - assignment was deleted.
            }

            // Step 4: Get the question (assignment description).
            $intro = strip_tags($assign->intro ?? '');
            $activity = strip_tags($assign->activity ?? '');

            $parts = [];
            if (trim($intro) !== '') {
                $parts[] = trim($intro);
            }
            if (trim($activity) !== '') {
                $parts[] = trim($activity);
            }

            $question = implode("\n\n", $parts);
            if (empty($question)) {
                mtrace("[AUTOGRADING TASK] Assignment question is empty for cmid: {$cmid}");
                return; // Don't retry - configuration error.
            }

            // Step 5: Get reference answer.
            $referenceAnswer = $autogradingconfig->answer ?? '';
            $autogradingoption = (int) $autogradingconfig->autograding_option;

            // For options 2 and 3, reference answer is required.
            if (($autogradingoption === 2 || $autogradingoption === 3) && empty($referenceAnswer)) {
                mtrace("[AUTOGRADING TASK] Reference answer required but empty for option {$autogradingoption}");
                return; // Don't retry - configuration error.
            }

            // Step 6: Get student submission text (including OCR-extracted text from images/PDFs).
            $studentResponse = $this->get_student_submission_text((int) $cm->instance, $userid, $submissionid);

            if (empty($studentResponse)) {
                mtrace("[AUTOGRADING TASK] No student submission content found for user {$userid}");
                return; // Don't retry - no submission to grade.
            }

            mtrace("[AUTOGRADING TASK] Student response: " . strlen($studentResponse) . " chars");

            // Step 7: Get AI provider configuration.
            $provider = get_config('local_autograding', 'ai_provider') ?: 'gemini';
            mtrace("[AUTOGRADING TASK] Using AI provider: {$provider}");

            // Step 8: Call AI API using the LLM service.
            // Note: Rate limiting (HTTP 429) is handled by throwing moodle_exception
            // which triggers Moodle's built-in task retry mechanism.
            $gradingResult = llm_service::grade(
                $provider,
                $question,
                $referenceAnswer,
                $studentResponse,
                $autogradingoption
            );

            if ($gradingResult === null) {
                mtrace("[AUTOGRADING TASK] AI API returned null result");
                return; // Don't retry - API returned invalid response.
            }

            mtrace("[AUTOGRADING TASK] AI API returned grade: {$gradingResult['grade']}");

            // Step 9: Save the grade.
            mtrace("[AUTOGRADING TASK] Saving grade to assignment...");
            $this->save_assignment_grade($cm, $userid, $gradingResult['grade'], $gradingResult['explanation'], $assign);
            mtrace("[AUTOGRADING TASK] Grade saved successfully for user {$userid}!");

        } catch (\moodle_exception $e) {
            // Re-throw moodle_exception to trigger retry.
            throw $e;
        } catch (\Exception $e) {
            mtrace("[AUTOGRADING TASK] Exception: " . $e->getMessage());
            // For unexpected errors, don't retry to avoid infinite loops.
            return;
        }
    }

    /**
     * Get student submission text from online text or file.
     *
     * @param int $assignid Assignment ID
     * @param int $userid User ID
     * @param int|null $submissionid Submission ID
     * @return string|null The submission text or null if not found
     */
    private function get_student_submission_text(int $assignid, int $userid, ?int $submissionid): ?string
    {
        global $DB;

        // Get the latest submission if submissionid not provided.
        if ($submissionid === null) {
            $submission = $DB->get_record('assign_submission', [
                'assignment' => $assignid,
                'userid' => $userid,
                'latest' => 1,
            ]);
        } else {
            $submission = $DB->get_record('assign_submission', ['id' => $submissionid]);
        }

        if (!$submission) {
            return null;
        }

        $text = '';

        // Try to get online text submission.
        $onlinetext = $DB->get_record('assignsubmission_onlinetext', [
            'assignment' => $assignid,
            'submission' => $submission->id,
        ]);

        if ($onlinetext && !empty($onlinetext->onlinetext)) {
            $text = strip_tags($onlinetext->onlinetext);
            $text = trim($text);
        }

        // If no online text, try file submission using OCR service.
        if (empty($text)) {
            $filesubmission = $DB->get_record('assignsubmission_file', [
                'assignment' => $assignid,
                'submission' => $submission->id,
            ]);

            if ($filesubmission && $filesubmission->numfiles > 0) {
                $text = ocr_service::extract_from_submission($submission, $assignid);
            }
        }

        return !empty($text) ? $text : null;
    }

    /**
     * Save the grade to the assignment.
     *
     * @param object $cm Course module
     * @param int $userid User ID
     * @param float $grade The grade (0-10)
     * @param string $explanation The grading explanation
     * @param object $assign The assignment record
     * @return void
     */
    private function save_assignment_grade(object $cm, int $userid, float $grade, string $explanation, object $assign): void
    {
        global $CFG, $DB;

        mtrace("[AUTOGRADING TASK] Saving grade - CM ID: {$cm->id}, User ID: {$userid}, Grade: {$grade}");

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        // Get assignment context and instance.
        $context = \context_module::instance($cm->id);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Create assign instance.
        $assigninstance = new \assign($context, $cm, $course);

        // Get the max grade for this assignment to scale the grade.
        $maxgrade = (float) $assign->grade;

        // Handle negative grades (scale reference) - use absolute value.
        if ($maxgrade < 0) {
            // Negative grade means it's a scale, get the scale max.
            $scale = $DB->get_record('scale', ['id' => abs($maxgrade)]);
            if ($scale) {
                $scaleitems = explode(',', $scale->scale);
                $maxgrade = count($scaleitems);
            } else {
                $maxgrade = 10;
            }
        }

        // Scale the grade from 0-10 to the assignment's grade range.
        $scaledgrade = ($grade / 10) * $maxgrade;

        // Build feedback with prefix.
        $feedbackprefix = get_string('autograding_feedback_prefix', 'local_autograding');
        $feedback = "{$feedbackprefix}\n\n{$explanation}";

        // Get the user's grade record or create one.
        $gradeitem = $assigninstance->get_user_grade($userid, true);

        if (!$gradeitem) {
            mtrace("[AUTOGRADING TASK] Could not get or create grade for user {$userid}");
            return;
        }

        // Set the grade.
        $gradeitem->grade = $scaledgrade;
        $gradeitem->grader = get_admin()->id; // Use admin as grader for automated grading.

        // Update the grade in database.
        $DB->update_record('assign_grades', $gradeitem);

        // Update feedback.
        $feedbackplugin = $assigninstance->get_feedback_plugin_by_type('comments');
        if ($feedbackplugin && $feedbackplugin->is_enabled()) {
            // Get or create feedback record.
            $feedbackcomment = $DB->get_record('assignfeedback_comments', [
                'assignment' => $assign->id,
                'grade' => $gradeitem->id,
            ]);

            if ($feedbackcomment) {
                $feedbackcomment->commenttext = $feedback;
                $feedbackcomment->commentformat = FORMAT_PLAIN;
                $DB->update_record('assignfeedback_comments', $feedbackcomment);
            } else {
                $feedbackcomment = new \stdClass();
                $feedbackcomment->assignment = $assign->id;
                $feedbackcomment->grade = $gradeitem->id;
                $feedbackcomment->commenttext = $feedback;
                $feedbackcomment->commentformat = FORMAT_PLAIN;
                $DB->insert_record('assignfeedback_comments', $feedbackcomment);
            }
        }

        // Update the gradebook.
        $assigninstance->update_grade($gradeitem);

        // Trigger graded event.
        \mod_assign\event\submission_graded::create_from_grade($assigninstance, $gradeitem)->trigger();
    }
}