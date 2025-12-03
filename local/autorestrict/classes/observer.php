<?php
/**
 * Observer class for auto restrict access
 *
 * @package    local_autorestrict
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autorestrict;

defined('MOODLE_INTERNAL') || die();

class observer {
    
    /**
     * Handle course module created event
     *
     * @param \core\event\course_module_created $event
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        global $DB;
        
        $cmid = $event->objectid;
        $courseid = $event->courseid;
        
        // Get the course module
        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
        if (!$cm) {
            return;
        }
        
        // Get the section info
        $section = $DB->get_record('course_sections', ['id' => $cm->section]);
        if (!$section) {
            return;
        }
        
        // Check if auto-restrict is enabled FOR THIS COURSE
        $config = self::get_course_config($courseid);
        if (!$config || empty($config->enabled)) {
            return;
        }
        
        // Get the tag assigned to this module (if any)
        $tag = self::get_module_difficulty_tag($cmid);
        
        // Build availability conditions based on section and tag
        $availability = self::build_availability_conditions($section->section, $tag, $config);
        
        if ($availability) {
            // Update the course module with availability
            $DB->set_field('course_modules', 'availability', json_encode($availability), ['id' => $cmid]);
            
            // Clear cache
            \course_modinfo::purge_course_cache($courseid);
        }
    }
    
    /**
     * Get course-specific configuration
     *
     * @param int $courseid Course ID
     * @return object|null Config object or null
     */
    protected static function get_course_config($courseid) {
        global $DB;
        return $DB->get_record('local_autorestrict_course', ['courseid' => $courseid]);
    }
    
    /**
     * Get the difficulty tag for a module
     *
     * @param int $cmid Course module ID
     * @return string|null Tag name or null
     */
    protected static function get_module_difficulty_tag($cmid) {
        global $DB;
        
        $sql = "SELECT t.name
                FROM {tag} t
                JOIN {tag_instance} ti ON ti.tagid = t.id
                WHERE ti.itemid = :cmid
                AND ti.itemtype = 'course_modules'
                AND t.name IN ('diff1', 'diff2', 'diff3', 'diff4')
                LIMIT 1";
        
        $tag = $DB->get_field_sql($sql, ['cmid' => $cmid]);
        return $tag ?: null;
    }
    
    /**
     * Build availability conditions
     *
     * @param int $sectionnumber Section number
     * @param string|null $difftag Difficulty tag
     * @param object $config Plugin config
     * @return array|null Availability structure or null
     */
    protected static function build_availability_conditions($sectionnumber, $difftag, $config) {
        $conditions = [];
        
        // Skip section 0 (general section) - usually doesn't need restrictions
        if ($sectionnumber == 0) {
            return null;
        }
        
        // For section 1, no previous section requirements
        if ($sectionnumber == 1) {
            return null;
        }
        
        // Condition based on previous section completion
        if (!empty($config->require_previous_section)) {
            $previousSection = $sectionnumber - 1;
            $minCompletions = !empty($config->min_section_completions) ? (int)$config->min_section_completions : 1;
            
            $conditions[] = [
                'type' => 'sectioncomplete',
                'section' => $previousSection,
                'mincompletions' => $minCompletions
            ];
        }
        
        // Condition based on previous section grade
        if (!empty($config->require_previous_grade)) {
            $previousSection = $sectionnumber - 1;
            $minGrade = !empty($config->min_section_grade) ? (float)$config->min_section_grade : 50;
            
            $conditions[] = [
                'type' => 'sectiongrade',
                'section' => $previousSection,
                'mingrade' => $minGrade
            ];
        }
        
        // Condition based on difficulty progression
        if (!empty($config->require_difficulty_progression) && $difftag) {
            $diffRequirements = self::get_difficulty_requirements($difftag, $config);
            if ($diffRequirements) {
                $conditions[] = $diffRequirements;
            }
        }
        
        if (empty($conditions)) {
            return null;
        }
        
        // Determine if we should hide completely or show greyed out
        // When hide_completely is true, showc = false (completely hidden)
        // When hide_completely is false, showc = true (shown greyed out with message)
        $showWhenNotMet = empty($config->hide_completely);
        
        // Return availability structure
        return [
            'op' => '&',
            'c' => $conditions,
            'showc' => array_fill(0, count($conditions), $showWhenNotMet)
        ];
    }
    
    /**
     * Get difficulty requirements based on current difficulty tag
     *
     * @param string $difftag Current difficulty tag
     * @param object $config Plugin config
     * @return array|null Condition or null
     */
    protected static function get_difficulty_requirements($difftag, $config) {
        $requirements = [
            'type' => 'diffcomplete',
            'diff1' => 0,
            'diff2' => 0,
            'diff3' => 0,
            'diff4' => 0
        ];
        
        switch ($difftag) {
            case 'diff2':
                // Need to complete diff1 first
                $requirements['diff1'] = !empty($config->min_diff1_for_diff2) ? (int)$config->min_diff1_for_diff2 : 2;
                break;
            case 'diff3':
                // Need to complete diff1 and diff2 first
                $requirements['diff1'] = !empty($config->min_diff1_for_diff3) ? (int)$config->min_diff1_for_diff3 : 3;
                $requirements['diff2'] = !empty($config->min_diff2_for_diff3) ? (int)$config->min_diff2_for_diff3 : 2;
                break;
            case 'diff4':
                // Need to complete diff1, diff2, and diff3 first
                $requirements['diff1'] = !empty($config->min_diff1_for_diff4) ? (int)$config->min_diff1_for_diff4 : 4;
                $requirements['diff2'] = !empty($config->min_diff2_for_diff4) ? (int)$config->min_diff2_for_diff4 : 3;
                $requirements['diff3'] = !empty($config->min_diff3_for_diff4) ? (int)$config->min_diff3_for_diff4 : 2;
                break;
            default:
                // diff1 - no requirements
                return null;
        }
        
        return $requirements;
    }
}
