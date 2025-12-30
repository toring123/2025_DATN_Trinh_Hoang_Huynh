<?php
declare(strict_types=1);

/**
 * Admin settings for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

defined('MOODLE_INTERNAL') || die();

use local_autograding\llm_service;

if ($hassiteconfig) {
    // Create new settings page under Site administration > Plugins > Local plugins.
    $settings = new admin_settingpage(
        'local_autograding',
        get_string('pluginname', 'local_autograding')
    );

    // Add AI Provider section header.
    $settings->add(new admin_setting_heading(
        'local_autograding/ai_provider_header',
        get_string('ai_provider_header', 'local_autograding'),
        get_string('ai_provider_header_desc', 'local_autograding')
    ));

    // Add AI Provider selection setting.
    $providers = [
        'gemini' => get_string('provider_gemini', 'local_autograding'),
        'qwen' => get_string('provider_qwen', 'local_autograding'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_autograding/ai_provider',
        get_string('ai_provider', 'local_autograding'),
        get_string('ai_provider_desc', 'local_autograding'),
        'gemini',
        $providers
    ));

    // Add System Instruction textarea.
    $defaultinstruction = get_string('system_instruction_default', 'local_autograding');
    $settings->add(new admin_setting_configtextarea(
        'local_autograding/system_instruction',
        get_string('system_instruction', 'local_autograding'),
        get_string('system_instruction_desc', 'local_autograding'),
        $defaultinstruction
    ));

    // Add Gemini API section header.
    $settings->add(new admin_setting_heading(
        'local_autograding/gemini_header',
        get_string('gemini_settings_header', 'local_autograding'),
        get_string('gemini_settings_desc', 'local_autograding')
    ));

    // Add Gemini API Key setting (password field for security).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_autograding/gemini_api_key',
        get_string('gemini_api_key', 'local_autograding'),
        get_string('gemini_api_key_desc', 'local_autograding'),
        ''
    ));

    // Add Gemini Model selection setting with dynamic options.
    // Fetch models from API if available, otherwise use defaults.
    $geminimodels = llm_service::get_available_models('gemini');
    $settings->add(new admin_setting_configselect(
        'local_autograding/gemini_model',
        get_string('gemini_model', 'local_autograding'),
        get_string('gemini_model_desc', 'local_autograding') . ' ' .
        get_string('refresh_page_for_models', 'local_autograding'),
        'gemini-2.5-flash',
        $geminimodels
    ));

    // Add Local Qwen section header.
    $settings->add(new admin_setting_heading(
        'local_autograding/qwen_header',
        get_string('qwen_settings_header', 'local_autograding'),
        get_string('qwen_settings_desc', 'local_autograding')
    ));

    // Add Local Qwen Endpoint URL setting.
    $settings->add(new admin_setting_configtext(
        'local_autograding/qwen_endpoint',
        get_string('qwen_endpoint', 'local_autograding'),
        get_string('qwen_endpoint_desc', 'local_autograding'),
        'http://localhost:11434'
    ));

    // Add Local Qwen Model selection setting with dynamic options.
    $qwenmodels = llm_service::get_available_models('qwen');
    $settings->add(new admin_setting_configselect(
        'local_autograding/qwen_model',
        get_string('qwen_model', 'local_autograding'),
        get_string('qwen_model_desc', 'local_autograding') . ' ' .
        get_string('refresh_page_for_models', 'local_autograding'),
        'qwen2.5:3b',
        $qwenmodels
    ));

    // Add OCR Server section header.
    $settings->add(new admin_setting_heading(
        'local_autograding/ocr_header',
        get_string('ocr_settings_header', 'local_autograding'),
        get_string('ocr_settings_desc', 'local_autograding')
    ));

    // Add OCR Server URL setting.
    $settings->add(new admin_setting_configtext(
        'local_autograding/ocr_server_url',
        get_string('ocr_server_url', 'local_autograding'),
        get_string('ocr_server_url_desc', 'local_autograding'),
        'http://127.0.0.1:8001'
    ));

    // Add OCR Check Connection button.
    $ocrcheckbtn = '<button type="button" id="check_ocr_connection" class="btn btn-secondary">' .
        get_string('check_connection', 'local_autograding') . '</button>' .
        '<span id="ocr_connection_status" class="ml-2"></span>';
    $settings->add(new admin_setting_heading(
        'local_autograding/ocr_check_connection',
        '',
        $ocrcheckbtn
    ));

    // Add the settings page to the local plugins category.
    $ADMIN->add('localplugins', $settings);

    // Inject JavaScript for conditional display of provider-specific settings.
    // This script will show/hide Gemini or Qwen settings based on the selected provider.
    // It also handles real-time model fetching when API key or endpoint changes.
    $PAGE->requires->js_amd_inline("
        require(['jquery'], function($) {
            $(document).ready(function() {
                var providerSelect = $('#id_s_local_autograding_ai_provider');
                var geminiApiKeyInput = $('#id_s_local_autograding_gemini_api_key');
                var geminiModelSelect = $('#id_s_local_autograding_gemini_model');
                var qwenEndpointInput = $('#id_s_local_autograding_qwen_endpoint');
                var qwenModelSelect = $('#id_s_local_autograding_qwen_model');

                // Define the setting rows to toggle.
                var geminiSettings = [
                    '#admin-gemini_header',
                    '#admin-gemini_api_key',
                    '#admin-gemini_model'
                ];
                var qwenSettings = [
                    '#admin-qwen_header',
                    '#admin-qwen_endpoint',
                    '#admin-qwen_model'
                ];

                // Debounce function to limit API calls.
                function debounce(func, wait) {
                    var timeout;
                    return function() {
                        var context = this, args = arguments;
                        clearTimeout(timeout);
                        timeout = setTimeout(function() {
                            func.apply(context, args);
                        }, wait);
                    };
                }

                // Fetch models from API and update dropdown.
                function fetchModels(provider, apikey, endpoint, selectElement) {
                    var ajaxUrl = M.cfg.wwwroot + '/local/autograding/ajax/get_models.php';
                    var params = { provider: provider };

                    if (provider === 'gemini' && apikey) {
                        params.apikey = apikey;
                    } else if (provider === 'qwen' && endpoint) {
                        params.endpoint = endpoint;
                    }

                    // Show loading state.
                    selectElement.prop('disabled', true);
                    var currentValue = selectElement.val();

                    $.ajax({
                        url: ajaxUrl,
                        type: 'GET',
                        data: params,
                        dataType: 'json',
                        success: function(response) {
                            selectElement.empty();

                            if (response.success && response.models && response.models.length > 0) {
                                $.each(response.models, function(i, model) {
                                    var option = $('<option></option>')
                                        .attr('value', model.id)
                                        .text(model.name);
                                    selectElement.append(option);
                                });

                                // Try to restore previous selection if still valid.
                                if (selectElement.find('option[value=\"' + currentValue + '\"]').length > 0) {
                                    selectElement.val(currentValue);
                                }
                            } else {
                                selectElement.append(
                                    $('<option></option>')
                                        .attr('value', '--nomodel--')
                                        .text('--No model--')
                                );
                            }
                        },
                        error: function() {
                            selectElement.empty();
                            selectElement.append(
                                $('<option></option>')
                                    .attr('value', '--nomodel--')
                                    .text('--No model--')
                            );
                        },
                        complete: function() {
                            selectElement.prop('disabled', false);
                        }
                    });
                }

                // Debounced handlers for input changes.
                var debouncedGeminiFetch = debounce(function() {
                    var apikey = geminiApiKeyInput.val();
                    fetchModels('gemini', apikey, '', geminiModelSelect);
                }, 500);

                var debouncedQwenFetch = debounce(function() {
                    var endpoint = qwenEndpointInput.val();
                    fetchModels('qwen', '', endpoint, qwenModelSelect);
                }, 500);

                // Attach input listeners for real-time fetching.
                geminiApiKeyInput.on('input', debouncedGeminiFetch);
                qwenEndpointInput.on('input', debouncedQwenFetch);

                function toggleProviderSettings() {
                    var selectedProvider = providerSelect.val();

                    if (selectedProvider === 'gemini') {
                        // Show Gemini settings, hide Qwen settings.
                        geminiSettings.forEach(function(selector) {
                            $(selector).closest('.form-item, .row').show();
                        });
                        qwenSettings.forEach(function(selector) {
                            $(selector).closest('.form-item, .row').hide();
                        });
                    } else if (selectedProvider === 'qwen') {
                        // Show Qwen settings, hide Gemini settings.
                        geminiSettings.forEach(function(selector) {
                            $(selector).closest('.form-item, .row').hide();
                        });
                        qwenSettings.forEach(function(selector) {
                            $(selector).closest('.form-item, .row').show();
                        });
                    }
                }

                // Initial toggle on page load.
                toggleProviderSettings();

                // Toggle on provider change.
                providerSelect.on('change', function() {
                    toggleProviderSettings();
                });

                // Check connection function.
                function checkConnection(service, endpoint, statusElement) {
                    var ajaxUrl = M.cfg.wwwroot + '/local/autograding/ajax/check_connection.php';
                    var params = { service: service };
                    if (endpoint) {
                        params.endpoint = endpoint;
                    }

                    statusElement.removeClass('text-success text-danger').html(
                        '<i class=\"fa fa-spinner fa-spin\"></i> Checking...'
                    );

                    $.ajax({
                        url: ajaxUrl,
                        type: 'GET',
                        data: params,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                var msg = '<i class=\"fa fa-check-circle\"></i> ' + response.message;
                                if (response.details) {
                                    if (response.details.model_count !== undefined) {
                                        msg += ' (' + response.details.model_count + ' models)';
                                    }
                                }
                                statusElement.removeClass('text-danger').addClass('text-success').html(msg);
                            } else {
                                statusElement.removeClass('text-success').addClass('text-danger').html(
                                    '<i class=\"fa fa-times-circle\"></i> ' + response.message
                                );
                            }
                        },
                        error: function(xhr, status, error) {
                            statusElement.removeClass('text-success').addClass('text-danger').html(
                                '<i class=\"fa fa-times-circle\"></i> Connection failed: ' + error
                            );
                        }
                    });
                }

                // OCR check connection button handler.
                $('#check_ocr_connection').on('click', function() {
                    var endpoint = $('#id_s_local_autograding_ocr_server_url').val();
                    checkConnection('ocr', endpoint, $('#ocr_connection_status'));
                });
            });
        });
    ");
}
