import cv2
import numpy as np

# Create a simple image with a face (just for testing)
img = np.zeros((300, 300, 3), dtype=np.uint8)

# Draw a simple face
cv2.circle(img, (150, 100), 40, (0, 255, 255), -1)  # Face
cv2.circle(img, (130, 90), 5, (0, 0, 0), -1)        # Left eye
cv2.circle(img, (170, 90), 5, (0, 0, 0), -1)        # Right eye
cv2.ellipse(img, (150, 120), (20, 10), 0, 0, 180, (0, 0, 0), 2)  # Smile

# Save the image
cv2.imwrite('test_face.jpg', img)
print("Test image created: test_face.jpg")

# Now test face detection
face_cascade = cv2.CascadeClassifier('/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml')
gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
faces = face_cascade.detectMultiScale(gray, 1.3, 5)
print(f"Faces detected: {len(faces)}")

# Draw rectangles around faces
for (x,y,w,h) in faces:
    cv2.rectangle(img,(x,y),(x+w,y+h),(255,0,0),2)

cv2.imwrite('test_face_detected.jpg', img)
print("Detection result saved as: test_face_detected.jpg")
