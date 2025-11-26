<?php
// File: classes/output/courseformat/state/section.php

namespace format_adaptive1\output\courseformat\state;

use core_courseformat\output\local\state\section as section_base;
use renderer_base;
use stdClass;
use context_course;

/**
 * Class này chịu trách nhiệm lọc MODULE trong từng section.
 */
class section extends section_base {

    public function export_for_template(renderer_base $output): stdClass {
        global $USER;

        // 1. Lấy dữ liệu gốc của section (Bao gồm cmlist - danh sách module)
        $data = parent::export_for_template($output);

        $format = $this->format;
        $course = $format->get_course();
        $context = context_course::instance($course->id);

        // Teacher thấy hết
        if (has_capability('moodle/course:update', $context)) {
            return $data;
        }

        $userid = $USER->id;
        $modinfo = get_fast_modinfo($course, $userid);

        // Lấy object section hiện tại từ modinfo
        // $data->id chính là section id
        $section = $modinfo->get_section_info_by_id($data->id);

        if (!$section) {
            return $data;
        }

        // --- LỌC CMLIST (Danh sách module trong section này) ---
        if (!empty($data->cmlist)) {
            $filtered_cms = [];
            
            foreach ($data->cmlist as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if (!$cm) continue;

                // Check điều kiện Module bằng Utils của bạn
                if (\format_adaptive1\utils::check_module_conditions($course, $userid, $cm, $section)) {
                    $filtered_cms[] = $cmid;
                }
            }

            // Cập nhật lại danh sách module chỉ còn những bài được phép học
            $data->cmlist = $filtered_cms;
        }

        return $data;
    }
}