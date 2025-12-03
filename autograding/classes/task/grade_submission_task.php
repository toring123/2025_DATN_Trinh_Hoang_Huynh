<?php
declare(strict_types=1);

/**
 * Adhoc task for asynchronous auto-grading of assignment submissions.
 *
 * @package    local_autograding
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autograding\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task that processes a single assignment submission for auto-grading.
 *
 * This task is queued by the event observer when a student submits an assignment.
 * It handles the heavy lifting of calling the Gemini API and saving the grade
 * asynchronously, preventing browser hangs and managing API rate limits.
 */
class grade_submission_task extends \core\task\adhoc_task {

    /**
     * Get the name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_grade_submission', 'local_autograding');
    }

    /**
     * Execute the task.
     *
     * This method retrieves submission data, calls the Gemini API for grading,
     * and saves the resulting grade. It implements concurrency locking to prevent
     * API rate limit errors.
     *
     * @return void
     * @throws \moodle_exception If the task should be retried later.
     */
    public function execute(): void {
        global $DB, $CFG;

        $data = $this->get_custom_data();

        // Validate required data - cast to int first, then validate they're positive.
        $cmid = (int)($data->cmid ?? 0);
        $userid = (int)($data->userid ?? 0);
        $contextid = (int)($data->contextid ?? 0);

        if ($cmid <= 0 || $userid <= 0 || $contextid <= 0) {
            mtrace("[AUTOGRADING TASK] ERROR: Missing or invalid required data (cmid: {$cmid}, userid: {$userid}, contextid: {$contextid})");
            return; // Don't retry - data is invalid.
        }

        $submissionid = isset($data->submissionid) ? (int)$data->submissionid : null;
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
            if (!$autogradingconfig || (int)$autogradingconfig->autograding_option === 0) {
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
            $autogradingoption = (int)$autogradingconfig->autograding_option;

            // For options 2 and 3, reference answer is required.
            if (($autogradingoption === 2 || $autogradingoption === 3) && empty($referenceAnswer)) {
                mtrace("[AUTOGRADING TASK] Reference answer required but empty for option {$autogradingoption}");
                return; // Don't retry - configuration error.
            }

            // Step 6: Get student submission data (text and images).
            $submissionData = $this->get_submission_data((int)$cm->instance, $userid, $submissionid);
            $studentResponse = $submissionData['text'];
            $imageParts = $submissionData['images'];

            // Check if we have any content to grade.
            $hasText = !empty($studentResponse);
            $hasImages = !empty($imageParts);

            if (!$hasText && !$hasImages) {
                mtrace("[AUTOGRADING TASK] No student submission content found for user {$userid}");
                return; // Don't retry - no submission to grade.
            }

            mtrace("[AUTOGRADING TASK] Student response: text=" . ($hasText ? strlen($studentResponse) . " chars" : "none") .
                   ", images=" . count($imageParts));

            // Step 7: Get AI provider configuration.
            $provider = get_config('local_autograding', 'ai_provider') ?: 'gemini';
            mtrace("[AUTOGRADING TASK] Using AI provider: {$provider}");

            // Step 8: Call AI API based on provider.
            // Note: Rate limiting (HTTP 429) is handled by throwing moodle_exception
            // which triggers Moodle's built-in task retry mechanism.
            $gradingResult = $this->call_ai_api(
                $provider,
                $question,
                $referenceAnswer,
                $studentResponse ?? '',
                $autogradingoption,
                $imageParts
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
    private function get_student_submission_text(int $assignid, int $userid, ?int $submissionid): ?string {
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
                $text = $this->extract_text_from_submission_files($submission, $assignid);
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
    private function extract_text_from_submission_files(object $submission, int $assignid): string {
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
                    $text = $this->extract_pdf_text($file);
                    if (!empty($text)) {
                        $extractedText[] = $text;
                    }
                } else if ($extension === 'docx') {
                    $text = $this->extract_docx_text($file);
                    if (!empty($text)) {
                        $extractedText[] = $text;
                    }
                } else if ($extension === 'txt') {
                    $text = $file->get_content();
                    if (!empty($text)) {
                        $extractedText[] = trim($text);
                    }
                }
                // Note: Image files are handled separately by extract_images_from_submission_files()
            } catch (\Exception $e) {
                mtrace("[AUTOGRADING TASK] Error extracting text from file {$filename}: " . $e->getMessage());
            }
        }

        return implode("\n\n", $extractedText);
    }

    /**
     * Supported image MIME types for Gemini Vision API.
     */
    private const SUPPORTED_IMAGE_MIMETYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
    ];

