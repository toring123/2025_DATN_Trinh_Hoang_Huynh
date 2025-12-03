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
        self::apply_restriction_to_module($event->objectid, $event->courseid);
    }
    
    /**
     * Handle course module updated event
     *
     * @param \core\event\course_module_updated $event
     */
    public static function course_module_updated(\core\event\course_module_updated $event) {
        self::apply_restriction_to_module($event->objectid, $event->courseid);
    }
    
    /**
     * Apply restriction to a single module
     *
     * @param int $cmid Course module ID
     * @param int $courseid Course ID
     */
    protected static function apply_restriction_to_module($cmid, $courseid) {
        global $DB;
        
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
        
        // Update the course module with availability (or clear if no conditions)
        if ($availability) {
            $DB->set_field('course_modules', 'availability', json_encode($availability), ['id' => $cmid]);
        } else {
            // No conditions - clear any existing restrictions
            $DB->set_field('course_modules', 'availability', null, ['id' => $cmid]);
        }
        
        // Clear cache
        \course_modinfo::purge_course_cache($courseid);
    }
    
    /**
     * Get course-specific configuration
     *
     * @param int $courseid Course ID
     * @return object|null Config object or null
     */
    public static function get_course_config($courseid) {
        global $DB;
        return $DB->get_record('local_autorestrict_course', ['courseid' => $courseid]);
    }
    
    /**
     * Apply restrictions to all existing modules in a course
     *
     * @param int $courseid Course ID
     * @param object $config Course config
     * @param bool $overwrite Whether to overwrite existing restrictions
     * @return array Results [applied, skipped, errors]
     */
    public static function apply_to_all_modules($courseid, $config, $overwrite = false) {
        global $DB;
        
        $results = ['applied' => 0, 'skipped' => 0, 'errors' => 0];
        
        // Get all modules in course with their sections
        $sql = "SELECT cm.id, cm.availability, cs.section
                FROM {course_modules} cm
                JOIN {course_sections} cs ON cs.id = cm.section
                WHERE cm.course = :courseid
                ORDER BY cs.section, cm.id";
        
        $modules = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        
        foreach ($modules as $cm) {
            // Skip if already has restrictions and not overwriting
            if (!$overwrite && !empty($cm->availability)) {
                $results['skipped']++;
                continue;
            }
            
            // Get difficulty tag for this module
            $tag = self::get_module_difficulty_tag($cm->id);
            
            // Build availability conditions
            $availability = self::build_availability_conditions($cm->section, $tag, $config);
            
            if ($availability) {
                try {
                    $DB->set_field('course_modules', 'availability', json_encode($availability), ['id' => $cm->id]);
                    $results['applied']++;
                } catch (\Exception $e) {
                    $results['errors']++;
                }
            } else {
                $results['skipped']++;
            }
        }
        
        // Clear cache
        \course_modinfo::purge_course_cache($courseid);
        
        return $results;
    }
    
    /**
     * Apply restrictions to all sections in a course
     *
     * @param int $courseid Course ID
     * @param object $config Course config
     * @param bool $overwrite Whether to overwrite existing restrictions
     * @return array Results [applied, skipped, errors]
     */
    public static function apply_to_all_sections($courseid, $config, $overwrite = false) {
        global $DB;
        
        $results = ['applied' => 0, 'skipped' => 0, 'errors' => 0];
        
        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
        
        foreach ($sections as $section) {
            // Skip if already has restrictions and not overwriting
            if (!$overwrite && !empty($section->availability)) {
                $results['skipped']++;
                continue;
            }
            
            // Build section availability (no difficulty tag for sections)
            $availability = self::build_section_availability($section->section, $config);
            
            if ($availability) {
                try {
                    $DB->set_field('course_sections', 'availability', json_encode($availability), ['id' => $section->id]);
                    $results['applied']++;
                } catch (\Exception $e) {
                    $results['errors']++;
                }
            } else {
                $results['skipped']++;
            }
        }
        
        // Clear cache
        \course_modinfo::purge_course_cache($courseid);
        
        return $results;
    }
    
    /**
     * Clear all restrictions from modules in a course
     *
     * @param int $courseid Course ID
     * @return int Number of modules cleared
     */
    public static function clear_all_module_restrictions($courseid) {
        global $DB;
        
        $count = $DB->count_records_select('course_modules', 
            'course = :courseid AND availability IS NOT NULL AND availability != :empty',
            ['courseid' => $courseid, 'empty' => '']);
        
        $DB->set_field('course_modules', 'availability', null, ['course' => $courseid]);
        \course_modinfo::purge_course_cache($courseid);
        
        return $count;
    }
    
    /**
     * Clear all restrictions from sections in a course
     *
     * @param int $courseid Course ID
     * @return int Number of sections cleared
     */
    public static function clear_all_section_restrictions($courseid) {
        global $DB;
        
        $count = $DB->count_records_select('course_sections', 
            'course = :courseid AND availability IS NOT NULL AND availability != :empty',
            ['courseid' => $courseid, 'empty' => '']);
        
        $DB->set_field('course_sections', 'availability', null, ['course' => $courseid]);
        \course_modinfo::purge_course_cache($courseid);
        
        return $count;
    }
    
    /**
     * Build section availability conditions (without difficulty)
     *
     * @param int $sectionnumber Section number
     * @param object $config Plugin config
     * @return array|null Availability structure or null
     */
    protected static function build_section_availability($sectionnumber, $config) {
        $conditions = [];
        
        // Skip section 0 and 1
        if ($sectionnumber <= 1) {
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
        
        if (empty($conditions)) {
            return null;
        }
        
        $showWhenNotMet = empty($config->hide_completely);
        
        return [
            'op' => '&',
            'c' => $conditions,
            'showc' => array_fill(0, count($conditions), $showWhenNotMet)
        ];
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
        
        // Only apply section-based conditions for section 2 and above
        // Section 0 is general section, section 1 has no previous content section
        if ($sectionnumber >= 2) {
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
        }
        
        // Condition based on difficulty progression (applies to ALL sections including 0 and 1)
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
