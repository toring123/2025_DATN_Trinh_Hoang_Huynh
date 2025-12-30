<?php
namespace availability_diffcomplete;

defined('MOODLE_INTERNAL') || die();

class frontend extends \core_availability\frontend {
    
    protected function get_javascript_strings() {
        return ['diff1', 'diff2', 'diff3', 'diff4', 'title', 'description'];
    }
    
    protected function get_javascript_init_params($course, \cm_info $cm = null, 
            \section_info $section = null) {
        global $DB;
        
        // Count activities for each difficulty level in the course
        $counts = [];
        $difftags = ['diff1', 'diff2', 'diff3', 'diff4'];
        
        foreach ($difftags as $tag) {
            $sql = "SELECT COUNT(DISTINCT cm.id)
                    FROM {course_modules} cm
                    JOIN {tag_instance} ti ON ti.itemid = cm.id
                    JOIN {tag} t ON t.id = ti.tagid
                    WHERE cm.course = :courseid
                    AND ti.itemtype = 'course_modules'
                    AND t.name = :tagname";
            
            $counts[$tag] = $DB->count_records_sql($sql, [
                'courseid' => $course->id,
                'tagname' => $tag
            ]);
        }
        
        return [$counts];
    }
    
    protected function allow_add($course, \cm_info $cm = null, 
            \section_info $section = null) {
        return true;
    }
}
