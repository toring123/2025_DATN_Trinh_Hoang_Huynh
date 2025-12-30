<?php
declare(strict_types=1);
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Auto grading';
$string['privacy:metadata'] = 'The Auto grading plugin does not store any personal data.';

$string['autograding_header'] = 'Cài đặt chấm điểm tự động';
$string['autograding_label'] = 'Kiểu chấm điểm';
$string['autograding_label_help'] = 'Select the auto grading option for this assignment:
* **Not use**: Auto grading is disabled for this assignment
* **Grading without answer**: Automatic grading without requiring student answers
* **Grading with text answer**: Automatic grading based on text submissions (requires answer text)
* **Grading with file answer**: Automatic grading based on PDF file submissions (requires PDF upload)';

$string['text_answer_label'] = 'Text Answer';
$string['text_answer_label_help'] = 'Enter the expected text answer for automatic grading. This field is required when "Grading with text answer" is selected. The text entered here will be used as the reference answer for comparing student submissions.';
$string['text_answer_required'] = 'Text answer is required when "Grading with text answer" is selected.';

$string['file_answer_label'] = 'Answer File';
$string['file_answer_label_help'] = 'Upload a PDF or image file containing the reference answer for automatic grading. This field is required when "Grading with file answer" is selected. The text will be automatically extracted and stored for comparison with student submissions. Accepted formats: PDF, JPG, JPEG, PNG, GIF, WEBP (max 10MB).';
$string['file_answer_required'] = 'Answer file is required when "Grading with file answer" is selected.';
$string['file_answer_pdf_only'] = 'Only PDF files are accepted for the answer file.';
$string['file_answer_invalid_type'] = 'Only PDF and image files (jpg, jpeg, png, gif, webp) are accepted.';

$string['option_notuse'] = 'Not use';
$string['option_without_answer'] = 'Grading without answer';
$string['option_with_text'] = 'Grading with text answer';
$string['option_with_file'] = 'Grading with file answer';

$string['gemini_settings_header'] = 'Google Gemini AI Settings';
$string['gemini_settings_desc'] = 'Configure the Google Gemini API for automatic grading. You need a valid API key from Google AI Studio (https://aistudio.google.com/).';
$string['gemini_api_key'] = 'Gemini API Key';
$string['gemini_api_key_desc'] = 'Enter your Google Gemini API key. This key is used to authenticate requests to the Gemini AI service for automatic grading.';
$string['gemini_model'] = 'Gemini Model';
$string['gemini_model_desc'] = 'Select the Gemini model to use for grading. Gemini 2.5 Flash is recommended for balance of speed and quality.';

$string['ai_provider_header'] = 'AI Provider Settings';
$string['ai_provider_header_desc'] = 'Configure the AI provider for automatic grading. You can choose between Google Gemini (cloud) or Local Qwen (self-hosted).';
$string['ai_provider'] = 'AI Provider';
$string['ai_provider_desc'] = 'Select the AI provider to use for automatic grading.';
$string['provider_gemini'] = 'Google Gemini (Cloud)';
$string['provider_qwen'] = 'Local Qwen (Self-hosted)';

$string['system_instruction'] = 'System Instruction';
$string['system_instruction_desc'] = 'Define the AI persona and behavior for grading. This instruction will be sent to the AI as a system message to guide how it should grade submissions.';
$string['system_instruction_default'] = '
Bạn là một hệ thống chấm thi tự động chuyên nghiệp, khách quan và chính xác.
Nhiệm vụ: Đánh giá [BÀI LÀM CỦA HỌC SINH] dựa trên [CÂU HỎI] và [ĐÁP ÁN CHUẨN].
QUY TẮC CHẤM ĐIỂM (Thang 0 - 10):
1. Phân tích ngữ nghĩa (Semantic Analysis): Tập trung vào ý chính và từ khóa quan trọng. Chấp nhận các cách diễn đạt khác nhau nếu ý nghĩa tương đương đáp án chuẩn.
2. Xử lý lỗi nhỏ: Bỏ qua lỗi chính tả hoặc lỗi định dạng (xuống dòng, dấu câu) nếu không làm thay đổi ý nghĩa câu trả lời.
3. Thang điểm chi tiết:
   - 0-2 điểm: Sai hoàn toàn, lạc đề hoặc không trả lời.
   - 3-5 điểm: Đúng một phần nhỏ, nhưng thiếu nhiều ý quan trọng hoặc hiểu sai bản chất.
   - 6-8 điểm: Hiểu bài, trả lời khá đúng nhưng thiếu sót nhỏ hoặc diễn đạt chưa chặt chẽ.
   - 9-10 điểm: Chính xác hoàn toàn, đầy đủ các ý trong đáp án chuẩn.
