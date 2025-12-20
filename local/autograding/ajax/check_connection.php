<?php
/**
 * AJAX endpoint for checking connection to Ollama and OCR servers.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

// Require login and admin capability.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Get the service type parameter.
$service = required_param('service', PARAM_ALPHA);

// Get optional endpoint override (for testing unsaved values).
$endpoint = optional_param('endpoint', '', PARAM_URL);

header('Content-Type: application/json');

/**
 * Check connection to Ollama server.
 *
 * @param string $endpoint The Ollama endpoint URL.
 * @return array Connection status result.
 */
function check_ollama_connection(string $endpoint): array {
    // Extract base URL from chat completions endpoint.
    // e.g., http://localhost:11434/v1/chat/completions -> http://localhost:11434
    $baseurl = preg_replace('#/v1/chat/completions$#', '', $endpoint);
    if (empty($baseurl)) {
        $baseurl = $endpoint;
    }

    // Try to connect to Ollama API tags endpoint.
    $tagsurl = rtrim($baseurl, '/') . '/api/tags';

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $tagsurl,
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
            'success' => false,
            'message' => get_string('invalid_response', 'local_autograding'),
            'details' => null,
        ];
    }

    // Count available models.
    $modelcount = isset($data['models']) ? count($data['models']) : 0;
    $modelnames = [];
    if (isset($data['models']) && is_array($data['models'])) {
        foreach (array_slice($data['models'], 0, 5) as $model) {
            $modelnames[] = $model['name'] ?? 'unknown';
        }
    }

    return [
        'success' => true,
        'message' => get_string('connection_success', 'local_autograding'),
        'details' => [
            'model_count' => $modelcount,
            'models' => $modelnames,
        ],
    ];
}

/**
 * Check connection to OCR server.
 *
 * @param string $endpoint The OCR server URL.
 * @return array Connection status result.
 */
function check_ocr_connection(string $endpoint): array {
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

// Validate service type.
if (!in_array($service, ['ollama', 'ocr'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid service type',
        'details' => null,
    ]);
    exit;
}

try {
    if ($service === 'ollama') {
        // Use provided endpoint or get from config.
        if (empty($endpoint)) {
            $endpoint = get_config('local_autograding', 'qwen_endpoint');
        }
        if (empty($endpoint)) {
            $endpoint = 'http://localhost:11434/v1/chat/completions';
        }
        $result = check_ollama_connection($endpoint);
    } else {
        // OCR service.
        if (empty($endpoint)) {
            $endpoint = get_config('local_autograding', 'ocr_server_url');
        }
        if (empty($endpoint)) {
            $endpoint = 'http://127.0.0.1:8001';
        }
        $result = check_ocr_connection($endpoint);
    }

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => get_string('servererror', 'local_autograding') . ': ' . $e->getMessage(),
        'details' => null,
    ]);
}
