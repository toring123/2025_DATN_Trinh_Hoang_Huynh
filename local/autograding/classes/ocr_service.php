<?php
declare(strict_types=1);
namespace local_autograding;

defined('MOODLE_INTERNAL') || die();

class ocr_service
{
    public const SUPPORTED_IMAGE_MIMETYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
    ];

    public static function is_enabled(): bool
    {
        $ocrServerUrl = get_config('local_autograding', 'ocr_server_url');
        return !empty($ocrServerUrl);
    }

    public static function get_server_url(): ?string
    {
        $url = get_config('local_autograding', 'ocr_server_url');
        return !empty($url) ? $url : null;
    }

    public static function check_connection(?string $endpoint = null): array
    {
        if (empty($endpoint)) {
            $endpoint = self::get_server_url();
        }
        if (empty($endpoint)) {
            $endpoint = 'http://127.0.0.1:8001';
        }

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

    public static function call_ocr_api(\stored_file $file, string $type): ?string
    {
        $ocrServerUrl = self::get_server_url();

        if (empty($ocrServerUrl)) {
            mtrace("[OCR SERVICE] OCR server URL not configured");
            return null;
        }

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
            $tempFile = tempnam(sys_get_temp_dir(), 'ocr_');
            file_put_contents($tempFile, $content);

            $ch = curl_init();

            if ($type === 'pdf') {
                $postFields = [
                    'file' => new \CURLFile($tempFile, $file->get_mimetype(), $filename),
                ];
            } else {
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
            if ($tempFile !== null && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    public static function extract_docx_text(\stored_file $file): string
    {
        $content = $file->get_content();

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

            $text = strip_tags($xml);
            $text = preg_replace('/\s+/', ' ', $text);

            return trim($text);
        } finally {
            @unlink($tempfile);
        }
    }

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

    public static function extract_pdf_text_by_cmid(int $cmid): ?string
    {
        $ocrServerUrl = self::get_server_url();

        if (empty($ocrServerUrl)) {
            debugging('[AUTOGRADING] ERROR: OCR server URL not configured', DEBUG_DEVELOPER);
            return null;
        }

        $tempFile = null;
        try {
            $fs = get_file_storage();
            $context = \context_system::instance();

            $files = $fs->get_area_files($context->id, 'local_autograding', 'answer_file', $cmid, 'id', false);

            if (empty($files)) {
                debugging('[AUTOGRADING] ERROR: No files found in storage for cmid ' . $cmid, DEBUG_DEVELOPER);
                return null;
            }

            $file = reset($files);
            $filename = $file->get_filename();
            $filecontent = $file->get_content();

            if (empty($filecontent)) {
                debugging('[AUTOGRADING] ERROR: File content is empty for file: ' . $filename, DEBUG_DEVELOPER);
                return null;
            }

            $url = rtrim($ocrServerUrl, '/') . '/ocr-pdf';

            $tempFile = tempnam(sys_get_temp_dir(), 'ocr_pdf_');
            file_put_contents($tempFile, $filecontent);

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
            if ($tempFile !== null && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }
}
