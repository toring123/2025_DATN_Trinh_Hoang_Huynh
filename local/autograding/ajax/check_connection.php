<?php
/**
 * AJAX endpoint for checking connection to OCR server.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/ocr_service.php');

use local_autograding\ocr_service;

// Require login and admin capability.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Get the service type parameter.
$service = required_param('service', PARAM_ALPHA);

// Get optional endpoint override (for testing unsaved values).
$endpoint = optional_param('endpoint', '', PARAM_URL);

header('Content-Type: application/json');

// Validate service type - only OCR is supported.
if ($service !== 'ocr') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid service type. Only "ocr" is supported.',
        'details' => null,
    ]);
    exit;
}

try {
    // Use OCR service to check connection.
    $result = ocr_service::check_connection(!empty($endpoint) ? $endpoint : null);
    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => get_string('servererror', 'local_autograding') . ': ' . $e->getMessage(),
        'details' => null,
    ]);
}
