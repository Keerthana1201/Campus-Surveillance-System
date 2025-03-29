<?php
session_start();
include("database/config.php");

// Ensure only Admins can access
if ($_SESSION['role'] !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

$message = "";

// Handle adding a department
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_department'])) {
    $department_name = trim($_POST['department_name']);

    if (!empty($department_name)) {
        $stmt = $conn->prepare("INSERT INTO Departments (department_name) VALUES (?)");
        $stmt->execute([$department_name]);
        $message = "Department added successfully!";
    } else {
        $message = "Department name cannot be empty.";
    }
}

// Handle updating a department
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_department'])) {
    $department_id = $_POST['department_id'];
    $department_name = trim($_POST['department_name']);

    if (!empty($department_name)) {
        $stmt = $conn->prepare("UPDATE Departments SET department_name = ? WHERE department_id = ?");
        $stmt->execute([$department_name, $department_id]);
        $message = "Department updated successfully!";
    } else {
        $message = "Department name cannot be empty.";
    }
}

// Handle deleting a department
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_department'])) {
    $department_id = $_POST['department_id'];

    // Check if department is assigned to users
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM Users WHERE department_id = ?");
    $checkStmt->execute([$department_id]);
    $count = $checkStmt->fetchColumn();

    if ($count > 0) {
        $message = "Cannot delete! Department is assigned to users.";
    } else {
        $stmt = $conn->prepare("DELETE FROM Departments WHERE department_id = ?");
        $stmt->execute([$department_id]);
        $message = "Department deleted successfully!";
    }
}

// Fetch departments sorted by name
$stmt = $conn->query("SELECT department_id, department_name FROM Departments ORDER BY department_name ASC");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user details
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Departments</title>
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
        <?php elseif ($role === 'Faculty'): ?>
            <a href="manage_students.php" class="text-white text-decoration-none d-block p-2">Manage Assigned Students</a>
        <?php endif; ?>

        <a href="logout.php" class="text-white text-decoration-none d-block p-2">Logout</a>
    </div>

    <!-- Main Content -->
    <div class="container mt-4">
        <h2>Manage Departments</h2>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <button class="btn btn-primary mb-3" onclick="openAddPopup()">Add Department</button>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Serial No.</th>
                    <th>Department Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $serial = 1;
                foreach ($departments as $dept): ?>
                    <tr>
                        <td><?= $serial++; ?></td>
                        <td><?= htmlspecialchars($dept['department_name']); ?></td>
                        <td>
                            <button class="btn btn-warning btn-sm" 
                                    onclick="openEditPopup(<?= $dept['department_id']; ?>, '<?= htmlspecialchars($dept['department_name']); ?>')">
                                Edit
                            </button>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="department_id" value="<?= $dept['department_id']; ?>">
                                <button type="submit" name="delete_department" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Department Modals -->
<div id="addPopup" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Department Name</label>
                        <input type="text" class="form-control" name="department_name" required>
                    </div>
                    <button type="submit" name="add_department" class="btn btn-success">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Edit Department Modal (Now Included) -->
<div id="editPopup" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" id="edit_department_id" name="department_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Department Name</label>
                        <input type="text" class="form-control" id="edit_department_name" name="department_name" required>
                    </div>

                    <button type="submit" name="edit_department" class="btn btn-success">Update</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openAddPopup() {
    new bootstrap.Modal(document.getElementById('addPopup')).show();
}

function openEditPopup(departmentId, departmentName) {
    document.getElementById('edit_department_id').value = departmentId;
    document.getElementById('edit_department_name').value = departmentName;
    new bootstrap.Modal(document.getElementById('editPopup')).show();
}
</script>


</body>
</html>
