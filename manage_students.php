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
// Fetch Active Batches with their Department
$batchStmt = $conn->query("SELECT b.batch_id, b.batch_name, d.department_id, d.department_name 
                           FROM Batches b 
                           JOIN Departments d ON b.department_id = d.department_id 
                           WHERE b.is_active = 1 
                           ORDER BY b.batch_name ASC");
$batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Students List
$stmt = $conn->query("SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.batch_id, b.batch_name, d.department_name, u.roll_number 
                      FROM Users u 
                      LEFT JOIN Batches b ON u.batch_id = b.batch_id 
                      LEFT JOIN Departments d ON b.department_id = d.department_id 
                      WHERE u.role = 'Student' 
                      ORDER BY u.first_name DESC");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['add_student'])) {
    // Get the student details from the form
    $roll_number = $_POST['roll_number'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $batch_id = $_POST['batch_id'];

    // Check if roll number, email, or phone number already exists
    $stmt = $conn->prepare("SELECT * FROM Users WHERE roll_number = ? OR email = ? OR phone = ?");
    $stmt->execute([$roll_number, $email, $phone]);
    $existingStudent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingStudent) {
        // If a duplicate is found, show an error message
        $error = "A student with the same Roll Number, Email, or Phone already exists.";
    } else {
        // Insert the new student if no duplicates were found
        $stmt = $conn->prepare("INSERT INTO Users (roll_number, first_name, last_name, email, phone, batch_id, role) 
                                VALUES (?, ?, ?, ?, ?, ?, 'Student')");
        $stmt->execute([$roll_number, $first_name, $last_name, $email, $phone, $batch_id]);

        header("Location: manage_students.php");
        exit();
    }
}

if (isset($_POST['edit_student'])) {
    // Get the student details from the form
    $edit_id = $_POST['edit_id'];
    $edit_roll_number = $_POST['edit_roll_number'];
    $edit_first_name = $_POST['edit_first_name'];
    $edit_last_name = $_POST['edit_last_name'];
    $edit_email = $_POST['edit_email'];
    $edit_phone = $_POST['edit_phone'];
    $edit_batch_id = $_POST['edit_batch_id'];

    // Check if roll number, email, or phone number already exists for another student
    $stmt = $conn->prepare("SELECT * FROM Users WHERE (roll_number = ? OR email = ? OR phone = ?) AND user_id != ?");
    $stmt->execute([$edit_roll_number, $edit_email, $edit_phone, $edit_id]);
    $existingStudent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingStudent) {
        // If a duplicate is found, show an error message
        $error = "A student with the same Roll Number, Email, or Phone already exists.";
    } else {
        // Update the student if no duplicates were found
        $stmt = $conn->prepare("UPDATE Users 
                                SET roll_number = ?, first_name = ?, last_name = ?, email = ?, phone = ?, batch_id = ? 
                                WHERE user_id = ?");
        $stmt->execute([$edit_roll_number, $edit_first_name, $edit_last_name, $edit_email, $edit_phone, $edit_batch_id, $edit_id]);

        header("Location: manage_students.php");
        exit();
    }
}
?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES); ?></div>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Students</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateDepartment() {
            const batchSelect = document.getElementById('batch_id');
            const departmentField = document.getElementById('department_name');
            const selectedOption = batchSelect.options[batchSelect.selectedIndex];
            departmentField.value = selectedOption.getAttribute('data-department');
        }

        function updateDepartmentEdit() {
            const batchSelect = document.getElementById('edit_batch_id');
            const departmentField = document.getElementById('edit_department_name');
            const selectedOption = batchSelect.options[batchSelect.selectedIndex];
            departmentField.value = selectedOption.getAttribute('data-department');
        }
    </script>
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
    <h2>Manage Students</h2>
 <!-- Search Input -->
    <input type="text" id="searchInput" class="form-control mb-3" placeholder="Search by name...">

    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addStudentModal">Add Student</button>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Roll Number</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Batch</th>
                <th>Department</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="studentsTable">
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?= htmlspecialchars($student['roll_number'] ?? '', ENT_QUOTES); ?></td>
                    <td><?= htmlspecialchars($student['first_name'] ?? '', ENT_QUOTES); ?></td>
                    <td><?= htmlspecialchars($student['last_name'] ?? '', ENT_QUOTES); ?></td>
                    <td><?= htmlspecialchars($student['email'] ?? '', ENT_QUOTES); ?></td>
                    <td><?= htmlspecialchars($student['phone'] ?? '', ENT_QUOTES); ?></td>
                    <td><?= htmlspecialchars($student['batch_name'] ?? '', ENT_QUOTES); ?></td>
                    <td><?= htmlspecialchars($student['department_name'] ?? '', ENT_QUOTES); ?></td>
<td>
    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editStudentModal" 
            data-id="<?= $student['user_id']; ?>"
            data-first-name="<?= htmlspecialchars($student['first_name'] ?? '', ENT_QUOTES); ?>"
            data-last-name="<?= htmlspecialchars($student['last_name'] ?? '', ENT_QUOTES); ?>"
            data-email="<?= htmlspecialchars($student['email'] ?? '', ENT_QUOTES); ?>"
            data-phone="<?= htmlspecialchars($student['phone'] ?? '', ENT_QUOTES); ?>"
            data-batch-year="<?= $student['batch_id']; ?>"
            data-department="<?= htmlspecialchars($student['department_name'] ?? '', ENT_QUOTES); ?>"
            data-roll-number="<?= htmlspecialchars($student['roll_number'] ?? '', ENT_QUOTES); ?>">Edit</button>
</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStudentModalLabel">Add Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_students.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="roll_number" class="form-label">Roll Number</label>
                        <input type="text" class="form-control" name="roll_number" id="roll_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" id="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" id="last_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" id="phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="batch_id" class="form-label">Batch</label>
                        <select class="form-select" name="batch_id" id="batch_id" required onchange="updateDepartment()">
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?= $batch['batch_id']; ?>" data-department="<?= htmlspecialchars($batch['department_name']); ?>">
                                    <?= htmlspecialchars($batch['batch_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="department_name" class="form-label">Department</label>
                        <input type="text" class="form-control" id="department_name" readonly>
                    </div>
                    <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStudentModalLabel">Edit Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_students.php">
                <div class="modal-body">
                    <input type="hidden" name="edit_id" id="edit_id">
<div class="mb-3">
    <label for="edit_roll_number" class="form-label">Roll Number</label>
    <input type="text" class="form-control" name="edit_roll_number" id="edit_roll_number" required>
</div>

                    <div class="mb-3">
                        <label for="edit_first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" name="edit_first_name" id="edit_first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="edit_last_name" id="edit_last_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" name="edit_email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" name="edit_phone" id="edit_phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_batch_id" class="form-label">Batch</label>
                        <select class="form-select" name="edit_batch_id" id="edit_batch_id" required onchange="updateDepartmentEdit()">
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?= $batch['batch_id']; ?>" data-department="<?= htmlspecialchars($batch['department_name']); ?>">
                                    <?= htmlspecialchars($batch['batch_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_department_name" class="form-label">Department</label>
                        <input type="text" class="form-control" id="edit_department_name" readonly>
                    </div>
                    <button type="submit" name="edit_student" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function updateDepartment() {
        const batchSelect = document.getElementById('batch_id');
        const departmentField = document.getElementById('department_name');
        const selectedOption = batchSelect.options[batchSelect.selectedIndex];
        departmentField.value = selectedOption.getAttribute('data-department');
    }

    function updateDepartmentEdit() {
        const batchSelect = document.getElementById('edit_batch_id');
        const departmentField = document.getElementById('edit_department_name');
        const selectedOption = batchSelect.options[batchSelect.selectedIndex];
        departmentField.value = selectedOption.getAttribute('data-department');
    }

// Populate Edit Modal with student data
document.getElementById('editStudentModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('edit_id').value = button.getAttribute('data-id');
    document.getElementById('edit_roll_number').value = button.getAttribute('data-roll-number'); // Prefill roll number
    document.getElementById('edit_first_name').value = button.getAttribute('data-first-name');
    document.getElementById('edit_last_name').value = button.getAttribute('data-last-name');
    document.getElementById('edit_email').value = button.getAttribute('data-email');
    document.getElementById('edit_phone').value = button.getAttribute('data-phone');
    document.getElementById('edit_batch_id').value = button.getAttribute('data-batch-year');
    document.getElementById('edit_department_name').value = button.getAttribute('data-department');
});


    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchQuery = this.value.toLowerCase();
        const rows = document.querySelectorAll('#studentsTable tr');
        rows.forEach(row => {
            const cells = row.getElementsByTagName('td');
            const match = Array.from(cells).some(cell => cell.textContent.toLowerCase().includes(searchQuery));
            row.style.display = match ? '' : 'none';
        });
    });
</script>


</body>
</html>
