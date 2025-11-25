<?php
// Start session and security headers
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Check if user is logged in and is an admin
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?session_expired=1");
    exit();
}

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "school_portal";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Fetch admin details for display
$adminName = 'Administrator';
$adminInitial = 'A';
try {
    $adminStmt = $conn->prepare("SELECT first_name, last_name, username FROM tbl_accounts WHERE account_id = ?");
    $adminStmt->bind_param("i", $_SESSION['account_id']);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();
    $adminData = $adminResult->fetch_assoc();
    
    if ($adminData) {
        $adminName = $adminData['first_name'] . ' ' . $adminData['last_name'];
        $adminInitial = strtoupper(substr($adminData['first_name'], 0, 1));
        // Update session for future use
        $_SESSION['fullname'] = $adminName;
    }
} catch (Exception $e) {
    // If database fetch fails, use session fallbacks
    if (isset($_SESSION['fullname']) && !empty($_SESSION['fullname'])) {
        $adminName = $_SESSION['fullname'];
        $adminInitial = substr($_SESSION['fullname'], 0, 1);
    } elseif (isset($_SESSION['username'])) {
        $adminName = $_SESSION['username'];
        $adminInitial = substr($_SESSION['username'], 0, 1);
    }
}

// Handle Add Teacher Assignment
if(isset($_POST['add_teacher'])) {
    $teacher_id = $conn->real_escape_string($_POST['teacher_id']);
    $subject_id = $conn->real_escape_string($_POST['subject_id']);
    $section_id = $conn->real_escape_string($_POST['section_id']);

    if($teacher_id && $subject_id && $section_id) {
        $teacher_check = $conn->query("SELECT * FROM tbl_accounts WHERE account_id='$teacher_id' AND role='teacher'");
        if($teacher_check->num_rows == 0){
            $error_msg = "Selected teacher does not exist.";
        } else {
            $dup = $conn->query("SELECT * FROM tbl_teacher_assignments 
                                 WHERE teacher_account_id='$teacher_id' AND subject_id='$subject_id' AND section_id='$section_id'");
            if($dup->num_rows > 0){
                $error_msg = "This teacher is already assigned to this subject and section.";
            } else {
                $conn->query("INSERT INTO tbl_teacher_assignments (teacher_account_id, subject_id, section_id) 
                              VALUES ('$teacher_id', '$subject_id', '$section_id')");
                header("Location: manage_teachers.php?success=1");
                exit();
            }
        }
    } else {
        $error_msg = "Please select all fields.";
    }
}

// Handle Update Teacher Assignment
if(isset($_POST['update_teacher'])){
    $assignment_id = $conn->real_escape_string($_POST['assignment_id']);
    $teacher_id = $conn->real_escape_string($_POST['teacher_id']);
    $subject_id = $conn->real_escape_string($_POST['subject_id']);
    $section_id = $conn->real_escape_string($_POST['section_id']);

    $dup = $conn->query("SELECT * FROM tbl_teacher_assignments 
                         WHERE teacher_account_id='$teacher_id' AND subject_id='$subject_id' AND section_id='$section_id' AND assignment_id!='$assignment_id'");
    if($dup->num_rows > 0){
        $error_msg = "This teacher is already assigned to this subject and section.";
    } else {
        $conn->query("UPDATE tbl_teacher_assignments 
                      SET teacher_account_id='$teacher_id', subject_id='$subject_id', section_id='$section_id' 
                      WHERE assignment_id='$assignment_id'");
        header("Location: manage_teachers.php?success=2");
        exit();
    }
}

// Handle Delete Teacher Assignment
if(isset($_POST['delete_teacher'])){
    $assignment_id = $conn->real_escape_string($_POST['assignment_id']);
    $conn->query("DELETE FROM tbl_teacher_assignments WHERE assignment_id='$assignment_id'");
    header("Location: manage_teachers.php?success=3");
    exit();
}

// Show success messages if redirected
if(isset($_GET['success'])){
    if($_GET['success']==1) $success_msg = "Teacher assignment added successfully!";
    if($_GET['success']==2) $success_msg = "Teacher assignment updated successfully!";
    if($_GET['success']==3) $success_msg = "Teacher assignment deleted successfully!";
}

// Fetch teacher assignments
$teacher_assignments = $conn->query("
    SELECT a.assignment_id, t.first_name, t.last_name, s.subject_name, sec.section_name, t.account_id AS teacher_id, s.subject_id, sec.section_id
    FROM tbl_teacher_assignments a
    JOIN tbl_accounts t ON t.account_id = a.teacher_account_id
    JOIN tbl_subjects s ON s.subject_id = a.subject_id
    JOIN tbl_sections sec ON sec.section_id = a.section_id
");

// Fetch teachers
$teachers = $conn->query("SELECT account_id, first_name, last_name FROM tbl_accounts WHERE role='teacher'");

// Fetch year levels
$yearlevels = $conn->query("SELECT year_level_id, level_name FROM tbl_yearlevels ORDER BY year_level_id");

// Fetch subjects with year level information
$subjects = $conn->query("
    SELECT s.subject_id, s.subject_name, yl.level_name, yl.year_level_id 
    FROM tbl_subjects s 
    LEFT JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id 
    ORDER BY yl.year_level_id, s.subject_name
");

// Fetch sections with year level information
$sections = $conn->query("
    SELECT s.section_id, s.section_name, yl.level_name, yl.year_level_id 
    FROM tbl_sections s 
    JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id 
    ORDER BY yl.year_level_id, s.section_name
");

// Prepare sections data for JavaScript
$sections_data = [];
$sections->data_seek(0);
while($sec = $sections->fetch_assoc()) {
    $sections_data[] = [
        'id' => $sec['section_id'],
        'name' => $sec['section_name'],
        'level_id' => $sec['year_level_id'],
        'level_name' => $sec['level_name']
    ];
}
$sections_json = json_encode($sections_data);

// Prepare subjects data for JavaScript
$subjects_data = [];
$subjects->data_seek(0);
while($sub = $subjects->fetch_assoc()) {
    $subjects_data[] = [
        'id' => $sub['subject_id'],
        'name' => $sub['subject_name'],
        'level_id' => $sub['year_level_id'],
        'level_name' => $sub['level_name']
    ];
}
$subjects_json = json_encode($subjects_data);

// Prepare year levels data for JavaScript
$yearlevels_data = [];
$yearlevels->data_seek(0);
while($yl = $yearlevels->fetch_assoc()) {
    $yearlevels_data[] = [
        'id' => $yl['year_level_id'],
        'name' => $yl['level_name']
    ];
}
$yearlevels_json = json_encode($yearlevels_data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduTrack - Manage Teachers</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root { 
    --primary: #2c3e50; 
    --secondary: #3498db; 
    --accent: #1abc9c; 
    --light: #ecf0f1; 
    --dark: #34495e; 
}
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    background-color: #f8f9fa; 
}
.sidebar { 
    background-color: var(--primary); 
    color: white; 
    min-height: 100vh; 
    padding:0; 
    box-shadow: 3px 0 10px rgba(0,0,0,0.1);
}
.sidebar-header { 
    padding: 20px; 
    background-color: rgba(0,0,0,0.2); 
    border-bottom:1px solid rgba(255,255,255,0.1);
}
.sidebar-menu { 
    list-style:none; 
    padding:0; 
    margin:0;
}
.sidebar-menu li { 
    width:100%; 
}
.sidebar-menu a { 
    color: rgba(255,255,255,0.8); 
    text-decoration:none; 
    display:block; 
    padding:12px 20px; 
    border-left:3px solid transparent; 
    transition:0.3s;
}
.sidebar-menu a:hover, .sidebar-menu a.active { 
    background-color: rgba(0,0,0,0.2); 
    color:white; 
    border-left:3px solid var(--accent);
}
.sidebar-menu i { 
    width:25px; 
    text-align:center; 
    margin-right:10px;
}
.main-content { 
    padding:20px;
}
.header { 
    background-color:white; 
    padding:15px 20px; 
    border-bottom:1px solid #e0e0e0; 
    display:flex; 
    justify-content:space-between; 
    align-items:center; 
    box-shadow:0 2px 4px rgba(0,0,0,0.05);
}
.user-info { 
    display:flex; 
    align-items:center;
}
.user-info .avatar { 
    width:40px; 
    height:40px; 
    border-radius:50%; 
    background-color: var(--secondary); 
    color:white; 
    display:flex; 
    align-items:center; 
    justify-content:center; 
    font-weight:bold; 
    margin-right:10px;
}
.table-actions button { 
    margin-right:5px;
}
.modal-header { 
    background-color: var(--primary); 
    color:white;
}
.error { 
    color:red; 
    margin-bottom:10px; 
}
.table thead th {
    background-color: var(--primary);
    color: white;
    border: none;
    padding: 15px 12px;
    font-weight: 600;
}
.card {
    border: none;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    transition: transform 0.3s, box-shadow 0.3s;
    margin-bottom: 20px;
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
}
.card-header {
    background-color: white;
    border-bottom: 1px solid #eee;
    font-weight: 600;
    padding: 15px 20px;
    border-radius: 8px 8px 0 0 !important;
}
.card-body {
    padding: 20px;
}
.btn-primary {
    background-color: var(--secondary);
    border-color: var(--secondary);
}
.btn-primary:hover {
    background-color: #2980b9;
    border-color: #2980b9;
}
.btn-success {
    background-color: var(--accent);
    border-color: var(--accent);
}
.btn-success:hover {
    background-color: #16a085;
    border-color: #16a085;
}
.btn-danger {
    background-color: #e74c3c;
    border-color: #e74c3c;
}
.btn-danger:hover {
    background-color: #c0392b;
    border-color: #c0392b;
}
.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}
.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}
.actions-container {
    background-color: white;
    padding: 15px 20px;
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.action-buttons {
    display: flex;
    gap: 10px;
}
.modal-header.warning-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--dark) 100%);
    color: white;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.warning-icon {
    color: #f39c12;
    font-size: 3rem;
    margin-bottom: 15px;
}
.warning-content {
    text-align: center;
    padding: 20px 0;
}
.warning-text {
    font-size: 1.1rem;
    color: var(--dark);
    margin-bottom: 10px;
}
.warning-subtext {
    color: #e74c3c;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Welcome Section Styling */
.welcome-section {
    background: linear-gradient(135deg, var(--secondary), var(--primary));
    color: white;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 25px;
}

.welcome-section h1 {
    font-weight: 700;
}

/* Enhanced notification styles */
.notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1050;
    max-width: 350px;
}

.notification {
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 8px;
    margin-bottom: 15px;
    transform: translateX(100%);
    opacity: 0;
    transition: transform 0.4s ease-out, opacity 0.4s ease-out;
}

.notification.show {
    transform: translateX(0);
    opacity: 1;
}

.notification.hide {
    transform: translateX(100%);
    opacity: 0;
}

.notification-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 4px;
    background-color: rgba(255,255,255,0.7);
    width: 100%;
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 5s linear;
}

.notification-progress.running {
    transform: scaleX(1);
}

/* Grade level selector styling */
.grade-level-selector {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px solid var(--accent);
    border-radius: 8px;
    padding: 8px 12px;
    font-weight: 600;
    color: var(--primary);
}

.grade-level-selector:focus {
    border-color: var(--secondary);
    box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
}

.submenu {
  display: none;
  list-style: none;
  padding-left: 20px;
}

.has-submenu > a::after {
  content: "▸";
  float: right;
}

.has-submenu.open > a::after {
  content: "▾";
}

.has-submenu.open .submenu {
  display: block;
}

/* Form section styling */
.form-section {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    border-left: 4px solid var(--accent);
}

.form-section h6 {
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 10px;
}
</style>
</head>
<body>
<div class="container-fluid">
<div class="row">
    <!-- Sidebar -->
    <div class="col-md-3 col-lg-2 sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-graduation-cap"></i> EduTrack</h4>
            <p class="mb-0">Admin Portal</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>

            <li class="has-submenu">
                <a href="#"><i class="fas fa-users-cog"></i><span>Manage Users</span></a>
                <ul class="submenu">
                    <li><a href="manage_student.php">Manage Students</a></li>
                    <li><a href="manage_teachers.php" class="active">Teachers Assignments</a></li>
                    <li><a href="manage_teacheracc.php">Teachers Accounts</a></li>
                    <li><a href="manage_parents.php">Parent Accounts</a></li>
                    <li><a href="create_account.php">Create Accounts</a></li>
                    <li><a href="manage_admins.php">Manage Admins</a></li>
                </ul>
            </li>

            <li class="has-submenu">
                <a href="#"><i class="fas fa-book-open"></i><span>Academics</span></a>
                <ul class="submenu">
                    <li><a href="year_levels.php">Year Levels</a></li>
                    <li><a href="manage_sections.php">Sections</a></li>
                    <li><a href="admin_subjects_manage.php">Student Subjects</a></li>
                    <li><a href="subject_adding.php">Add Subjects</a></li>
                    <li><a href="careerpath.php">Career Result</a></li>
                </ul>
            </li>

            <li class="has-submenu">
                <a href="#"><i class="fas fa-cogs"></i><span>System</span></a>
                <ul class="submenu">
                    <li><a href="feedbacks.php">View Feedbacks</a></li>
                    <li><a href="archive_manager.php">Archive Manager</a></li>
                    <li><a href="change_pass.php">Settings</a></li>
                </ul>
            </li>

            <li><a href="logout.php" class="text-danger" onclick="return confirmLogout()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
        <div class="header">
            <h3>Manage Teachers</h3>
            <div class="user-info">
                <div class="avatar"><?= $adminInitial ?></div>
                <div>
                    <div class="fw-bold"><?= htmlspecialchars($adminName) ?></div>
                    <div class="text-muted">Administrator</div>
                </div>
            </div>
        </div>

        <div class="main-content">
            <!-- Welcome Banner -->
            <div class="welcome-section">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1>Teacher Assignments Management</h1>
                        <p>Manage teacher assignments, assign subjects and sections, and organize teaching responsibilities</p>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-chalkboard-teacher fa-4x"></i>
                    </div>
                </div>
            </div>

            <!-- Notification Container -->
            <div class="notification-container" id="notificationContainer">
                <!-- Notifications will be dynamically inserted here -->
            </div>

            <!-- Success/Error Messages (Kept for backward compatibility) -->
            <?php if(isset($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?= $success_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= $error_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Action Buttons Container -->
            <div class="actions-container">
                <h4 class="mb-0">Teacher Assignments</h4>
                <div class="action-buttons">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                        <i class="fas fa-plus me-1"></i> Add Teacher Assignment
                    </button>
                    <button class="btn btn-primary" id="editSelectedBtn" style="display: none;" data-bs-toggle="modal" data-bs-target="#editTeacherModal">
                        <i class="fas fa-edit me-1"></i> Edit Assignment
                    </button>
                    <button class="btn btn-danger" id="deleteSelectedBtn" style="display: none;" data-bs-toggle="modal" data-bs-target="#deleteTeacherModal">
                        <i class="fas fa-trash me-1"></i> Delete Assignment
                    </button>
                </div>
            </div>

            <!-- Teacher Assignments Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>#</th>
                                    <th>Teacher</th>
                                    <th>Subject</th>
                                    <th>Section</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($teacher_assignments->num_rows > 0): 
                                    $count = 1; 
                                    while($row = $teacher_assignments->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input assignment-checkbox" 
                                                   data-assignment='<?= json_encode($row) ?>' 
                                                   value="<?= $row['assignment_id'] ?>">
                                        </td>
                                        <td><?= $count++; ?></td>
                                        <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                        <td><?= htmlspecialchars($row['subject_name']) ?></td>
                                        <td><?= htmlspecialchars($row['section_name']) ?></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-chalkboard-teacher fa-2x text-muted mb-2"></i>
                                            <p class="text-muted">No teacher assignments found.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Add Teacher Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if(isset($error_msg)) echo "<div class='error'>$error_msg</div>"; ?>
                
                <!-- Teacher Selection -->
                <div class="form-section">
                    <h6><i class="fas fa-user-tie me-2"></i>Teacher Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Select Teacher</label>
                        <select name="teacher_id" class="form-select" required>
                            <option value="">Choose a teacher...</option>
                            <?php $teachers->data_seek(0); while($t = $teachers->fetch_assoc()): ?>
                                <option value="<?= $t['account_id'] ?>"><?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <!-- Grade Level and Subject Selection -->
                <div class="form-section">
                    <h6><i class="fas fa-book me-2"></i>Academic Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Grade Level</label>
                        <select id="grade_level" class="form-select grade-level-selector" required>
                            <option value="">Select grade level first...</option>
                            <?php $yearlevels->data_seek(0); while($yl = $yearlevels->fetch_assoc()): ?>
                                <option value="<?= $yl['year_level_id'] ?>"><?= htmlspecialchars($yl['level_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Choose the grade level to filter available subjects and sections
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <select name="subject_id" id="subject_id" class="form-select" required disabled>
                            <option value="">Select grade level first</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Section</label>
                        <select name="section_id" id="section_id" class="form-select" required disabled>
                            <option value="">Select grade level first</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_teacher" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i>Add Assignment
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </form>
  </div>
</div>

<!-- Edit Teacher Modal -->
<div class="modal fade" id="editTeacherModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Teacher Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="assignment_id" id="edit_assignment_id">
                
                <!-- Teacher Selection -->
                <div class="form-section">
                    <h6><i class="fas fa-user-tie me-2"></i>Teacher Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Select Teacher</label>
                        <select name="teacher_id" id="edit_teacher_id" class="form-select" required>
                            <option value="">Choose a teacher...</option>
                            <?php $teachers->data_seek(0); while($t = $teachers->fetch_assoc()): ?>
                                <option value="<?= $t['account_id'] ?>"><?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <!-- Grade Level and Subject Selection -->
                <div class="form-section">
                    <h6><i class="fas fa-book me-2"></i>Academic Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Grade Level</label>
                        <select id="edit_grade_level" class="form-select grade-level-selector" required>
                            <option value="">Select grade level first...</option>
                            <?php $yearlevels->data_seek(0); while($yl = $yearlevels->fetch_assoc()): ?>
                                <option value="<?= $yl['year_level_id'] ?>"><?= htmlspecialchars($yl['level_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <select name="subject_id" id="edit_subject_id" class="form-select" required disabled>
                            <option value="">Select grade level first</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Section</label>
                        <select name="section_id" id="edit_section_id" class="form-select" required disabled>
                            <option value="">Select grade level first</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_teacher" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Update Assignment
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </form>
  </div>
</div>

<!-- Delete Teacher Modal -->
<div class="modal fade" id="deleteTeacherModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST">
        <div class="modal-content">
            <div class="modal-header warning-header">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="assignment_id" id="delete_assignment_id">
                <div class="warning-content">
                    <div class="warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="warning-text">
                        Are you sure you want to delete <strong id="delete_assignment_name"></strong>?
                    </div>
                    <div class="warning-subtext">
                        <i class="fas fa-info-circle me-1"></i>
                        This action cannot be undone and will permanently remove this teacher assignment.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="submit" name="delete_teacher" class="btn btn-danger">
                    <i class="fas fa-trash me-1"></i> Delete Assignment
                </button>
            </div>
        </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Store data for filtering
const sectionsData = <?= $sections_json ?>;
const subjectsData = <?= $subjects_json ?>;
const yearlevelsData = <?= $yearlevels_json ?>;

// Function to populate subjects based on selected grade level
function populateSubjects(gradeLevelId, targetSelectId) {
    const subjectSelect = document.getElementById(targetSelectId);
    
    // Reset subject dropdown
    subjectSelect.innerHTML = '<option value="">Select a subject...</option>';
    
    if (!gradeLevelId) {
        subjectSelect.disabled = true;
        return;
    }
    
    // Enable the subject dropdown
    subjectSelect.disabled = false;
    
    // Filter subjects by grade level
    const filteredSubjects = subjectsData.filter(subject => subject.level_id == gradeLevelId);
    
    // Add filtered subjects to dropdown
    filteredSubjects.forEach(subject => {
        const option = document.createElement('option');
        option.value = subject.id;
        option.textContent = subject.name;
        subjectSelect.appendChild(option);
    });
}

// Function to populate sections based on selected grade level
function populateSections(gradeLevelId, targetSelectId) {
    const sectionSelect = document.getElementById(targetSelectId);
    
    // Reset section dropdown
    sectionSelect.innerHTML = '<option value="">Select a section...</option>';
    
    if (!gradeLevelId) {
        sectionSelect.disabled = true;
        return;
    }
    
    // Enable the section dropdown
    sectionSelect.disabled = false;
    
    // Filter sections by grade level
    const filteredSections = sectionsData.filter(section => section.level_id == gradeLevelId);
    
    // Add filtered sections to dropdown
    filteredSections.forEach(section => {
        const option = document.createElement('option');
        option.value = section.id;
        option.textContent = section.name;
        sectionSelect.appendChild(option);
    });
}

// Add event listeners when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add modal grade level change event for add modal
    document.getElementById('grade_level').addEventListener('change', function() {
        const gradeLevelId = this.value;
        populateSubjects(gradeLevelId, 'subject_id');
        populateSections(gradeLevelId, 'section_id');
    });
    
    // Add modal grade level change event for edit modal
    document.getElementById('edit_grade_level').addEventListener('change', function() {
        const gradeLevelId = this.value;
        populateSubjects(gradeLevelId, 'edit_subject_id');
        populateSections(gradeLevelId, 'edit_section_id');
    });
});

// Notification system
function showNotification(message, type = 'success') {
    const notificationContainer = document.getElementById('notificationContainer');
    const notificationId = 'notification-' + Date.now();
    
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    
    const notification = document.createElement('div');
    notification.id = notificationId;
    notification.className = `notification alert ${alertClass} alert-dismissible fade show`;
    notification.innerHTML = `
        <i class="fas ${icon} me-2"></i> ${message}
        <button type="button" class="btn-close" onclick="dismissNotification('${notificationId}')"></button>
        <div class="notification-progress"></div>
    `;
    
    notificationContainer.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
        notification.classList.add('show');
        
        // Start progress bar
        setTimeout(() => {
            const progressBar = notification.querySelector('.notification-progress');
            progressBar.classList.add('running');
        }, 50);
    }, 10);
    
    // Auto-dismiss after 5 seconds
    const autoDismissTimer = setTimeout(() => {
        dismissNotification(notificationId);
    }, 5000);
    
    // Store timer reference for potential cleanup
    notification.autoDismissTimer = autoDismissTimer;
    
    // Pause dismissal on hover
    notification.addEventListener('mouseenter', () => {
        clearTimeout(autoDismissTimer);
        const progressBar = notification.querySelector('.notification-progress');
        progressBar.classList.remove('running');
        progressBar.style.width = progressBar.offsetWidth + 'px';
    });
    
    // Resume dismissal when mouse leaves
    notification.addEventListener('mouseleave', () => {
        const progressBar = notification.querySelector('.notification-progress');
        progressBar.classList.add('running');
        
        notification.autoDismissTimer = setTimeout(() => {
            dismissNotification(notificationId);
        }, 5000);
    });
}

function dismissNotification(notificationId) {
    const notification = document.getElementById(notificationId);
    if (notification) {
        // Clear any active timer
        if (notification.autoDismissTimer) {
            clearTimeout(notification.autoDismissTimer);
        }
        
        // Start hide animation
        notification.classList.remove('show');
        notification.classList.add('hide');
        
        // Remove from DOM after animation completes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 400);
    }
}

// Check for success messages from PHP and show notifications
document.addEventListener('DOMContentLoaded', function() {
    <?php if(isset($_GET['success'])): ?>
        <?php if($_GET['success'] == 1): ?>
            showNotification('Teacher assignment added successfully!');
        <?php elseif($_GET['success'] == 2): ?>
            showNotification('Teacher assignment updated successfully!');
        <?php elseif($_GET['success'] == 3): ?>
            showNotification('Teacher assignment deleted successfully!');
        <?php endif; ?>
        
        // Clean URL without reloading
        const url = new URL(window.location);
        url.searchParams.delete('success');
        window.history.replaceState({}, '', url);
    <?php endif; ?>
    
    <?php if(isset($error_msg)): ?>
        showNotification('<?= addslashes($error_msg) ?>', 'error');
    <?php endif; ?>
});

let selectedAssignment = null;

// Handle checkbox selection
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.assignment-checkbox');
    const editBtn = document.getElementById('editSelectedBtn');
    const deleteBtn = document.getElementById('deleteSelectedBtn');

    // Select all functionality
    selectAll.addEventListener('change', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateActionButtons();
    });

    // Individual checkbox handling
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedBoxes = document.querySelectorAll('.assignment-checkbox:checked');
            selectAll.checked = checkedBoxes.length === checkboxes.length;
            selectAll.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
            updateActionButtons();
        });
    });

    function updateActionButtons() {
        const checkedBoxes = document.querySelectorAll('.assignment-checkbox:checked');
        if (checkedBoxes.length === 1) {
            selectedAssignment = JSON.parse(checkedBoxes[0].getAttribute('data-assignment'));
            editBtn.style.display = 'inline-block';
            deleteBtn.style.display = 'inline-block';
        } else if (checkedBoxes.length > 1) {
            editBtn.style.display = 'none';
            deleteBtn.style.display = 'inline-block';
        } else {
            editBtn.style.display = 'none';
            deleteBtn.style.display = 'none';
            selectedAssignment = null;
        }
    }
});

