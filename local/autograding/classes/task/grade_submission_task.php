<?php
declare(strict_types=1);
namespace local_autograding\task;

use local_autograding\llm_service;
use local_autograding\ocr_service;
use local_autograding\grading_status;

defined('MOODLE_INTERNAL') || die();
class grade_submission_task extends \core\task\adhoc_task
{
    public function get_name(): string
    {
        return get_string('task_grade_submission', 'local_autograding');
    }

    public function execute(): void
    {
        global $DB, $CFG;

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_autograding');
        $lock = $lockfactory->get_lock('grade_submission_task', 0);

        if (!$lock) {
            mtrace("[AUTOGRADING TASK] Another grading task is running, rescheduling...");
            $task = new self();
            $task->set_custom_data($this->get_custom_data());
            $task->set_next_run_time(time() + 10);
            \core\task\manager::queue_adhoc_task($task);
            return;
        }

        try {
            $this->execute_grading();
        } finally {
            $lock->release();
        }
    }

    private function execute_grading(): void
    {
        global $DB, $CFG;

        $data = $this->get_custom_data();

        $cmid = (int) ($data->cmid ?? 0);
        $userid = (int) ($data->userid ?? 0);
        $contextid = (int) ($data->contextid ?? 0);

        if ($cmid <= 0 || $userid <= 0 || $contextid <= 0) {
            mtrace("[AUTOGRADING TASK] ERROR: Missing or invalid required data (cmid: {$cmid}, userid: {$userid}, contextid: {$contextid})");
            return;
        }

        $submissionid = isset($data->submissionid) ? (int) $data->submissionid : null;
        if ($submissionid !== null && $submissionid <= 0) {
            $submissionid = null;
        }

        mtrace("[AUTOGRADING TASK] Starting grading task for cmid: {$cmid}, userid: {$userid}");

        if ($submissionid !== null) {
            $attempts = grading_status::get_attempts($submissionid);
            mtrace("[AUTOGRADING TASK] Attempt {$attempts} of " . grading_status::MAX_ATTEMPTS);

            if (grading_status::is_max_attempts_reached($submissionid)) {
                mtrace("[AUTOGRADING TASK] Max attempts reached for submission {$submissionid}, marking as failed");
                grading_status::set_failed($submissionid, 'Maximum retry attempts exceeded');
                return;
            }

            grading_status::set_processing($submissionid);
        }

        try {
            $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                mtrace("[AUTOGRADING TASK] Course module {$cmid} no longer exists, skipping");
                $this->handle_permanent_failure($submissionid, 'Assignment was deleted');
                return;
            }

            $autogradingconfig = $DB->get_record('local_autograding', ['cmid' => $cmid]);
            if (!$autogradingconfig || (int) $autogradingconfig->autograding_option === 0) {
                mtrace("[AUTOGRADING TASK] Autograding not enabled for cmid: {$cmid}");
                $this->handle_permanent_failure($submissionid, 'Autograding was disabled');
                return;
            }

            $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', IGNORE_MISSING);
            if (!$assign) {
                mtrace("[AUTOGRADING TASK] Assignment instance not found for cmid: {$cmid}");
                $this->handle_permanent_failure($submissionid, 'Assignment was deleted');
                return;
            }

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
                $this->handle_permanent_failure($submissionid, 'Assignment has no question/description');
                return;
            }

            $referenceAnswer = $autogradingconfig->answer ?? '';
            $autogradingoption = (int) $autogradingconfig->autograding_option;

            if (($autogradingoption === 2 || $autogradingoption === 3) && empty($referenceAnswer)) {
                mtrace("[AUTOGRADING TASK] Reference answer required but empty for option {$autogradingoption}");
                $this->handle_permanent_failure($submissionid, 'Reference answer not configured');
                return;
            }

            $studentResponse = $this->get_student_submission_text((int) $cm->instance, $userid, $submissionid);

            if (empty($studentResponse)) {
                mtrace("[AUTOGRADING TASK] No student submission content found for user {$userid}");
                $this->handle_permanent_failure($submissionid, 'No submission content to grade');
                return;
            }

            mtrace("[AUTOGRADING TASK] Student response: " . strlen($studentResponse) . " chars");

            $provider = get_config('local_autograding', 'ai_provider') ?: 'gemini';
            mtrace("[AUTOGRADING TASK] Using AI provider: {$provider}");

