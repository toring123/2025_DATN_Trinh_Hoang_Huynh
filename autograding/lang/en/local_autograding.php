<?php
declare(strict_types=1);

/**
 * English language strings for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Autograding';
$string['privacy:metadata'] = 'The Autograding plugin does not store any personal data.';

// Form elements.
$string['autograding_header'] = 'Autograding Settings';
$string['autograding_label'] = 'Autograding';
$string['autograding_label_help'] = 'Select the autograding option for this assignment:
* **Not use**: Autograding is disabled for this assignment
* **Grading without answer**: Automatic grading without requiring student answers
* **Grading with text answer**: Automatic grading based on text submissions (requires answer text)
* **Grading with file answer**: Automatic grading based on PDF file submissions (requires PDF upload)';

// Text answer field.
$string['text_answer_label'] = 'Text Answer';
$string['text_answer_label_help'] = 'Enter the expected text answer for automatic grading. This field is required when "Grading with text answer" is selected. The text entered here will be used as the reference answer for comparing student submissions.';
$string['text_answer_required'] = 'Text answer is required when "Grading with text answer" is selected.';

// File answer field.
$string['file_answer_label'] = 'PDF Answer File';
$string['file_answer_label_help'] = 'Upload a PDF file containing the reference answer for automatic grading. This field is required when "Grading with file answer" is selected. The text will be automatically extracted from the PDF and stored for comparison with student submissions. Only PDF files are accepted (max 10MB).';
$string['file_answer_required'] = 'PDF file is required when "Grading with file answer" is selected.';
$string['file_answer_pdf_only'] = 'Only PDF files are accepted for the answer file.';

// Options.
$string['option_notuse'] = 'Not use';
$string['option_without_answer'] = 'Grading without answer';
$string['option_with_text'] = 'Grading with text answer';
$string['option_with_file'] = 'Grading with file answer';

// Gemini API Settings.
$string['gemini_settings_header'] = 'Google Gemini AI Settings';
$string['gemini_settings_desc'] = 'Configure the Google Gemini API for automatic grading. You need a valid API key from Google AI Studio (https://aistudio.google.com/).';
$string['gemini_api_key'] = 'Gemini API Key';
$string['gemini_api_key_desc'] = 'Enter your Google Gemini API key. This key is used to authenticate requests to the Gemini AI service for automatic grading.';
$string['gemini_model'] = 'Gemini Model';
$string['gemini_model_desc'] = 'Select the Gemini model to use for grading. Gemini 2.5 Flash is recommended for balance of speed and quality.';

// AI Provider Settings.
$string['ai_provider_header'] = 'AI Provider Settings';
$string['ai_provider_header_desc'] = 'Configure the AI provider for automatic grading. You can choose between Google Gemini (cloud) or Local Qwen (self-hosted).';
$string['ai_provider'] = 'AI Provider';
$string['ai_provider_desc'] = 'Select the AI provider to use for automatic grading.';
$string['provider_gemini'] = 'Google Gemini (Cloud)';
$string['provider_qwen'] = 'Local Qwen (Self-hosted)';

// System Instruction Settings.
$string['system_instruction'] = 'System Instruction';
$string['system_instruction_desc'] = 'Define the AI persona and behavior for grading. This instruction will be sent to the AI as a system message to guide how it should grade submissions.';
$string['system_instruction_default'] = '
Hãy đóng vai trò là một chuyên gia chấm thi khách quan và nghiêm khắc. Nhiệm vụ của bạn là đánh giá câu trả lời của học sinh dựa trên câu hỏi và đáp án chuẩn được cung cấp.
Yêu cầu xử lý:
1. So sánh kỹ lưỡng ý nghĩa, từ khóa và logic của [BÀI LÀM CỦA HỌC SINH] so với [CÂU HỎI] và [ĐÁP ÁN CHUẨN].
2. Chấm điểm trên thang điểm từ 0 đến 10 (có thể dùng số thập phân).
- 0 điểm: Sai hoàn toàn hoặc không trả lời.
- 10 điểm: Chính xác hoàn toàn, đầy đủ ý như [ĐÁP ÁN CHUẨN].
3. Giải thích ngắn gọn lý do tại sao cho số điểm đó (chỉ ra lỗi sai hoặc phần thiếu nếu có).
';
$string['system_instruction_footer'] = '
QUAN TRỌNG:
- Bạn chỉ được phép trả về kết quả dưới dạng JSON thuần túy.
- Không được thêm bất kỳ văn bản, lời chào, hay định dạng markdown (```json) nào khác vào đầu hoặc cuối.
- Cấu trúc JSON bắt buộc như sau: {\"grade\": <số_điểm>, \"explanation\": \"<lời_giải_thích>\"}";
Ví dụ phản hồi JSON hợp lệ:
{
  "grade": 8.5,
  "explanation": "Câu trả lời đúng về mặt ý chính nhưng thiếu một số chi tiết quan trọng so với đáp án chuẩn."
}';

// Local Qwen Settings.
$string['qwen_settings_header'] = 'Local Qwen AI Settings';
$string['qwen_settings_desc'] = 'Configure the local Qwen AI endpoint for automatic grading. This requires a self-hosted Qwen model with OpenAI-compatible API.';
$string['qwen_endpoint'] = 'Qwen Endpoint URL';
$string['qwen_endpoint_desc'] = 'Enter the URL of your local Qwen API endpoint. Default: http://localhost:11434/v1/chat/completions';
$string['qwen_model'] = 'Qwen Model';
$string['qwen_model_desc'] = 'Enter the Qwen model name to use. Default: qwen2.5-3b-instruct';

// Auto-grading messages.
$string['autograding_disabled'] = 'Auto-grading is not enabled for this assignment.';
$string['autograding_no_api_key'] = 'Gemini API key is not configured. Please contact the administrator.';
$string['autograding_api_error'] = 'Error communicating with Gemini API: {$a}';
$string['autograding_invalid_response'] = 'Invalid response from Gemini API. Could not parse grading result.';
$string['autograding_success'] = 'Assignment automatically graded by AI.';
$string['autograding_no_submission'] = 'No submission content found to grade.';
$string['autograding_no_reference'] = 'No reference answer configured for this assignment.';
$string['autograding_feedback_prefix'] = '[AI Auto-Grading]';

// Adhoc task strings.
$string['task_grade_submission'] = 'Process auto-grading for assignment submission';
$string['ratelimited'] = 'API rate limit exceeded';
$string['locktimeout'] = 'Could not acquire lock for API call';

// Image grading strings (for Qwen provider limitations).
$string['qwen_image_warning'] = '[Note: Image(s) were submitted but could not be processed by Local Qwen. Only text content was graded. For image/handwriting grading, please use Gemini provider.]';
$string['qwen_image_only_error'] = 'This submission contains only image files, but the Local Qwen provider cannot process images. Please switch to Gemini provider for image/handwriting grading, or ask the student to resubmit in text format.';