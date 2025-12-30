<?php
declare(strict_types=1);

/**
 * OCR Service class for local_autograding plugin.
 *
 * Handles text extraction from files (PDF, DOCX, images) via OCR server.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

namespace local_autograding;

defined('MOODLE_INTERNAL') || die();

/**
 * OCR Service class for text extraction.
 */
class ocr_service
{
    /** @var array Supported image MIME types for OCR text extraction */
    public const SUPPORTED_IMAGE_MIMETYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
    ];

    /**
     * Check if OCR server is configured and enabled.
     *
     * @return bool True if OCR server is enabled
     */
    public static function is_enabled(): bool
    {
        $ocrServerUrl = get_config('local_autograding', 'ocr_server_url');
        return !empty($ocrServerUrl);
    }

    /**
     * Get the OCR server URL.
     *
     * @return string|null The OCR server URL or null if not configured
     */
    public static function get_server_url(): ?string
    {
        $url = get_config('local_autograding', 'ocr_server_url');
        return !empty($url) ? $url : null;
    }

    /**
     * Check connection to OCR server.
     *
     * @param string|null $endpoint Optional endpoint URL override. If null, uses configured URL.
     * @return array Connection status result with keys: success, message, details
     */
    public static function check_connection(?string $endpoint = null): array
    {
        // Use provided endpoint or get from config.
        if (empty($endpoint)) {
            $endpoint = self::get_server_url();
        }
        if (empty($endpoint)) {
            $endpoint = 'http://127.0.0.1:8001';
        }

        // Try to connect to OCR server health endpoint.
        $healthurl = rtrim($endpoint, '/') . '/health';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $healthurl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return [
                'success' => false,
                'message' => get_string('connection_failed', 'local_autograding') . ': ' . $error,
                'details' => null,
            ];
        }

        if ($httpcode !== 200) {
            return [
                'success' => false,
                'message' => get_string('connection_failed', 'local_autograding') . ' (HTTP ' . $httpcode . ')',
                'details' => null,
            ];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Even if response is not JSON, if HTTP 200 then server is up.
            return [
                'success' => true,
                'message' => get_string('connection_success', 'local_autograding'),
                'details' => ['status' => 'OK'],
            ];
        }

        return [
            'success' => true,
            'message' => get_string('connection_success', 'local_autograding'),
            'details' => $data,
        ];
    }

    /**
     * Extract text from all submission files.
     *
     * Routes files to appropriate extraction method based on type.
     *
     * @param object $submission The submission record
     * @param int $assignid Assignment ID
     * @return string Extracted text from all files
     */
    public static function extract_from_submission(object $submission, int $assignid): string
    {
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
        $ocrEnabled = self::is_enabled();

        foreach ($files as $file) {
            $filename = $file->get_filename();
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $mimeType = $file->get_mimetype();

            try {
                if ($extension === 'pdf') {
                    // Use OCR server for PDF.
                    if ($ocrEnabled) {
                        mtrace("[OCR SERVICE] Using OCR server for PDF: {$filename}");
                        $text = self::call_ocr_api($file, 'pdf');
                        if (!empty($text)) {
                            $extractedText[] = $text;
                        }
                    } else {
                        mtrace("[OCR SERVICE] OCR server not configured, skipping PDF: {$filename}");
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
                } else if ($ocrEnabled && in_array($mimeType, self::SUPPORTED_IMAGE_MIMETYPES, true)) {
                    // Use OCR server for images.
                    mtrace("[OCR SERVICE] Using OCR server for image: {$filename}");
                    $text = self::call_ocr_api($file, 'image');
                    if (!empty($text)) {
                        $extractedText[] = $text;
                    }
                }
            } catch (\Exception $e) {
                mtrace("[OCR SERVICE] Error extracting text from file {$filename}: " . $e->getMessage());
            }
        }

        return implode("\n\n", $extractedText);
    }

    /**
     * Call the OCR server to extract text from a file.
     *
     * Supports both image files (via /ocr endpoint) and PDF files (via /ocr-pdf endpoint).
     *
     * @param \stored_file $file The file to extract text from
     * @param string $type The file type: 'image' or 'pdf'
     * @return string|null Extracted text, or null on error
     */
    public static function call_ocr_api(\stored_file $file, string $type): ?string
    {
        $ocrServerUrl = self::get_server_url();

        if (empty($ocrServerUrl)) {
            mtrace("[OCR SERVICE] OCR server URL not configured");
            return null;
        }

        // Determine endpoint based on file type.
        $endpoint = ($type === 'pdf') ? '/ocr-pdf' : '/ocr';
        $url = rtrim($ocrServerUrl, '/') . $endpoint;

        $filename = $file->get_filename();
        $content = $file->get_content();

        if (empty($content)) {
            mtrace("[OCR SERVICE] File {$filename} is empty, skipping OCR");
            return null;
        }

        mtrace("[OCR SERVICE] Sending file to OCR server: {$filename} via {$endpoint}");

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
                mtrace("[OCR SERVICE] OCR API curl error: {$curlError}");
                return null;
            }

            if ($httpCode !== 200) {
                mtrace("[OCR SERVICE] OCR API returned HTTP {$httpCode}: " . substr($response, 0, 500));
                return null;
            }

            $responseData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                mtrace("[OCR SERVICE] OCR API JSON decode error: " . json_last_error_msg());
                return null;
            }

            $extractedText = $responseData['text'] ?? '';

            if (!empty($extractedText)) {
                mtrace("[OCR SERVICE] OCR extracted " . strlen($extractedText) . " characters from {$filename}");
            } else {
                mtrace("[OCR SERVICE] OCR returned empty text for {$filename}");
            }

            return $extractedText;

        } catch (\Exception $e) {
            mtrace("[OCR SERVICE] OCR API exception: " . $e->getMessage());
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
    public static function extract_docx_text(\stored_file $file): string
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
     * Extract text from a single file based on its type.
     *
     * @param \stored_file $file The file to extract text from
     * @return string|null Extracted text, or null if extraction failed
     */
    public static function extract_from_file(\stored_file $file): ?string
    {
        $filename = $file->get_filename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeType = $file->get_mimetype();

        if ($extension === 'pdf') {
            if (self::is_enabled()) {
                return self::call_ocr_api($file, 'pdf');
            }
            return null;
        }

        if ($extension === 'docx') {
            return self::extract_docx_text($file);
        }

        if ($extension === 'txt') {
            return $file->get_content();
        }

        if (in_array($mimeType, self::SUPPORTED_IMAGE_MIMETYPES, true)) {
            if (self::is_enabled()) {
                return self::call_ocr_api($file, 'image');
            }
            return null;
        }

        return null;
    }

    /**
     * Extract text from a PDF file stored in the answer_file area by cmid.
     *
     * This method retrieves files from the 'local_autograding/answer_file' file area
     * and uses the OCR server to extract text content.
     *
     * @param int $cmid Course module ID
     * @return string|null Extracted text, or null on error
     */
    public static function extract_pdf_text_by_cmid(int $cmid): ?string
    {
        // Get OCR server URL from config.
        $ocrServerUrl = self::get_server_url();

        if (empty($ocrServerUrl)) {
            debugging('[AUTOGRADING] ERROR: OCR server URL not configured', DEBUG_DEVELOPER);
            return null;
        }

        $tempFile = null;
        try {
            // Step 1: Get the uploaded file.
            $fs = get_file_storage();
            $context = \context_system::instance();

            $files = $fs->get_area_files($context->id, 'local_autograding', 'answer_file', $cmid, 'id', false);

            if (empty($files)) {
                debugging('[AUTOGRADING] ERROR: No files found in storage for cmid ' . $cmid, DEBUG_DEVELOPER);
                return null;
            }

            // Step 2: Get the first (and only) file.
            $file = reset($files);
            $filename = $file->get_filename();
            $filecontent = $file->get_content();

            if (empty($filecontent)) {
                debugging('[AUTOGRADING] ERROR: File content is empty for file: ' . $filename, DEBUG_DEVELOPER);
                return null;
            }

            // Step 3: Send file to OCR server.
            $url = rtrim($ocrServerUrl, '/') . '/ocr-pdf';

            // Create a temporary file for curl upload.
            $tempFile = tempnam(sys_get_temp_dir(), 'ocr_pdf_');
            file_put_contents($tempFile, $filecontent);

            // Use curl for multipart/form-data upload.
            $ch = curl_init();

            $postFields = [
                'file' => new \CURLFile($tempFile, $file->get_mimetype(), $filename),
            ];

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
                debugging('[AUTOGRADING] OCR API curl error: ' . $curlError, DEBUG_DEVELOPER);
                return null;
            }

            if ($httpCode !== 200) {
                debugging('[AUTOGRADING] OCR API returned HTTP ' . $httpCode . ': ' . substr($response, 0, 500), DEBUG_DEVELOPER);
                return null;
            }

            $responseData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                debugging('[AUTOGRADING] OCR API JSON decode error: ' . json_last_error_msg(), DEBUG_DEVELOPER);
                return null;
            }

            $extractedText = $responseData['text'] ?? '';

            if (empty($extractedText)) {
                debugging('[AUTOGRADING] OCR returned empty text for cmid ' . $cmid, DEBUG_DEVELOPER);
                return '';
            }

            return trim($extractedText);

        } catch (\Exception $e) {
            debugging('[AUTOGRADING] ERROR: Exception during PDF extraction for cmid ' . $cmid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        } finally {
            // Always clean up temp file.
            if ($tempFile !== null && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }
}