    /**
     * Extract images from submission files for vision-based grading.
     *
     * This method retrieves all image files from the submission, converts them
     * to Base64, and returns them in a format suitable for the Gemini Vision API.
     *
     * @param object $submission The submission record
     * @param int $assignid Assignment ID
     * @return array Array of image parts with 'mimeType' and 'data' (Base64)
     */
    private function extract_images_from_submission_files(object $submission, int $assignid): array {
        $fs = get_file_storage();
        $cm = get_coursemodule_from_instance('assign', $assignid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $files = $fs->get_area_files(
            $context->id,
            'assignsubmission_file',
            'submission_files',
            $submission->id,
            'id',
            false // Exclude directories
        );

        $imageParts = [];

        foreach ($files as $file) {
            // Skip directories and empty files.
            if ($file->is_directory() || $file->get_filesize() === 0) {
                continue;
            }

            $mimeType = $file->get_mimetype();
            $filename = $file->get_filename();

            // Check if the file is a supported image type.
            if (!in_array($mimeType, self::SUPPORTED_IMAGE_MIMETYPES, true)) {
                continue;
            }

            try {
                // Read file content and convert to Base64.
                $content = $file->get_content();
                if (empty($content)) {
                    mtrace("[AUTOGRADING TASK] Image file {$filename} is empty, skipping");
                    continue;
                }

                $base64Data = base64_encode($content);

                $imageParts[] = [
                    'mimeType' => $mimeType,
                    'data' => $base64Data,
                    'filename' => $filename, // For logging purposes
                ];

                mtrace("[AUTOGRADING TASK] Extracted image: {$filename} ({$mimeType}, " . strlen($content) . " bytes)");

            } catch (\Exception $e) {
                mtrace("[AUTOGRADING TASK] Error extracting image {$filename}: " . $e->getMessage());
            }
        }

        mtrace("[AUTOGRADING TASK] Total images extracted: " . count($imageParts));

        return $imageParts;
    }

    /**
     * Get submission data including both text and images.
     *
     * @param int $assignid Assignment ID
     * @param int $userid User ID
     * @param int|null $submissionid Submission ID
     * @return array{text: string|null, images: array} Array with 'text' and 'images' keys
     */
    private function get_submission_data(int $assignid, int $userid, ?int $submissionid): array {
        global $DB;

        $result = [
            'text' => null,
            'images' => [],
        ];

        // Get the submission record.
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
            return $result;
        }

        // Get text submission.
        $result['text'] = $this->get_student_submission_text($assignid, $userid, $submissionid);

        // Get image submissions.
        $filesubmission = $DB->get_record('assignsubmission_file', [
            'assignment' => $assignid,
            'submission' => $submission->id,
        ]);

        if ($filesubmission && $filesubmission->numfiles > 0) {
            $result['images'] = $this->extract_images_from_submission_files($submission, $assignid);
        }

        return $result;
    }

