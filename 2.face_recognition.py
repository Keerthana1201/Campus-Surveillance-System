import os
import cv2
import numpy as np
import logging
import mysql.connector
from deepface import DeepFace
from mtcnn import MTCNN
from sklearn.metrics.pairwise import cosine_similarity
from datetime import datetime, timedelta

# Configure logging
logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")

# MySQL Database Connection
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="campusIntel"
)
cursor = conn.cursor()

# Paths
DATASET_FOLDER = r"C:\Users\Apple\Downloads\datasets"
CONFIDENCE_THRESHOLD = 0.99
SIMILARITY_THRESHOLD = 0.9
TIME_THRESHOLD_MINUTES = 2  # Ignore re-entry within 2 minutes

# Initialize MTCNN for face detection
detector = MTCNN()

# Movement tracking
movement_tracker = {}

def preprocess_face(face_crop):
    """Preprocess the face image before embedding extraction."""
    try:
        face_resized = cv2.resize(face_crop, (112, 112))
        face_norm = np.array(face_resized, dtype=np.float32) / 255.0
        return face_norm
    except Exception as e:
        logging.error(f"❌ Error in preprocessing: {e}")
        return None

def extract_embedding(face_image):
    """Extract facial embedding using DeepFace's ArcFace model."""
    try:
        temp_path = "temp_face.jpg"
        cv2.imwrite(temp_path, (face_image * 255).astype(np.uint8))
        result = DeepFace.represent(img_path=temp_path, model_name="ArcFace", enforce_detection=False,
                                    detector_backend="opencv")
        if isinstance(result, list) and len(result) > 0 and "embedding" in result[0]:
            embedding = np.array(result[0]["embedding"], dtype=np.float32)
            return embedding / np.linalg.norm(embedding)
    except Exception as e:
        logging.error(f"❌ Error extracting embedding: {e}")
    return None

def create_dataset_embeddings():
    """Create a dataset of embeddings from stored images."""
    dataset = {}
    for person in os.listdir(DATASET_FOLDER):
        person_path = os.path.join(DATASET_FOLDER, person)
        if os.path.isdir(person_path):
            for img in os.listdir(person_path):
                if img.lower().endswith(('jpg', 'png', 'jpeg')):
                    img_path = os.path.join(person_path, img)
                    img_array = cv2.imread(img_path)

                    if img_array is None:
                        logging.warning(f"⚠ Could not load {img_path}. Skipping.")
                        continue

                    processed_face = preprocess_face(img_array)
                    if processed_face is None:
                        continue

                    embedding = extract_embedding(processed_face)
                    if embedding is not None:
                        dataset[img_path] = (embedding, person)
    return dataset

# Load dataset embeddings
dataset = create_dataset_embeddings()
logging.info("✅ Dataset embeddings computed.")

def should_log_entry(name):
    """
    Checks if the last entry for a given name was within the last TIME_THRESHOLD_MINUTES.
    Returns True if logging is allowed, False otherwise.
    """
    try:
        query = """SELECT timestamp FROM EntryExitLog WHERE name = %s ORDER BY timestamp DESC LIMIT 1"""
        cursor.execute(query, (name,))
        result = cursor.fetchone()

        if result:
            last_entry_time = result[0]
            current_time = datetime.now()

            # Calculate time difference
            time_difference = current_time - last_entry_time
            if time_difference < timedelta(minutes=TIME_THRESHOLD_MINUTES):
                logging.info(f"🕒 Skipping duplicate entry for {name} (Last seen {time_difference.seconds} sec ago)")
                return False  # Skip insert
        return True  # Proceed with insert
    except mysql.connector.Error as err:
        logging.error(f"❌ MySQL Error: {err}")
        return True  # Default to inserting in case of error

def log_entry_exit(name, move_type, similarity, image_path):
    """Insert entry/exit record into MySQL database if allowed."""
    try:
        if not should_log_entry(name):
            return  # Skip logging if recent entry exists

        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        # Convert NumPy float32 to native Python float
        similarity = float(similarity)

        sql = """INSERT INTO EntryExitLog (name, timestamp, Move_type, similarity, image_path)
                 VALUES (%s, %s, %s, %s, %s)"""
        values = (name, timestamp, move_type, similarity, image_path)
        
        cursor.execute(sql, values)
        conn.commit()
        logging.info(f"✅ Logged {move_type} for {name} at {timestamp}")
    except mysql.connector.Error as err:
        logging.error(f"❌ MySQL Error: {err}")

def process_frame(frame):
    """Process each frame to detect multiple faces and track movement."""
    if frame is None or frame.size == 0:
        logging.warning("⚠ Skipping empty or invalid frame.")
        return

    faces = detector.detect_faces(frame)
    logging.info(f"🔍 Detected {len(faces)} faces in the frame.")

    for i, face in enumerate(faces):
        confidence = face.get("confidence", 0)
        if confidence < CONFIDENCE_THRESHOLD:
            continue

        x, y, w, h = face['box']
        if w <= 0 or h <= 0:
            continue

        x_center = x + w // 2
        face_crop = frame[y:y + h, x:x + w]

        if face_crop.size == 0:
            continue

        processed_face = preprocess_face(face_crop)
        if processed_face is None:
            continue

        embedding = extract_embedding(processed_face)
        if embedding is None:
            continue

        matched_person_name = "Unknown"
        max_sim = 0.0
        matched_image_path = None

        # Compare with dataset
        for img_path, (stored_embedding, person_name) in dataset.items():
            sim = cosine_similarity([embedding], [stored_embedding])[0][0]
            if sim > max_sim and sim >= SIMILARITY_THRESHOLD:
                max_sim = sim
                matched_person_name = person_name
                matched_image_path = img_path
                break  # Stop searching after first strong match

        logging.info(f"🔍 Face {i + 1}: Detected {matched_person_name} | Similarity: {max_sim:.2f}")

        if matched_person_name != "Unknown":
            move_type = "Stationary"
            if matched_person_name not in movement_tracker:
                movement_tracker[matched_person_name] = {"last_x": x_center}
                move_type = "Entry"
            else:
                last_x = movement_tracker[matched_person_name]["last_x"]
                movement_tracker[matched_person_name]["last_x"] = x_center

                if x_center > last_x:
                    move_type = "Exit"
                elif x_center < last_x:
                    move_type = "Entry"

            log_entry_exit(matched_person_name, move_type, max_sim, matched_image_path)

def recognize_faces(video_path):
    """Process a video file for face recognition."""
    cap = cv2.VideoCapture(video_path)
    if not cap.isOpened():
        logging.error("❌ Error: Unable to open video file.")
        return

    ret, frame = cap.read()
    while ret:
        process_frame(frame)
        ret, frame = cap.read()

    cap.release()
    logging.info("✅ Processing complete.")

if _name_ == "_main_":
    test_video = r"C:\wamp64\www\CampusIntel\sources\feed.MP4"
    recognize_faces(test_video)
    logging.info("✅ Face recognition completed.")