            $gradingResult = llm_service::grade(
                $provider,
                $question,
                $referenceAnswer,
                $studentResponse,
                $autogradingoption
            );

            if ($gradingResult === null) {
                mtrace("[AUTOGRADING TASK] AI API returned null result");
                $this->handle_retryable_failure($submissionid, 'AI API returned invalid response');
                return;
            }

            mtrace("[AUTOGRADING TASK] AI API returned grade: {$gradingResult['grade']}");

            mtrace("[AUTOGRADING TASK] Saving grade to assignment...");
            $this->save_assignment_grade($cm, $userid, $gradingResult['grade'], $gradingResult['explanation'], $assign);
            mtrace("[AUTOGRADING TASK] Grade saved successfully for user {$userid}!");

            if ($submissionid !== null) {
                grading_status::set_success($submissionid);
                mtrace("[AUTOGRADING TASK] Status updated to SUCCESS");
            }

        } catch (\moodle_exception $e) {
            $errorCode = $e->errorcode ?? '';
            if ($errorCode === 'ratelimited' || $errorCode === 'servererror') {
                $this->handle_retryable_failure($submissionid, $e->getMessage());
                throw $e;
            }
            $this->handle_permanent_failure($submissionid, $e->getMessage());
        } catch (\Exception $e) {
            mtrace("[AUTOGRADING TASK] Exception: " . $e->getMessage());
            $this->handle_retryable_failure($submissionid, $e->getMessage());
        }
    }

    private function handle_retryable_failure(?int $submissionid, string $errorMessage): void
    {
        if ($submissionid === null) {
            return;
        }

        if (grading_status::is_max_attempts_reached($submissionid)) {
            mtrace("[AUTOGRADING TASK] Max attempts reached, marking as permanently failed");
            grading_status::set_failed($submissionid, $errorMessage);
        } else {
            mtrace("[AUTOGRADING TASK] Will retry (attempts: " . grading_status::get_attempts($submissionid) . ")");
        }
    }

    private function handle_permanent_failure(?int $submissionid, string $errorMessage): void
    {
        if ($submissionid !== null) {
            grading_status::set_failed($submissionid, $errorMessage);
            mtrace("[AUTOGRADING TASK] Status updated to FAILED: {$errorMessage}");
        }
    }

    private function get_student_submission_text(int $assignid, int $userid, ?int $submissionid): ?string
    {
        global $DB;

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

        $onlinetext = $DB->get_record('assignsubmission_onlinetext', [
            'assignment' => $assignid,
            'submission' => $submission->id,
        ]);

        if ($onlinetext && !empty($onlinetext->onlinetext)) {
            $text = strip_tags($onlinetext->onlinetext);
            $text = trim($text);
        }

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

    private function save_assignment_grade(object $cm, int $userid, float $grade, string $explanation, object $assign): void
    {
        global $CFG, $DB;

        mtrace("[AUTOGRADING TASK] Saving grade - CM ID: {$cm->id}, User ID: {$userid}, Grade: {$grade}");

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $context = \context_module::instance($cm->id);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        $assigninstance = new \assign($context, $cm, $course);

        $maxgrade = (float) $assign->grade;

        if ($maxgrade < 0) {
            $scale = $DB->get_record('scale', ['id' => abs($maxgrade)]);
            if ($scale) {
                $scaleitems = explode(',', $scale->scale);
                $maxgrade = count($scaleitems);
            } else {
                $maxgrade = 10;
            }
        }

        $scaledgrade = ($grade / 10) * $maxgrade;

        $feedbackprefix = get_string('autograding_feedback_prefix', 'local_autograding');
        $feedback = "{$feedbackprefix}\n\n{$explanation}";

        $gradeitem = $assigninstance->get_user_grade($userid, true);

        if (!$gradeitem) {
            mtrace("[AUTOGRADING TASK] Could not get or create grade for user {$userid}");
            return;
        }

        $gradeitem->grade = $scaledgrade;
        $gradeitem->grader = get_admin()->id;

        $DB->update_record('assign_grades', $gradeitem);

        $feedbackplugin = $assigninstance->get_feedback_plugin_by_type('comments');
        if ($feedbackplugin && $feedbackplugin->is_enabled()) {
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

        $assigninstance->update_grade($gradeitem);

        \mod_assign\event\submission_graded::create_from_grade($assigninstance, $gradeitem)->trigger();
    }
}