    /**
     * Extract text from a PDF file.
     *
     * @param \stored_file $file The stored file
     * @return string Extracted text
     */
    private function extract_pdf_text(\stored_file $file): string {
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
    private function extract_docx_text(\stored_file $file): string {
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
            return $this->extract_docx_text_basic($file);
        }

        // Create temp file for PhpWord.
        $tempfile = tempnam(sys_get_temp_dir(), 'docx_');
        file_put_contents($tempfile, $file->get_content());

        try {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($tempfile);
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $text .= $this->extract_element_text($element) . "\n";
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
    private function extract_docx_text_basic(\stored_file $file): string {
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
    private function extract_element_text(mixed $element): string {
        $text = '';

        if (method_exists($element, 'getText')) {
            $elementText = $element->getText();
            if (is_string($elementText)) {
                $text .= $elementText;
            }
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $childElement) {
                $text .= $this->extract_element_text($childElement);
            }
        }

        return $text;
    }

    /**
     * Call the AI API for grading based on the selected provider.
     *
     * @param string $provider The AI provider ('gemini' or 'qwen')
     * @param string $question The assignment question
     * @param string $referenceAnswer The reference answer
     * @param string $studentResponse The student's text response
     * @param int $autogradingoption The autograding option (1, 2, or 3)
     * @param array $imageParts Array of image parts for vision-based grading
     * @return array|null Array with 'grade' and 'explanation', or null on error
     * @throws \moodle_exception When rate limited (HTTP 429) to trigger retry.
     */
    private function call_ai_api(
        string $provider,
        string $question,
        string $referenceAnswer,
        string $studentResponse,
        int $autogradingoption,
        array $imageParts = []
    ): ?array {
        // Determine if this is an image-based submission.
        $hasImages = !empty($imageParts);

        // Build the user content based on the autograding option and submission type.
        if ($hasImages) {
            // Image-based grading (handwriting).
            if ($autogradingoption === 1) {
                $userContent = $this->build_image_content_without_reference($question);
            } else {
                $userContent = $this->build_image_content_with_reference($question, $referenceAnswer);
            }
        } else {
            // Text-based grading.
            if ($autogradingoption === 1) {
                $userContent = $this->build_user_content_without_reference($question, $studentResponse);
            } else {
                $userContent = $this->build_user_content_with_reference($question, $referenceAnswer, $studentResponse);
                // mtrace("[AUTOGRADING TASK] User content:", $userContent);
            }
        }

        // Get the system instruction from config.
        $systemInstruction = get_config('local_autograding', 'system_instruction');
        if (empty($systemInstruction)) {
            $systemInstruction = get_string('system_instruction_default', 'local_autograding');
        }
        $systemInstruction .= "\n" . get_string('system_instruction_footer', 'local_autograding');

        // Route to the appropriate provider.
        if ($provider === 'qwen') {
            return $this->call_qwen_api($systemInstruction, $userContent, $imageParts);
        } else {
            return $this->call_gemini_api($systemInstruction, $userContent, $imageParts);
        }
    }

    /**
     * Call the Google Gemini API for grading.
     *
     * Supports both text-only and vision (image + text) grading using Gemini's
     * multimodal capabilities.
     *
     * @param string $systemInstruction The system instruction for the AI
     * @param string $userContent The user content (grading request text)
     * @param array $imageParts Array of image parts for vision-based grading
     * @return array|null Array with 'grade' and 'explanation', or null on error
     * @throws \moodle_exception When rate limited (HTTP 429) to trigger retry.
     */
    private function call_gemini_api(string $systemInstruction, string $userContent, array $imageParts = []): ?array {
        // Get API key.
        $apiKey = get_config('local_autograding', 'gemini_api_key');
        if (empty($apiKey)) {
            mtrace("[AUTOGRADING TASK] Gemini API key not configured");
            return null;
        }

        // Get the configured model or use default.
        $model = get_config('local_autograding', 'gemini_model') ?: 'gemini-2.5-flash';

        // Build the API endpoint URL.
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        // Build the content parts array.
        $contentParts = [];

        // If we have images, add them first (vision grading).
        if (!empty($imageParts)) {
            mtrace("[AUTOGRADING TASK] Building vision payload with " . count($imageParts) . " image(s)");

            foreach ($imageParts as $imagePart) {
                $contentParts[] = [
                    'inlineData' => [
                        'mimeType' => $imagePart['mimeType'],
                        'data' => $imagePart['data'],
                    ],
                ];
                mtrace("[AUTOGRADING TASK] Added image: " . ($imagePart['filename'] ?? 'unknown') . " ({$imagePart['mimeType']})");
            }
        }

        // Add the text prompt (already contains image-specific instructions if needed).
        $contentParts[] = ['text' => $userContent];

        // Prepare the request payload with systemInstruction.
        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $contentParts,
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
            ],
        ];

        try {
            // Use Moodle's HTTP client.
            $client = new \core\http_client();

            mtrace("[AUTOGRADING TASK] API Model: " . $model);
            mtrace("[AUTOGRADING TASK] Sending request to Gemini API...");

            $response = $client->post($endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($payload),
                'timeout' => 60,
            ]);

            $httpcode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            mtrace("[AUTOGRADING TASK] HTTP Response Code: " . $httpcode);

            // Handle rate limiting - throw exception to trigger retry.
            if ($httpcode === 429) {
                mtrace("[AUTOGRADING TASK] Rate limited (HTTP 429), task will be retried");
                throw new \moodle_exception('ratelimited', 'local_autograding', '', null,
                    'API rate limit exceeded, task will be automatically retried');
            }

            if ($httpcode !== 200) {
                $errorMsg = "HTTP {$httpcode}: " . substr($responseBody, 0, 500);
                mtrace("[AUTOGRADING TASK] API ERROR: " . $errorMsg);
                return null;
            }

            $responseData = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                mtrace("[AUTOGRADING TASK] JSON decode error: " . json_last_error_msg());
                return null;
            }

            // Extract the text content from Gemini response.
            $textContent = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (empty($textContent)) {
                mtrace("[AUTOGRADING TASK] No text content in response");
                return null;
            }

