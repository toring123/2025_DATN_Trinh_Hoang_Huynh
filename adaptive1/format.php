<?php
// File: course/format/adaptive1/format.php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');

// 1. DEBUG: Kiểm tra đường dẫn file vật lý
$rendererfile = $CFG->dirroot . '/course/format/adaptive1/classes/output/renderer.php';

if (!file_exists($rendererfile)) {
    // Nếu chạy vào đây -> Bạn đang sai tên thư mục hoặc tên file
    die('<div style="background:red; color:white; padding:20px; margin:20px;">
        <h3>LỖI KHÔNG TÌM THẤY FILE!</h3>
        <p>Moodle đang tìm file tại: <strong>' . $rendererfile . '</strong></p>
        <p>Hãy kiểm tra kỹ lại xem thư mục <strong>classes</strong> và <strong>output</strong> đã viết thường (lowercase) chưa?</p>
        </div>');
}

// 2. DEBUG: Ép load file để xem có lỗi cú pháp hay permission không
try {
    require_once($rendererfile);
} catch (Throwable $e) {
    // Nếu chạy vào đây -> File tồn tại nhưng không đọc được (Lỗi Permission hoặc code sai cú pháp)
    die('<div style="background:red; color:white; padding:20px; margin:20px;">
        <h3>LỖI KHÔNG ĐỌC ĐƯỢC FILE!</h3>
        <p>Lỗi: ' . $e->getMessage() . '</p>
        <p>Gợi ý: Chạy lệnh chown/chmod trên server.</p>
        </div>');
}

// 3. DEBUG: Kiểm tra xem Class có đúng namespace không
$classname = 'format_adaptive1\output\renderer';
if (!class_exists($classname)) {
    // Nếu chạy vào đây -> File đúng, nhưng namespace trong file renderer.php sai
    die('<div style="background:red; color:white; padding:20px; margin:20px;">
        <h3>LỖI SAI NAMESPACE!</h3>
        <p>File đã load được, nhưng không tìm thấy class: <strong>' . $classname . '</strong></p>
        <p>Hãy mở file renderer.php và kiểm tra dòng đầu tiên có phải là: <br>
        <code>namespace format_adaptive1\output;</code> hay không?</p>
        </div>');
}

// --- NẾU QUA ĐƯỢC 3 BƯỚC TRÊN THÌ MỚI CHẠY CODE CHÍNH ---

$format = course_get_format($course);
$course = $format->get_course();
$context = context_course::instance($course->id);

if (($marker >= 0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

course_create_sections_if_missing($course, 0);

// Cách gọi renderer an toàn nhất khi đang debug: Gọi trực tiếp class thay vì qua Factory
$renderer = new \format_adaptive1\output\renderer($PAGE, null);

if (!is_null($displaysection)) {
    $format->set_sectionnum($displaysection);
}

$outputclass = $format->get_output_classname('content');
$widget = new $outputclass($format);

echo $renderer->render($widget);