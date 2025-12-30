<?php
defined('MOODLE_INTERNAL') || die();

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

function local_autorestrict_get_course_settings($courseid) {
    global $DB;
    return $DB->get_record('local_autorestrict_course', ['courseid' => $courseid]);
}
