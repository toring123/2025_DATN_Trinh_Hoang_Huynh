<?php
namespace format_adaptive1\output\courseformat\state;

use core_courseformat\output\local\state\course as course_base;
use renderer_base;
use stdClass;
use context_course;

class course extends course_base {

    public function export_for_template(renderer_base $output): stdClass {
        global $USER;

        // 1. Lấy dữ liệu gốc
        $data = parent::export_for_template($output);
        $course = $this->format->get_course();
        $context = context_course::instance($course->id);

        // --- NẾU LÀ ADMIN/TEACHER ---
        if (has_capability('moodle/course:update', $context)) {
            return $data;
        }

        // --- NẾU LÀ STUDENT (Logic Adaptive) ---
        $userid = $USER->id;
        $modinfo = get_fast_modinfo($course, $userid); // Reset modinfo

        // Mảng dùng để tạo "Dấu vân tay" (Fingerprint) cho cấu trúc khóa học
        // Chúng ta sẽ ghi lại ID của tất cả những thứ được phép hiện
        $structure_fingerprint = [];

        // 1. LỌC SECTION
        if (!empty($data->sectionlist)) {
            $filtered_sections = [];
            foreach ($data->sectionlist as $sectionid) {
                $section = $modinfo->get_section_info_by_id($sectionid);
                if (!$section) continue;

                if (\format_adaptive1\utils::check_section_conditions($course, $userid, $section)) {
                    $filtered_sections[] = $sectionid;
                    $structure_fingerprint[] = 's' . $sectionid; // Ghi nhớ section này hiện
                }
            }
            $data->sectionlist = $filtered_sections;
        }

        // 2. TẠO STATEKEY ĐỘNG (QUAN TRỌNG NHẤT)
        // Đây là bước thay thế việc gọi AJAX.
        // Chúng ta băm (hash) danh sách ID ở trên thành 1 chuỗi.
        // Nếu danh sách ID thay đổi -> Hash thay đổi -> Statekey thay đổi.
        // -> Trình duyệt TỰ ĐỘNG cập nhật giao diện mà không cần tương tác.
        
        $fingerprint_string = implode('_', $structure_fingerprint);
        
        // Nối thêm UserID để cache của user này không dính sang user kia
        $data->statekey = $data->statekey . '_u' . $userid . '_' . md5($fingerprint_string);

        return $data;
    }
}