// Pre-fill Edit Modal
var editModal = document.getElementById('editTeacherModal');
editModal.addEventListener('show.bs.modal', function (event) {
    if (selectedAssignment) {
        // Get the subject data to find the grade level
        const selectedSubject = subjectsData.find(subject => subject.id == selectedAssignment.subject_id);
        
        if (selectedSubject) {
            // Set the grade level first
            document.getElementById('edit_grade_level').value = selectedSubject.level_id;
            
            // Populate subjects and sections based on grade level
            populateSubjects(selectedSubject.level_id, 'edit_subject_id');
            populateSections(selectedSubject.level_id, 'edit_section_id');
            
            // Then set the specific values
            setTimeout(() => {
                document.getElementById('edit_assignment_id').value = selectedAssignment.assignment_id;
                document.getElementById('edit_teacher_id').value = selectedAssignment.teacher_id;
                document.getElementById('edit_subject_id').value = selectedAssignment.subject_id;
                document.getElementById('edit_section_id').value = selectedAssignment.section_id;
            }, 50);
        }
    }
});

// Pre-fill Delete Modal
var deleteModal = document.getElementById('deleteTeacherModal');
deleteModal.addEventListener('show.bs.modal', function (event) {
    if (selectedAssignment) {
        document.getElementById('delete_assignment_id').value = selectedAssignment.assignment_id;
        document.getElementById('delete_assignment_name').textContent = 
            selectedAssignment.first_name + ' ' + selectedAssignment.last_name + 
            " (" + selectedAssignment.subject_name + " - " + selectedAssignment.section_name + ")";
    }
});

