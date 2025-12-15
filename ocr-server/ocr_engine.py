import easyocr
from threading import Lock

class OCRSingleton:
    _instance = None
    _lock = Lock()

    def __new__(cls, languages=['vi', 'en'], gpu=True):
        if cls._instance is None:
            with cls._lock:
                if cls._instance is None:
                    print("Đang khởi tạo EasyOCR Reader (chỉ chạy một lần)...")
                    cls._instance = easyocr.Reader(languages, gpu=gpu)
        return cls._instance

reader = OCRSingleton(['vi', 'en'], gpu=True)