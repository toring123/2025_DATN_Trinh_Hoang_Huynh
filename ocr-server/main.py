from fastapi import FastAPI, HTTPException, File, UploadFile, Request
from fastapi.responses import JSONResponse
from pydantic import BaseModel
from typing import List, Optional
import base64
from io import BytesIO
from PIL import Image
import numpy as np
import logging
import os
import fitz

from ocr_engine import reader

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="EasyOCR API – Hỗ trợ tiếng Việt & tiếng Anh",
    description="Nhận ảnh Base64 → trả về văn bản đã OCR",
    version="1.0.0"
)

class ImagePart(BaseModel):
    mimeType: str
    data: str
    filename: Optional[str] = None

class OCRRequest(BaseModel):
    imageParts: List[ImagePart]

class OCRResponse(BaseModel):
    text: str

def base64_to_image(base64_string: str) -> Image.Image:
    try:
        if ";base64," in base64_string:
            base64_string = base64_string.split(";base64,")[1]
        img_data = base64.b64decode(base64_string)
        return Image.open(BytesIO(img_data)).convert("RGB")
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Base64 decode lỗi: {str(e)}")

@app.post("/ocr", response_model=OCRResponse)
async def ocr_endpoint(request: Request):
    """
    Nhận file ảnh qua multipart/form-data.
    Sử dụng Request trực tiếp để chấp nhận mọi tên field (files[0], files[1], image, v.v.)
    """
    
    form = await request.form()
    logger.info(f"Received form data: {form}")
    files = []

    for key, value in form.multi_items():
        if hasattr(value, 'file') and hasattr(value, 'filename'):
            files.append(value)
            logger.info(f"Found file: {key} -> {value.filename}")

    if not files:
        raise HTTPException(status_code=400, detail="Không có file nào được upload (kiểm tra lại tên field gửi lên)")

    all_texts = []

    for idx, file in enumerate(files):
        logger.info(f"Đang xử lý ảnh {idx + 1}/{len(files)} – {file.filename or 'unknown'}")

        try:
            image_bytes = await file.read()
            
            image = Image.open(BytesIO(image_bytes)).convert("RGB")
            image_np = np.array(image)

            result = reader.readtext(
                image_np,
                detail=0,
                paragraph=True,
                width_ths=0.2,
                height_ths=0.2
            )

            text = " ".join(result)
            all_texts.append(text)

        except Exception as e:
            logger.error(f"Lỗi khi OCR ảnh {file.filename}: {str(e)}")
            raise HTTPException(status_code=500, detail=f"OCR lỗi tại ảnh {file.filename}: {str(e)}")

    full_text = " ".join(all_texts)

    return OCRResponse(
        text=full_text.strip()
    )

@app.post("/ocr-pdf", response_model=OCRResponse)
async def ocr_pdf_endpoint(file: UploadFile = File(...)):
    """
    Xử lý file PDF: render từng trang thành ảnh và OCR
    """
    if not file.filename.lower().endswith('.pdf'):
        raise HTTPException(status_code=400, detail="Chỉ chấp nhận file PDF")
    
    logger.info(f"--> Đang xử lý PDF: {file.filename}")
    
    try:
        pdf_bytes = await file.read()
        
        doc = fitz.open(stream=pdf_bytes, filetype="pdf")
        
        if len(doc) == 0:
            raise HTTPException(status_code=400, detail="PDF có 0 trang")
        
        full_text = ""
        
        for page_num, page in enumerate(doc):
            logger.info(f"Đang xử lý trang {page_num + 1}/{len(doc)}")
            
            mat = fitz.Matrix(2, 2)
            pix = page.get_pixmap(matrix=mat)
            
            img_data = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.h, pix.w, pix.n)
            
            if pix.n == 4:
                img_data = img_data[..., :3]
            
            result = reader.readtext(img_data, detail=0)
            
            page_text = " ".join(result)
            full_text += f"{page_text}"
            logger.info(f"Đã xong trang {page_num + 1}")
        
        doc.close()
        
        return OCRResponse(text=full_text.strip())
        
    except fitz.fitz.FileDataError as e:
        logger.error(f"Lỗi đọc PDF: {str(e)}")
        raise HTTPException(status_code=400, detail=f"File PDF không hợp lệ: {str(e)}")
    except Exception as e:
        logger.error(f"Lỗi xử lý PDF: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Lỗi xử lý PDF: {str(e)}")

@app.get("/")
async def root():
    return {"message": "EasyOCR FastAPI Server đang chạy – POST /ocr với Base64 image"}

@app.get("/health")
async def health():
    return {"status": "healthy"}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="127.0.0.1", port=8001, reload=True)