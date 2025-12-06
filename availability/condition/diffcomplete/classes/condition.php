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
    
    /** @var int Minimum diff1 completions (whole course) */
    protected $diff1;
    
    /** @var int Minimum diff2 completions (whole course) */
    protected $diff2;
    
    /** @var int Minimum diff3 completions (whole course) */
    protected $diff3;
    
    /** @var int Minimum diff4 completions (whole course) */
    protected $diff4;
    
    /** @var int|null Section number for section-based requirements */
    protected $section;
    
    /** @var int Minimum diff1 completions in section */
    protected $sectiondiff1;
    
    /** @var int Minimum diff2 completions in section */
    protected $sectiondiff2;
    
    /** @var int Minimum diff3 completions in section */
    protected $sectiondiff3;
    
    /** @var int Minimum diff4 completions in section */
    protected $sectiondiff4;
    
    /**
     * Constructor
     *
     * @param \stdClass $structure Data structure from JSON decode
     */
    public function __construct($structure) {
        // Course-wide requirements
        $this->diff1 = isset($structure->diff1) ? (int)$structure->diff1 : 0;
        $this->diff2 = isset($structure->diff2) ? (int)$structure->diff2 : 0;
        $this->diff3 = isset($structure->diff3) ? (int)$structure->diff3 : 0;
        $this->diff4 = isset($structure->diff4) ? (int)$structure->diff4 : 0;
        
        // Section-based requirements
        $this->section = isset($structure->section) ? (int)$structure->section : null;
        $this->sectiondiff1 = isset($structure->sectiondiff1) ? (int)$structure->sectiondiff1 : 0;
        $this->sectiondiff2 = isset($structure->sectiondiff2) ? (int)$structure->sectiondiff2 : 0;
        $this->sectiondiff3 = isset($structure->sectiondiff3) ? (int)$structure->sectiondiff3 : 0;
        $this->sectiondiff4 = isset($structure->sectiondiff4) ? (int)$structure->sectiondiff4 : 0;
    }
    
    /**
     * Save the data
     *
     * @return \stdClass Structure to save
     */
    public function save() {
        $data = (object)[
            'type' => 'diffcomplete',
            'diff1' => $this->diff1,
            'diff2' => $this->diff2,
            'diff3' => $this->diff3,
            'diff4' => $this->diff4
        ];
        
        // Add section-based requirements if any
        if ($this->section !== null) {
            $data->section = $this->section;
            $data->sectiondiff1 = $this->sectiondiff1;
            $data->sectiondiff2 = $this->sectiondiff2;
            $data->sectiondiff3 = $this->sectiondiff3;
            $data->sectiondiff4 = $this->sectiondiff4;
        }
        
        return $data;
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
        
        // Check course-wide difficulty requirements
        if ($this->diff1 > 0) {
            $count = $this->get_tag_completion_count($course->id, 'diff1', $userid, null);
            if ($count < $this->diff1) {
                $allow = false;
            }
        }
        
        if ($allow && $this->diff2 > 0) {
            $count = $this->get_tag_completion_count($course->id, 'diff2', $userid, null);
            if ($count < $this->diff2) {
                $allow = false;
            }
        }
        
        if ($allow && $this->diff3 > 0) {
            $count = $this->get_tag_completion_count($course->id, 'diff3', $userid, null);
            if ($count < $this->diff3) {
                $allow = false;
            }
        }
        
        if ($allow && $this->diff4 > 0) {
            $count = $this->get_tag_completion_count($course->id, 'diff4', $userid, null);
            if ($count < $this->diff4) {
                $allow = false;
            }
        }
        
        // Check section-based difficulty requirements
        if ($allow && $this->section !== null) {
            if ($this->sectiondiff1 > 0) {
                $count = $this->get_tag_completion_count($course->id, 'diff1', $userid, $this->section);
                if ($count < $this->sectiondiff1) {
                    $allow = false;
                }
            }
            
            if ($allow && $this->sectiondiff2 > 0) {
                $count = $this->get_tag_completion_count($course->id, 'diff2', $userid, $this->section);
                if ($count < $this->sectiondiff2) {
                    $allow = false;
                }
            }
            
            if ($allow && $this->sectiondiff3 > 0) {
                $count = $this->get_tag_completion_count($course->id, 'diff3', $userid, $this->section);
                if ($count < $this->sectiondiff3) {
                    $allow = false;
                }
            }
            
            if ($allow && $this->sectiondiff4 > 0) {
                $count = $this->get_tag_completion_count($course->id, 'diff4', $userid, $this->section);
                if ($count < $this->sectiondiff4) {
                    $allow = false;
                }
            }
        }
        
        if ($not) {
            $allow = !$allow;
        }
        
        return $allow;
    }
    
    /**
     * Get number of completed activities with a specific tag
     * Considers both:
     * 1. Activities with completion tracking that are marked complete
     * 2. Activities with no completion tracking but have submissions
     *
     * @param int $courseid Course ID
     * @param string $tagname Tag name
     * @param int $userid User ID
     * @param int|null $sectionnumber Section number to limit to (null = whole course)
     * @return int Number of completed activities
     */
    protected function get_tag_completion_count($courseid, $tagname, $userid, $sectionnumber = null) {
        global $DB;
        
        // Part 1: Count activities with completion tracking that are complete
        $params1 = [
            'courseid' => $courseid,
            'userid' => $userid,
            'completionstate' => COMPLETION_COMPLETE,
            'tagname' => $tagname
        ];
        
        $sql1 = "SELECT DISTINCT cm.id
                FROM {course_modules} cm
                JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                JOIN {tag_instance} ti ON ti.itemid = cm.id
                JOIN {tag} t ON t.id = ti.tagid
                JOIN {course_sections} cs ON cs.id = cm.section
                WHERE cm.course = :courseid
                AND cmc.userid = :userid
                AND cmc.completionstate >= :completionstate
                AND ti.itemtype = 'course_modules'
                AND t.name = :tagname";
        
        if ($sectionnumber !== null) {
            $sql1 .= " AND cs.section = :sectionnumber";
            $params1['sectionnumber'] = $sectionnumber;
        }
        
        $completedWithTracking = $DB->get_fieldset_sql($sql1, $params1);
        
        // Part 2: Count activities with NO completion tracking but have submissions
        $params2 = [
            'courseid' => $courseid,
            'userid' => $userid,
            'tagname' => $tagname,
            'completionnone' => COMPLETION_TRACKING_NONE
        ];
        
        $sql2 = "SELECT DISTINCT cm.id
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                JOIN {tag_instance} ti ON ti.itemid = cm.id
                JOIN {tag} t ON t.id = ti.tagid
                JOIN {course_sections} cs ON cs.id = cm.section
                WHERE cm.course = :courseid
                AND cm.completion = :completionnone
                AND ti.itemtype = 'course_modules'
                AND t.name = :tagname";
        
        if ($sectionnumber !== null) {
            $sql2 .= " AND cs.section = :sectionnumber";
            $params2['sectionnumber'] = $sectionnumber;
        }
        
        $noTrackingModules = $DB->get_records_sql($sql2, $params2);
        
        // Check each no-tracking module for submissions
        $completedNoTracking = [];
        foreach ($noTrackingModules as $cm) {
            if ($this->has_user_submission($cm->id, $userid)) {
                $completedNoTracking[] = $cm->id;
            }
        }
        
        // Merge and count unique IDs
        $allCompleted = array_unique(array_merge($completedWithTracking, $completedNoTracking));
        
        return count($allCompleted);
    }
    
    /**
     * Check if user has made a submission for a course module
     * Supports: assign, quiz, forum, workshop, glossary, data, wiki, lesson
     *
     * @param int $cmid Course module ID
     * @param int $userid User ID
     * @return bool True if user has submission
     */
    protected function has_user_submission($cmid, $userid) {
        global $DB;
        
        // Get module info
        $cm = $DB->get_record_sql("
            SELECT cm.id, cm.instance, m.name as modname
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            WHERE cm.id = :cmid
        ", ['cmid' => $cmid]);
        
        if (!$cm) {
            return false;
        }
        
        switch ($cm->modname) {
            case 'assign':
                // Check assign_submission table
                return $DB->record_exists_sql("
                    SELECT 1 FROM {assign_submission}
                    WHERE assignment = :instance AND userid = :userid AND status = 'submitted'
                ", ['instance' => $cm->instance, 'userid' => $userid]);
                
            case 'quiz':
                // Check quiz_attempts table for finished attempts
                return $DB->record_exists_sql("
                    SELECT 1 FROM {quiz_attempts}
                    WHERE quiz = :instance AND userid = :userid AND state = 'finished'
                ", ['instance' => $cm->instance, 'userid' => $userid]);
                
            case 'forum':
                // Check forum_posts for any posts by user
                return $DB->record_exists_sql("
                    SELECT 1 FROM {forum_posts} fp
                    JOIN {forum_discussions} fd ON fd.id = fp.discussion
                    WHERE fd.forum = :instance AND fp.userid = :userid
                ", ['instance' => $cm->instance, 'userid' => $userid]);
                
            case 'workshop':
                // Check workshop_submissions
                return $DB->record_exists_sql("
                    SELECT 1 FROM {workshop_submissions}
                    WHERE workshopid = :instance AND authorid = :userid
                ", ['instance' => $cm->instance, 'userid' => $userid]);
                
            case 'glossary':
                // Check glossary_entries
                return $DB->record_exists_sql("
                    SELECT 1 FROM {glossary_entries}
                    WHERE glossaryid = :instance AND userid = :userid
                ", ['instance' => $cm->instance, 'userid' => $userid]);
                
            case 'data':
                // Check data_records
                return $DB->record_exists_sql("
                    SELECT 1 FROM {data_records}
                    WHERE dataid = :instance AND userid = :userid
                ", ['instance' => $cm->instance, 'userid' => $userid]);
                
            case 'wiki':
                // Check wiki contributions
                return $DB->record_exists_sql("
                    SELECT 1 FROM {wiki_versions} wv
                    JOIN {wiki_pages} wp ON wp.id = wv.pageid
                    JOIN {wiki_subwikis} ws ON ws.id = wp.subwikiid
                    WHERE ws.wikiid = :instance AND wv.userid = :userid
                ", ['instance' => $cm->instance, 'userid' => $userid]);
                
            case 'lesson':
                // Check lesson_attempts
                return $DB->record_exists_sql("
                    SELECT 1 FROM {lesson_attempts}
                    WHERE lessonid = :instance AND userid = :userid
                ", ['instance' => $cm->instance, 'userid' => $userid]);
                
            case 'scorm':
                // Check scorm_scoes_track
                return $DB->record_exists_sql("
                    SELECT 1 FROM {scorm_scoes_track}
                    WHERE scormid = :instance AND userid = :userid
                ", ['instance' => $cm->instance, 'userid' => $userid]);
                
            case 'h5pactivity':
                // Check h5pactivity_attempts
                return $DB->record_exists_sql("
                    SELECT 1 FROM {h5pactivity_attempts}
                    WHERE h5pactivityid = :instance AND userid = :userid
                ", ['instance' => $cm->instance, 'userid' => $userid]);
                
            case 'choice':
                // Check choice_answers
                return $DB->record_exists_sql("
                    SELECT 1 FROM {choice_answers}
                    WHERE choiceid = :instance AND userid = :userid
                ", ['instance' => $cm->instance, 'userid' => $userid]);
                
            case 'feedback':
                // Check feedback_completed
                return $DB->record_exists_sql("
                    SELECT 1 FROM {feedback_completed}
                    WHERE feedback = :instance AND userid = :userid
                ", ['instance' => $cm->instance, 'userid' => $userid]);
                
            case 'survey':
                // Check survey_answers
                return $DB->record_exists_sql("
                    SELECT 1 FROM {survey_answers}
                    WHERE survey = :instance AND userid = :userid
                ", ['instance' => $cm->instance, 'userid' => $userid]);
                
            default:
                // For unsupported modules, check if viewed (log)
                return $DB->record_exists_sql("
                    SELECT 1 FROM {logstore_standard_log}
                    WHERE contextinstanceid = :cmid 
                    AND contextlevel = :contextlevel
                    AND userid = :userid
                    AND action = 'viewed'
                ", ['cmid' => $cmid, 'contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);
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
        $parts = [];
        
        // Course-wide requirements
        $courseReqs = [];
        if ($this->diff1 > 0) {
            $courseReqs[] = "diff1: {$this->diff1}";
        }
        if ($this->diff2 > 0) {
            $courseReqs[] = "diff2: {$this->diff2}";
        }
        if ($this->diff3 > 0) {
            $courseReqs[] = "diff3: {$this->diff3}";
        }
        if ($this->diff4 > 0) {
            $courseReqs[] = "diff4: {$this->diff4}";
        }
        if (!empty($courseReqs)) {
            $parts[] = implode(', ', $courseReqs) . ' ' . get_string('incourse', 'availability_diffcomplete');
        }
        
        // Section-based requirements
        if ($this->section !== null) {
            $sectionReqs = [];
            if ($this->sectiondiff1 > 0) {
                $sectionReqs[] = "diff1: {$this->sectiondiff1}";
            }
            if ($this->sectiondiff2 > 0) {
                $sectionReqs[] = "diff2: {$this->sectiondiff2}";
            }
            if ($this->sectiondiff3 > 0) {
                $sectionReqs[] = "diff3: {$this->sectiondiff3}";
            }
            if ($this->sectiondiff4 > 0) {
                $sectionReqs[] = "diff4: {$this->sectiondiff4}";
            }
            if (!empty($sectionReqs)) {
                $parts[] = implode(', ', $sectionReqs) . ' ' . get_string('insection', 'availability_diffcomplete', $this->section);
            }
        }
        
        $reqstring = implode('; ', $parts);
        
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
        $debug = "course[diff1:{$this->diff1} diff2:{$this->diff2} diff3:{$this->diff3} diff4:{$this->diff4}]";
        if ($this->section !== null) {
            $debug .= " section{$this->section}[diff1:{$this->sectiondiff1} diff2:{$this->sectiondiff2} diff3:{$this->sectiondiff3} diff4:{$this->sectiondiff4}]";
        }
        return $debug;
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
            
            // Course-wide requirements
            if ($this->diff1 > 0 && $this->get_tag_completion_count($course->id, 'diff1', $userid, null) < $this->diff1) {
                $meets = false;
            }
            if ($meets && $this->diff2 > 0 && $this->get_tag_completion_count($course->id, 'diff2', $userid, null) < $this->diff2) {
                $meets = false;
            }
            if ($meets && $this->diff3 > 0 && $this->get_tag_completion_count($course->id, 'diff3', $userid, null) < $this->diff3) {
                $meets = false;
            }
            if ($meets && $this->diff4 > 0 && $this->get_tag_completion_count($course->id, 'diff4', $userid, null) < $this->diff4) {
                $meets = false;
            }
            
            // Section-based requirements
            if ($meets && $this->section !== null) {
                if ($this->sectiondiff1 > 0 && $this->get_tag_completion_count($course->id, 'diff1', $userid, $this->section) < $this->sectiondiff1) {
                    $meets = false;
                }
                if ($meets && $this->sectiondiff2 > 0 && $this->get_tag_completion_count($course->id, 'diff2', $userid, $this->section) < $this->sectiondiff2) {
                    $meets = false;
                }
                if ($meets && $this->sectiondiff3 > 0 && $this->get_tag_completion_count($course->id, 'diff3', $userid, $this->section) < $this->sectiondiff3) {
                    $meets = false;
                }
                if ($meets && $this->sectiondiff4 > 0 && $this->get_tag_completion_count($course->id, 'diff4', $userid, $this->section) < $this->sectiondiff4) {
                    $meets = false;
                }
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
