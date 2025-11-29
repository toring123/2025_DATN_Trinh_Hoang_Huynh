<?php
/**
 * Availability condition based on difficulty level completion counts
 *
 * @package    availability_diffcomplete
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_diffcomplete;

defined('MOODLE_INTERNAL') || die();

class condition extends \core_availability\condition {
    
    /** @var int Minimum diff1 completions */
    protected $diff1;
    
    /** @var int Minimum diff2 completions */
    protected $diff2;
    
    /** @var int Minimum diff3 completions */
    protected $diff3;
    
    /** @var int Minimum diff4 completions */
    protected $diff4;
    
    /**
     * Constructor
     *
     * @param \stdClass $structure Data structure from JSON decode
     */
    public function __construct($structure) {
        $this->diff1 = isset($structure->diff1) ? (int)$structure->diff1 : 0;
        $this->diff2 = isset($structure->diff2) ? (int)$structure->diff2 : 0;
        $this->diff3 = isset($structure->diff3) ? (int)$structure->diff3 : 0;
        $this->diff4 = isset($structure->diff4) ? (int)$structure->diff4 : 0;
    }
    
    /**
     * Save the data
     *
     * @return \stdClass Structure to save
     */
    public function save() {
        return (object)[
            'type' => 'diffcomplete',
            'diff1' => $this->diff1,
            'diff2' => $this->diff2,
            'diff3' => $this->diff3,
            'diff4' => $this->diff4
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
        
        $allow = true;
        
        // Check each difficulty level
        if ($this->diff1 > 0) {
            $count = $this->get_tag_completion_count($course->id, 'diff1', $userid);
            if ($count < $this->diff1) {
                $allow = false;
            }
        }
        
        if ($allow && $this->diff2 > 0) {
            $count = $this->get_tag_completion_count($course->id, 'diff2', $userid);
            if ($count < $this->diff2) {
                $allow = false;
            }
        }
        
        if ($allow && $this->diff3 > 0) {
            $count = $this->get_tag_completion_count($course->id, 'diff3', $userid);
            if ($count < $this->diff3) {
                $allow = false;
            }
        }
        
        if ($allow && $this->diff4 > 0) {
            $count = $this->get_tag_completion_count($course->id, 'diff4', $userid);
            if ($count < $this->diff4) {
                $allow = false;
            }
        }
        
        if ($not) {
            $allow = !$allow;
        }
        
        return $allow;
    }
    
    /**
     * Get number of completed activities with a specific tag
     *
     * @param int $courseid Course ID
     * @param string $tagname Tag name
     * @param int $userid User ID
     * @return int Number of completed activities
     */
    protected function get_tag_completion_count($courseid, $tagname, $userid) {
        global $DB;
        
        $sql = "SELECT COUNT(DISTINCT cm.id)
                FROM {course_modules} cm
                JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                JOIN {tag_instance} ti ON ti.itemid = cm.id
                JOIN {tag} t ON t.id = ti.tagid
                WHERE cm.course = :courseid
                AND cmc.userid = :userid
                AND cmc.completionstate >= :completionstate
                AND ti.itemtype = 'course_modules'
                AND t.name = :tagname";
        
        return $DB->count_records_sql($sql, [
            'courseid' => $courseid,
            'userid' => $userid,
            'completionstate' => COMPLETION_COMPLETE,
            'tagname' => $tagname
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
        $requirements = [];
        if ($this->diff1 > 0) {
            $requirements[] = "diff1: {$this->diff1}";
        }
        if ($this->diff2 > 0) {
            $requirements[] = "diff2: {$this->diff2}";
        }
        if ($this->diff3 > 0) {
            $requirements[] = "diff3: {$this->diff3}";
        }
        if ($this->diff4 > 0) {
            $requirements[] = "diff4: {$this->diff4}";
        }
        
        $reqstring = implode(', ', $requirements);
        
        if ($not) {
            return get_string('requires_not', 'availability_diffcomplete', $reqstring);
        } else {
            return get_string('requires', 'availability_diffcomplete', $reqstring);
        }
    }
    
    /**
     * Get debug string
     *
     * @return string Debug string
     */
    protected function get_debug_string() {
        return "diff1:{$this->diff1} diff2:{$this->diff2} diff3:{$this->diff3} diff4:{$this->diff4}";
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
            $meets = true;
            
            if ($this->diff1 > 0 && $this->get_tag_completion_count($course->id, 'diff1', $userid) < $this->diff1) {
                $meets = false;
            }
            if ($meets && $this->diff2 > 0 && $this->get_tag_completion_count($course->id, 'diff2', $userid) < $this->diff2) {
                $meets = false;
            }
            if ($meets && $this->diff3 > 0 && $this->get_tag_completion_count($course->id, 'diff3', $userid) < $this->diff3) {
                $meets = false;
            }
            if ($meets && $this->diff4 > 0 && $this->get_tag_completion_count($course->id, 'diff4', $userid) < $this->diff4) {
                $meets = false;
            }
            
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
