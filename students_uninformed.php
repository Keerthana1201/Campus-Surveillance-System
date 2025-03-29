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

// Handle "Mark as Informed" action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['mark_informed'])) {
    $student_id = $_POST['student_id'];
    $reason = $_POST['reason'] ?? NULL;
    
    // Check if entry already exists
    $checkStmt = $conn->prepare("SELECT 1 FROM InformedEntryExitLog WHERE user_id = ? AND DATE(informed_at) = CURDATE()");
    $checkStmt->execute([$student_id]);

    if ($checkStmt->fetch()) {
        $message = "Student already marked as informed for today.";
    } else {
        $insertStmt = $conn->prepare("INSERT INTO InformedEntryExitLog (user_id, time, type, reason) VALUES (?, NOW(), 'Exit', ?)");
        if ($insertStmt->execute([$student_id, $reason])) {
            $message = "Student successfully marked as informed.";
        } else {
            $message = "Error marking student.";
        }
    }
}

// Fetch uninformed students based on EntryExitLog, checking the last 7 days
try {
    $query = "SELECT 
                EEL.timestamp AS entry_time,
                SUBSTRING_INDEX(EEL.name, '_', -1) AS roll_number,
                U.first_name,
                U.last_name,
                B.batch_name,
                B.batch_id
              FROM 
                EntryExitLog EEL
              JOIN 
                Users U ON SUBSTRING_INDEX(EEL.name, '_', -1) = U.roll_number
              JOIN 
                Batches B ON U.batch_id = B.batch_id
              WHERE 
                EEL.timestamp >= CURDATE() - INTERVAL 7 DAY
                AND U.role = 'Student' 
                AND NOT EXISTS (
                    SELECT 1
                    FROM InformedEntryExitLog IEE
                    WHERE IEE.user_id = U.user_id
                    AND DATE(IEE.informed_at) = CURDATE()
                )
              ORDER BY 
                EEL.timestamp DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $uninformedData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Uninformed Students</title>
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

    <!-- Main Content -->
    <div class="container mt-4">
        <h2>Students Uninformed List</h2>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="accordion" id="uninformedAccordion">
            <?php
            foreach ($uninformedData as $record): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#batch_<?= $record['batch_id']; ?>">
                            <?= htmlspecialchars($record['batch_name']); ?>
                        </button>
                    </h2>
                    <div id="batch_<?= $record['batch_id']; ?>" class="accordion-collapse collapse">
                        <div class="accordion-body">
                            <p>
                                <?= htmlspecialchars($record['first_name'] . " " . $record['last_name'] . " (" . $record['roll_number'] . ")"); ?>
                                <?php if ($role === 'HOD' || $role === 'Faculty'): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="student_id" value="<?= $record['roll_number']; ?>">
                                        <input type="text" name="reason" placeholder="Reason" required>
                                        <button type="submit" name="mark_informed" class="btn btn-sm btn-success">Mark as Informed</button>
                                    </form>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>
