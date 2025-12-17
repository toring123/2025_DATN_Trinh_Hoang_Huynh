<?php
declare(strict_types=1);

/**
 * LLM Service class for local_autograding plugin.
 *
 * Handles all LLM API interactions for grading (Gemini and Qwen/Ollama).
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

namespace local_autograding;

defined('MOODLE_INTERNAL') || die();

/**
 * LLM Service class for AI-based grading.
 */
class llm_service
{
    /** @var string Gemini API base URL */
    private const GEMINI_API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    /** @var string Default Ollama endpoint */
    private const DEFAULT_OLLAMA_ENDPOINT = 'http://localhost:11434';

    /**
     * Get list of available models from the provider API.
     *
     * @param string $provider The AI provider ('gemini' or 'qwen')
     * @return array List of models as [id => display_name]
     */
    public static function get_available_models(string $provider): array
    {
        if ($provider === 'gemini') {
            return self::fetch_gemini_models();
        } else {
            return self::fetch_ollama_models();
        }
    }

    /**
     * Get list of available models using provided credentials (for real-time validation).
     * Unlike get_available_models(), this does NOT fall back to defaults on failure.
     *
     * @param string $provider The AI provider ('gemini' or 'qwen')
     * @param string $apikey Optional API key for Gemini (if empty, uses saved config)
     * @param string $endpoint Optional endpoint for Qwen (if empty, uses saved config)
     * @return array List of models as [id => display_name], or ['--nomodel--' => '--No model--'] on failure
     */
    public static function get_available_models_with_credentials(string $provider, string $apikey = '', string $endpoint = ''): array
    {
        if ($provider === 'gemini') {
            return self::fetch_gemini_models_realtime($apikey);
        } else {
            return self::fetch_ollama_models_realtime($endpoint);
        }
    }

    /**
     * Fetch available Gemini models from Google API.
     *
     * @return array List of models as [id => display_name]
     */
    private static function fetch_gemini_models(): array
    {
        $apiKey = get_config('local_autograding', 'gemini_api_key');
        if (empty($apiKey)) {
            // Return default models when no API key configured.
            return self::get_default_gemini_models();
        }

        $url = self::GEMINI_API_BASE . "/models?key={$apiKey}";

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return self::get_default_gemini_models();
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE || empty($data['models'])) {
                return self::get_default_gemini_models();
            }

            $models = [];
            foreach ($data['models'] as $model) {
                // Filter to only include generateContent capable models.
                $supportedMethods = $model['supportedGenerationMethods'] ?? [];
                if (in_array('generateContent', $supportedMethods, true)) {
                    $name = $model['name'] ?? '';
                    // Remove 'models/' prefix.
                    $id = str_replace('models/', '', $name);
                    $displayName = $model['displayName'] ?? $id;
                    $models[$id] = $displayName;
                }
            }

