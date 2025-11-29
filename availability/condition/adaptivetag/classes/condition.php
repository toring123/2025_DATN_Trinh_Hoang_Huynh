<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Availability plugin for adaptive learning based on difficulty tags
 *
 * File: availability/condition/adaptivetag/classes/condition.php
 *
 * @package    availability_adaptivetag
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_adaptivetag;

defined('MOODLE_INTERNAL') || die();

/**
 * Condition main class
 */
class condition extends \core_availability\condition {
    
    /** @var string Required tag */
    protected $requiredtag;
    
    /** @var int Minimum completions required */
    protected $mincompletions;
    
    /**
     * Constructor
     *
     * @param \stdClass $structure Data structure from JSON decode
     */
    public function __construct($structure) {
        if (isset($structure->tag)) {
            $this->requiredtag = $structure->tag;
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
            'type' => 'adaptivetag',
            'tag' => $this->requiredtag,
            'mincompletions' => $this->mincompletions
        ];
    }
    
    /**
     * Check if user is available
     *
     * @param bool $not Set true if we are inverting the condition
     * @param info $info Item we're checking
     * @param bool $grabthelot Performance hint
     * @param int $userid User ID to check availability for
     * @return bool True if available
     */
    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        global $DB;
        
        $course = $info->get_course();
        $allow = false;
        
        // Get user's completed activities with the required tag
        $sql = "SELECT COUNT(DISTINCT cm.id)
                FROM {course_modules} cm
                JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                JOIN {tag_instance} ti ON ti.itemid = cm.id
                JOIN {tag} t ON t.id = ti.tagid
                WHERE cm.course = :courseid
                AND cmc.userid = :userid
                AND cmc.completionstate >= :completionstate
                AND ti.itemtype = 'course_modules'
                AND ti.component = 'core'
                AND t.name = :tagname";
        
        $params = [
            'courseid' => $course->id,
            'userid' => $userid,
            'completionstate' => COMPLETION_COMPLETE,
            'tagname' => $this->requiredtag
        ];
        
        $completedcount = $DB->count_records_sql($sql, $params);
        
        if ($completedcount >= $this->mincompletions) {
            $allow = true;
        }
        
        if ($not) {
            $allow = !$allow;
        }
        
        return $allow;
    }
    
    /**
     * Get description of restriction
     *
     * @param bool $full Set true if this is the 'full information' view
     * @param bool $not Set true if we are inverting the condition
     * @param info $info Item we're checking
     * @return string Information string (for admin) about all restrictions
     */
    public function get_description($full, $not, \core_availability\info $info) {
        if ($not) {
            return get_string('requires_not_tag', 'availability_adaptivetag', 
                ['tag' => $this->requiredtag, 'count' => $this->mincompletions]);
        } else {
            return get_string('requires_tag', 'availability_adaptivetag', 
                ['tag' => $this->requiredtag, 'count' => $this->mincompletions]);
        }
    }
    
    /**
     * Get debug string
     *
     * @return string Debug string
     */
    protected function get_debug_string() {
        return 'tag:' . $this->requiredtag . ' (min: ' . $this->mincompletions . ')';
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
     * Filter the user list to only include users who meet this condition.
     *
     * @param array $users Array of userid => object with fields id and other user info
     * @param bool $not True if condition is negated
     * @param \core_availability\info $info Info about item
     * @param \core_availability\capability_checker $checker Capability checker
     * @return array Filtered array of users
     */
    public function filter_user_list(array $users, $not, \core_availability\info $info,
            \core_availability\capability_checker $checker) {
        global $DB;
        
        if (empty($users)) {
            return $users;
        }
        
        $course = $info->get_course();
        
        // Get list of user IDs
        $userids = array_keys($users);
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        
        // Get users who have completed the required number of activities with the tag
        $sql = "SELECT cmc.userid, COUNT(DISTINCT cm.id) as completed
                FROM {course_modules} cm
                JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                JOIN {tag_instance} ti ON ti.itemid = cm.id
                JOIN {tag} t ON t.id = ti.tagid
                WHERE cm.course = :courseid
                AND cmc.completionstate >= :completionstate
                AND ti.itemtype = 'course_modules'
                AND ti.component = 'core'
                AND t.name = :tagname
                AND cmc.userid $insql
                GROUP BY cmc.userid";
        
        $params = array_merge([
            'courseid' => $course->id,
            'completionstate' => COMPLETION_COMPLETE,
            'tagname' => $this->requiredtag
        ], $inparams);
        
        $completions = $DB->get_records_sql($sql, $params);
        
        // Filter users based on condition
        $result = [];
        foreach ($users as $userid => $user) {
            $completed = isset($completions[$userid]) ? $completions[$userid]->completed : 0;
            $meets = ($completed >= $this->mincompletions);
            
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
     * Get users who match the condition
     *
     * @param bool $not Set to true for NOT condition
     * @param \core_availability\info $info Info about current context
     * @param bool $onlyactive Only return active enrolments
     * @return array Array of user IDs
     */
    public function get_user_list($not, \core_availability\info $info, $onlyactive) {
        global $DB;
        
        $course = $info->get_course();
        
        // Get all enrolled users
        $context = \context_course::instance($course->id);
        list($enrolledsql, $enrolledparams) = get_enrolled_sql($context, '', 0, $onlyactive);
        
        // Get users who meet the completion requirement
        $sql = "SELECT DISTINCT u.id
                FROM {user} u
                JOIN ($enrolledsql) eu ON eu.id = u.id
                LEFT JOIN (
                    SELECT cmc.userid, COUNT(DISTINCT cm.id) as completed
                    FROM {course_modules} cm
                    JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                    JOIN {tag_instance} ti ON ti.itemid = cm.id
                    JOIN {tag} t ON t.id = ti.tagid
                    WHERE cm.course = :courseid
                    AND cmc.completionstate >= :completionstate
                    AND ti.itemtype = 'course_modules'
                    AND ti.component = 'core'
                    AND t.name = :tagname
                    GROUP BY cmc.userid
                ) comp ON comp.userid = u.id
                WHERE " . ($not ? "COALESCE(comp.completed, 0) < :mincompletions" : 
                                   "comp.completed >= :mincompletions");
        
        $params = array_merge($enrolledparams, [
            'courseid' => $course->id,
            'completionstate' => COMPLETION_COMPLETE,
            'tagname' => $this->requiredtag,
            'mincompletions' => $this->mincompletions
        ]);
        
        return $DB->get_fieldset_sql($sql, $params);
    }
}