/**
 * JavaScript for toggling AI provider settings visibility.
 *
 * @module     local_autograding/settings_toggle
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    'use strict';

    return {
        /**
         * Initialize the settings toggle functionality.
         */
        init: function() {
            $(document).ready(function() {
                var providerSelect = $('#id_s_local_autograding_ai_provider');

                // If the provider select doesn't exist, we're not on the settings page.
                if (providerSelect.length === 0) {
                    return;
                }

                // Define the setting names (without prefix) to toggle.
                var geminiSettings = ['gemini_header', 'gemini_api_key', 'gemini_model'];
                var qwenSettings = ['qwen_header', 'qwen_endpoint', 'qwen_model'];

                /**
                 * Find and return the entire admin setting row/section.
                 * Moodle admin settings are wrapped in specific container elements.
                 * @param {string} settingName The setting name (e.g., 'gemini_api_key').
                 * @return {jQuery} The entire setting row element.
                 */
                function getSettingSection(settingName) {
                    // Method 1: Find by the setting ID pattern used in Moodle admin pages.
                    // The full ID is typically: id_s_local_autograding_<settingname>
                    var inputElement = $('#id_s_local_autograding_' + settingName);
                    
                    if (inputElement.length > 0) {
                        // Traverse up to find the main container div for this setting.
                        // In Boost theme, it's usually a div with class 'row' or 'form-group'.
                        // In Classic theme, it might be 'form-item' or similar.
                        var container = inputElement.closest('[id^="admin-"]');
                        if (container.length > 0) {
                            return container;
                        }
                        // Try other common containers.
                        container = inputElement.closest('.row.mb-3, .form-group, .form-item');
                        if (container.length > 0) {
                            return container;
                        }
                    }
                    
                    // Method 2: Try finding by admin-<settingname> ID directly (for headers).
                    var adminElement = $('#admin-' + settingName);
                    if (adminElement.length > 0) {
                        return adminElement;
                    }
                    
                    // Method 3: Find by data-name attribute.
                    var dataElement = $('[data-name="s_local_autograding_' + settingName + '"]');
                    if (dataElement.length > 0) {
                        return dataElement;
                    }

                    return $();
                }

                /**
                 * Toggle visibility of all settings in an array.
                 * @param {Array} settings Array of setting names.
                 * @param {boolean} show Whether to show or hide.
                 */
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

                /**
                 * Toggle visibility of provider-specific settings.
                 */
                function toggleProviderSettings() {
                    var selectedProvider = providerSelect.val();

                    if (selectedProvider === 'gemini') {
                        // Show Gemini settings, hide Qwen settings.
                        toggleSettings(geminiSettings, true);
                        toggleSettings(qwenSettings, false);
                    } else if (selectedProvider === 'qwen') {
                        // Show Qwen settings, hide Gemini settings.
                        toggleSettings(geminiSettings, false);
                        toggleSettings(qwenSettings, true);
                    }
                }

                // Initial toggle on page load.
                toggleProviderSettings();

                // Toggle on provider change.
                providerSelect.on('change', function() {
                    toggleProviderSettings();
                });
            });
        }
    };
});
