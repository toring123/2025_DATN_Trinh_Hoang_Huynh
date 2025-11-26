<?php
namespace format_adaptive1\output\courseformat\state;

use core_courseformat\output\local\state\course as course_base;
use renderer_base;
use stdClass;
use context_course;

/**
 * Chỉ lọc SECTION LIST ở cấp độ khóa học.
 */
class course extends course_base {

    public function export_for_template(renderer_base $output): stdClass {
        global $USER;

        // 1. Lấy dữ liệu gốc (Chỉ có sectionlist)
        $data = parent::export_for_template($output);

        $course = $this->format->get_course();
        $context = context_course::instance($course->id);

        if (has_capability('moodle/course:update', $context)) {
            return $data;
        }

        $userid = $USER->id;
        $modinfo = get_fast_modinfo($course, $userid);

        // --- LỌC SECTION LIST ---
        if (!empty($data->sectionlist)) {
            $filtered_sections = [];
            foreach ($data->sectionlist as $sectionid) {
                $section = $modinfo->get_section_info_by_id($sectionid);
                if (!$section) continue;

                // Check điều kiện Section
                if (\format_adaptive1\utils::check_section_conditions($course, $userid, $section)) {
                    $filtered_sections[] = $sectionid;
                }
                // Nếu không thỏa mãn -> Không thêm vào danh sách -> Ẩn khỏi cây thư mục
            }
            $data->sectionlist = $filtered_sections;
        }

        return $data;
    }
}