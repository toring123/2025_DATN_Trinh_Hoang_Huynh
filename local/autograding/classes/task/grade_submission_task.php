<?php
declare(strict_types=1);

/**
 * Adhoc task for asynchronous auto-grading of assignment submissions.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
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
     * This method retrieves submission data, calls the Gemini API for grading,
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

            // Step 8: Call AI API based on provider.
            // Note: Rate limiting (HTTP 429) is handled by throwing moodle_exception
            // which triggers Moodle's built-in task retry mechanism.
            $gradingResult = $this->call_ai_api(
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
     * Extract text from submission files (PDF, DOCX, images).
     *
     * If the OCR server is configured, it will be used for PDFs and images.
     * Otherwise, falls back to local PDF parser and ignores images.
     *
     * @param object $submission The submission record
     * @param int $assignid Assignment ID
     * @return string Extracted text
     */
    private function extract_text_from_submission_files(object $submission, int $assignid): string
    {
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
        $ocrServerUrl = get_config('local_autograding', 'ocr_server_url');
        $ocrEnabled = !empty($ocrServerUrl);

        foreach ($files as $file) {
            $filename = $file->get_filename();
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $mimeType = $file->get_mimetype();

            try {
                if ($extension === 'pdf') {
                    // Use OCR server for PDF.
                    if ($ocrEnabled) {
                        mtrace("[AUTOGRADING TASK] Using OCR server for PDF: {$filename}");
                        $text = $this->call_ocr_api($file, 'pdf');
                        if (!empty($text)) {
                            $extractedText[] = $text;
                        }
                    } else {
                        mtrace("[AUTOGRADING TASK] OCR server not configured, skipping PDF: {$filename}");
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
                } else if ($ocrEnabled && in_array($mimeType, self::SUPPORTED_IMAGE_MIMETYPES, true)) {
                    // Use OCR server for images.
                    mtrace("[AUTOGRADING TASK] Using OCR server for image: {$filename}");
                    $text = $this->call_ocr_api($file, 'image');
                    if (!empty($text)) {
                        $extractedText[] = $text;
                    }
                }
            } catch (\Exception $e) {
                mtrace("[AUTOGRADING TASK] Error extracting text from file {$filename}: " . $e->getMessage());
            }
        }

        return implode("\n\n", $extractedText);
    }

    /**
     * Supported image MIME types for OCR text extraction.
     */
    private const SUPPORTED_IMAGE_MIMETYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
    ];

    /**
     * Call the OCR server to extract text from a file.
     *
     * This method sends files to an external OCR server for text extraction.
     * Supports both image files (via /ocr endpoint) and PDF files (via /ocr-pdf endpoint).
     *
     * @param \stored_file $file The file to extract text from
     * @param string $type The file type: 'image' or 'pdf'
     * @return string|null Extracted text, or null on error
     */
    private function call_ocr_api(\stored_file $file, string $type): ?string
    {
        $ocrServerUrl = get_config('local_autograding', 'ocr_server_url');

        if (empty($ocrServerUrl)) {
            mtrace("[AUTOGRADING TASK] OCR server URL not configured");
            return null;
        }

        // Determine endpoint based on file type.
        $endpoint = ($type === 'pdf') ? '/ocr-pdf' : '/ocr';
        $url = rtrim($ocrServerUrl, '/') . $endpoint;

        $filename = $file->get_filename();
        $content = $file->get_content();

        if (empty($content)) {
            mtrace("[AUTOGRADING TASK] File {$filename} is empty, skipping OCR");
            return null;
        }

        mtrace("[AUTOGRADING TASK] Sending file to OCR server: {$filename} via {$endpoint}");

        $tempFile = null;
        try {
            // Create a temporary file for curl upload.
            $tempFile = tempnam(sys_get_temp_dir(), 'ocr_');
            file_put_contents($tempFile, $content);

            // Use curl for multipart/form-data upload.
            $ch = curl_init();

            if ($type === 'pdf') {
                // For PDF endpoint, use 'file' as field name.
                $postFields = [
                    'file' => new \CURLFile($tempFile, $file->get_mimetype(), $filename),
                ];
            } else {
                // For image endpoint, use 'files[0]' as field name.
                $postFields = [
                    'files[0]' => new \CURLFile($tempFile, $file->get_mimetype(), $filename),
                ];
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($curlError) {
                mtrace("[AUTOGRADING TASK] OCR API curl error: {$curlError}");
                return null;
            }

            if ($httpCode !== 200) {
                mtrace("[AUTOGRADING TASK] OCR API returned HTTP {$httpCode}: " . substr($response, 0, 500));
                return null;
            }

            $responseData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                mtrace("[AUTOGRADING TASK] OCR API JSON decode error: " . json_last_error_msg());
                return null;
            }

            $extractedText = $responseData['text'] ?? '';

            if (!empty($extractedText)) {
                mtrace("[AUTOGRADING TASK] OCR extracted " . strlen($extractedText) . " characters from {$filename}");
            } else {
                mtrace("[AUTOGRADING TASK] OCR returned empty text for {$filename}");
            }

            return $extractedText;

        } catch (\Exception $e) {
            mtrace("[AUTOGRADING TASK] OCR API exception: " . $e->getMessage());
            return null;
        } finally {
            // Always clean up temp file.
            if ($tempFile !== null && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }
    /**
     * Extract text from a DOCX file using ZipArchive.
     *
     * @param \stored_file $file The stored file
     * @return string Extracted text
     */
    private function extract_docx_text(\stored_file $file): string
    {
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
     * Call the AI API for grading based on the selected provider.
     *
     * @param string $provider The AI provider ('gemini' or 'qwen')
     * @param string $question The assignment question
     * @param string $referenceAnswer The reference answer
     * @param string $studentResponse The student's text response
     * @param int $autogradingoption The autograding option (1, 2, or 3)
     * @return array|null Array with 'grade' and 'explanation', or null on error
     * @throws \moodle_exception When rate limited (HTTP 429) to trigger retry.
     */
    private function call_ai_api(
        string $provider,
        string $question,
        string $referenceAnswer,
        string $studentResponse,
        int $autogradingoption
    ): ?array {
        // Build the user content based on the autograding option.
        if ($autogradingoption === 1) {
            $userContent = $this->build_user_content_without_reference($question, $studentResponse);
        } else {
            $userContent = $this->build_user_content_with_reference($question, $referenceAnswer, $studentResponse);
        }

        // Get the system instruction from config.
        $systemInstruction = get_config('local_autograding', 'system_instruction');
        if (empty($systemInstruction)) {
            $systemInstruction = get_string('system_instruction_default', 'local_autograding');
        }
        $systemInstruction .= "\n" . get_string('system_instruction_footer', 'local_autograding');

        // Route to the appropriate provider.
        if ($provider === 'qwen') {
            return $this->call_qwen_api($systemInstruction, $userContent);
        } else {
            return $this->call_gemini_api($systemInstruction, $userContent);
        }
    }

    /**
     * Call the Google Gemini API for grading.
     *
     * @param string $systemInstruction The system instruction for the AI
     * @param string $userContent The user content (grading request text)
     * @return array|null Array with 'grade' and 'explanation', or null on error
     * @throws \moodle_exception When rate limited (HTTP 429) to trigger retry.
     */
    private function call_gemini_api(string $systemInstruction, string $userContent): ?array
    {
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
                    'parts' => [
                        ['text' => $userContent],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
                'response_mime_type' => 'application/json',
            ],
        ];

        try {
            // Use native cURL for API calls.
            $ch = curl_init();

            mtrace("[AUTOGRADING TASK] API Model: " . $model);
            mtrace("[AUTOGRADING TASK] Sending request to Gemini API...");

            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
            ]);

            $responseBody = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($curlError) {
                mtrace("[AUTOGRADING TASK] Gemini API curl error: {$curlError}");
                return null;
            }

            mtrace("[AUTOGRADING TASK] HTTP Response Code: " . $httpcode);

            // Handle rate limiting - throw exception to trigger retry.
            if ($httpcode === 429) {
                mtrace("[AUTOGRADING TASK] Rate limited (HTTP 429), task will be retried");
                throw new \moodle_exception(
                    'ratelimited',
                    'local_autograding',
                    '',
                    null,
                    'API rate limit exceeded, task will be automatically retried'
                );
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
     * @param string $systemInstruction The system instruction for the AI
     * @param string $userContent The user content (grading request)
     * @return array|null Array with 'grade' and 'explanation', or null on error
     * @throws \moodle_exception When rate limited (HTTP 429) to trigger retry.
     */
    private function call_qwen_api(string $systemInstruction, string $userContent): ?array
    {
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
            'temperature' => 0.3,
            'max_tokens' => 8192,
        ];

        mtrace("[AUTOGRADING TASK] payload " . json_encode($payload, JSON_UNESCAPED_UNICODE));

        try {
            // Use native cURL for local API calls.
            $ch = curl_init();

            mtrace("[AUTOGRADING TASK] API Model: " . $model);
            mtrace("[AUTOGRADING TASK] Sending request to Qwen API at: " . $endpoint);

            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120, // Longer timeout for local models.
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
            ]);

            $responseBody = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($curlError) {
                mtrace("[AUTOGRADING TASK] Qwen API curl error: {$curlError}");
                return null;
            }

            mtrace("[AUTOGRADING TASK] HTTP Response Code: " . $httpcode);

            // Handle rate limiting - throw exception to trigger retry.
            if ($httpcode === 429) {
                mtrace("[AUTOGRADING TASK] Rate limited (HTTP 429), task will be retried");
                throw new \moodle_exception(
                    'ratelimited',
                    'local_autograding',
                    '',
                    null,
                    'API rate limit exceeded, task will be automatically retried'
                );
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
            return $this->parse_grading_response($textContent);

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
    private function build_user_content_with_reference(string $question, string $referenceAnswer, string $studentResponse): string
    {
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
    private function build_user_content_without_reference(string $question, string $studentResponse): string
    {
        return "---
                    [CÂU HỎI]:
                    {$question}
                    [CÂU TRẢ LỜI CỦA HỌC SINH]:
                    {$studentResponse}
                ---
                ";
    }



    /**
     * Parse the grading response from Gemini.
     *
     * @param string $responseText The response text from Gemini
     * @return array|null Array with 'grade' and 'explanation', or null on error
     */
    private function parse_grading_response(string $responseText): ?array
    {
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
        $grade = (float) $data['grade'];
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