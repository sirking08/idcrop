import sys
import os
import cv2
from PIL import Image

def detect_and_crop(image_path, output_path):
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
            os.makedirs(os.path.dirname(output_path), exist_ok=True)
            
            # Crop and save
            face_img = img[y1:y2, x1:x2]
            face_pil = Image.fromarray(cv2.cvtColor(face_img, cv2.COLOR_BGR2RGB))
            
            try:
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
    if len(sys.argv) != 3:
        print("Usage: python detect_face.py <input_image> <output_image>")
        sys.exit(1)
        
    image_path = sys.argv[1]
    output_path = sys.argv[2]
    
    if not os.path.exists(image_path):
        print(f"Error: Input file '{image_path}' does not exist")
        sys.exit(1)
        
    success = detect_and_crop(image_path, output_path)
    sys.exit(0 if success else 1)
