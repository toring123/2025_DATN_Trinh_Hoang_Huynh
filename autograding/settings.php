<?php
declare(strict_types=1);

/**
 * Admin settings for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create new settings page under Site administration > Plugins > Local plugins.
    $settings = new admin_settingpage(
        'local_autograding',
        get_string('pluginname', 'local_autograding')
    );

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

    // Add Gemini Model selection setting.
    $models = [
        'gemini-2.0-flash' => 'Gemini 2.0 Flash (Recommended)',
        'gemini-1.5-flash' => 'Gemini 1.5 Flash',
        'gemini-1.5-pro' => 'Gemini 1.5 Pro',
        'gemini-pro' => 'Gemini Pro (Legacy)',
    ];
    $settings->add(new admin_setting_configselect(
        'local_autograding/gemini_model',
        get_string('gemini_model', 'local_autograding'),
        get_string('gemini_model_desc', 'local_autograding'),
        'gemini-2.0-flash',
        $models
    ));

    // Add the settings page to the local plugins category.
    $ADMIN->add('localplugins', $settings);
}
