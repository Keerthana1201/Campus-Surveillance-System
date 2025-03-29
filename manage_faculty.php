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

// Function to generate a random password
function generateRandomPassword($length = 8) {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
}

// Function to generate a unique roll number
function generateUniqueRollNumber($conn) {
    do {
        $roll_number = "FAC_" . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Users WHERE roll_number = ?");
        $stmt->execute([$roll_number]);
        $count = $stmt->fetchColumn();
    } while ($count > 0);
    
    return $roll_number;
}

// Handle Add Faculty
if (isset($_POST['add_faculty'])) {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $role = $_POST['role'] ?? '';
    $department_id = $_POST['department_id'] ?? '';

    if (!empty($first_name) && !empty($last_name) && !empty($email) && !empty($phone) && !empty($role) && !empty($department_id)) {
        $checkStmt = $conn->prepare("SELECT email, phone FROM Users WHERE email = ? OR phone = ?");
        $checkStmt->execute([$email, $phone]);
        $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            if ($existingUser['email'] == $email) {
                $message = "Error: This email is already in use!";
            } elseif ($existingUser['phone'] == $phone) {
                $message = "Error: This phone number is already in use!";
            }
        } else {
            $roll_number = generateUniqueRollNumber($conn);
            $randomPassword = generateRandomPassword();

            $stmt = $conn->prepare("INSERT INTO Users (first_name, last_name, email, phone, role, department_id, password, roll_number) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            if ($stmt->execute([$first_name, $last_name, $email, $phone, $role, $department_id, $randomPassword, $roll_number])) {
                $message = "Faculty added successfully!";
            } else {
                $message = "Error: Failed to add faculty. Please try again!";
            }
        }
    } else {
        $message = "Error: All fields are required.";
    }
}

// Handle Edit Faculty
if (isset($_POST['edit_faculty'])) {
    $edit_id = $_POST['edit_id'] ?? '';
    $edit_first_name = $_POST['edit_first_name'] ?? '';
    $edit_last_name = $_POST['edit_last_name'] ?? '';
    $edit_email = $_POST['edit_email'] ?? '';
    $edit_phone = $_POST['edit_phone'] ?? '';
    $edit_role = $_POST['edit_role'] ?? '';
    $edit_department_id = $_POST['edit_department_id'] ?? '';

    if (!empty($edit_id) && !empty($edit_first_name) && !empty($edit_last_name) && !empty($edit_email) && !empty($edit_phone) && !empty($edit_role) && !empty($edit_department_id)) {
        $checkStmt = $conn->prepare("SELECT user_id FROM Users WHERE (email = ? OR phone = ?) AND user_id != ?");
        $checkStmt->execute([$edit_email, $edit_phone, $edit_id]);
        $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            $message = "Error: Email or phone number is already in use!";
        } else {
            $updateStmt = $conn->prepare("UPDATE Users SET first_name=?, last_name=?, email=?, phone=?, role=?, department_id=? WHERE user_id=?");
            if ($updateStmt->execute([$edit_first_name, $edit_last_name, $edit_email, $edit_phone, $edit_role, $edit_department_id, $edit_id])) {
                $message = "Faculty updated successfully!";
            } else {
                $message = "Error: Failed to update faculty.";
            }
        }
    } else {
        $message = "Error: All fields are required.";
    }
}

// Handle Delete Faculty
if (isset($_POST['delete_faculty'])) {
    $delete_id = $_POST['delete_id'];
    
    if (!empty($delete_id)) {
        $deleteStmt = $conn->prepare("DELETE FROM Users WHERE user_id = ?");
        if ($deleteStmt->execute([$delete_id])) {
            $message = "Faculty deleted successfully.";
        } else {
            $message = "Error: Failed to delete faculty.";
        }
    } else {
        $message = "Error: Invalid faculty ID.";
    }
}

