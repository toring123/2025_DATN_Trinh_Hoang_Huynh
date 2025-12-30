<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);
require_capability('local/autorestrict:manage', $context);

$PAGE->set_url(new moodle_url('/local/autorestrict/course_settings.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_autorestrict'));
$PAGE->set_heading($course->fullname);

$PAGE->navbar->add(get_string('pluginname', 'local_autorestrict'));

$settings = $DB->get_record('local_autorestrict_course', ['courseid' => $courseid]);

$form = new \local_autorestrict\form\course_settings_form(null, ['courseid' => $courseid]);

if ($settings) {
    $form->set_data($settings);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
} else if ($data = $form->get_data()) {
    $now = time();
    
    $isEnabled = !empty($data->enabled);
    
    if ($settings) {
        $data->id = $settings->id;
        $data->timemodified = $now;
        $DB->update_record('local_autorestrict_course', $data);
    } else {
        $data->timecreated = $now;
        $data->timemodified = $now;
        $DB->insert_record('local_autorestrict_course', $data);
    }
    
    if ($isEnabled) {
        $config = \local_autorestrict\observer::get_course_config($courseid);
        $results = \local_autorestrict\observer::apply_to_all_modules($courseid, $config, true);
        \core\notification::success(get_string('settings_saved', 'local_autorestrict'));
        \core\notification::info(get_string('auto_applied', 'local_autorestrict', $results));
    } else {
        $clearedModules = \local_autorestrict\observer::clear_all_module_restrictions($courseid);
        $clearedSections = \local_autorestrict\observer::clear_all_section_restrictions($courseid);
        \core\notification::success(get_string('settings_saved', 'local_autorestrict'));
        if ($clearedModules > 0 || $clearedSections > 0) {
            \core\notification::info(get_string('auto_cleared', 'local_autorestrict', 
                (object)['modules' => $clearedModules, 'sections' => $clearedSections]));
        }
    }
    
    redirect(new moodle_url('/local/autorestrict/course_settings.php', ['courseid' => $courseid]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_autorestrict'));
echo html_writer::tag('p', get_string('course_settings_desc', 'local_autorestrict'));

$bulkUrl = new moodle_url('/local/autorestrict/bulk_difficulty.php', ['courseid' => $courseid]);
echo html_writer::tag('p', 
    html_writer::link($bulkUrl, get_string('manage_difficulty', 'local_autorestrict'), ['class' => 'btn btn-secondary'])
);

$form->display();
echo $OUTPUT->footer();