4. Lưu ý: Nếu [Câu Hỏi] có nhiều câu hỏi, hãy chấm điểm theo từng câu với mức điểm tối đa của từng câu, từng ý được ghi sẵn trong [Đáp Án Chuẩn].
';
$string['system_instruction_footer'] = '
YÊU CẦU ĐẦU RA (JSON Strict Mode):
- Chỉ trả về duy nhất một chuỗi JSON hợp lệ.
- Không bao gồm markdown.
- Cấu trúc bắt buộc:
{
  "grade": <số_thực_từ_0_đến_10>,
  "explanation": "<Nhận xét chi tiết. Sử dụng ký tự \\n để xuống dòng giữa các ý, Nếu đề bài có nhiều câu thì hãy giải thích cho từng câu và ngăn cách bởi ký tự \\n. Ví dụ: \"Ý 1 đúng.\\nTuy nhiên ý 2 còn thiếu.\">"
}';

$string['qwen_settings_header'] = 'Local Qwen AI Settings';
$string['qwen_settings_desc'] = 'Configure the local Qwen AI endpoint for automatic grading. This requires a self-hosted Qwen model with OpenAI-compatible API.';
$string['qwen_endpoint'] = 'Qwen Endpoint URL';
$string['qwen_endpoint_desc'] = 'Enter the URL of your local Qwen API endpoint. Default: http://localhost:11434/v1/chat/completions';
$string['qwen_model'] = 'Qwen Model';
$string['qwen_model_desc'] = 'Select the Qwen model to use for grading.';

$string['refresh_page_for_models'] = '(Save settings and refresh page to update available models from API)';

$string['autograding_disabled'] = 'Auto-grading is not enabled for this assignment.';
$string['autograding_no_api_key'] = 'Gemini API key is not configured. Please contact the administrator.';
$string['autograding_api_error'] = 'Error communicating with Gemini API: {$a}';
$string['autograding_invalid_response'] = 'Invalid response from Gemini API. Could not parse grading result.';
$string['autograding_success'] = 'Assignment automatically graded by AI.';
$string['autograding_no_submission'] = 'No submission content found to grade.';
$string['autograding_no_reference'] = 'No reference answer configured for this assignment.';
$string['autograding_feedback_prefix'] = '[AI Auto-Grading]';

$string['task_grade_submission'] = 'Process auto-grading for assignment submission';
$string['ratelimited'] = 'API rate limit exceeded';
$string['locktimeout'] = 'Could not acquire lock for API call';

$string['qwen_image_warning'] = '[Note: Image(s) were submitted but could not be processed by Local Qwen. Only text content was graded. For image/handwriting grading, please use Gemini provider.]';
$string['qwen_image_only_error'] = 'This submission contains only image files, but the Local Qwen provider cannot process images. Please switch to Gemini provider for image/handwriting grading, or ask the student to resubmit in text format.';

$string['ocr_settings_header'] = 'OCR Server Settings';
$string['ocr_settings_desc'] = 'Configure the OCR server for extracting text from PDFs and images before grading. Leave empty to use built-in parsers (for PDF/DOCX) or Vision API (for images).';
$string['ocr_server_url'] = 'OCR Server URL';
$string['ocr_server_url_desc'] = 'The URL of the OCR server (e.g., http://127.0.0.1:8001). When configured, PDFs and images will be sent to this server for text extraction.';
$string['ocr_api_error'] = 'Error communicating with OCR server: {$a}';

$string['no_model_available'] = '--No model--';
$string['fetching_models'] = 'Fetching models...';

$string['grading_progress_title'] = 'AI Grading Progress';
$string['status_pending'] = 'Pending';
$string['status_processing'] = 'Processing';
$string['status_success'] = 'Success';
$string['status_failed'] = 'Failed';
$string['student'] = 'Student';
$string['status'] = 'Status';
$string['attempts'] = 'Attempts';
$string['last_updated'] = 'Last Updated';
$string['error_message'] = 'Error Message';
$string['actions'] = 'Actions';
$string['retry'] = 'Retry';
$string['retrying'] = 'Retrying...';
$string['grade_manually'] = 'Grade manually';
$string['view_grade'] = 'View grade';
$string['back_to_grading'] = 'Back to Grading';
$string['no_submissions_yet'] = 'No submissions have been queued for AI grading yet.';
$string['auto_refresh_info'] = 'This page auto-refreshes every 10 seconds.';

$string['task_send_failure_digest'] = 'Send daily digest of failed grading attempts';

$string['messageprovider:grading_failure'] = 'Grading failure notifications';
$string['digest_subject'] = 'AI Grading Failures: {$a}';
$string['digest_small'] = '{$a} submissions failed AI grading';
$string['digest_message'] = 'AI grading failed for {$a->count} submission(s) in "{$a->assignmentname}" ({$a->coursename}).

Students affected: {$a->students}{$a->more}

View progress and retry: {$a->url}';
$string['and_more'] = ' and {$a} more';

$string['autograding:viewprogress'] = 'View AI grading progress';
$string['autograding:manage'] = 'Manage autograding settings';

$string['servererror'] = 'Server error occurred';

$string['check_connection'] = 'Check Connection';
$string['checking_connection'] = 'Checking...';
$string['connection_success'] = 'Connection successful';
$string['connection_failed'] = 'Connection failed';
$string['invalid_response'] = 'Invalid response from server';
$string['ollama_connection_info'] = 'Models available: {$a}';
$string['ocr_connection_info'] = 'OCR server is running';