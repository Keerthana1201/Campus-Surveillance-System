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


// Fetch Departments
$deptStmt = $conn->query("SELECT department_id, department_name FROM Departments ORDER BY department_name ASC");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Faculty
$facultyStmt = $conn->query("SELECT user_id, first_name, last_name FROM Users WHERE role = 'Faculty'");
$faculty = $facultyStmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize message variable
$message = "";

// Handle Add Batch
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_batch'])) {
    $batch_name = $_POST['batch_name'] ?? '';
    $department_id = $_POST['department_id'] ?? '';
    $start_year = $_POST['start_year'] ?? '';
    $end_year = $_POST['end_year'] ?? '';
    $faculty_id = $_POST['faculty_id'] ?? NULL;

    if ($batch_name && $department_id && $start_year && $end_year) {
        $stmt = $conn->prepare("INSERT INTO Batches (batch_name, department_id, start_year, end_year, faculty_id) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$batch_name, $department_id, $start_year, $end_year, $faculty_id])) {
            $message = "Batch added successfully!";
        } else {
            $message = "Error adding batch.";
        }
    } else {
        $message = "All fields are required!";
    }
}

// Handle Edit Batch
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_batch'])) {
    $edit_id = $_POST['edit_id'] ?? '';
    $edit_batch_name = $_POST['edit_batch_name'] ?? '';
    $edit_department_id = $_POST['edit_department_id'] ?? '';
    $edit_start_year = $_POST['edit_start_year'] ?? '';
    $edit_end_year = $_POST['edit_end_year'] ?? '';
    $edit_faculty_id = $_POST['edit_faculty_id'] ?? NULL;

    if ($edit_id && $edit_batch_name && $edit_department_id && $edit_start_year && $edit_end_year) {
        $stmt = $conn->prepare("UPDATE Batches SET batch_name=?, department_id=?, start_year=?, end_year=?, faculty_id=? WHERE batch_id=?");
        if ($stmt->execute([$edit_batch_name, $edit_department_id, $edit_start_year, $edit_end_year, $edit_faculty_id, $edit_id])) {
            $message = "Batch updated successfully!";
        } else {
            $message = "Error updating batch.";
        }
    } else {
        $message = "All fields are required!";
    }
}

// Handle Delete Batch
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_batch'])) {
    $delete_id = $_POST['delete_id'] ?? '';
    
    if ($delete_id) {
        $stmt = $conn->prepare("DELETE FROM Batches WHERE batch_id = ?");
        if ($stmt->execute([$delete_id])) {
            $message = "Batch deleted successfully.";
        } else {
            $message = "Error deleting batch.";
        }
    } else {
        $message = "Invalid batch ID.";
    }
}

