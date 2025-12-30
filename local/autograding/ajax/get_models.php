<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

use local_autograding\llm_service;

require_login();
require_capability('moodle/site:config', context_system::instance());

$provider = required_param('provider', PARAM_ALPHA);

$apikey = optional_param('apikey', '', PARAM_RAW);
$endpoint = optional_param('endpoint', '', PARAM_URL);

if (!in_array($provider, ['gemini', 'qwen'], true)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid provider',
        'models' => [],
    ]);
    exit;
}

try {
    if ($provider === 'gemini') {
        $models = llm_service::get_available_models_with_credentials('gemini', $apikey, '');
    } else {
        $models = llm_service::get_available_models_with_credentials('qwen', '', $endpoint);
    }

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
