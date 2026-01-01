# Tên Dự Án

Nghiên cứu, bổ sung khả năng điều hướng tiến trình học tập của sinh viên trên hệ LMS Moodle

## Tài liệu chi tiết

Tài liệu được trình bày chi tiết tại: [Tài liệu](https://docs.google.com/document/d/1KSeo6XedCPZmHQBWRCpEO1Y22fdH8D-vUGrKrUqg_5I)

## Cách triển khai Moodle & MariaDB trên local
Hệ thống LMS được đóng gói sử dụng Docker Compose với image từ Bitnami.
* Cơ sở dữ liệu MariaDB được thiết lập qua cổng 3306
* Ứng dụng LMS Moodle được thiết lập trên cổng HTTP 8080, cổng HTTPS 8443

Mở terminal tại thư mục moodle và chạy lệnh
```
docker-compose up -d
```

Sau khi khởi động thành công, truy cập Moodle tại địa chỉ:
* URL: http://localhost:8080
* Tài khoản quản trị mặc định: user
* Mật khẩu mặc định: bitnami

Dữ liệu của Moodle và Database được lưu trữ bền vững (persist) trong các Docker Volumes:
* mariadb_data: Dữ liệu database.
* moodle_data: Source code của Moodle.
* moodledata_data: File upload và session data của Moodle.

## Cách cài đặt plugin vào Moodle

Để cài đặt các plugin tự phát triển vào hệ thống Moodle đang chạy trên Docker, thực hiện các bước sau:

### Bước 1: Copy mã nguồn vào Container
Mở Terminal (CMD hoặc PowerShell) và chạy lần lượt các lệnh sau để copy folder plugin vào đúng vị trí trong container Moodle:


**1. Copy Plugin loại Local:**
```bash
docker cp (đường dẫn thư mục lưu project)\local\autograding moodle-app:/bitnami/moodle/local/
docker cp (đường dẫn thư mục lưu project)\local\autorestrict moodle-app:/bitnami/moodle/local/
```
**2. Copy Plugin loại Availability Condition:**
```bash
docker cp (đường dẫn thư mục lưu project)\availability\condition\diffcomplete moodle-app:/bitnami/moodle/availability/condition/
docker cp (đường dẫn thư mục lưu project)\availability\condition\sectioncomplete moodle-app:/bitnami/moodle/availability/condition/
docker cp (đường dẫn thư mục lưu project)\availability\condition\sectiongrade moodle-app:/bitnami/moodle/availability/condition/
```

## Cách thiết lập server OCR

Mở Terminal tại thư mục ocr-server, gõ lệnh sau để tạo thư mục lưu model (nếu chưa có):
```
mkdir models
```

Chạy lệnh sau để đóng gói server
```
docker build -t ocr_gpu_server .
```

Chạy server trên Docker (Server OCR được thiết lập trên cổng 8001)
```
docker run -d --name ocr_server --gpus all -p 8001:8001 -v "%cd%\models:/root/.EasyOCR/model" ocr_gpu_server
```

## Cách thiết lập server LLM sử dụng Ollama

Mở Terminal tại thư mục ocr-server, gõ lệnh sau để triển khai server LLM riêng (Server LLM được thiết lập trên cổng 11434)
```
docker run -d --gpus all -v ollama_data:/root/.ollama -p 11434:11434 --name llm_server ollama/ollama
```

Tải model Qwen2.5-3B
```
docker exec -it llm_server ollama run qwen2.5:3b
```