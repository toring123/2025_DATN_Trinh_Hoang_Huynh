<?php
/**
 * AJAX endpoint for fetching AI models from provider APIs.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

use local_autograding\llm_service;

// Require login and admin capability.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Get the provider parameter.
$provider = required_param('provider', PARAM_ALPHA);

// Get optional credentials for real-time validation (not yet saved).
$apikey = optional_param('apikey', '', PARAM_RAW);
$endpoint = optional_param('endpoint', '', PARAM_URL);

// Validate provider.
if (!in_array($provider, ['gemini', 'qwen'], true)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid provider',
        'models' => [],
    ]);
    exit;
}

try {
    // Fetch models using provided credentials or saved config.
    if ($provider === 'gemini') {
        $models = llm_service::get_available_models_with_credentials('gemini', $apikey, '');
    } else {
        $models = llm_service::get_available_models_with_credentials('qwen', '', $endpoint);
    }

    // Convert to array format for JSON.
    $modelsArray = [];
    foreach ($models as $id => $name) {
        $modelsArray[] = [
            'id' => $id,
            'name' => $name,
        ];
    }

    echo json_encode([
        'success' => true,
        'models' => $modelsArray,
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'models' => [],
    ]);
}