            return !empty($models) ? $models : self::get_default_gemini_models();

        } catch (\Exception $e) {
            return self::get_default_gemini_models();
        }
    }

    /**
     * Get default Gemini models when API is unavailable.
     *
     * @return array Default models
     */
    private static function get_default_gemini_models(): array
    {
        return [
            'gemini-2.5-flash' => 'Gemini 2.5 Flash (Recommended)',
            'gemini-2.0-flash' => 'Gemini 2.0 Flash',
            'gemini-1.5-flash' => 'Gemini 1.5 Flash',
            'gemini-1.5-pro' => 'Gemini 1.5 Pro',
        ];
    }

    /**
     * Fetch available models from Ollama/OpenAI-compatible API.
     *
     * @return array List of models as [id => display_name]
     */
    private static function fetch_ollama_models(): array
    {
        $endpoint = get_config('local_autograding', 'qwen_endpoint');
        if (empty($endpoint)) {
            $endpoint = self::DEFAULT_OLLAMA_ENDPOINT . '/v1/chat/completions';
        }

        // Extract base URL from endpoint (remove /v1/chat/completions).
        $baseUrl = preg_replace('#/v1/chat/completions$#', '', $endpoint);
        $modelsUrl = rtrim($baseUrl, '/') . '/v1/models';

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $modelsUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return self::get_default_ollama_models();
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return self::get_default_ollama_models();
            }

            $models = [];

            // Handle OpenAI-compatible format: {"data": [{"id": "model-name", ...}], "object": "list"}
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $model) {
                    $id = $model['id'] ?? '';
                    if (!empty($id)) {
                        $models[$id] = $id;
                    }
                }
            }
            // Handle Ollama native format: {"models": [{"name": "model-name", ...}]}
            else if (isset($data['models']) && is_array($data['models'])) {
                foreach ($data['models'] as $model) {
                    $name = $model['name'] ?? '';
                    if (!empty($name)) {
                        $models[$name] = $name;
                    }
                }
            }

            return !empty($models) ? $models : self::get_default_ollama_models();

        } catch (\Exception $e) {
            return self::get_default_ollama_models();
        }
    }

    /**
     * Get default Ollama models when API is unavailable.
     *
     * @return array Default models
     */
    private static function get_default_ollama_models(): array
    {
        return [
            'qwen2.5:3b' => 'Qwen 2.5 3B',
            'qwen2.5:7b' => 'Qwen 2.5 7B',
            'llama3.2:3b' => 'Llama 3.2 3B',
        ];
    }

    /**
     * Fetch Gemini models using provided API key for real-time validation.
     * Unlike fetch_gemini_models(), this does NOT fall back to defaults.
     *
     * @param string $apiKey The API key to use (if empty, uses saved config)
     * @return array List of models, or ['--nomodel--' => '--No model--'] on failure
     */
    private static function fetch_gemini_models_realtime(string $apiKey = ''): array
    {
        // Use provided key or fall back to saved config.
        if (empty($apiKey)) {
            $apiKey = get_config('local_autograding', 'gemini_api_key');
        }

        if (empty($apiKey)) {
            return ['--nomodel--' => '--No model--'];
        }

        $url = self::GEMINI_API_BASE . "/models?key={$apiKey}";

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return ['--nomodel--' => '--No model--'];
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE || empty($data['models'])) {
                return ['--nomodel--' => '--No model--'];
            }

            $models = [];
            foreach ($data['models'] as $model) {
                $supportedMethods = $model['supportedGenerationMethods'] ?? [];
                if (in_array('generateContent', $supportedMethods, true)) {
                    $name = $model['name'] ?? '';
                    $id = str_replace('models/', '', $name);
                    $displayName = $model['displayName'] ?? $id;
                    $models[$id] = $displayName;
                }
            }

            return !empty($models) ? $models : ['--nomodel--' => '--No model--'];

        } catch (\Exception $e) {
            return ['--nomodel--' => '--No model--'];
        }
    }

    /**
     * Fetch Ollama models using provided endpoint for real-time validation.
     * Unlike fetch_ollama_models(), this does NOT fall back to defaults.
     *
     * @param string $endpoint The endpoint URL to use (if empty, uses saved config)
     * @return array List of models, or ['--nomodel--' => '--No model--'] on failure
     */
    private static function fetch_ollama_models_realtime(string $endpoint = ''): array
    {
        // Use provided endpoint or fall back to saved config.
        if (empty($endpoint)) {
            $endpoint = get_config('local_autograding', 'qwen_endpoint');
        }

        if (empty($endpoint)) {
            $endpoint = self::DEFAULT_OLLAMA_ENDPOINT . '/v1/chat/completions';
        }

        // Extract base URL from endpoint (remove /v1/chat/completions).
        $baseUrl = preg_replace('#/v1/chat/completions$#', '', $endpoint);
        $modelsUrl = rtrim($baseUrl, '/') . '/v1/models';

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $modelsUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return ['--nomodel--' => '--No model--'];
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['--nomodel--' => '--No model--'];
            }

            $models = [];

            // Handle OpenAI-compatible format.
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $model) {
                    $id = $model['id'] ?? '';
                    if (!empty($id)) {
                        $models[$id] = $id;
                    }
                }
            }
            // Handle Ollama native format.
            else if (isset($data['models']) && is_array($data['models'])) {
                foreach ($data['models'] as $model) {
                    $name = $model['name'] ?? '';
                    if (!empty($name)) {
                        $models[$name] = $name;
                    }
                }
            }

            return !empty($models) ? $models : ['--nomodel--' => '--No model--'];

        } catch (\Exception $e) {
            return ['--nomodel--' => '--No model--'];
        }
    }
    /**
     * Grade a student submission using AI.
     *
     * @param string $provider The AI provider ('gemini' or 'qwen')
     * @param string $question The assignment question
     * @param string $referenceAnswer The reference answer
     * @param string $studentResponse The student's text response
     * @param int $autogradingoption The autograding option (1, 2, or 3)
     * @return array|null Array with 'grade' and 'explanation', or null on error
     * @throws \moodle_exception When rate limited (HTTP 429) to trigger retry.
     */
    public static function grade(
        string $provider,
        string $question,
        string $referenceAnswer,
        string $studentResponse,
        int $autogradingoption
    ): ?array {
        // Build the user content based on the autograding option.
        if ($autogradingoption === 1) {
            $userContent = self::build_user_content_without_reference($question, $studentResponse);
        } else {
            $userContent = self::build_user_content_with_reference($question, $referenceAnswer, $studentResponse);
        }

        // Get the system instruction from config.
        $systemInstruction = get_config('local_autograding', 'system_instruction');
        if (empty($systemInstruction)) {
            $systemInstruction = get_string('system_instruction_default', 'local_autograding');
        }
        $systemInstruction .= "\n" . get_string('system_instruction_footer', 'local_autograding');

        // Route to the appropriate provider.
        if ($provider === 'qwen') {
            return self::call_qwen_api($systemInstruction, $userContent);
        } else {
            return self::call_gemini_api($systemInstruction, $userContent);
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
    private static function call_gemini_api(string $systemInstruction, string $userContent): ?array
    {
        // Get API key.
        $apiKey = get_config('local_autograding', 'gemini_api_key');
        if (empty($apiKey)) {
            mtrace("[LLM SERVICE] Gemini API key not configured");
            return null;
        }

        // Get the configured model or use default.
        $model = get_config('local_autograding', 'gemini_model') ?: 'gemini-2.5-flash';

        // Build the API endpoint URL.
        $endpoint = self::GEMINI_API_BASE . "/models/{$model}:generateContent?key={$apiKey}";

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
            $ch = curl_init();

            mtrace("[LLM SERVICE] API Model: " . $model);
            mtrace("[LLM SERVICE] Sending request to Gemini API...");

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
                mtrace("[LLM SERVICE] Gemini API curl error: {$curlError}");
                return null;
            }

            mtrace("[LLM SERVICE] HTTP Response Code: " . $httpcode);

            // Handle rate limiting - throw exception to trigger retry.
            if ($httpcode === 429) {
                mtrace("[LLM SERVICE] Rate limited (HTTP 429), task will be retried");
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
                mtrace("[LLM SERVICE] API ERROR: " . $errorMsg);
                return null;
            }

            $responseData = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                mtrace("[LLM SERVICE] JSON decode error: " . json_last_error_msg());
                return null;
            }

            // Extract the text content from Gemini response.
            $textContent = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (empty($textContent)) {
                mtrace("[LLM SERVICE] No text content in response");
                return null;
            }

            // Parse the JSON from the response text.
            return self::parse_grading_response($textContent);

        } catch (\moodle_exception $e) {
            // Re-throw moodle_exception (including rate limit) to trigger retry.
            throw $e;
        } catch (\Exception $e) {
            mtrace("[LLM SERVICE] Gemini API Exception: " . $e->getMessage());
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
    private static function call_qwen_api(string $systemInstruction, string $userContent): ?array
    {
        // Get endpoint URL from config.
        $configEndpoint = get_config('local_autograding', 'qwen_endpoint');

        // Ensure endpoint has the correct path.
        if (empty($configEndpoint)) {
            $endpoint = self::DEFAULT_OLLAMA_ENDPOINT . '/v1/chat/completions';
        } else {
            // If the endpoint doesn't contain /v1/chat/completions, append it.
            $endpoint = rtrim($configEndpoint, '/');
            if (strpos($endpoint, '/v1/chat/completions') === false) {
                $endpoint .= '/v1/chat/completions';
            }
        }

        // Get the configured model or use default.
        $model = get_config('local_autograding', 'qwen_model') ?: 'qwen2.5:3b';

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

        mtrace("[LLM SERVICE] payload " . json_encode($payload, JSON_UNESCAPED_UNICODE));

        try {
            $ch = curl_init();

            mtrace("[LLM SERVICE] API Model: " . $model);
            mtrace("[LLM SERVICE] Sending request to Qwen API at: " . $endpoint);

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
                mtrace("[LLM SERVICE] Qwen API curl error: {$curlError}");
                return null;
            }

            mtrace("[LLM SERVICE] HTTP Response Code: " . $httpcode);

            // Handle rate limiting - throw exception to trigger retry.
            if ($httpcode === 429) {
                mtrace("[LLM SERVICE] Rate limited (HTTP 429), task will be retried");
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
                mtrace("[LLM SERVICE] API ERROR: " . $errorMsg);
                return null;
            }

            $responseData = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                mtrace("[LLM SERVICE] JSON decode error: " . json_last_error_msg());
                return null;
            }

            // Extract the text content from OpenAI-compatible response.
            $textContent = $responseData['choices'][0]['message']['content'] ?? null;

            if (empty($textContent)) {
                mtrace("[LLM SERVICE] No text content in response");
                return null;
            }

            // Parse the JSON from the response text.
            return self::parse_grading_response($textContent);

        } catch (\moodle_exception $e) {
            // Re-throw moodle_exception (including rate limit) to trigger retry.
            throw $e;
        } catch (\Exception $e) {
            mtrace("[LLM SERVICE] Qwen API Exception: " . $e->getMessage());
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
    private static function build_user_content_with_reference(
        string $question,
        string $referenceAnswer,
        string $studentResponse
    ): string {
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
    private static function build_user_content_without_reference(
        string $question,
        string $studentResponse
    ): string {
        return "---
[CÂU HỎI]:
{$question}
[CÂU TRẢ LỜI CỦA HỌC SINH]:
{$studentResponse}
---
";
    }

    /**
     * Parse the grading response from AI.
     *
     * @param string $responseText The response text from AI
     * @return array|null Array with 'grade' and 'explanation', or null on error
     */
    private static function parse_grading_response(string $responseText): ?array
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
            mtrace("[LLM SERVICE] Failed to parse grading response: " . json_last_error_msg());
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
}
