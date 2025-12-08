<?php
declare(strict_types=1);

/**
 * Event observer class for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autograding;

use core\event\course_module_created;
use core\event\course_module_updated;
use core\event\course_module_deleted;
use mod_assign\event\assessable_submitted;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer class.
 */
class observer {

    /**
     * Handle course module created event.
     *
     * @param course_module_created $event The event
     * @return void
     */
    public static function course_module_created(course_module_created $event): void {
        // Only process assign modules.
        if ($event->other['modulename'] !== 'assign') {
            return;
        }

        $cmid = (int)$event->objectid;
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
    public static function course_module_updated(course_module_updated $event): void {
        // Only process assign modules.
        if ($event->other['modulename'] !== 'assign') {
            return;
        }

        $cmid = (int)$event->objectid;
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
    private static function save_from_request(int $cmid): void {
        // Get the autograding option from the form data.
        $autogradingoption = optional_param('autograding_option', null, PARAM_INT);

        if ($autogradingoption === null) {
            return;
        }

        // Get the text answer if provided.
        $textanswer = null;
        if ($autogradingoption === 2) {
            $textanswer = optional_param('autograding_text_answer', '', PARAM_TEXT);
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
    public static function course_module_deleted(course_module_deleted $event): void {
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
    public static function assessable_submitted(assessable_submitted $event): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        error_log("[AUTOGRADING] ========================================");
        error_log("[AUTOGRADING] ASSESSABLE_SUBMITTED EVENT TRIGGERED");
        error_log("[AUTOGRADING] ========================================");

        try {
            $contextid = $event->contextid;
            $userid = (int)$event->userid;

            error_log("[AUTOGRADING] Context ID: " . $contextid);
            error_log("[AUTOGRADING] User ID: " . $userid);

            // Get context and course module.
            $context = \context::instance_by_id($contextid);
            if (!($context instanceof \context_module)) {
                error_log("[AUTOGRADING] ERROR: Context is not a module context, skipping");
                return;
            }

            $cmid = (int)$context->instanceid;
            $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);

            if (!$cm) {
                error_log("[AUTOGRADING] ERROR: Course module not found");
                return;
            }

            error_log("[AUTOGRADING] Course Module ID: " . $cmid);
            error_log("[AUTOGRADING] Assignment Instance ID: " . $cm->instance);

            // Quick check: Is autograding enabled for this assignment?
            $autogradingconfig = $DB->get_record('local_autograding', ['cmid' => $cmid]);
            if (!$autogradingconfig || (int)$autogradingconfig->autograding_option === 0) {
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

            $submissionid = (int)$submission->id;
            error_log("[AUTOGRADING] Submission ID (from DB): " . $submissionid);

            // Deduplication: Check if a task for this submission already exists in the queue.
            $existingtasks = \core\task\manager::get_adhoc_tasks('\\local_autograding\\task\\grade_submission_task');
            foreach ($existingtasks as $existingtask) {
                $existingdata = $existingtask->get_custom_data();
                if (isset($existingdata->submissionid) && (int)$existingdata->submissionid === $submissionid) {
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

    /**
     * Get student submission text from online text or file.
     *
     * @param int $assignid Assignment ID
     * @param int $userid User ID
     * @param int|null $submissionid Submission ID
     * @return string|null The submission text or null if not found
     */
    private static function get_student_submission_text(int $assignid, int $userid, ?int $submissionid): ?string {
        global $DB, $CFG;

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

        // If no online text, try file submission.
        if (empty($text)) {
            $filesubmission = $DB->get_record('assignsubmission_file', [
                'assignment' => $assignid,
                'submission' => $submission->id,
            ]);

            if ($filesubmission && $filesubmission->numfiles > 0) {
                $text = self::extract_text_from_submission_files($submission, $assignid);
            }
        }

        return !empty($text) ? $text : null;
    }

    /**
     * Extract text from submission files (PDF or DOCX).
     *
     * @param object $submission The submission record
     * @param int $assignid Assignment ID
     * @return string Extracted text
     */
    private static function extract_text_from_submission_files(object $submission, int $assignid): string {
        global $CFG;

        $fs = get_file_storage();
        $cm = get_coursemodule_from_instance('assign', $assignid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $files = $fs->get_area_files(
            $context->id,
            'assignsubmission_file',
            'submission_files',
            $submission->id,
            'id',
            false
        );

        $extractedText = [];

        foreach ($files as $file) {
            $filename = $file->get_filename();
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            try {
                if ($extension === 'pdf') {
                    $text = self::extract_pdf_text($file);
                    if (!empty($text)) {
                        $extractedText[] = $text;
                    }
                } else if ($extension === 'docx') {
                    $text = self::extract_docx_text($file);
                    if (!empty($text)) {
                        $extractedText[] = $text;
                    }
                } else if ($extension === 'txt') {
                    $text = $file->get_content();
                    if (!empty($text)) {
                        $extractedText[] = trim($text);
                    }
                }
            } catch (\Exception $e) {
                debugging('Error extracting text from file ' . $filename . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return implode("\n\n", $extractedText);
    }

    /**
     * Extract text from a PDF file.
     *
     * @param \stored_file $file The stored file
     * @return string Extracted text
     */
    private static function extract_pdf_text(\stored_file $file): string {
        global $CFG;

        $autoloadpath = $CFG->dirroot . '/local/autograding/vendor/autoload.php';
        if (!file_exists($autoloadpath)) {
            throw new \Exception('PDF parser not installed');
        }

        require_once($autoloadpath);

        $content = $file->get_content();
        if (empty($content)) {
            return '';
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseContent($content);

        return trim($pdf->getText());
    }

    /**
     * Extract text from a DOCX file.
     *
     * @param \stored_file $file The stored file
     * @return string Extracted text
     */
    private static function extract_docx_text(\stored_file $file): string {
        global $CFG;

        // Check if phpoffice/phpword is available.
        $autoloadpath = $CFG->dirroot . '/local/autograding/vendor/autoload.php';
        if (!file_exists($autoloadpath)) {
            throw new \Exception('Document parser not installed');
        }

        require_once($autoloadpath);

        // Check if PhpWord is available.
        if (!class_exists('\PhpOffice\PhpWord\IOFactory')) {
            // Fallback: basic DOCX text extraction without PhpWord.
            return self::extract_docx_text_basic($file);
        }

        // Create temp file for PhpWord.
        $tempfile = tempnam(sys_get_temp_dir(), 'docx_');
        file_put_contents($tempfile, $file->get_content());

        try {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($tempfile);
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $text .= self::extract_element_text($element) . "\n";
                }
            }

            return trim($text);
        } finally {
            @unlink($tempfile);
        }
    }

    /**
     * Basic DOCX text extraction without external library.
     *
     * @param \stored_file $file The stored file
     * @return string Extracted text
     */
    private static function extract_docx_text_basic(\stored_file $file): string {
        $content = $file->get_content();
        
        // Create temp file.
        $tempfile = tempnam(sys_get_temp_dir(), 'docx_');
        file_put_contents($tempfile, $content);

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tempfile) !== true) {
                return '';
            }

            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            if (empty($xml)) {
                return '';
            }

            // Remove XML tags and get text content.
            $text = strip_tags($xml);
            $text = preg_replace('/\s+/', ' ', $text);

            return trim($text);
        } finally {
            @unlink($tempfile);
        }
    }

    /**
     * Extract text from PhpWord element recursively.
     *
     * @param mixed $element The element to extract text from
     * @return string Extracted text
     */
    private static function extract_element_text(mixed $element): string {
        $text = '';

        if (method_exists($element, 'getText')) {
            $elementText = $element->getText();
            if (is_string($elementText)) {
                $text .= $elementText;
            }
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $childElement) {
                $text .= self::extract_element_text($childElement);
            }
        }

        return $text;
    }

    /**
     * Call the Google Gemini API for grading.
     *
     * @param string $apiKey The API key
     * @param string $question The assignment question
     * @param string $referenceAnswer The reference answer
     * @param string $studentResponse The student's response
     * @param int $autogradingoption The autograding option (1, 2, or 3)
     * @return array|null Array with 'grade' and 'explanation', or null on error
     */
    private static function call_gemini_api(
        string $apiKey,
        string $question,
        string $referenceAnswer,
        string $studentResponse,
        int $autogradingoption
    ): ?array {
        // Build the prompt based on the autograding option.
        if ($autogradingoption === 1) {
            // Grading without reference answer.
            $prompt = self::build_prompt_without_reference($question, $studentResponse);
        } else {
            // Grading with reference answer (options 2 and 3).
            $prompt = self::build_prompt_with_reference($question, $referenceAnswer, $studentResponse);
        }

        // Get the configured model or use default.
        $model = 'gemini-2.5-flash';

        // Build the API endpoint URL.
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        // Prepare the request payload.
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
            ],
        ];

        try {
            // Use Moodle's HTTP client (curl wrapper).
            $curl = new \curl();
            $curl->setopt([
                'CURLOPT_HTTPHEADER' => [
                    'Content-Type: application/json',
                ],
                'CURLOPT_TIMEOUT' => 60,
                'CURLOPT_RETURNTRANSFER' => true,
            ]);

            error_log("[AUTOGRADING] API Endpoint: " . preg_replace('/key=[^&]+/', 'key=***HIDDEN***', $endpoint));
            error_log("[AUTOGRADING] API Model: " . $model);
            error_log("[AUTOGRADING] Sending request to Gemini API...");

            $response = $curl->post($endpoint, json_encode($payload));
            $httpcode = $curl->get_info()['http_code'] ?? 0;

            error_log("[AUTOGRADING] HTTP Response Code: " . $httpcode);
            error_log("[AUTOGRADING] Response length: " . strlen($response));

            if ($httpcode !== 200) {
                $errorMsg = "HTTP {$httpcode}: " . substr($response, 0, 500);
                error_log("[AUTOGRADING] API ERROR: " . $errorMsg);
                debugging(get_string('autograding_api_error', 'local_autograding', $errorMsg), DEBUG_DEVELOPER);
                return null;
            }

            error_log("[AUTOGRADING] Raw API Response: " . substr($response, 0, 1000));

            $responseData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("[AUTOGRADING] JSON decode error: " . json_last_error_msg());
                debugging(get_string('autograding_invalid_response', 'local_autograding'), DEBUG_DEVELOPER);
                return null;
            }

            // Extract the text content from Gemini response.
            $textContent = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (empty($textContent)) {
                error_log("[AUTOGRADING] ERROR: No text content in response");
                error_log("[AUTOGRADING] Response structure: " . print_r($responseData, true));
                debugging(get_string('autograding_invalid_response', 'local_autograding'), DEBUG_DEVELOPER);
                return null;
            }

            error_log("[AUTOGRADING] Extracted text content: " . $textContent);

            // Parse the JSON from the response text.
            return self::parse_grading_response($textContent);

        } catch (\Exception $e) {
            error_log("[AUTOGRADING] API Exception: " . $e->getMessage());
            error_log("[AUTOGRADING] Exception trace: " . $e->getTraceAsString());
            debugging(get_string('autograding_api_error', 'local_autograding', $e->getMessage()), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Build the grading prompt with reference answer.
     *
     * @param string $question The question
     * @param string $referenceAnswer The reference answer
     * @param string $studentResponse The student's response
     * @return string The complete prompt
     */
    private static function build_prompt_with_reference(string $question, string $referenceAnswer, string $studentResponse): string {
        return "Hãy đóng vai trò là một chuyên gia chấm thi khách quan và nghiêm khắc. Nhiệm vụ của bạn là đánh giá câu trả lời của học sinh dựa trên câu hỏi và đáp án chuẩn được cung cấp.

Dưới đây là dữ liệu đầu vào:
---
[CÂU HỎI]:
{$question}

[ĐÁP ÁN CHUẨN]:
{$referenceAnswer}

[CÂU TRẢ LỜI CỦA HỌC SINH]:
{$studentResponse}
---

Yêu cầu xử lý:
1. So sánh kỹ lưỡng ý nghĩa, từ khóa và logic của câu trả lời học sinh so với đáp án chuẩn.
2. Chấm điểm trên thang điểm từ 0 đến 10 (có thể dùng số thập phân, ví dụ: 8.5).
   - 0 điểm: Sai hoàn toàn hoặc không trả lời.
   - 10 điểm: Chính xác hoàn toàn, đầy đủ ý như đáp án chuẩn.
3. Giải thích ngắn gọn lý do tại sao cho số điểm đó (chỉ ra lỗi sai hoặc phần thiếu nếu có).

QUAN TRỌNG:
- Bạn chỉ được phép trả về kết quả dưới dạng JSON thuần túy.
- Không được thêm bất kỳ văn bản, lời chào, hay định dạng markdown (```json) nào khác vào đầu hoặc cuối.
- Cấu trúc JSON bắt buộc như sau:
{
  \"grade\": <số_điểm>,
  \"explanation\": \"<lời_giải_thích>\"
}";
    }

    /**
     * Build the grading prompt without reference answer (option 1).
     *
     * @param string $question The question
     * @param string $studentResponse The student's response
     * @return string The complete prompt
     */
    private static function build_prompt_without_reference(string $question, string $studentResponse): string {
        return "Hãy đóng vai trò là một chuyên gia chấm thi khách quan và nghiêm khắc. Nhiệm vụ của bạn là đánh giá câu trả lời của học sinh dựa trên câu hỏi được cung cấp.

Dưới đây là dữ liệu đầu vào:
---
[CÂU HỎI]:
{$question}

[CÂU TRẢ LỜI CỦA HỌC SINH]:
{$studentResponse}
---

Yêu cầu xử lý:
1. Đánh giá câu trả lời của học sinh dựa trên kiến thức chuyên môn và yêu cầu của câu hỏi.
2. Chấm điểm trên thang điểm từ 0 đến 10 (có thể dùng số thập phân, ví dụ: 8.5).
   - 0 điểm: Sai hoàn toàn hoặc không trả lời.
   - 10 điểm: Chính xác hoàn toàn, đầy đủ và logic.
3. Giải thích ngắn gọn lý do tại sao cho số điểm đó (chỉ ra lỗi sai hoặc phần thiếu nếu có).

QUAN TRỌNG:
- Bạn chỉ được phép trả về kết quả dưới dạng JSON thuần túy.
- Không được thêm bất kỳ văn bản, lời chào, hay định dạng markdown (```json) nào khác vào đầu hoặc cuối.
- Cấu trúc JSON bắt buộc như sau:
{
  \"grade\": <số_điểm>,
  \"explanation\": \"<lời_giải_thích>\"
}";
    }

    /**
     * Parse the grading response from Gemini.
     *
     * @param string $responseText The response text from Gemini
     * @return array|null Array with 'grade' and 'explanation', or null on error
     */
    private static function parse_grading_response(string $responseText): ?array {
        error_log("[AUTOGRADING] Parsing grading response...");
        error_log("[AUTOGRADING] Raw response text: " . $responseText);

        // Clean up the response text - remove possible markdown code blocks.
        $responseText = trim($responseText);
        
        // Remove markdown code block markers if present.
        $responseText = preg_replace('/^```(?:json)?\s*/i', '', $responseText);
        $responseText = preg_replace('/\s*```$/i', '', $responseText);
        $responseText = trim($responseText);

        error_log("[AUTOGRADING] Cleaned response text: " . $responseText);

        // Try to decode JSON.
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[AUTOGRADING] Initial JSON decode failed: " . json_last_error_msg());
            // Try to extract JSON from text if direct parsing fails.
            if (preg_match('/\{[^{}]*"grade"\s*:\s*[\d.]+[^{}]*"explanation"\s*:\s*"[^"]*"[^{}]*\}/s', $responseText, $matches)) {
                error_log("[AUTOGRADING] Trying regex extraction: " . $matches[0]);
                $data = json_decode($matches[0], true);
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['grade'])) {
            error_log("[AUTOGRADING] ERROR: Failed to parse grading response");
            error_log("[AUTOGRADING] JSON error: " . json_last_error_msg());
            debugging(get_string('autograding_invalid_response', 'local_autograding') . ' Raw: ' . substr($responseText, 0, 200), DEBUG_DEVELOPER);
            return null;
        }

        // Validate and normalize grade.
        $grade = (float)$data['grade'];
        $grade = max(0, min(10, $grade)); // Clamp between 0 and 10.

        $explanation = $data['explanation'] ?? '';

        error_log("[AUTOGRADING] Parsed grade: " . $grade);
        error_log("[AUTOGRADING] Parsed explanation: " . $explanation);

        return [
            'grade' => $grade,
            'explanation' => $explanation,
        ];
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
    private static function save_assignment_grade(object $cm, int $userid, float $grade, string $explanation, object $assign): void {
        global $CFG, $DB;

        error_log("[AUTOGRADING] === SAVING GRADE ===");
        error_log("[AUTOGRADING] CM ID: " . $cm->id);
        error_log("[AUTOGRADING] User ID: " . $userid);
        error_log("[AUTOGRADING] Grade (0-10): " . $grade);

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        // Get assignment context and instance.
        $context = \context_module::instance($cm->id);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Create assign instance.
        $assigninstance = new \assign($context, $cm, $course);

        // Get the max grade for this assignment to scale the grade.
        $maxgrade = (float)$assign->grade;
        error_log("[AUTOGRADING] Assignment max grade: " . $maxgrade);
        
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
            error_log("[AUTOGRADING] Using scale, adjusted max grade: " . $maxgrade);
        }

        // Scale the grade from 0-10 to the assignment's grade range.
        $scaledgrade = ($grade / 10) * $maxgrade;
        error_log("[AUTOGRADING] Scaled grade: " . $scaledgrade);

        // Build feedback with prefix.
        $feedbackprefix = get_string('autograding_feedback_prefix', 'local_autograding');
        $feedback = "{$feedbackprefix}\n\n{$explanation}";

        // Get the user's grade record or create one.
        error_log("[AUTOGRADING] Getting user grade record...");
        $gradeitem = $assigninstance->get_user_grade($userid, true);

        if (!$gradeitem) {
            error_log("[AUTOGRADING] ERROR: Could not get or create grade for user " . $userid);
            debugging('Could not get or create grade for user ' . $userid, DEBUG_DEVELOPER);
            return;
        }

        error_log("[AUTOGRADING] Grade item ID: " . $gradeitem->id);

        // Set the grade.
        $gradeitem->grade = $scaledgrade;
        $gradeitem->grader = get_admin()->id; // Use admin as grader for automated grading.

        error_log("[AUTOGRADING] Grader ID: " . $gradeitem->grader);

        // Update the grade in database.
        error_log("[AUTOGRADING] Updating assign_grades table...");
        $DB->update_record('assign_grades', $gradeitem);

        // Update feedback.
        error_log("[AUTOGRADING] Updating feedback...");
        $feedbackplugin = $assigninstance->get_feedback_plugin_by_type('comments');
        if ($feedbackplugin && $feedbackplugin->is_enabled()) {
            error_log("[AUTOGRADING] Feedback comments plugin is enabled");
            // Get or create feedback record.
            $feedbackcomment = $DB->get_record('assignfeedback_comments', [
                'assignment' => $assign->id,
                'grade' => $gradeitem->id,
            ]);

            if ($feedbackcomment) {
                error_log("[AUTOGRADING] Updating existing feedback comment");
                $feedbackcomment->commenttext = $feedback;
                $feedbackcomment->commentformat = FORMAT_PLAIN;
                $DB->update_record('assignfeedback_comments', $feedbackcomment);
            } else {
                error_log("[AUTOGRADING] Creating new feedback comment");
                $feedbackcomment = new \stdClass();
                $feedbackcomment->assignment = $assign->id;
                $feedbackcomment->grade = $gradeitem->id;
                $feedbackcomment->commenttext = $feedback;
                $feedbackcomment->commentformat = FORMAT_PLAIN;
                $DB->insert_record('assignfeedback_comments', $feedbackcomment);
            }
        } else {
            error_log("[AUTOGRADING] WARNING: Feedback comments plugin not enabled");
        }

        // Update the gradebook.
        error_log("[AUTOGRADING] Updating gradebook...");
        $assigninstance->update_grade($gradeitem);

        // Trigger graded event.
        error_log("[AUTOGRADING] Triggering submission_graded event...");
        \mod_assign\event\submission_graded::create_from_grade($assigninstance, $gradeitem)->trigger();
        error_log("[AUTOGRADING] === GRADE SAVED SUCCESSFULLY ===");
    }
}