import cv2
import os
import numpy as np
from mtcnn import MTCNN

# Initialize MTCNN face detector
detector = MTCNN()

# Set dataset path
DATASET_FOLDER = "dataset_train"
os.makedirs(DATASET_FOLDER, exist_ok=True)

def enhance_image(img):
    """Apply CLAHE to improve image contrast."""
    img_yuv = cv2.cvtColor(img, cv2.COLOR_RGB2YUV)
    img_yuv[:,:,0] = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8)).apply(img_yuv[:,:,0])
    return cv2.cvtColor(img_yuv, cv2.COLOR_YUV2RGB)

def capture_images(person_name, total_images=100):
    """Captures images of a person for DeepFace recognition."""
    person_folder = os.path.join(DATASET_FOLDER, person_name)
    os.makedirs(person_folder, exist_ok=True)

    cap = cv2.VideoCapture(0)
    if not cap.isOpened():
        print("Error: Could not access the webcam.")
        return

    image_count = 0
    while image_count < total_images:
        ret, frame = cap.read()
        if not ret:
            break

        # Detect faces
        faces = detector.detect_faces(frame)
        if faces:
            x, y, w, h = faces[0]['box']
            face_crop = frame[y:y+h, x:x+w]  # Crop the detected face

            # Convert to RGB (DeepFace requirement)
            face_rgb = cv2.cvtColor(face_crop, cv2.COLOR_BGR2RGB)

            # Enhance contrast using CLAHE
            face_enhanced = enhance_image(face_rgb)

            # Resize to 112x112 (DeepFace model requirement)
            face_resized = cv2.resize(face_enhanced, (112, 112))

            # Save the processed face
            image_path = os.path.join(person_folder, f"{person_name}_{image_count+1:03d}.jpg")
            cv2.imwrite(image_path, cv2.cvtColor(face_resized, cv2.COLOR_RGB2BGR))

            image_count += 1
            print(f"✅ Captured Image {image_count}/{total_images}")

        # Display frame with bounding box
        for face in faces:
            x, y, w, h = face['box']
            cv2.rectangle(frame, (x, y), (x+w, y+h), (0, 255, 0), 2)

        cv2.imshow("Capturing Images - Press 'q' to Quit", frame)
        if cv2.waitKey(100) & 0xFF == ord("q"):
            break

    cap.release()
    cv2.destroyAllWindows()
    print(f"✅ Captured {image_count} images for {person_name} in '{person_folder}'")

if _name_ == "_main_":
    person_name = input("Enter person's name: ").strip()
    capture_images(person_name)