// Fetch Batches
$stmt = $conn->query("SELECT Batches.*, Departments.department_name, Users.first_name, Users.last_name 
                      FROM Batches 
                      LEFT JOIN Departments ON Batches.department_id = Departments.department_id 
                      LEFT JOIN Users ON Batches.faculty_id = Users.user_id 
                      ORDER BY start_year DESC");
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Batches</title>
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
						<a href="students_inform.php" class="text-white text-decoration-none d-block p-2">Inform</a>
        
        <?php elseif ($role === 'HOD'): ?>
            <a href="manage_faculty.php" class="text-white text-decoration-none d-block p-2">Manage Faculty</a>
            <a href="manage_batches.php" class="text-white text-decoration-none d-block p-2">Manage Batches</a>
            <a href="manage_students.php" class="text-white text-decoration-none d-block p-2">Manage Students</a>
						<a href="students_inform.php" class="text-white text-decoration-none d-block p-2">Inform</a>
        
        <?php elseif ($role === 'Faculty'): ?>
            <a href="manage_students.php" class="text-white text-decoration-none d-block p-2">Manage Assigned Students</a>
        <?php endif; ?>

        <a href="logout.php" class="text-white text-decoration-none d-block p-2">Logout</a>
    </div>
	
<div class="container mt-4">
    <h2>Manage Batches</h2>
    <input type="text" id="searchInput" class="form-control mb-3" placeholder="Search Batches...">

    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addBatchModal">Add Batch</button>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Batch Name</th>
                <th>Department</th>
                <th>Start Year</th>
                <th>End Year</th>
                <th>Faculty Assigned</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($batches as $batch): ?>
                <tr>
                    <td><?= htmlspecialchars($batch['batch_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($batch['department_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($batch['start_year'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($batch['end_year'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars(($batch['first_name'] ?? '') . " " . ($batch['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?: 'None'; ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#editBatchModal"
                            onclick="fillEditModal(
                                '<?= htmlspecialchars($batch['batch_id'], ENT_QUOTES, 'UTF-8'); ?>', 
                                '<?= htmlspecialchars($batch['batch_name'], ENT_QUOTES, 'UTF-8'); ?>', 
                                '<?= htmlspecialchars($batch['department_id'], ENT_QUOTES, 'UTF-8'); ?>', 
                                '<?= htmlspecialchars($batch['start_year'], ENT_QUOTES, 'UTF-8'); ?>', 
                                '<?= htmlspecialchars($batch['end_year'], ENT_QUOTES, 'UTF-8'); ?>', 
                                '<?= htmlspecialchars($batch['faculty_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'
                            )">
                            Edit
                        </button>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="delete_id" value="<?= htmlspecialchars($batch['batch_id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" name="delete_batch" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add Batch Modal -->
<div class="modal fade" id="addBatchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="text" class="form-control mb-3" name="batch_name" placeholder="Batch Name" required>
                    <select class="form-control mb-3" name="department_id" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['department_id']; ?>"><?= $dept['department_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" class="form-control mb-3" name="start_year" placeholder="Start Year" required>
                    <input type="number" class="form-control mb-3" name="end_year" placeholder="End Year" required>
                    <select class="form-control mb-3" name="faculty_id">
                        <option value="">Assign Faculty (Optional)</option>
                        <?php foreach ($faculty as $fac): ?>
                            <option value="<?= $fac['user_id']; ?>"><?= $fac['first_name'] . " " . $fac['last_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="add_batch" class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Batch Modal -->
<div class="modal fade" id="editBatchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" id="editBatchId" name="edit_id">
                    <input type="text" class="form-control mb-3" id="editBatchName" name="edit_batch_name" placeholder="Batch Name" required>
                    <select class="form-control mb-3" id="editDepartmentId" name="edit_department_id" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['department_id']; ?>"><?= $dept['department_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" class="form-control mb-3" id="editStartYear" name="edit_start_year" placeholder="Start Year" required>
                    <input type="number" class="form-control mb-3" id="editEndYear" name="edit_end_year" placeholder="End Year" required>
                    <select class="form-control mb-3" id="editFacultyId" name="edit_faculty_id">
                        <option value="">Assign Faculty (Optional)</option>
                        <?php foreach ($faculty as $fac): ?>
                            <option value="<?= $fac['user_id']; ?>"><?= $fac['first_name'] . " " . $fac['last_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="edit_batch" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
// Search functionality
document.getElementById("searchInput").addEventListener("keyup", function() {
    let query = this.value.toLowerCase();
    document.querySelectorAll("tbody tr").forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(query) ? "" : "none";
    });
});

function fillEditModal(batch_id, batch_name, department_id, start_year, end_year, faculty_id) {
    // Populate the modal with the current batch's details
    document.getElementById('editBatchId').value = batch_id;
    document.getElementById('editBatchName').value = batch_name;
    document.getElementById('editDepartmentId').value = department_id;
    document.getElementById('editStartYear').value = start_year;
    document.getElementById('editEndYear').value = end_year;
    document.getElementById('editFacultyId').value = faculty_id;
}


</script>

</body>
</html>
