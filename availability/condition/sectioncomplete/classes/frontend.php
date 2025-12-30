<?php
namespace availability_sectioncomplete;

defined('MOODLE_INTERNAL') || die();

class frontend extends \core_availability\frontend {
    
    protected function get_javascript_strings() {
        return ['section', 'mincompletions', 'error_invalidsection', 'title', 'description', 'choose', 'activities'];
    }
    
    protected function get_javascript_init_params($course, \cm_info $cm = null, 
            \section_info $section = null) {
        global $DB;
        
        $sql = "SELECT cs.id, cs.section, cs.name, COUNT(cm.id) as activitycount
                FROM {course_sections} cs
                LEFT JOIN {course_modules} cm ON cm.section = cs.id AND cm.completion > 0
                WHERE cs.course = :courseid
                GROUP BY cs.id, cs.section, cs.name
                ORDER BY cs.section ASC";
        
        $sections = $DB->get_records_sql($sql, ['courseid' => $course->id]);
        
        $sectionlist = [];
        foreach ($sections as $sec) {
            $name = $sec->name ? $sec->name : get_string('section') . ' ' . $sec->section;
            $sectionlist[] = [
                'number' => $sec->section,
                'name' => $name,
                'activitycount' => (int)$sec->activitycount
            ];
        }
        
        return [$sectionlist];
    }
    
    protected function allow_add($course, \cm_info $cm = null, 
            \section_info $section = null) {
        return true;
    }
}
