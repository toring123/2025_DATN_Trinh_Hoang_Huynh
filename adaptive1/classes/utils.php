<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace format_adaptive1;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/completionlib.php');

class utils {

    // Cache lưu tags để tối ưu hiệu năng
    private static $tag_cache = [];

    /**
     * HÀM LOG DEBUG RA BROWSER CONSOLE
     * Nhấn F12 -> Console để xem kết quả tính toán
     */
    private static function console_log($label, $data) {
        global $CFG;
        // Chỉ hiện log nếu Moodle đang bật chế độ Debug (Site Admin > Development > Debugging)
        // Hoặc bạn có thể comment dòng if này lại để luôn hiện log khi dev
        // if (empty($CFG->debugdisplay)) {
        //      return; 
        // }

        $json_data = json_encode($data);
        // In ra script JS tô màu xanh lá cây để dễ nhìn
        echo "<script>console.log('%c[ADAPTIVE] $label', 'color: #00AA00; font-weight: bold;', $json_data);</script>";
    }

    /**
     * Get the difficulty level.
     * Đã bỏ  để tránh lỗi type.
     */
    public static function get_module_difficulty($cm): int {
        if (isset(self::$tag_cache[$cm->id])) {
            return self::$tag_cache[$cm->id];
        }

        $tags = \core_tag_tag::get_item_tags('core', 'course_modules', $cm->id);
        $difficulty = 1; 

        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $tagname = \core_text::strtolower($tag->name);
                if ($tagname === 'diff4') { $difficulty = 4; break; }
                else if ($tagname === 'diff3') { $difficulty = 3; break; }
                else if ($tagname === 'diff2') { $difficulty = 2; break; }
            }
        }

        self::$tag_cache[$cm->id] = $difficulty;
        return $difficulty;
    }

    /**
     * Check if a student meets the conditions to view a module.
     */
    public static function check_module_conditions($course, int $userid, $cm, $section): bool {
        $difficulty = self::get_module_difficulty($cm);

        // LOG: In ra tên module và độ khó
        // self::console_log("Check Module: " . $cm->name, "Difficulty: " . $difficulty);

        if ($difficulty === 1) {
            return true;
        }

        $format = course_get_format($course);
        $options = $format->get_format_options();
        
        $is_visible = false;
        $req_data = [];

        if ($difficulty === 2) {
            $req_count = $options['unlock_diff2_count'] ?? 0;
            $req_grade = $options['unlock_diff2_grade'] ?? 0;
            $is_visible = self::check_difficulty_requirements($course, $userid, $section, 1, $req_count, $req_grade);
            $req_data = ['Need Count (Diff1)' => $req_count, 'Need Grade (Diff1)' => $req_grade];
        } else if ($difficulty === 3) {
            $req_count = $options['unlock_diff3_count'] ?? 0;
            $req_grade = $options['unlock_diff3_grade'] ?? 0;
            $is_visible = self::check_difficulty_requirements($course, $userid, $section, 2, $req_count, $req_grade);
            $req_data = ['Need Count (Diff2)' => $req_count, 'Need Grade (Diff2)' => $req_grade];
        } else if ($difficulty === 4) {
            $req_count = $options['unlock_diff4_count'] ?? 0;
            $req_grade = $options['unlock_diff4_grade'] ?? 0;
            $is_visible = self::check_difficulty_requirements($course, $userid, $section, 3, $req_count, $req_grade);
            $req_data = ['Need Count (Diff3)' => $req_count, 'Need Grade (Diff3)' => $req_grade];
        }

        // LOG KẾT QUẢ MODULE
        // if (!$is_visible) {
        //     self::console_log("MODULE HIDDEN: " . $cm->name, [
        //         'Difficulty' => $difficulty,
        //         'Requirements' => $req_data,
        //         'Result' => 'HIDDEN'
        //     ]);
        // }

        return $is_visible;
    }

    /**
     * Check if a student meets the conditions to view a section.
     */
    public static function check_section_conditions( $course, int $userid,  $section): bool {
        if ($section->section <= 1) {
            return true;
        }

        $format = course_get_format($course);
        $options = $format->get_format_options();

        $requiredcount = $options['unlock_nextsection_count'] ?? 0;
        $requiredgrade = $options['unlock_nextsection_grade'] ?? 0;
        $requiredcoursegrade = $options['unlock_nextsection_coursegrade'] ?? 0;

        $prevsection = $format->get_section($section->section - 1);
        if (!$prevsection) {
            return false;
        }

        // Tính toán stats của section trước
        $stats = self::get_section_stats($course, $userid, $prevsection);
        $coursegrade = self::get_course_grade($course, $userid);

        $pass_count = ($stats['completed_count'] >= $requiredcount);
        $pass_grade = ($stats['average_grade'] >= $requiredgrade);
        $pass_course = ($coursegrade >= $requiredcoursegrade);

        $is_visible = $pass_count && $pass_grade && $pass_course;

        // LOG CHI TIẾT SECTION
        // self::console_log("CHECK SECTION " . $section->section, [
        //     'Prev Section' => $prevsection->section,
        //     'User Stats' => [
        //         'Completed Count' => $stats['completed_count'],
        //         'Avg Grade' => round($stats['average_grade'], 2),
        //         'Course Grade' => round($coursegrade, 2)
        //     ],
        //     'Requirements' => [
        //         'Count' => $requiredcount,
        //         'Grade' => $requiredgrade,
        //         'Course Grade' => $requiredcoursegrade
        //     ],
        //     'Checks' => [
        //         'Count Pass?' => $pass_count,
        //         'Grade Pass?' => $pass_grade,
        //         'Course Pass?' => $pass_course
        //     ],
        //     'FINAL RESULT' => $is_visible ? 'VISIBLE' : 'HIDDEN'
        // ]);

        return $is_visible;
    }

    private static function check_difficulty_requirements( $course, int $userid,  $section, int $previousdifficulty, int $requiredcount, int $requiredgrade): bool {
        $stats = self::get_difficulty_stats($course, $userid, $section, $previousdifficulty);
        
        // Log phụ để biết stats hiện tại
        // self::console_log("Stats for Diff $previousdifficulty in Sec " . $section->section, $stats);

        if ($stats['completed_count'] < $requiredcount) return false;
        if ($stats['average_grade'] < $requiredgrade) return false;
        return true;
    }

    private static function get_difficulty_stats( $course, int $userid,  $section, int $difficulty): array {
        $modinfo = get_fast_modinfo($course, $userid);
        $completedcount = 0;
        $totalgrades = 0;
        $gradecount = 0;
        $completion = new \completion_info($course);

        if (!empty($modinfo->sections[$section->section])) {
            // self::console_log("Có module trong section", $section->section);
            foreach ($modinfo->sections[$section->section] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                // self::console_log("Checking Module in Section: " . $cm->name, "Difficulty: " . self::get_module_difficulty($cm));
                if (!$cm->uservisible){
                    // self::console_log("Module không hiển thị, bỏ qua", $cm->name);
                    continue;
                }
                if (self::get_module_difficulty($cm) !== $difficulty){
                    // self::console_log("Module không đúng độ khó, bỏ qua", $cm->name);
                    continue;
                }
                    
                $val = self::get_user_grade_percentage($course, $cm, $userid);
                
                if ($val !== null) {
                    $totalgrades += $val;
                    $completedcount++;
                    // Debug log điểm nếu cần
                    self::console_log("Got Grade for " . $cm->name, $val);
                } else{
                    self::console_log("No Grade for " . $cm->name, "User may not have been graded yet.");
                }
            }
        } else{
            // self::console_log("Không có module trong section", $section->section);
        }
        // self::console_log("difficult: $difficulty, completedcount: $completedcount, totalGrades: $totalgrades", "");
        return [
            'completed_count' => $completedcount,
            'average_grade' => $completedcount > 0 ? $totalgrades / $completedcount : 0,
        ];
    }

    private static function get_section_stats( $course, int $userid,  $section): array {
        $modinfo = get_fast_modinfo($course, $userid);
        $completedcount = 0;
        $totalgrades = 0;
        $gradecount = 0;
        $completion = new \completion_info($course);

        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if (!$cm->uservisible) continue;

                $val = self::get_user_grade_percentage($course, $cm, $userid);
                if ($val !== null) {
                    $totalgrades += $val;
                    $completedcount++;
                }
            }
        }

        return [
            'completed_count' => $completedcount,
            'average_grade' => $completedcount > 0 ? $totalgrades / $completedcount : 0,
        ];
    }

    private static function get_user_grade_percentage($course, $cm, $userid) {
        $grades = grade_get_grades($course->id, 'mod', $cm->modname, $cm->instance, $userid);
        if (!empty($grades->items[0]->grades[$userid])) {
            $grade = $grades->items[0]->grades[$userid];
            if (isset($grade->grade) && $grade->grade !== null) {
                $grademax = $grades->items[0]->grademax ?? 100;
                $grademin = $grades->items[0]->grademin ?? 0;
                if ($grademax > $grademin) {
                    return (($grade->grade - $grademin) / ($grademax - $grademin)) * 100;
                }
            }
        }
        return null;
    }

    /**
     * Tính điểm trung bình toàn khóa học (Chỉ tính các bài ĐÃ CÓ ĐIỂM).
     * Thay thế logic cũ phụ thuộc vào Gradebook Aggregation.
     */
    private static function get_course_grade(\stdClass $course, int $userid): float {
        // Lấy danh sách toàn bộ module trong khóa học
        $modinfo = get_fast_modinfo($course, $userid);
        
        $total_percentage = 0;
        $count = 0;

        // Duyệt qua tất cả các CM (Course Modules) trong khóa học
        foreach ($modinfo->cms as $cm) {
            // Bỏ qua nếu module bị ẩn hoặc không truy cập được
            if (!$cm->uservisible) {
                continue;
            }

            // Gọi hàm lấy điểm % (Hàm này đã viết ở dưới, trả về null nếu không có điểm)
            $val = self::get_user_grade_percentage($course, $cm, $userid);

            if ($val !== null) {
                $total_percentage += $val;
                $count++;
                
                // Debug log nếu muốn kiểm tra kỹ
                // self::console_log("Course Grade Item: " . $cm->name, $val);
            }
        }

        // Tránh lỗi chia cho 0
        if ($count === 0) {
            return 0;
        }

        // Trả về trung bình cộng phần trăm (0 - 100)
        return $total_percentage / $count;
    }
}