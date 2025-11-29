<?php
/**
 * Availability condition based on section completion count
 *
 * @package    availability_sectioncomplete
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_sectioncomplete;

defined('MOODLE_INTERNAL') || die();

class condition extends \core_availability\condition {
    
    /** @var int Section number */
    protected $sectionnumber;
    
    /** @var int Minimum completions required */
    protected $mincompletions;
    
    /**
     * Constructor
     *
     * @param \stdClass $structure Data structure from JSON decode
     */
    public function __construct($structure) {
        if (isset($structure->section)) {
            $this->sectionnumber = (int)$structure->section;
        }
        if (isset($structure->mincompletions)) {
            $this->mincompletions = (int)$structure->mincompletions;
        } else {
            $this->mincompletions = 1;
        }
    }
    
    /**
     * Save the data
     *
     * @return \stdClass Structure to save
     */
    public function save() {
        return (object)[
            'type' => 'sectioncomplete',
            'section' => $this->sectionnumber,
            'mincompletions' => $this->mincompletions
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
        $completedcount = $this->get_section_completion_count($course->id, $this->sectionnumber, $userid);
        
        $allow = ($completedcount >= $this->mincompletions);
        
        if ($not) {
            $allow = !$allow;
        }
        
        return $allow;
    }
    
    /**
     * Get the number of completed activities in a section
     *
     * @param int $courseid Course ID
     * @param int $sectionnumber Section number
     * @param int $userid User ID
     * @return int Number of completed activities
     */
    protected function get_section_completion_count($courseid, $sectionnumber, $userid) {
        global $DB;
        
        $sql = "SELECT COUNT(DISTINCT cm.id)
                FROM {course_modules} cm
                JOIN {course_sections} cs ON cs.id = cm.section
                JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                WHERE cm.course = :courseid
                AND cs.section = :sectionnumber
                AND cmc.userid = :userid
                AND cmc.completionstate >= :completionstate
                AND cm.completion > 0";
        
        return $DB->count_records_sql($sql, [
            'courseid' => $courseid,
            'sectionnumber' => $sectionnumber,
            'userid' => $userid,
            'completionstate' => COMPLETION_COMPLETE
        ]);
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
            return get_string('requires_notcomplete', 'availability_sectioncomplete', 
                ['section' => $this->sectionnumber, 'count' => $this->mincompletions]);
        } else {
            return get_string('requires_complete', 'availability_sectioncomplete', 
                ['section' => $this->sectionnumber, 'count' => $this->mincompletions]);
        }
    }
    
    /**
     * Get debug string
     *
     * @return string Debug string
     */
    protected function get_debug_string() {
        return 'section:' . $this->sectionnumber . ' min:' . $this->mincompletions;
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
            $completedcount = $this->get_section_completion_count($course->id, $this->sectionnumber, $userid);
            $meets = ($completedcount >= $this->mincompletions);
            
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
