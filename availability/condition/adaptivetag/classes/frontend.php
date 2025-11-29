<?php
namespace availability_adaptivetag;

defined('MOODLE_INTERNAL') || die();

/**
 * Frontend class for UI rendering
 */
class frontend extends \core_availability\frontend {
    
    /**
     * Get JavaScript strings
     *
     * @return array Array of strings for JavaScript
     */
    protected function get_javascript_strings() {
        return ['tag', 'mincompletions', 'error_invalidtag', 'title', 'description'];
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
        
        // Get all standard tags (isstandard = 1) that can be used for difficulty levels
        // This includes tags like diff1, diff2, diff3, diff4
        $sql = "SELECT DISTINCT t.id, t.name, t.rawname
                FROM {tag} t
                WHERE t.isstandard = 1
                ORDER BY t.name";
        
        $tags = $DB->get_records_sql($sql);
        
        // If no standard tags, also try to get tags used in this course
        if (empty($tags)) {
            $sql = "SELECT DISTINCT t.id, t.name, t.rawname
                    FROM {tag} t
                    JOIN {tag_instance} ti ON ti.tagid = t.id
                    JOIN {course_modules} cm ON cm.id = ti.itemid
                    WHERE cm.course = :courseid
                    AND ti.itemtype = 'course_modules'
                    ORDER BY t.name";
            
            $tags = $DB->get_records_sql($sql, ['courseid' => $course->id]);
        }
        
        $taglist = [];
        foreach ($tags as $tag) {
            $taglist[] = $tag->name;
        }
        
        return [$taglist];
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