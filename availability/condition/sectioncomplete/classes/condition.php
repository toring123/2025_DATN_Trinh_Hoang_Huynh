<?php
namespace availability_sectioncomplete;

defined('MOODLE_INTERNAL') || die();

class condition extends \core_availability\condition {
    
    protected $sectionnumber;
    
    protected $mincompletions;
    
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
    
    public function save() {
        return (object)[
            'type' => 'sectioncomplete',
            'section' => $this->sectionnumber,
            'mincompletions' => $this->mincompletions
        ];
    }
    
    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        $users = [$userid => true];
        $filtered = $this->filter_user_list($users, $not, $info, null);
        return array_key_exists($userid, $filtered);
    }
    
    protected function get_section_completion_count($courseid, $sectionnumber, $userid) {
        $counts = $this->bulk_section_completion_counts($courseid, $sectionnumber, [$userid]);
        return (int)($counts[$userid] ?? 0);
    }
    
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
    
    public function get_description($full, $not, \core_availability\info $info) {
        if ($not) {
            return get_string('requires_notcomplete', 'availability_sectioncomplete', 
                ['section' => $this->sectionnumber, 'count' => $this->mincompletions]);
        } else {
            return get_string('requires_complete', 'availability_sectioncomplete', 
                ['section' => $this->sectionnumber, 'count' => $this->mincompletions]);
        }
    }
    
    protected function get_debug_string() {
        return 'section:' . $this->sectionnumber . ' min:' . $this->mincompletions;
    }
    
    public function is_applied_to_user_lists() {
        return true;
    }
    
    public function filter_user_list(array $users, $not, \core_availability\info $info,
            ?\core_availability\capability_checker $checker = null) {
        if (empty($users)) {
            return $users;
        }

        $course = $info->get_course();
        $userids = array_keys($users);

        $counts = $this->bulk_section_completion_counts($course->id, $this->sectionnumber, $userids);

        $result = [];
        foreach ($users as $userid => $user) {
            $completedcount = (int)($counts[$userid] ?? 0);
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

    protected function bulk_section_completion_counts(int $courseid, int $sectionnumber, array $userids): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        list($userSql, $userParams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $params = [
            'courseid' => $courseid,
            'sectionnumber' => $sectionnumber,
            'completionstate' => COMPLETION_COMPLETE
        ] + $userParams;

        $sql = "SELECT cmc.userid, COUNT(DISTINCT cm.id) AS cnt
                FROM {course_modules} cm
                JOIN {course_sections} cs ON cs.id = cm.section
                JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid $userSql
                WHERE cm.course = :courseid
                  AND cs.section = :sectionnumber
                  AND cm.completion > 0
                  AND cmc.completionstate >= :completionstate
                  AND cm.deletioninprogress = 0
                GROUP BY cmc.userid";

        $trackingCounts = $DB->get_records_sql_menu($sql, $params);

        $submissionCounts = $this->bulk_section_submission_counts($courseid, $sectionnumber, $userids, $userSql, $userParams);

        $result = [];
        foreach ($userids as $uid) {
            $result[$uid] = (int)($trackingCounts[$uid] ?? 0) + (int)($submissionCounts[$uid] ?? 0);
        }

        return $result;
    }

    protected function bulk_section_submission_counts(int $courseid, int $sectionnumber, array $userids, string $userSql, array $userParams): array {
        global $DB;

        $params = [
            'courseid' => $courseid,
            'sectionnumber' => $sectionnumber,
            'completionnone' => COMPLETION_TRACKING_NONE
        ];

        $modules = $DB->get_records_sql("SELECT cm.id, cm.instance, m.name AS modname
                                         FROM {course_modules} cm
                                         JOIN {modules} m ON m.id = cm.module
                                         JOIN {course_sections} cs ON cs.id = cm.section
                                         WHERE cm.course = :courseid
                                           AND cs.section = :sectionnumber
                                           AND cm.completion = :completionnone
                                           AND cm.deletioninprogress = 0", $params);

        if (empty($modules)) {
            return [];
        }

        $byMod = [];
        foreach ($modules as $cm) {
            $byMod[$cm->modname][] = $cm;
        }

        $aggregate = [];

        foreach ($byMod as $modname => $cmlist) {
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

        foreach ($userids as $uid) {
            if (!isset($aggregate[$uid])) {
                $aggregate[$uid] = 0;
            }
        }

        return $aggregate;
    }
}
