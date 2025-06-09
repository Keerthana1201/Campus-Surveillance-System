**INTELLIGENT CAMPUS MONITORING AND TRACKING SYSTEM**

**Aim of the Project**

This system is designed to detect and monitor students entering or exiting the campus without proper authorization, using a real-time camera setup at the college gate. It cross-verifies individuals against a secure student dataset. If an unauthorized student is detected, the system immediately generates an alert sent to the HOD dashboard on the website. Authorized students can be managed through a Faculty Dashboard where faculty can update student status to prevent false alerts.

**Technologies Used**

**Python** for core development and automation

**OpenCV** for real-time video capture and processing

**MTCNN** for accurate and efficient face detection

**DeepFace** with ArcFace model for reliable face recognition

**MySQL** for robust database management

**Tkinter** for building interactive GUI components

**NumPy & Pandas** for numerical computations and data handling

**scikit-learn** for similarity measurements and data analysis

**HTML** for developing user-friendly web dashboards

**Project Use**

This project provides a secure, automated, and scalable student monitoring solution to enhance campus safety. It significantly reduces manual effort for faculty by automating entry/exit verification and alert generation, while maintaining precise logs of student movement.

**Methodology**

**1.Dataset Preparation**

- Capture multiple face images per individual using a webcam with OpenCV.
- Use MTCNN to detect faces in each frame.
- Crop and save detected faces in folders named by person (including name and register number).

**2.Dataset Preprocessing**

- Convert images from BGR to RGB for DeepFace compatibility.
- Apply CLAHE to enhance contrast and handle lighting variations.
- Resize images to 112×112 pixels.
- Save processed images for training.

**3.Model Training**

- Structure dataset into individual folders with multiple images per person.
- Extract face embeddings using DeepFace (ArcFace model) after preprocessing.
- Store embeddings for matching during recognition using cosine similarity.

**4.Face Detection**

- Detect faces in real-time video using MTCNN.
- Use a confidence threshold of 0.99 to filter detections.
- Crop, resize, and normalize detected faces for recognition.
- Support multiple faces per frame with bounding boxes.

**5.Face Recognition**

- Extract embeddings of detected faces with DeepFace.
- Match embeddings to stored dataset using cosine similarity (threshold 0.9).
- Label recognized faces or mark as "Unknown".
- Track movements to log entry/exit with timestamps in MySQL.
- Avoid duplicate logs within 2 minutes.

**6.Website Creation**

- Implement secure login with role-based access (Admin, HOD, Faculty) using PHP sessions and MySQL.
- Responsive dashboard with Bootstrap and dynamic sidebar menus based on roles.
- Manage students, faculty, departments, and batches with CRUD operations.

**7.Alert Generation**

- Track uninformed students by cross-checking entry logs with informed exit records.
- Display uninformed students with relevant details for action.
- Generate alerts for HODs on uninformed entries/exits.
- Allow faculty/HOD to mark students as informed with reasons, preventing duplicate alerts.
- Ensure real-time monitoring and prompt security interventions.



**Advantages**

- Real-time monitoring ensuring instant detection and alerts
- Automated alert system that notifies HOD of unauthorized entries/exits
- Faculty intervention dashboard to update student statuses and prevent unnecessary alerts
- Enhanced campus security with minimal manual supervisionScalable architecture that can be extended to monitor multiple campus locations or other applications


