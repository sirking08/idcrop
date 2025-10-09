#!/usr/bin/env python3
import pytesseract
from PIL import Image
import os

# Set the path to the tesseract executable
pytesseract.pytesseract.tesseract_cmd = '/usr/bin/tesseract'

def test_ocr():
    # Create a test image with some text
    from PIL import Image, ImageDraw, ImageFont
    
    # Create a blank image with white background
    img = Image.new('RGB', (400, 200), color='white')
    d = ImageDraw.Draw(img)
    
    # Try to use a default font, fall back to any available font if that fails
    try:
        font = ImageFont.truetype("Arial", 24)
    except:
        try:
            font = ImageFont.truetype("DejaVuSans.ttf", 24)
        except:
            font = ImageFont.load_default()
    
    # Draw some text
    d.text((10, 10), "Test ID: 123-4567-8901", fill="black", font=font)
    
    # Save the test image
    test_image_path = 'test_ocr_image.jpg'
    img.save(test_image_path)
    print(f"Created test image at: {os.path.abspath(test_image_path)}")
    
    # Try to read the text back using Tesseract
    try:
        text = pytesseract.image_to_string(Image.open(test_image_path))
        print("Tesseract output:")
        print(text)
        print("Test completed successfully!")
    except Exception as e:
        print(f"Error running Tesseract: {e}")
        print("Please make sure Tesseract OCR is properly installed and in your system PATH.")
        print("On Ubuntu/Debian, you can install it with: sudo apt-get install tesseract-ocr")

if __name__ == "__main__":
    test_ocr()