            // Parse the JSON from the response text.
            return $this->parse_grading_response($textContent);

        } catch (\moodle_exception $e) {
            // Re-throw moodle_exception (including rate limit) to trigger retry.
            throw $e;
        } catch (\Exception $e) {
            mtrace("[AUTOGRADING TASK] Gemini API Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Call the Local Qwen API for grading (OpenAI-compatible format).
     *
     * Note: Local Qwen (text-model) cannot process images. If images are submitted,
     * they will be ignored and a warning will be included in the feedback.
     *
     * @param string $systemInstruction The system instruction for the AI
     * @param string $userContent The user content (grading request)
     * @param array $imageParts Array of image parts (will be ignored with warning)
     * @return array|null Array with 'grade' and 'explanation', or null on error
     * @throws \moodle_exception When rate limited (HTTP 429) to trigger retry.
     */
    private function call_qwen_api(string $systemInstruction, string $userContent, array $imageParts = []): ?array {
        // Check if images were submitted - Qwen cannot process them.
        $imageWarning = '';
        if (!empty($imageParts)) {
            mtrace("[AUTOGRADING TASK] WARNING: " . count($imageParts) . " image(s) submitted but Local Qwen cannot process images");
            $imageWarning = get_string('qwen_image_warning', 'local_autograding');

            // If there's no text content and only images, we cannot grade.
            if (empty(trim($userContent)) || $userContent === $this->build_user_content_without_reference('', '')) {
                mtrace("[AUTOGRADING TASK] ERROR: Only images submitted but Qwen cannot process images");
                return [
                    'grade' => 0,
                    'explanation' => get_string('qwen_image_only_error', 'local_autograding'),
                ];
            }
        }

        // Get endpoint URL.
        $endpoint = get_config('local_autograding', 'qwen_endpoint');
        if (empty($endpoint)) {
            $endpoint = 'http://localhost:11434/v1/chat/completions';
        }

        // Get the configured model or use default.
        $model = get_config('local_autograding', 'qwen_model') ?: 'qwen2.5-3b-instruct';

        // Prepare the request payload (OpenAI-compatible format).
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemInstruction],
                ['role' => 'user', 'content' => $userContent],
            ],
            'temperature' => 0.2,
            'max_tokens' => 500,
        ];

        mtrace("[AUTOGRADING TASK] payload " . json_encode($payload, JSON_UNESCAPED_UNICODE));

        try {
            // Use Moodle's HTTP client.
            $client = new \core\http_client();

            mtrace("[AUTOGRADING TASK] API Model: " . $model);
            mtrace("[AUTOGRADING TASK] Sending request to Qwen API at: " . $endpoint);

            $response = $client->post($endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($payload),
                'timeout' => 120, // Longer timeout for local models.
            ]);

            $httpcode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            mtrace("[AUTOGRADING TASK] HTTP Response Code: " . $httpcode);

            // Handle rate limiting - throw exception to trigger retry.
            if ($httpcode === 429) {
                mtrace("[AUTOGRADING TASK] Rate limited (HTTP 429), task will be retried");
                throw new \moodle_exception('ratelimited', 'local_autograding', '', null,
                    'API rate limit exceeded, task will be automatically retried');
            }

            if ($httpcode !== 200) {
                $errorMsg = "HTTP {$httpcode}: " . substr($responseBody, 0, 500);
                mtrace("[AUTOGRADING TASK] API ERROR: " . $errorMsg);
                return null;
            }

            $responseData = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                mtrace("[AUTOGRADING TASK] JSON decode error: " . json_last_error_msg());
                return null;
            }

            // Extract the text content from OpenAI-compatible response.
            $textContent = $responseData['choices'][0]['message']['content'] ?? null;

            if (empty($textContent)) {
                mtrace("[AUTOGRADING TASK] No text content in response");
                return null;
            }

            // Parse the JSON from the response text.
            $result = $this->parse_grading_response($textContent);

            // Append image warning to explanation if images were submitted.
            if ($result !== null && !empty($imageWarning)) {
                $result['explanation'] = $imageWarning . "\n\n" . $result['explanation'];
            }

            return $result;

        } catch (\moodle_exception $e) {
            // Re-throw moodle_exception (including rate limit) to trigger retry.
            throw $e;
        } catch (\Exception $e) {
            mtrace("[AUTOGRADING TASK] Qwen API Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Build the user content for grading with reference answer.
     *
     * @param string $question The question
     * @param string $referenceAnswer The reference answer
     * @param string $studentResponse The student's response
     * @return string The user content for the AI
     */
    private function build_user_content_with_reference(string $question, string $referenceAnswer, string $studentResponse): string {
        return "---
                    [CÂU HỎI]:
                    {$question}
                    [ĐÁP ÁN CHUẨN]:
                    {$referenceAnswer}
                    [CÂU TRẢ LỜI CỦA HỌC SINH]:
                    {$studentResponse}
                ---
                ";
    }

    /**
     * Build the user content for grading without reference answer (option 1).
     *
     * @param string $question The question
     * @param string $studentResponse The student's response
     * @return string The user content for the AI
     */
    private function build_user_content_without_reference(string $question, string $studentResponse): string {
        return "---
                    [CÂU HỎI]:
                    {$question}
                    [CÂU TRẢ LỜI CỦA HỌC SINH]:
                    {$studentResponse}
                ---
                ";
    }

    /**
     * Build the user content for image-based grading with reference answer.
     *
     * This method creates a prompt for grading handwritten submissions
     * where the student's answer is in attached images.
     *
     * @param string $question The question
     * @param string $referenceAnswer The reference answer
     * @return string The user content for the AI (Heredoc format)
     */
    private function build_image_content_with_reference(string $question, string $referenceAnswer): string {
        return <<<EOT
[CÂU HỎI]:
{$question}

[ĐÁP ÁN CHUẨN]:
{$referenceAnswer}

[BÀI LÀM CỦA HỌC SINH]:
(Vui lòng xem các hình ảnh bài làm viết tay được đính kèm trong request này)

[YÊU CẦU CHẤM ĐIỂM]:
1. Hãy quan sát kỹ các hình ảnh, trích xuất nội dung chữ viết tay của học sinh.
2. Nếu có nhiều ảnh, hãy tự động ghép nối nội dung theo thứ tự hợp lý.
3. So sánh nội dung đó với [CÂU HỎI] và [ĐÁP ÁN CHUẨN] để chấm điểm và đưa ra nhận xét chi tiết.
EOT;
    }

    /**
     * Build the user content for image-based grading without reference answer (option 1).
     *
     * This method creates a prompt for grading handwritten submissions
     * where no reference answer is provided.
     *
     * @param string $question The question
     * @return string The user content for the AI (Heredoc format)
     */
    private function build_image_content_without_reference(string $question): string {
        return <<<EOT
[CÂU HỎI]:
{$question}

[BÀI LÀM CỦA HỌC SINH]:
(Vui lòng xem các hình ảnh bài làm viết tay được đính kèm trong request này)

[YÊU CẦU CHẤM ĐIỂM]:
1. Hãy quan sát kỹ các hình ảnh, trích xuất nội dung chữ viết tay của học sinh.
2. Nếu có nhiều ảnh, hãy tự động ghép nối nội dung theo thứ tự hợp lý.
3. Dựa trên kiến thức chuyên môn và yêu cầu của [CÂU HỎI], hãy chấm điểm và đưa ra nhận xét chi tiết.
EOT;
    }

    /**
     * Parse the grading response from Gemini.
     *
     * @param string $responseText The response text from Gemini
     * @return array|null Array with 'grade' and 'explanation', or null on error
     */
    private function parse_grading_response(string $responseText): ?array {
        // Clean up the response text - remove possible markdown code blocks.
        $responseText = trim($responseText);

        // Remove markdown code block markers if present.
        $responseText = preg_replace('/^```(?:json)?\s*/i', '', $responseText);
        $responseText = preg_replace('/\s*```$/i', '', $responseText);
        $responseText = trim($responseText);

        // Try to decode JSON.
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to extract JSON from text if direct parsing fails.
            if (preg_match('/\{[^{}]*"grade"\s*:\s*[\d.]+[^{}]*"explanation"\s*:\s*"[^"]*"[^{}]*\}/s', $responseText, $matches)) {
                $data = json_decode($matches[0], true);
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['grade'])) {
            mtrace("[AUTOGRADING TASK] Failed to parse grading response: " . json_last_error_msg());
            return null;
        }

        // Validate and normalize grade.
        $grade = (float)$data['grade'];
        $grade = max(0, min(10, $grade)); // Clamp between 0 and 10.

        $explanation = $data['explanation'] ?? '';

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
    private function save_assignment_grade(object $cm, int $userid, float $grade, string $explanation, object $assign): void {
        global $CFG, $DB;

        mtrace("[AUTOGRADING TASK] Saving grade - CM ID: {$cm->id}, User ID: {$userid}, Grade: {$grade}");

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        // Get assignment context and instance.
        $context = \context_module::instance($cm->id);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Create assign instance.
        $assigninstance = new \assign($context, $cm, $course);

        // Get the max grade for this assignment to scale the grade.
        $maxgrade = (float)$assign->grade;

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
