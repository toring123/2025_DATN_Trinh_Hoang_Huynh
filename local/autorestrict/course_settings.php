<?php
/**
 * Course settings page for auto restrict
 *
 * @package    local_autorestrict
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = required_param('courseid', PARAM_INT);

// Get the course.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Require login and course access.
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/autorestrict:manage', $context);

// Set up the page.
$PAGE->set_url(new moodle_url('/local/autorestrict/course_settings.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_autorestrict'));
$PAGE->set_heading($course->fullname);

// Add navigation.
$PAGE->navbar->add(get_string('pluginname', 'local_autorestrict'));

// Get existing settings for this course.
$settings = $DB->get_record('local_autorestrict_course', ['courseid' => $courseid]);

// Create the form.
$form = new \local_autorestrict\form\course_settings_form(null, ['courseid' => $courseid]);

// Set form data if settings exist.
if ($settings) {
    $form->set_data($settings);
}

// Handle form submission.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
} else if ($data = $form->get_data()) {
    $now = time();
    
    if ($settings) {
        // Update existing settings.
        $data->id = $settings->id;
        $data->timemodified = $now;
        $DB->update_record('local_autorestrict_course', $data);
    } else {
        // Insert new settings.
        $data->timecreated = $now;
        $data->timemodified = $now;
        $DB->insert_record('local_autorestrict_course', $data);
    }
    
    // Show success message.
    \core\notification::success(get_string('settings_saved', 'local_autorestrict'));
    redirect(new moodle_url('/local/autorestrict/course_settings.php', ['courseid' => $courseid]));
}

// Output.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_autorestrict'));
echo html_writer::tag('p', get_string('course_settings_desc', 'local_autorestrict'));
$form->display();
echo $OUTPUT->footer();
