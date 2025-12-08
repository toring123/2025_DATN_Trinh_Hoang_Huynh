<?php
/**
 * Bulk set difficulty for activities
 *
 * @package    local_autorestrict
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

// Get the course.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Require login and course access.
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/autorestrict:manage', $context);

// Set up the page.
$PAGE->set_url(new moodle_url('/local/autorestrict/bulk_difficulty.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('bulk_difficulty', 'local_autorestrict'));
$PAGE->set_heading($course->fullname);

// Add navigation.
$PAGE->navbar->add(get_string('pluginname', 'local_autorestrict'), 
    new moodle_url('/local/autorestrict/course_settings.php', ['courseid' => $courseid]));
$PAGE->navbar->add(get_string('bulk_difficulty', 'local_autorestrict'));

// Handle form submission.
if ($action === 'setdiff' && confirm_sesskey()) {
    $cmids = optional_param_array('cmids', [], PARAM_INT);
    $difftag = optional_param('difftag', '', PARAM_ALPHANUMEXT);
    
    if (!empty($cmids)) {
        $results = \local_autorestrict\observer::bulk_set_difficulty($cmids, $difftag, $courseid);
        if ($difftag) {
            \core\notification::success(get_string('bulk_diff_set', 'local_autorestrict', $results));
        } else {
            \core\notification::success(get_string('bulk_diff_cleared', 'local_autorestrict', $results));
        }
    } else {
        \core\notification::warning(get_string('no_activities_selected', 'local_autorestrict'));
    }
    
    redirect(new moodle_url('/local/autorestrict/bulk_difficulty.php', ['courseid' => $courseid]));
}

// Get all modules with their sections.
$sql = "SELECT cm.id, cm.instance, m.name as modname, cs.section, cs.name as sectionname
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        JOIN {course_sections} cs ON cs.id = cm.section
        WHERE cm.course = :courseid
        AND cm.deletioninprogress = 0
        ORDER BY cs.section, cm.id";

$modules = $DB->get_records_sql($sql, ['courseid' => $courseid]);

// Get activity names and diff tags.
foreach ($modules as $cm) {
    $cminfo = get_fast_modinfo($courseid)->get_cm($cm->id);
    $cm->activityname = $cminfo->name;
    
    // Get highest diff tag for this module (diff4 > diff3 > diff2 > diff1).
    $tagsql = "SELECT t.name
               FROM {tag} t
               JOIN {tag_instance} ti ON ti.tagid = t.id
               WHERE ti.itemid = :cmid
               AND ti.itemtype = 'course_modules'
               AND t.name IN ('diff1', 'diff2', 'diff3', 'diff4')
               ORDER BY t.name DESC";
    $tags = $DB->get_records_sql($tagsql, ['cmid' => $cm->id], 0, 1); // Limit 1
    $cm->difftag = !empty($tags) ? reset($tags)->name : '';
}

// Output.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulk_difficulty', 'local_autorestrict'));
echo html_writer::tag('p', get_string('bulk_difficulty_desc', 'local_autorestrict'));

// Filter by section.
$sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
$sectionFilter = optional_param('section', -1, PARAM_INT);

echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::start_div('form-inline');
echo html_writer::label(get_string('filter_section', 'local_autorestrict') . ': ', 'section', true, ['class' => 'mr-2']);
$sectionOptions = [-1 => get_string('all_sections', 'local_autorestrict')];
foreach ($sections as $sec) {
    $sectionOptions[$sec->section] = $sec->name ?: get_string('section') . ' ' . $sec->section;
}
echo html_writer::select($sectionOptions, 'section', $sectionFilter, false, ['class' => 'form-control mr-2', 'onchange' => 'this.form.submit()']);
echo html_writer::end_div();
echo html_writer::end_tag('form');

// Main form.
echo html_writer::start_tag('form', ['method' => 'post', 'id' => 'bulk-diff-form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'setdiff']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Action bar.
echo html_writer::start_div('mb-3 p-3 bg-light rounded');
echo html_writer::start_div('form-inline');

// Select/deselect all.
echo html_writer::tag('button', get_string('selectall', 'local_autorestrict'), 
    ['type' => 'button', 'class' => 'btn btn-sm btn-outline-secondary mr-2', 'onclick' => 'selectAll(true)']);
echo html_writer::tag('button', get_string('deselectall', 'local_autorestrict'), 
    ['type' => 'button', 'class' => 'btn btn-sm btn-outline-secondary mr-3', 'onclick' => 'selectAll(false)']);

// Difficulty selector.
echo html_writer::label(get_string('set_to', 'local_autorestrict') . ': ', 'difftag', true, ['class' => 'mr-2']);
$diffOptions = [
    '' => get_string('no_difficulty', 'local_autorestrict'),
    'diff1' => get_string('diff1', 'local_autorestrict'),
    'diff2' => get_string('diff2', 'local_autorestrict'),
    'diff3' => get_string('diff3', 'local_autorestrict'),
    'diff4' => get_string('diff4', 'local_autorestrict'),
];
echo html_writer::select($diffOptions, 'difftag', '', false, ['class' => 'form-control mr-2', 'id' => 'difftag']);

echo html_writer::tag('button', get_string('apply', 'local_autorestrict'), 
    ['type' => 'submit', 'class' => 'btn btn-primary']);

echo html_writer::end_div();
echo html_writer::end_div();

// Activities table.
$table = new html_table();
$table->head = [
    html_writer::checkbox('selectall', 1, false, '', ['onclick' => 'selectAll(this.checked)']),
    get_string('activity'),
    get_string('section'),
    get_string('type', 'local_autorestrict'),
    get_string('current_difficulty', 'local_autorestrict'),
];
$table->attributes['class'] = 'table table-striped table-hover';
$table->id = 'activities-table';

$currentSection = -1;
foreach ($modules as $cm) {
    // Filter by section.
    if ($sectionFilter >= 0 && $cm->section != $sectionFilter) {
        continue;
    }
    
    // Section separator.
    if ($cm->section != $currentSection) {
        $currentSection = $cm->section;
    }
    
    $checkbox = html_writer::checkbox('cmids[]', $cm->id, false, '', ['class' => 'activity-checkbox']);
    
    $sectionName = $cm->sectionname ?: get_string('section') . ' ' . $cm->section;
    
    // Current difficulty badge (highest only).
    $diffBadge = '-';
    if (!empty($cm->difftag)) {
        $badgeClass = 'badge-secondary';
        if ($cm->difftag === 'diff1') $badgeClass = 'badge-success';
        if ($cm->difftag === 'diff2') $badgeClass = 'badge-info';
        if ($cm->difftag === 'diff3') $badgeClass = 'badge-warning';
        if ($cm->difftag === 'diff4') $badgeClass = 'badge-danger';
        $diffBadge = html_writer::tag('span', $cm->difftag, ['class' => "badge $badgeClass"]);
    }
    
    $table->data[] = [
        $checkbox,
        $cm->activityname,
        $sectionName,
        $cm->modname,
        $diffBadge,
    ];
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('no_activities', 'local_autorestrict'), 'info');
} else {
    echo html_writer::table($table);
}

echo html_writer::end_tag('form');

// Back button.
echo html_writer::tag('p', 
    html_writer::link(
        new moodle_url('/local/autorestrict/course_settings.php', ['courseid' => $courseid]),
        get_string('back'),
        ['class' => 'btn btn-secondary mt-3']
    )
);

// JavaScript.
echo html_writer::script("
function selectAll(checked) {
    document.querySelectorAll('.activity-checkbox').forEach(function(cb) {
        cb.checked = checked;
    });
}
");

echo $OUTPUT->footer();
