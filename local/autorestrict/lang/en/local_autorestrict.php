<?php
$string['pluginname'] = 'Tự động hạn chế truy cập';
$string['autorestrict:manage'] = 'Quản lý cài đặt tự động hạn chế';

$string['course_settings_desc'] = 'Cấu hình cài đặt hạn chế tự động cho khóa học này. Khi bạn lưu, các hạn chế sẽ được tự động áp dụng cho tất cả các hoạt động dựa trên các cài đặt bên dưới.';
$string['settings_saved'] = 'Cài đặt đã được lưu thành công.';

$string['section_settings'] = 'Cài đặt tiến trình chương';

$string['enabled'] = 'Bật tự động hạn chế';
$string['enabled_help'] = 'Khi được bật, các hạn chế sẽ được tự động áp dụng cho tất cả các hoạt động. Khi tắt, tất cả các hạn chế sẽ bị xóa.';

$string['hide_completely'] = 'Ẩn hoàn toàn khi bị hạn chế';
$string['hide_completely_help'] = 'Khi được bật, các hoạt động sẽ bị ẩn hoàn toàn đối với học viên không đáp ứng yêu cầu. Khi tắt, các hoạt động sẽ hiển thị mờ đi kèm với thông báo giải thích các yêu cầu.';

$string['require_previous_section'] = 'Yêu cầu hoàn thành chương trước';
$string['require_previous_section_help'] = 'Yêu cầu hoàn thành các hoạt động trong chương trước trước khi truy cập các hoạt động trong chương hiện tại.';

$string['min_section_completions'] = 'Số lượng hoàn thành chương tối thiểu';
$string['min_section_completions_help'] = 'Số lượng hoạt động tối thiểu phải hoàn thành trong chương trước.';

$string['require_previous_grade'] = 'Yêu cầu điểm chương trước';
$string['require_previous_grade_help'] = 'Yêu cầu điểm trung bình tối thiểu trong chương trước.';

$string['min_section_grade'] = 'Điểm chương tối thiểu (%)';
$string['min_section_grade_help'] = 'Điểm trung bình tối thiểu cần thiết trong chương trước.';

$string['require_difficulty_progression'] = 'Yêu cầu tiến trình độ khó';
$string['require_difficulty_progression_help'] = 'Yêu cầu hoàn thành các cấp độ khó dễ hơn trước khi truy cập các cấp độ khó hơn.';

$string['difficulty_settings'] = 'Tiến trình độ khó toàn khóa học';
$string['difficulty_settings_desc'] = 'Cấu hình số lượng hoàn thành cần thiết trên toàn bộ khóa học để mở khóa từng cấp độ khó.';

$string['min_diff1_for_diff2'] = 'Số lượng hoàn thành Diff1 để mở khóa Diff2 (khóa học)';
$string['min_diff1_for_diff3'] = 'Số lượng hoàn thành Diff1 để mở khóa Diff3 (khóa học)';
$string['min_diff2_for_diff3'] = 'Số lượng hoàn thành Diff2 để mở khóa Diff3 (khóa học)';
$string['min_diff1_for_diff4'] = 'Số lượng hoàn thành Diff1 để mở khóa Diff4 (khóa học)';
$string['min_diff2_for_diff4'] = 'Số lượng hoàn thành Diff2 để mở khóa Diff4 (khóa học)';
$string['min_diff3_for_diff4'] = 'Số lượng hoàn thành Diff3 để mở khóa Diff4 (khóa học)';

$string['section_difficulty_settings'] = 'Tiến trình độ khó theo chương';
$string['section_difficulty_settings_desc'] = 'Cấu hình số lượng hoàn thành cần thiết trong cùng một chương để mở khóa từng cấp độ khó.';

$string['section_min_diff1_for_diff2'] = 'Số lượng hoàn thành Diff1 để mở khóa Diff2 (chương)';
$string['section_min_diff1_for_diff3'] = 'Số lượng hoàn thành Diff1 để mở khóa Diff3 (chương)';
$string['section_min_diff2_for_diff3'] = 'Số lượng hoàn thành Diff2 để mở khóa Diff3 (chương)';
$string['section_min_diff1_for_diff4'] = 'Số lượng hoàn thành Diff1 để mở khóa Diff4 (chương)';
$string['section_min_diff2_for_diff4'] = 'Số lượng hoàn thành Diff2 để mở khóa Diff4 (chương)';
$string['section_min_diff3_for_diff4'] = 'Số lượng hoàn thành Diff3 để mở khóa Diff4 (chương)';

$string['error_negative'] = 'Giá trị không thể là số âm.';
$string['error_grade_range'] = 'Điểm phải nằm trong khoảng từ 0 đến 100.';

$string['auto_applied'] = 'Các hạn chế đã được tự động áp dụng: {$a->applied} hoạt động được cập nhật, {$a->skipped} bị bỏ qua.';
$string['auto_cleared'] = 'Các hạn chế đã được tự động xóa: {$a->modules} hoạt động, {$a->sections} chương.';

$string['bulk_difficulty'] = 'Thiết lập độ khó hàng loạt';
$string['bulk_difficulty_desc'] = 'Chọn các hoạt động và gán cấp độ khó. Các hoạt động có nhiều thẻ độ khó sẽ sử dụng cấp độ cao nhất.';
$string['filter_section'] = 'Lọc theo chương';
$string['all_sections'] = 'Tất cả các chương';
$string['selectall'] = 'Chọn tất cả';
$string['deselectall'] = 'Bỏ chọn tất cả';
$string['set_to'] = 'Đặt độ khó thành';
$string['no_difficulty'] = 'Không có độ khó (xóa thẻ)';
$string['diff1'] = 'Độ khó 1 (Dễ)';
$string['diff2'] = 'Độ khó 2 (Trung bình)';
$string['diff3'] = 'Độ khó 3 (Khó)';
$string['diff4'] = 'Độ khó 4 (Chuyên gia)';
$string['apply'] = 'Áp dụng';
$string['type'] = 'Loại';
$string['current_difficulty'] = 'Độ khó hiện tại';
$string['no_activities'] = 'Không tìm thấy hoạt động nào trong khóa học này.';
$string['no_activities_selected'] = 'Không có hoạt động nào được chọn.';
$string['bulk_diff_set'] = 'Độ khó đã được thiết lập cho {$a->success} hoạt động.';
$string['bulk_diff_cleared'] = 'Độ khó đã được xóa cho {$a->success} hoạt động.';
$string['manage_difficulty'] = 'Quản lý thẻ độ khó';