// Fetch Departments
$deptStmt = $conn->query("SELECT department_id, department_name FROM Departments ORDER BY department_name ASC");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Faculty List
$stmt = $conn->query("SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.role, d.department_id, d.department_name 
                      FROM Users u 
                      LEFT JOIN Departments d ON u.department_id = d.department_id 
                      WHERE u.role IN ('HOD', 'Faculty') 
                      ORDER BY u.first_name DESC");
$faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <h2>Manage Faculty</h2>
	<input type="text" id="searchInput" class="form-control mb-3" placeholder="Search Faculty...">

    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addFacultyModal">Add Faculty</button>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Department</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($faculty as $fac): ?>
                <tr>
                    <td><?= htmlspecialchars($fac['first_name']); ?></td>
                    <td><?= htmlspecialchars($fac['last_name']); ?></td>
                    <td><?= htmlspecialchars($fac['email']); ?></td>
                    <td><?= htmlspecialchars($fac['phone']); ?></td>
                    <td><?= htmlspecialchars($fac['role']); ?></td>
                    <td><?= htmlspecialchars($fac['department_name'] ?? 'N/A'); ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm" 
        data-bs-toggle="modal" 
        data-bs-target="#editFacultyModal"
        onclick="fillEditModal(
            '<?= $fac['user_id']; ?>', 
            '<?= htmlspecialchars($fac['first_name']); ?>', 
            '<?= htmlspecialchars($fac['last_name']); ?>', 
            '<?= htmlspecialchars($fac['email']); ?>', 
            '<?= htmlspecialchars($fac['phone']); ?>', 
            '<?= htmlspecialchars($fac['role']); ?>', 
            '<?= htmlspecialchars($fac['department_id']); ?>'
        )">
    Edit
</button>

                        <form method="post" class="d-inline">
                            <input type="hidden" name="delete_id" value="<?= $fac['user_id']; ?>">
                            <button type="submit" name="delete_faculty" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add Faculty Modal -->
<div class="modal fade" id="addFacultyModal" tabindex="-1" aria-labelledby="addFacultyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFacultyModalLabel">Add Faculty</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-control" name="role" required>
                            <option value="Faculty">Faculty</option>
                            <option value="HOD">HOD</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-control" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id']; ?>"><?= $dept['department_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_faculty" class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Edit Faculty Modal -->
<div class="modal fade" id="editFacultyModal" tabindex="-1" aria-labelledby="editFacultyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Faculty</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" id="edit_id" name="edit_id">
                    <div class="mb-3"><label>First Name</label><input type="text" class="form-control" id="edit_first_name" name="edit_first_name" required></div>
                    <div class="mb-3"><label>Last Name</label><input type="text" class="form-control" id="edit_last_name" name="edit_last_name" required></div>
                    <div class="mb-3"><label>Email</label><input type="email" class="form-control" id="edit_email" name="edit_email" required></div>
                    <div class="mb-3"><label>Phone</label><input type="text" class="form-control" id="edit_phone" name="edit_phone" required></div>
                    <div class="mb-3"><label>Role</label>
                        <select class="form-control" id="edit_role" name="edit_role" required>
                            <option value="Faculty">Faculty</option>
                            <option value="HOD">HOD</option>
                        </select>
                    </div>
                    <div class="mb-3"><label>Department</label>
                        <select class="form-control" id="edit_department_id" name="edit_department_id" required>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id']; ?>"><?= $dept['department_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="edit_faculty" class="btn btn-primary">Update</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function fillEditModal(id, first_name, last_name, email, phone, role, department_id) {
    document.getElementById("edit_id").value = id;
    document.getElementById("edit_first_name").value = first_name;
    document.getElementById("edit_last_name").value = last_name;
    document.getElementById("edit_email").value = email;
    document.getElementById("edit_phone").value = phone;
    document.getElementById("edit_role").value = role;
    document.getElementById("edit_department_id").value = department_id;
}


document.getElementById("searchInput").addEventListener("keyup", function() {
    let searchQuery = this.value.toLowerCase();
    let rows = document.querySelectorAll("tbody tr");
    
    rows.forEach(row => {
        let firstName = row.children[0].textContent.toLowerCase();
        let lastName = row.children[1].textContent.toLowerCase();
        
        if (firstName.includes(searchQuery) || lastName.includes(searchQuery)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
});
</script>
	
	
</div>
</body>
</html>
