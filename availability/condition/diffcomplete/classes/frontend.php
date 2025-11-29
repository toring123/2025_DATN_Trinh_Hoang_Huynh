<?php
/**
 * Frontend class for difficulty completion condition
 *
 * @package    availability_diffcomplete
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_diffcomplete;

defined('MOODLE_INTERNAL') || die();

class frontend extends \core_availability\frontend {
    
    /**
     * Get JavaScript strings
     *
     * @return array Array of strings for JavaScript
     */
    protected function get_javascript_strings() {
        return ['diff1', 'diff2', 'diff3', 'diff4', 'title', 'description'];
    }
    
    /**
     * Get JavaScript init params
     *
     * @param \stdClass $course Course object
     * @param \cm_info|null $cm Course module info
     * @param \section_info|null $section Section info
     * @return array JavaScript init parameters
     */
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
    
    /**
     * Allow adding of instance
     *
     * @param \stdClass $course Course object
     * @param \cm_info|null $cm Course module
     * @param \section_info|null $section Section info
     * @return bool True if can add
     */
    protected function allow_add($course, \cm_info $cm = null, 
            \section_info $section = null) {
        return true;
    }
}
