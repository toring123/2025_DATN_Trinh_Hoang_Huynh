define(['jquery'], function($) {
    'use strict';

    return {
        init: function() {
            $(document).ready(function() {
                var providerSelect = $('#id_s_local_autograding_ai_provider');

                if (providerSelect.length === 0) {
                    return;
                }

                var geminiSettings = ['gemini_header', 'gemini_api_key', 'gemini_model'];
                var qwenSettings = ['qwen_header', 'qwen_endpoint', 'qwen_model'];

                function getSettingSection(settingName) {
                    var inputElement = $('#id_s_local_autograding_' + settingName);
                    
                    if (inputElement.length > 0) {
                        var container = inputElement.closest('[id^="admin-"]');
                        if (container.length > 0) {
                            return container;
                        }
                        container = inputElement.closest('.row.mb-3, .form-group, .form-item');
                        if (container.length > 0) {
                            return container;
                        }
                    }
                    
                    var adminElement = $('#admin-' + settingName);
                    if (adminElement.length > 0) {
                        return adminElement;
                    }
                    
                    var dataElement = $('[data-name="s_local_autograding_' + settingName + '"]');
                    if (dataElement.length > 0) {
                        return dataElement;
                    }

                    return $();
                }

                function toggleSettings(settings, show) {
                    settings.forEach(function(settingName) {
                        var section = getSettingSection(settingName);
                        if (section.length > 0) {
                            if (show) {
                                section.show();
                            } else {
                                section.hide();
                            }
                        }
                    });
                }

                function toggleProviderSettings() {
                    var selectedProvider = providerSelect.val();

                    if (selectedProvider === 'gemini') {
                        toggleSettings(geminiSettings, true);
                        toggleSettings(qwenSettings, false);
                    } else if (selectedProvider === 'qwen') {
                        toggleSettings(geminiSettings, false);
                        toggleSettings(qwenSettings, true);
                    }
                }

                toggleProviderSettings();

                providerSelect.on('change', function() {
                    toggleProviderSettings();
                });
            });
        }
    };
});
