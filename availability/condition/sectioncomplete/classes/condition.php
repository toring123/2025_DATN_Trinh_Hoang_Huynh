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
        
        // First, get all course modules in the section
        $sql = "SELECT cm.id, cm.module, cm.instance, cm.completion
                FROM {course_modules} cm
                JOIN {course_sections} cs ON cs.id = cm.section
                WHERE cm.course = :courseid
                AND cs.section = :sectionnumber
                AND cm.deletioninprogress = 0";
        
        $cms = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'sectionnumber' => $sectionnumber
        ]);
        
        if (empty($cms)) {
            return 0;
        }
        
        $completedcount = 0;
        
        foreach ($cms as $cm) {
            $iscompleted = false;
            
            // Check if activity has completion tracking enabled
            if ($cm->completion > 0) {
                // Check completion table
                $completion = $DB->get_record('course_modules_completion', [
                    'coursemoduleid' => $cm->id,
                    'userid' => $userid
                ]);
                
                if ($completion && $completion->completionstate >= COMPLETION_COMPLETE) {
                    $iscompleted = true;
                }
            } else {
                // For activities without completion tracking, check if they have submissions
                $iscompleted = $this->has_user_submission($cm, $userid);
            }
            
            if ($iscompleted) {
                $completedcount++;
            }
        }
        
        return $completedcount;
    }
    
    /**
     * Check if user has made a submission for this activity
     *
     * @param object $cm Course module object
     * @param int $userid User ID
     * @return bool True if user has submitted
     */
    protected function has_user_submission($cm, $userid) {
        global $DB;
        
        $modulename = $DB->get_field('modules', 'name', ['id' => $cm->module]);
        
        if (!$modulename) {
            return false;
        }
        
        $tables = [
            'assign' => 'assign_submission',
            'quiz' => 'quiz_attempts',
            'forum' => 'forum_posts',
            'workshop' => 'workshop_submissions',
            'choice' => 'choice_answers',
            'feedback' => 'feedback_completed',
            'survey' => 'survey_answers',
            'lesson' => 'lesson_attempts',
            'scorm' => 'scorm_scoes_track',
            'data' => 'data_records',
            'glossary' => 'glossary_entries',
            'wiki' => 'wiki_pages',
            'chat' => 'chat_messages',
            'h5pactivity' => 'h5pactivity_attempts'
        ];
        
        if (!isset($tables[$modulename])) {
            return false;
        }
        
        $table = $tables[$modulename];
        
        switch ($modulename) {
            case 'assign':
                return $DB->record_exists($table, ['assignment' => $cm->instance, 'userid' => $userid]);
            case 'quiz':
                return $DB->record_exists($table, ['quiz' => $cm->instance, 'userid' => $userid]);
            case 'forum':
                $sql = "SELECT COUNT(fp.id)
                        FROM {forum_posts} fp
                        JOIN {forum_discussions} fd ON fd.id = fp.discussion
                        WHERE fd.forum = :forum AND fp.userid = :userid";
                return $DB->count_records_sql($sql, ['forum' => $cm->instance, 'userid' => $userid]) > 0;
            case 'workshop':
                return $DB->record_exists($table, ['workshopid' => $cm->instance, 'authorid' => $userid]);
            case 'choice':
                return $DB->record_exists($table, ['choiceid' => $cm->instance, 'userid' => $userid]);
            case 'feedback':
                return $DB->record_exists($table, ['feedback' => $cm->instance, 'userid' => $userid]);
            case 'survey':
                return $DB->record_exists($table, ['survey' => $cm->instance, 'userid' => $userid]);
            case 'lesson':
                return $DB->record_exists($table, ['lessonid' => $cm->instance, 'userid' => $userid]);
            case 'scorm':
                $sql = "SELECT COUNT(sst.id)
                        FROM {scorm_scoes_track} sst
                        JOIN {scorm_scoes} ss ON ss.id = sst.scoid
                        WHERE ss.scorm = :scorm AND sst.userid = :userid";
                return $DB->count_records_sql($sql, ['scorm' => $cm->instance, 'userid' => $userid]) > 0;
            case 'data':
                return $DB->record_exists($table, ['dataid' => $cm->instance, 'userid' => $userid]);
            case 'glossary':
                return $DB->record_exists($table, ['glossaryid' => $cm->instance, 'userid' => $userid]);
            case 'wiki':
                $sql = "SELECT COUNT(wp.id)
                        FROM {wiki_pages} wp
                        JOIN {wiki_subwikis} ws ON ws.id = wp.subwikiid
                        WHERE ws.wikiid = :wiki AND wp.userid = :userid";
                return $DB->count_records_sql($sql, ['wiki' => $cm->instance, 'userid' => $userid]) > 0;
            case 'chat':
                return $DB->record_exists($table, ['chatid' => $cm->instance, 'userid' => $userid]);
            case 'h5pactivity':
                return $DB->record_exists($table, ['h5pactivityid' => $cm->instance, 'userid' => $userid]);
            default:
                return false;
        }
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
