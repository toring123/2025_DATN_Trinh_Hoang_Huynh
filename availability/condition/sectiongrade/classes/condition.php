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

    public function __construct($structure) {
        $this->sectionnumber = isset($structure->section) ? (int)$structure->section : 0;
        $this->mingrade = isset($structure->mingrade) ? (float)$structure->mingrade : 50.0;
    }

    public function save() {
        return (object)[
            'type' => 'sectiongrade',
            'section' => $this->sectionnumber,
            'mingrade' => $this->mingrade
        ];
    }

    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        // Tận dụng logic của filter_user_list để tránh viết lặp code
        // Tạo mảng giả lập 1 user để check
        $users = [$userid => true]; 
        $filtered = $this->filter_user_list($users, $not, $info, null);
        
        return array_key_exists($userid, $filtered);
    }

    public function is_applied_to_user_lists() {
        return true;
    }

    /**
     * Hàm lọc danh sách user đã được tối ưu hiệu năng (Bulk processing)
     * Chỉ tính trung bình các bài user NHÌN THẤY ĐƯỢC và ĐÃ ĐƯỢC CHẤM ĐIỂM
     */
    public function filter_user_list(array $users, $not, \core_availability\info $info, ?\core_availability\capability_checker $checker = null) {
        global $DB;

        if (empty($users)) {
            return $users;
        }

        $course = $info->get_course();
        $userids = array_keys($users);
        
        // Phải tính riêng cho từng user vì uservisible khác nhau
        $result = [];
        
        foreach ($users as $userid => $user) {
            // Cache per-user modinfo to avoid repeated loads if same user appears
            static $modinfoCache = [];
            if (!isset($modinfoCache[$userid])) {
                $modinfoCache[$userid] = get_fast_modinfo($course, $userid);
            }
            $modinfo = $modinfoCache[$userid];

            $visibleCmIds = [];
            foreach ($modinfo->get_cms() as $cm) {
                if ($cm->sectionnum == $this->sectionnumber && $cm->uservisible) {
                    $visibleCmIds[] = $cm->id;
                }
            }

            $average = 0;

            if (!empty($visibleCmIds)) {
                list($insql, $params) = $DB->get_in_or_equal($visibleCmIds, SQL_PARAMS_NAMED);
                $params['userid'] = $userid;
                $params['courseid'] = $course->id;

                $sql = "SELECT SUM(gg.finalgrade - gi.grademin) as total_achieved,
                               SUM(gi.grademax - gi.grademin) as total_max
                        FROM {course_modules} cm
                        JOIN {modules} m ON m.id = cm.module
                        JOIN {grade_items} gi ON gi.iteminstance = cm.instance 
                             AND gi.itemmodule = m.name 
                             AND gi.courseid = :courseid
                             AND gi.itemtype = 'mod'
                        JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
                        WHERE cm.id $insql
                          AND gg.finalgrade IS NOT NULL
                          AND gi.grademax > gi.grademin";

                $grade = $DB->get_record_sql($sql, $params);

                if ($grade && $grade->total_max > 0) {
                    $average = ($grade->total_achieved / $grade->total_max) * 100;
                }
            }

            $allow = ($average >= $this->mingrade);
            if ($not) {
                $allow = !$allow;
            }

            if ($allow) {
                $result[$userid] = $user;
            }
        }

        return $result;
    }

    public function get_description($full, $not, \core_availability\info $info) {
        $str = $not ? 'requires_notgrade' : 'requires_grade';
        return get_string($str, 'availability_sectiongrade', 
            ['section' => $this->sectionnumber, 'grade' => $this->mingrade]);
    }

    protected function get_debug_string() {
        return 'section:' . $this->sectionnumber . ' mingrade:' . $this->mingrade;
    }
}