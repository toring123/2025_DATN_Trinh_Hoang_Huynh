# Tên Dự Án

Nghiên cứu, bổ sung khả năng điều hướng tiến trình học tập của sinh viên trên hệ LMS Moodle

## Tài liệu chi tiết

Tài liệu được trình bày chi tiết tại: [Tài liệu](https://docs.google.com/document/d/1KSeo6XedCPZmHQBWRCpEO1Y22fdH8D-vUGrKrUqg_5I)

## Cách thiết lập server OCR

Mở Terminal tại thư mục ocr-server, gõ lệnh sau để tạo thư mục lưu model (nếu chưa có):
```
mkdir models
```

Chạy lệnh sau để đóng gói server.
```
docker build -t ocr_gpu_server .
```

Chạy server trên Docker
```
docker run -d ^ --name ocr_server ^ --gpus all ^ -p 8001:8001 ^ -v "%cd%\models:/root/.EasyOCR/model" ^ ocr_gpu_server
```