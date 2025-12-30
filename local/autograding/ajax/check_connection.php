<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/ocr_service.php');

use local_autograding\ocr_service;

require_login();
require_capability('moodle/site:config', context_system::instance());

$service = required_param('service', PARAM_ALPHA);

$endpoint = optional_param('endpoint', '', PARAM_URL);

header('Content-Type: application/json');

if ($service !== 'ocr') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid service type. Only "ocr" is supported.',
        'details' => null,
    ]);
    exit;
}

try {
    $result = ocr_service::check_connection(!empty($endpoint) ? $endpoint : null);
    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => get_string('servererror', 'local_autograding') . ': ' . $e->getMessage(),
        'details' => null,
    ]);
}
