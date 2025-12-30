<?php
namespace availability_sectiongrade;

defined('MOODLE_INTERNAL') || die();

class frontend extends \core_availability\frontend {
    
    protected function get_javascript_strings() {
        return ['section', 'mingrade', 'error_invalidsection', 'title', 'description', 'choose'];
    }
    
    protected function get_javascript_init_params($course, \cm_info $cm = null, 
            \section_info $section = null) {
        global $DB;
        
        $sections = $DB->get_records('course_sections', 
            ['course' => $course->id], 
            'section ASC', 
            'id, section, name'
        );
        
        $sectionlist = [];
        foreach ($sections as $sec) {
            $name = $sec->name ? $sec->name : get_string('section') . ' ' . $sec->section;
            $sectionlist[] = [
                'number' => $sec->section,
                'name' => $name
            ];
        }
        
        return [$sectionlist];
    }
    
    protected function allow_add($course, \cm_info $cm = null, 
            \section_info $section = null) {
        return true;
    }
}
