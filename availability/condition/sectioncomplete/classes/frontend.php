<?php
/**
 * Frontend class for section completion condition
 *
 * @package    availability_sectioncomplete
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_sectioncomplete;

defined('MOODLE_INTERNAL') || die();

class frontend extends \core_availability\frontend {
    
    /**
     * Get JavaScript strings
     *
     * @return array Array of strings for JavaScript
     */
    protected function get_javascript_strings() {
        return ['section', 'mincompletions', 'error_invalidsection', 'title', 'description'];
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
        
        // Get all sections in the course with activity count
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
