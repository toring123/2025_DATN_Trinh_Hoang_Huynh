<?php
/**
 * Library functions for local_autorestrict
 *
 * @package    local_autorestrict
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add link to course navigation
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param context $context The course context
 */
function local_autorestrict_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('local/autorestrict:manage', $context)) {
        $url = new moodle_url('/local/autorestrict/course_settings.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('pluginname', 'local_autorestrict'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'autorestrict',
            new pix_icon('i/settings', '')
        );
    }
}

/**
 * Get course settings for autorestrict
 *
 * @param int $courseid Course ID
 * @return object|null Settings object or null if not configured
 */
function local_autorestrict_get_course_settings($courseid) {
    global $DB;
    return $DB->get_record('local_autorestrict_course', ['courseid' => $courseid]);
}
