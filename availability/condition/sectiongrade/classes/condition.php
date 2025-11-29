<?php
/**
 * Availability condition based on section average grade
 *
 * @package    availability_sectiongrade
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_sectiongrade;

defined('MOODLE_INTERNAL') || die();

class condition extends \core_availability\condition {
    
    /** @var int Section number */
    protected $sectionnumber;
    
    /** @var float Minimum average grade required (percentage) */
    protected $mingrade;
    
    /**
     * Constructor
     *
     * @param \stdClass $structure Data structure from JSON decode
     */
    public function __construct($structure) {
        if (isset($structure->section)) {
            $this->sectionnumber = (int)$structure->section;
        }
        if (isset($structure->mingrade)) {
            $this->mingrade = (float)$structure->mingrade;
        } else {
            $this->mingrade = 50.0;
        }
    }
    
    /**
     * Save the data
     *
     * @return \stdClass Structure to save
     */
    public function save() {
        return (object)[
            'type' => 'sectiongrade',
            'section' => $this->sectionnumber,
            'mingrade' => $this->mingrade
        ];
    }
    
    /**
     * Check if user is available
     *
     * @param bool $not Set true if we are inverting the condition
     * @param \core_availability\info $info Item we're checking
     * @param bool $grabthelot Performance hint
     * @param int $userid User ID to check availability for
     * @return bool True if available
     */
    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        $course = $info->get_course();
        $averagegrade = $this->get_section_average_grade($course->id, $this->sectionnumber, $userid);
        
        $allow = ($averagegrade >= $this->mingrade);
        
        if ($not) {
            $allow = !$allow;
        }
        
        return $allow;
    }
    
    /**
     * Get the average grade for a user in a section
     *
     * @param int $courseid Course ID
     * @param int $sectionnumber Section number
     * @param int $userid User ID
     * @return float Average grade percentage
     */
    protected function get_section_average_grade($courseid, $sectionnumber, $userid) {
        global $DB;
        
        // Get all graded activities in the section
        $sql = "SELECT cm.id, cm.instance, m.name as modname, gi.id as gradeitemid
                FROM {course_modules} cm
                JOIN {course_sections} cs ON cs.id = cm.section
                JOIN {modules} m ON m.id = cm.module
                JOIN {grade_items} gi ON gi.iteminstance = cm.instance 
                    AND gi.itemmodule = m.name AND gi.courseid = cm.course
                WHERE cm.course = :courseid
                AND cs.section = :sectionnumber
                AND gi.itemtype = 'mod'";
        
        $activities = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'sectionnumber' => $sectionnumber
        ]);
        
        if (empty($activities)) {
            return 0;
        }
        
        $totalpercentage = 0;
        $count = 0;
        
        foreach ($activities as $activity) {
            $grade = $DB->get_record('grade_grades', [
                'itemid' => $activity->gradeitemid,
                'userid' => $userid
            ]);
            
            if ($grade && $grade->finalgrade !== null) {
                $gradeitem = $DB->get_record('grade_items', ['id' => $activity->gradeitemid]);
                if ($gradeitem && $gradeitem->grademax > 0) {
                    $percentage = ($grade->finalgrade / $gradeitem->grademax) * 100;
                    $totalpercentage += $percentage;
                    $count++;
                }
            }
        }
        
        if ($count == 0) {
            return 0;
        }
        
        return $totalpercentage / $count;
    }
    
    /**
     * Get description of restriction
     *
     * @param bool $full Set true if this is the 'full information' view
     * @param bool $not Set true if we are inverting the condition
     * @param \core_availability\info $info Item we're checking
     * @return string Information string about restriction
     */
    public function get_description($full, $not, \core_availability\info $info) {
        if ($not) {
            return get_string('requires_notgrade', 'availability_sectiongrade', 
                ['section' => $this->sectionnumber, 'grade' => $this->mingrade]);
        } else {
            return get_string('requires_grade', 'availability_sectiongrade', 
                ['section' => $this->sectionnumber, 'grade' => $this->mingrade]);
        }
    }
    
    /**
     * Get debug string
     *
     * @return string Debug string
     */
    protected function get_debug_string() {
        return 'section:' . $this->sectionnumber . ' mingrade:' . $this->mingrade;
    }
    
    /**
     * Check if this condition applies to user lists
     *
     * @return bool True if this condition applies to user lists
     */
    public function is_applied_to_user_lists() {
        return true;
    }
    
    /**
     * Filter the user list
     *
     * @param array $users Array of users
     * @param bool $not True if condition is negated
     * @param \core_availability\info $info Info about item
     * @param \core_availability\capability_checker $checker Capability checker
     * @return array Filtered array of users
     */
    public function filter_user_list(array $users, $not, \core_availability\info $info,
            \core_availability\capability_checker $checker) {
        
        if (empty($users)) {
            return $users;
        }
        
        $course = $info->get_course();
        $result = [];
        
        foreach ($users as $userid => $user) {
            $averagegrade = $this->get_section_average_grade($course->id, $this->sectionnumber, $userid);
            $meets = ($averagegrade >= $this->mingrade);
            
            if ($not) {
                $meets = !$meets;
            }
            
            if ($meets) {
                $result[$userid] = $user;
            }
        }
        
        return $result;
    }
}
