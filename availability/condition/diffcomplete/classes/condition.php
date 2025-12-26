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
        // Reuse bulk counting even for single user to keep logic identical to user-list path
        $users = [$userid => true];
        $filtered = $this->filter_user_list($users, $not, $info, null);
        return array_key_exists($userid, $filtered);
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
            ?\core_availability\capability_checker $checker = null) {
        if (empty($users)) {
            return $users;
        }

        $course = $info->get_course();
        $userids = array_keys($users);

        // Collect all tag/section combinations we need
        $requirements = [];
        foreach (['diff1', 'diff2', 'diff3', 'diff4'] as $tag) {
            if ($this->$tag > 0) {
                $requirements[] = [$tag, null, $this->$tag];
            }
        }
        if ($this->section !== null) {
            foreach ([['diff1', $this->sectiondiff1], ['diff2', $this->sectiondiff2],
                      ['diff3', $this->sectiondiff3], ['diff4', $this->sectiondiff4]] as [$tag, $min]) {
                if ($min > 0) {
                    $requirements[] = [$tag, $this->section, $min];
                }
            }
        }

        if (empty($requirements)) {
            return $users;
        }

        // Precompute counts per (tag, section) for all users in bulk to avoid N+1 queries
        $countsCache = [];
        foreach ($requirements as [$tag, $section, $min]) {
            $countsCache[$tag . '|' . ($section === null ? 'all' : $section)] =
                $this->bulk_tag_counts($course->id, $userids, $tag, $section);
        }

        $result = [];

        foreach ($users as $userid => $user) {
            $meets = true;

            foreach ($requirements as [$tag, $section, $min]) {
                $cacheKey = $tag . '|' . ($section === null ? 'all' : $section);
                $userCount = $countsCache[$cacheKey][$userid] ?? 0;
                if ($userCount < $min) {
                    $meets = false;
                    break;
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

    /**
     * Bulk count completions for a tag for a list of users, optionally limited to a section.
     * Counts both completion-tracking activities and non-tracking activities with submissions.
     *
     * @param int $courseid
     * @param array $userids
     * @param string $tagname
     * @param int|null $sectionnumber
     * @return array userid => count
     */
    protected function bulk_tag_counts(int $courseid, array $userids, string $tagname, ?int $sectionnumber): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        // Part 1: completion tracking activities (uses course_modules_completion)
        list($userSql, $userParams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = [
            'courseid' => $courseid,
            'tagname' => $tagname,
            'completionstate' => COMPLETION_COMPLETE
        ] + $userParams;

        $sql = "SELECT cmc.userid, COUNT(DISTINCT cm.id) AS cnt
                FROM {course_modules} cm
                JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid $userSql
                JOIN {tag_instance} ti ON ti.itemid = cm.id AND ti.itemtype = 'course_modules'
                JOIN {tag} t ON t.id = ti.tagid
                JOIN {course_sections} cs ON cs.id = cm.section
                WHERE cm.course = :courseid
                  AND cm.completion > 0
                  AND cmc.completionstate >= :completionstate
                  AND t.name = :tagname";

        if ($sectionnumber !== null) {
            $sql .= " AND cs.section = :sectionnumber";
            $params['sectionnumber'] = $sectionnumber;
        }

        $trackingCounts = $DB->get_records_sql_menu($sql . ' GROUP BY cmc.userid', $params);

        // Part 2: activities with no completion tracking but submissions
        $submissionCounts = $this->bulk_submission_counts($courseid, $userids, $tagname, $sectionnumber);

        // Merge counts
        $result = [];
        foreach ($userids as $uid) {
            $result[$uid] = (int)($trackingCounts[$uid] ?? 0) + (int)($submissionCounts[$uid] ?? 0);
        }

        return $result;
    }

    /**
     * Bulk submission counts for completion-none activities with a given tag.
     *
     * @param int $courseid
     * @param array $userids
     * @param string $tagname
     * @param int|null $sectionnumber
     * @return array userid => count
     */
    protected function bulk_submission_counts(int $courseid, array $userids, string $tagname, ?int $sectionnumber): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        list($userSql, $userParams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // First, find all completion-none course modules matching the tag (and section if provided)
        $params = [
            'courseid' => $courseid,
            'completionnone' => COMPLETION_TRACKING_NONE,
            'tagname' => $tagname
        ];
        $sql = "SELECT cm.id, cm.instance, m.name AS modname
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                JOIN {tag_instance} ti ON ti.itemid = cm.id AND ti.itemtype = 'course_modules'
                JOIN {tag} t ON t.id = ti.tagid
                JOIN {course_sections} cs ON cs.id = cm.section
                WHERE cm.course = :courseid
                  AND cm.completion = :completionnone
                  AND t.name = :tagname";
        if ($sectionnumber !== null) {
            $sql .= " AND cs.section = :sectionnumber";
            $params['sectionnumber'] = $sectionnumber;
        }

        $modules = $DB->get_records_sql($sql, $params);
        if (empty($modules)) {
            return [];
        }

        // Group module IDs by module name for targeted submission queries
        $byMod = [];
        foreach ($modules as $cm) {
            $byMod[$cm->modname][] = $cm;
        }

        $aggregate = [];

        foreach ($byMod as $modname => $cmlist) {
            $cmIds = array_map(static function($cm) { return $cm->id; }, $cmlist);
            $instanceIds = array_map(static function($cm) { return $cm->instance; }, $cmlist);

            switch ($modname) {
                case 'assign': {
                    list($in, $p) = $DB->get_in_or_equal($instanceIds, SQL_PARAMS_NAMED, 'a');
                    $sqlSubmit = "SELECT s.userid, COUNT(DISTINCT s.assignment) AS cnt
                                  FROM {assign_submission} s
                                  WHERE s.assignment $in
                                    AND s.userid $userSql
                                    AND s.status = 'submitted'
                                  GROUP BY s.userid";
                    $rows = $DB->get_records_sql_menu($sqlSubmit, $p + $userParams);
                    break;
                }
                case 'quiz': {
                    list($in, $p) = $DB->get_in_or_equal($instanceIds, SQL_PARAMS_NAMED, 'q');
                    $sqlSubmit = "SELECT qa.userid, COUNT(DISTINCT qa.quiz) AS cnt
                                  FROM {quiz_attempts} qa
                                  WHERE qa.quiz $in
                                    AND qa.userid $userSql
                                    AND qa.state = 'finished'
                                  GROUP BY qa.userid";
                    $rows = $DB->get_records_sql_menu($sqlSubmit, $p + $userParams);
                    break;
                }
                case 'forum': {
                    list($in, $p) = $DB->get_in_or_equal($instanceIds, SQL_PARAMS_NAMED, 'f');
                    $sqlSubmit = "SELECT fp.userid, COUNT(DISTINCT fd.forum) AS cnt
                                  FROM {forum_posts} fp
                                  JOIN {forum_discussions} fd ON fd.id = fp.discussion
                                  WHERE fd.forum $in
                                    AND fp.userid $userSql
                                  GROUP BY fp.userid";
                    $rows = $DB->get_records_sql_menu($sqlSubmit, $p + $userParams);
                    break;
                }
                case 'workshop': {
                    list($in, $p) = $DB->get_in_or_equal($instanceIds, SQL_PARAMS_NAMED, 'w');
                    $sqlSubmit = "SELECT ws.authorid AS userid, COUNT(DISTINCT ws.workshopid) AS cnt
                                  FROM {workshop_submissions} ws
                                  WHERE ws.workshopid $in
                                    AND ws.authorid $userSql
                                  GROUP BY ws.authorid";
                    $rows = $DB->get_records_sql_menu($sqlSubmit, $p + $userParams);
                    break;
                }
                case 'choice': {
                    list($in, $p) = $DB->get_in_or_equal($instanceIds, SQL_PARAMS_NAMED, 'c');
                    $sqlSubmit = "SELECT ca.userid, COUNT(DISTINCT ca.choiceid) AS cnt
                                  FROM {choice_answers} ca
                                  WHERE ca.choiceid $in
                                    AND ca.userid $userSql
                                  GROUP BY ca.userid";
                    $rows = $DB->get_records_sql_menu($sqlSubmit, $p + $userParams);
                    break;
                }
                case 'feedback': {
                    list($in, $p) = $DB->get_in_or_equal($instanceIds, SQL_PARAMS_NAMED, 'fb');
                    $sqlSubmit = "SELECT fc.userid, COUNT(DISTINCT fc.feedback) AS cnt
                                  FROM {feedback_completed} fc
                                  WHERE fc.feedback $in
                                    AND fc.userid $userSql
                                  GROUP BY fc.userid";
                    $rows = $DB->get_records_sql_menu($sqlSubmit, $p + $userParams);
                    break;
                }
                case 'survey': {
                    list($in, $p) = $DB->get_in_or_equal($instanceIds, SQL_PARAMS_NAMED, 'sv');
                    $sqlSubmit = "SELECT sa.userid, COUNT(DISTINCT sa.survey) AS cnt
                                  FROM {survey_answers} sa
                                  WHERE sa.survey $in
                                    AND sa.userid $userSql
                                  GROUP BY sa.userid";
                    $rows = $DB->get_records_sql_menu($sqlSubmit, $p + $userParams);
                    break;
                }
                case 'lesson': {
                    list($in, $p) = $DB->get_in_or_equal($instanceIds, SQL_PARAMS_NAMED, 'l');
                    $sqlSubmit = "SELECT la.userid, COUNT(DISTINCT la.lessonid) AS cnt
                                  FROM {lesson_attempts} la
                                  WHERE la.lessonid $in
                                    AND la.userid $userSql
                                  GROUP BY la.userid";
                    $rows = $DB->get_records_sql_menu($sqlSubmit, $p + $userParams);
                    break;
                }
                case 'data': {
                    list($in, $p) = $DB->get_in_or_equal($instanceIds, SQL_PARAMS_NAMED, 'd');
                    $sqlSubmit = "SELECT dr.userid, COUNT(DISTINCT dr.dataid) AS cnt
                                  FROM {data_records} dr
                                  WHERE dr.dataid $in
                                    AND dr.userid $userSql
                                  GROUP BY dr.userid";
                    $rows = $DB->get_records_sql_menu($sqlSubmit, $p + $userParams);
                    break;
                }
                case 'glossary': {
                    list($in, $p) = $DB->get_in_or_equal($instanceIds, SQL_PARAMS_NAMED, 'g');
                    $sqlSubmit = "SELECT ge.userid, COUNT(DISTINCT ge.glossaryid) AS cnt
                                  FROM {glossary_entries} ge
                                  WHERE ge.glossaryid $in
                                    AND ge.userid $userSql
                                  GROUP BY ge.userid";
                    $rows = $DB->get_records_sql_menu($sqlSubmit, $p + $userParams);
                    break;
                }
                case 'wiki': {
                    list($in, $p) = $DB->get_in_or_equal($instanceIds, SQL_PARAMS_NAMED, 'wk');
                    $sqlSubmit = "SELECT wp.userid, COUNT(DISTINCT ws.wikiid) AS cnt
                                  FROM {wiki_pages} wp
                                  JOIN {wiki_subwikis} ws ON ws.id = wp.subwikiid
                                  WHERE ws.wikiid $in
                                    AND wp.userid $userSql
                                  GROUP BY wp.userid";
                    $rows = $DB->get_records_sql_menu($sqlSubmit, $p + $userParams);
                    break;
                }
                case 'chat': {
                    list($in, $p) = $DB->get_in_or_equal($instanceIds, SQL_PARAMS_NAMED, 'ch');
                    $sqlSubmit = "SELECT cm.userid, COUNT(DISTINCT cm.chatid) AS cnt
                                  FROM {chat_messages} cm
                                  WHERE cm.chatid $in
                                    AND cm.userid $userSql
                                  GROUP BY cm.userid";
                    $rows = $DB->get_records_sql_menu($sqlSubmit, $p + $userParams);
                    break;
                }
                case 'h5pactivity': {
                    list($in, $p) = $DB->get_in_or_equal($instanceIds, SQL_PARAMS_NAMED, 'h');
                    $sqlSubmit = "SELECT ha.userid, COUNT(DISTINCT ha.h5pactivityid) AS cnt
                                  FROM {h5pactivity_attempts} ha
                                  WHERE ha.h5pactivityid $in
                                    AND ha.userid $userSql
                                  GROUP BY ha.userid";
                    $rows = $DB->get_records_sql_menu($sqlSubmit, $p + $userParams);
                    break;
                }
                default:
                    $rows = [];
            }

            foreach ($rows as $uid => $cnt) {
                $aggregate[$uid] = ($aggregate[$uid] ?? 0) + (int)$cnt;
            }
        }

        // Ensure all users appear in result (missing users = 0)
        foreach ($userids as $uid) {
            if (!isset($aggregate[$uid])) {
                $aggregate[$uid] = 0;
            }
        }

        return $aggregate;
    }
}
