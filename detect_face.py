import sys
import os
import re
import cv2
import pytesseract
from PIL import Image

# Set the path to the tesseract executable
pytesseract.pytesseract.tesseract_cmd = '/usr/bin/tesseract'

# Configure Tesseract path if needed
# pytesseract.pytesseract.tesseract_cmd = r'/usr/bin/tesseract'

def extract_id_number(image_path):
    """
    Extract ID number from image using OCR
    Returns the extracted ID number or None if not found
    """
    try:
        # Read image using OpenCV
        img = cv2.imread(image_path)
        if img is None:
            print(f"Error: Could not read image at {image_path}")
            return None
            
        # Convert to grayscale for better OCR
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # Apply thresholding to get binary image
        _, thresh = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        
        # Use Tesseract to extract text
        text = pytesseract.image_to_string(thresh, config='--psm 6')
        
        # Look for ID number patterns (modify regex based on your ID format)
        # This regex looks for common ID number patterns like:
        # - 123-4567-8901
        # - 123 4567 8901
        # - 1234567890
        id_patterns = [
            r'\b\d{3}[\s-]?\d{4}[\s-]?\d{4}\b',  # 123-4567-8901 or 123 4567 8901 or 12345678901
            r'\b\d{10,12}\b'  # 10-12 digit number
        ]
        
        for pattern in id_patterns:
            match = re.search(pattern, text)
            if match:
                # Clean up the matched ID (remove spaces, dashes)
                id_number = re.sub(r'[\s-]', '', match.group(0))
                print(f"Extracted ID: {id_number}")
                return id_number
                
        print("No ID number found in the image")
        return None
        
    except Exception as e:
        print(f"Error in OCR processing: {e}")
        return None

def detect_and_crop(image_path, output_dir, use_id_as_filename=False):
    # If use_id_as_filename is True, try to extract ID and use it as filename
    if use_id_as_filename:
        id_number = extract_id_number(image_path)
        if id_number:
            # Create output path with ID as filename
            ext = os.path.splitext(os.path.basename(image_path))[1] or '.jpg'
            output_path = os.path.join(output_dir, f"{id_number}{ext}")
        else:
            # Fallback to original behavior if no ID found
            output_path = os.path.join(output_dir, os.path.basename(image_path))
    else:
        output_path = os.path.join(output_dir, os.path.basename(image_path))
    # Load image
    img = cv2.imread(image_path)
    if img is None:
        print(f"Error: Could not read image at {image_path}")
        return False

    # Convert to grayscale
    try:
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    except cv2.error as e:
        print(f"Error converting image to grayscale: {e}")
        return False

    # Try to find the Haar Cascade file
    cascade_paths = [
        '/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml',
        '/usr/local/share/opencv4/haarcascades/haarcascade_frontalface_default.xml',
        '/usr/local/share/opencv/haarcascades/haarcascade_frontalface_default.xml',
        'haarcascade_frontalface_default.xml'
    ]
    
    face_cascade = None
    for path in cascade_paths:
        if os.path.exists(path):
            face_cascade = cv2.CascadeClassifier(path)
            break
    
    if face_cascade is None:
        print("Error: Could not find Haar Cascade classifier file")
        return False

    try:
        # Detect faces
        faces = face_cascade.detectMultiScale(gray, 1.3, 5)

        if len(faces) == 0:
            print("No face detected")
            return False

        for (x, y, w, h) in faces:
            # Add padding to make it square (like ID photos)
            padding = int(0.3 * h)
            x1 = max(0, x - padding)
            y1 = max(0, y - padding)
            x2 = min(img.shape[1], x + w + padding)
            y2 = min(img.shape[0], y + h + padding)

            # Ensure output directory exists
            os.makedirs(output_dir, exist_ok=True)
            
            # Generate output path
            if use_id_as_filename:
                # Try to extract ID from image
                id_number = extract_id_number(image_path)
                if id_number:
                    ext = os.path.splitext(os.path.basename(image_path))[1] or '.jpg'
                    output_path = os.path.join(output_dir, f"{id_number}{ext}")
                else:
                    # Fallback to original filename if no ID found
                    output_path = os.path.join(output_dir, os.path.basename(image_path))
            
            # Ensure we don't overwrite existing files
            counter = 1
            original_output_path = output_path
            while os.path.exists(output_path):
                name, ext = os.path.splitext(original_output_path)
                output_path = f"{name}_{counter}{ext}"
                counter += 1
            
            # Crop and save
            face_img = img[y1:y2, x1:x2]
            face_pil = Image.fromarray(cv2.cvtColor(face_img, cv2.COLOR_BGR2RGB))
            
            try:
                # First try with the provided path
                face_pil.save(output_path)
                print("Face cropped and saved to:", output_path)
                return True  # Success
            except Exception as e:
                print(f"Error saving image to {output_path}: {e}")
                # Try with absolute path if relative path fails
                try:
                    abs_path = os.path.abspath(output_path)
                    os.makedirs(os.path.dirname(abs_path), exist_ok=True)
                    face_pil.save(abs_path)
                    print(f"Successfully saved to absolute path: {abs_path}")
                    return True
                except Exception as e2:
                    print(f"Failed to save with absolute path {abs_path}: {e2}")
                    return False
            
    except Exception as e:
        print(f"Error during face detection: {e}")
        return False

if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description='Detect and crop faces from images with optional OCR for ID extraction')
    parser.add_argument('input_image', help='Path to the input image file')
    parser.add_argument('output_dir', help='Directory to save the output image')
    parser.add_argument('--use-id', action='store_true', help='Extract ID from image and use it as filename')
    
    args = parser.parse_args()
    
    if not os.path.exists(args.input_image):
        print(f"Error: Input file '{args.input_image}' does not exist")
        sys.exit(1)
        
    success = detect_and_crop(args.input_image, args.output_dir, args.use_id)
    sys.exit(0 if success else 1)