// Reset modals when they are hidden
document.getElementById('addTeacherModal').addEventListener('hidden.bs.modal', function () {
    // Reset all form fields
    this.querySelector('form').reset();
    
    // Reset dropdowns to disabled state
    document.getElementById('subject_id').disabled = true;
    document.getElementById('section_id').disabled = true;
    document.getElementById('subject_id').innerHTML = '<option value="">Select grade level first</option>';
    document.getElementById('section_id').innerHTML = '<option value="">Select grade level first</option>';
});

document.getElementById('editTeacherModal').addEventListener('hidden.bs.modal', function () {
    // Reset all form fields
    this.querySelector('form').reset();
    
    // Reset dropdowns to disabled state
    document.getElementById('edit_subject_id').disabled = true;
    document.getElementById('edit_section_id').disabled = true;
    document.getElementById('edit_subject_id').innerHTML = '<option value="">Select grade level first</option>';
    document.getElementById('edit_section_id').innerHTML = '<option value="">Select grade level first</option>';
});

// Enhanced logout confirmation
function confirmLogout() {
    if (confirm('Are you sure you want to logout?')) {
        localStorage.clear();
        sessionStorage.clear();
        return true;
    }
    return false;
}

// Set up logout link handler
const logoutLink = document.querySelector('a[href="logout.php"]');
if (logoutLink) {
    logoutLink.addEventListener('click', function(e) {
        if (!confirmLogout()) {
            e.preventDefault();
        }
    });
}

// Toggle submenu
document.querySelectorAll(".has-submenu > a").forEach(link => {
    link.addEventListener("click", e => {
        e.preventDefault();
        link.parentElement.classList.toggle("open");
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?>