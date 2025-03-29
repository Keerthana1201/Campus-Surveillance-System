<?php
session_start();
include("database/config.php");

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$department_id = $_SESSION['department_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM Users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Role Display Formatting
$roleDisplay = match ($role) {
    'Admin' => 'Administrator',
    'HOD' => 'Head of Department',
    'Faculty' => 'Faculty Member',
    default => 'User'
};

// Fetch user details
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM Users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle Search
$searchQuery = "";
$students = [];

if (isset($_POST['search'])) {
    $searchQuery = trim($_POST['search_query']);
    if (!empty($searchQuery)) {
        $stmt = $conn->prepare("SELECT user_id, roll_number, first_name, last_name FROM Users 
                                WHERE role = 'Student' 
                                AND (roll_number LIKE ? OR first_name LIKE ? OR last_name LIKE ?)");
        $searchParam = "%$searchQuery%";
        $stmt->execute([$searchParam, $searchParam, $searchParam]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle marking as informed
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['mark_informed'])) {
    $student_id = trim($_POST['student_id']);
    $reason = !empty($_POST['reason']) ? trim($_POST['reason']) : NULL;

    // Validate input
    if (!ctype_digit($student_id)) {
        $message = "Invalid Student ID.";
    } else {
        // Check if already informed today
        $checkStmt = $conn->prepare("SELECT 1 FROM InformedEntryExitLog WHERE user_id = ? AND DATE(informed_at) = CURDATE()");
        $checkStmt->execute([$student_id]);

        if ($checkStmt->fetch()) {
            $message = "Student already marked as informed today.";
        } else {
            // Insert into the InformedEntryExitLog table
            $insertStmt = $conn->prepare("INSERT INTO InformedEntryExitLog (user_id, time, type, reason) VALUES (?, NOW(), 'Exit', ?)");
            if ($insertStmt->execute([$student_id, $reason])) {
                $message = "Student successfully marked as informed.";
            } else {
                $message = "Error marking student.";
            }
        }
    }
}

// Fetch informed students list
try {
    $query = "SELECT 
                IEE.log_id, 
                U.roll_number, 
                U.first_name, 
                U.last_name, 
                IEE.time, 
                IEE.type, 
                IEE.reason, 
                IEE.informed_at 
              FROM InformedEntryExitLog IEE
              JOIN Users U ON IEE.user_id = U.user_id
              WHERE U.role = 'Student'
              ORDER BY IEE.informed_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $informedStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Informed Students</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>


<div class="d-flex">
    <!-- Sidebar -->
    <div class="bg-dark text-white vh-100 p-3" style="width: 250px;">
        <p class="text-center">
            <strong><?= htmlspecialchars($user['first_name'] . " " . $user['last_name']); ?></strong> <br>
            <small class="text-warning">(<?= htmlspecialchars($roleDisplay); ?>)</small> <br>
            <small><?= htmlspecialchars($user['email']); ?></small>
        </p>
        <hr>
        <a href="students_uninformed.php" class="text-white text-decoration-none d-block p-2">Uninformed Students</a>
        
        <?php if ($role === 'Admin'): ?>
            <a href="manage_departments.php" class="text-white text-decoration-none d-block p-2">Manage Departments</a>
            <a href="manage_faculty.php" class="text-white text-decoration-none d-block p-2">Manage Faculty</a>
            <a href="manage_batches.php" class="text-white text-decoration-none d-block p-2">Manage Batches</a>
            <a href="manage_students.php" class="text-white text-decoration-none d-block p-2">Manage Students</a>
        
        <?php elseif ($role === 'HOD'): ?>
            <a href="manage_faculty.php" class="text-white text-decoration-none d-block p-2">Manage Faculty</a>
            <a href="manage_batches.php" class="text-white text-decoration-none d-block p-2">Manage Batches</a>
            <a href="manage_students.php" class="text-white text-decoration-none d-block p-2">Manage Students</a>
			<a href="students_inform.php" class="text-white text-decoration-none d-block p-2">Inform</a>
        
        <?php elseif ($role === 'Faculty'): ?>
            <a href="manage_students.php" class="text-white text-decoration-none d-block p-2">Manage Assigned Students</a>
			<a href="students_inform.php" class="text-white text-decoration-none d-block p-2">Inform</a>
        <?php endif; ?>

        <a href="logout.php" class="text-white text-decoration-none d-block p-2">Logout</a>
    </div>
	
<div class="container mt-4">
    <h2>Informed Students List</h2>

    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Search Form -->
    <form method="post" class="mb-3">
        <div class="input-group">
            <input type="text" name="search_query" class="form-control" placeholder="Search by Roll Number or Name" value="<?= htmlspecialchars($searchQuery); ?>" required>
            <button type="submit" name="search" class="btn btn-primary">Search</button>
        </div>
    </form>

    <?php if (!empty($students)): ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Roll Number</th>
                    <th>Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['roll_number']); ?></td>
                        <td><?= htmlspecialchars($student['first_name'] . " " . $student['last_name']); ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['user_id']); ?>">
                                <input type="text" name="reason" class="form-control" placeholder="Reason for exit" required>
                                <button type="submit" name="mark_informed" class="btn btn-success mt-1">Mark as Informed</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif (isset($_POST['search'])): ?>
        <p class="text-danger">No students found.</p>
    <?php endif; ?>

    <h3 class="mt-5">Previously Informed Students</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Roll Number</th>
                <th>Name</th>
                <th>Exit Time</th>
                <th>Type</th>
                <th>Reason</th>
                <th>Marked At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($informedStudents as $student): ?>
                <tr>
                    <td><?= htmlspecialchars($student['roll_number']); ?></td>
                    <td><?= htmlspecialchars($student['first_name'] . " " . $student['last_name']); ?></td>
                    <td><?= htmlspecialchars($student['time']); ?></td>
                    <td><?= htmlspecialchars($student['type']); ?></td>
                    <td><?= htmlspecialchars($student['reason']); ?></td>
                    <td><?= htmlspecialchars($student['informed_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
