<?php
/**
 * Frontend class for section grade condition
 *
 * @package    availability_sectiongrade
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_sectiongrade;

defined('MOODLE_INTERNAL') || die();

class frontend extends \core_availability\frontend {
    
    /**
     * Get JavaScript strings
     *
     * @return array Array of strings for JavaScript
     */
    protected function get_javascript_strings() {
        return ['section', 'mingrade', 'error_invalidsection', 'title', 'description', 'choose'];
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
        
        // Get all sections in the course
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
