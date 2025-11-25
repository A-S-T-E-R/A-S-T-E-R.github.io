<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");


require 'db_connect.php';

// Ensure only logged-in admins can access
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
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
        $_SESSION['fullname'] = $adminName;
    }
} catch (Exception $e) {
    // Fallback handling
}

// Fetch all students for the dropdown
$students = $conn->query("
    SELECT s.student_id, CONCAT(a.first_name, ' ', a.last_name) AS fullname,
           yl.level_name, sec.section_name
    FROM tbl_students s
    JOIN tbl_accounts a ON s.account_id = a.account_id
    LEFT JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id
    LEFT JOIN tbl_sections sec ON s.section_id = sec.section_id
    ORDER BY a.first_name, a.last_name
")->fetch_all(MYSQLI_ASSOC);
$selected_student_id = $_POST['student_id'] ?? $_GET['student_id'] ?? null;
$enrolled_subjects = [];
$available_subjects = [];
$student_info = [];
$show_available_subjects = false; // Add this flag - ONLY FOR SENIOR HIGH

if ($selected_student_id) {
    // Fetch student information including year level
    $stmt_info = $conn->prepare("
        SELECT s.student_id, CONCAT(a.first_name, ' ', a.last_name) AS fullname,
               yl.level_name, yl.year_level_id, yl.education_stage, sec.section_name
        FROM tbl_students s
        JOIN tbl_accounts a ON s.account_id = a.account_id
        LEFT JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id
        LEFT JOIN tbl_sections sec ON s.section_id = sec.section_id
        WHERE s.student_id = ?
    ");
    $stmt_info->bind_param("i", $selected_student_id);
    $stmt_info->execute();
    $student_info = $stmt_info->get_result()->fetch_assoc();
    $stmt_info->close();

    // Determine if we should show available subjects (only for Senior High)
    $education_stage = $student_info['education_stage'] ?? '';
    $show_available_subjects = ($education_stage === 'Senior High');
    // Fetch enrolled subjects for selected student
    $stmt_enrolled = $conn->prepare("
        SELECT s.subject_id, s.subject_name, e.lecture_units, e.lab_units
        FROM tbl_subject_enrollments e
        JOIN tbl_subjects s ON e.subject_id = s.subject_id
        WHERE e.student_id = ?
        ORDER BY s.subject_name
    ");
    $stmt_enrolled->bind_param("i", $selected_student_id);
    $stmt_enrolled->execute();
    $enrolled_subjects = $stmt_enrolled->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_enrolled->close();

    // Fetch available subjects for selected student
    $year_level_id = $student_info['year_level_id'] ?? null;
    $education_stage = $student_info['education_stage'] ?? null;
    
        if ($show_available_subjects) {
        $year_level_id = $student_info['year_level_id'] ?? null;
        
        if ($year_level_id) {
            // For Senior High students, show all subjects for their year level
            $stmt_available = $conn->prepare("
                SELECT s.subject_id, s.subject_name
                FROM tbl_subjects s
                WHERE s.subject_id NOT IN (
                    SELECT subject_id FROM tbl_subject_enrollments WHERE student_id = ?
                )
                AND s.year_level_id = ?
                ORDER BY s.subject_name
            ");
            $stmt_available->bind_param("ii", $selected_student_id, $year_level_id);
            $stmt_available->execute();
            $available_subjects = $stmt_available->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_available->close();
        } else {
            // If year level is not set, show all available subjects
            $stmt_available = $conn->prepare("
                SELECT s.subject_id, s.subject_name
                FROM tbl_subjects s
                WHERE s.subject_id NOT IN (
                    SELECT subject_id FROM tbl_subject_enrollments WHERE student_id = ?
                )
                ORDER BY s.subject_name
            ");
            $stmt_available->bind_param("i", $selected_student_id);
            $stmt_available->execute();
            $available_subjects = $stmt_available->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_available->close();
        }
    }
}
// Handle subject enrollment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll_subject'])) {
    $subject_id = $_POST['subject_id'];
    $student_id = $_POST['student_id_hidden'];
    
    // Check if already enrolled
    $check_stmt = $conn->prepare("SELECT * FROM tbl_subject_enrollments WHERE student_id = ? AND subject_id = ?");
    $check_stmt->bind_param("ii", $student_id, $subject_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        // Get student's section_id
        $section_stmt = $conn->prepare("SELECT section_id FROM tbl_students WHERE student_id = ?");
        $section_stmt->bind_param("i", $student_id);
        $section_stmt->execute();
        $student_section = $section_stmt->get_result()->fetch_assoc();
        $section_id = $student_section['section_id'];
        $section_stmt->close();
        
        // Enroll the subject (using default values for units)
        $enroll_stmt = $conn->prepare("INSERT INTO tbl_subject_enrollments (student_id, subject_id, lecture_units, lab_units) VALUES (?, ?, 0, 0)");
        $enroll_stmt->bind_param("ii", $student_id, $subject_id);
        $enroll_stmt->execute();
        $enroll_stmt->close();
        
        // AUTO-ASSIGN TEACHER: Find a teacher assigned to this subject and section
        $teacher_assign_stmt = $conn->prepare("
            SELECT ta.teacher_account_id 
            FROM tbl_teacher_assignments ta 
            WHERE ta.subject_id = ? AND ta.section_id = ?
            LIMIT 1
        ");
        $teacher_assign_stmt->bind_param("ii", $subject_id, $section_id);
        $teacher_assign_stmt->execute();
        $teacher_result = $teacher_assign_stmt->get_result();
        
        if ($teacher_result->num_rows == 0) {
            // If no teacher is assigned, assign the first available teacher for this subject
            $find_teacher_stmt = $conn->prepare("
                SELECT ta.teacher_account_id 
                FROM tbl_teacher_assignments ta 
                WHERE ta.subject_id = ?
                LIMIT 1
            ");
            $find_teacher_stmt->bind_param("i", $subject_id);
            $find_teacher_stmt->execute();
            $available_teacher = $find_teacher_stmt->get_result()->fetch_assoc();
            $find_teacher_stmt->close();
            
            if ($available_teacher) {
                // Assign this teacher to the student's section for this subject
                $assign_teacher_stmt = $conn->prepare("
                    INSERT INTO tbl_teacher_assignments (teacher_account_id, subject_id, section_id) 
                    VALUES (?, ?, ?)
                ");
                $assign_teacher_stmt->bind_param("iii", $available_teacher['teacher_account_id'], $subject_id, $section_id);
                $assign_teacher_stmt->execute();
                $assign_teacher_stmt->close();
            } else {
                $_SESSION['warning'] = "Warning: No teacher is currently assigned to this subject. The student will be enrolled but won't appear on any teacher's dashboard until a teacher is assigned.";
            }
        }
        $teacher_assign_stmt->close();
        
        // Log the action
        $log_stmt = $conn->prepare("INSERT INTO tbl_audit_logs (account_id, action) VALUES (?, ?)");
        $log_action = "Enrolled student ID $student_id in subject ID $subject_id";
        $log_stmt->bind_param("is", $_SESSION['account_id'], $log_action);
        $log_stmt->execute();
        $log_stmt->close();
        
        // Refresh the page to show updated lists
        header("Location: admin_subjects_manage.php?student_id=".$student_id);
        exit();
    }
    $check_stmt->close();
}

// Handle subject dropping
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['drop_subject'])) {
    $subject_id = $_POST['subject_id'];
    $student_id = $_POST['student_id_hidden'];
    
    $drop_stmt = $conn->prepare("DELETE FROM tbl_subject_enrollments WHERE student_id = ? AND subject_id = ?");
    $drop_stmt->bind_param("ii", $student_id, $subject_id);
    $drop_stmt->execute();
    $drop_stmt->close();
    
    // Log the action
    $log_stmt = $conn->prepare("INSERT INTO tbl_audit_logs (account_id, action) VALUES (?, ?)");
    $log_action = "Dropped student ID $student_id from subject ID $subject_id";
    $log_stmt->bind_param("is", $_SESSION['account_id'], $log_action);
    $log_stmt->execute();
    $log_stmt->close();
    
    // Refresh the page to show updated lists
    header("Location: admin_subjects_manage.php?student_id=".$student_id);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>EduTrack - Admin Manage Student Subjects</title>
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
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background-color: var(--primary);
            color: white;
            min-height: 100vh;
            padding: 0;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 20px;
            background-color: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            width: 100%;
        }
        
        .sidebar-menu a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            display: block;
            padding: 12px 20px;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover {
            background-color: rgba(0,0,0,0.2);
            color: white;
            border-left: 3px solid var(--accent);
        }
        
        .sidebar-menu a.active {
            background-color: rgba(0,0,0,0.2);
            color: white;
            border-left: 3px solid var(--accent);
        }
        
        .sidebar-menu i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }
        
        .main-content {
            padding: 20px;
        }
        
        .header {
            background-color: white;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--secondary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
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
            display: flex;
            align-items: center;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .badge-admin {
            background-color: var(--accent);
        }
        
        .table th {
            background-color: var(--light);
            font-weight: 600;
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
        
        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.5rem;
        }
        
        .card-icon.blue {
            background-color: #e3f2fd;
            color: var(--secondary);
        }
        
        .card-icon.green {
            background-color: #e8f5e9;
            color: var(--accent);
        }
        
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
        
        .student-selector {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
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
                    <li><a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>

                      <li class="has-submenu">
                        <a href="#"><i class="fas fa-users-cog"></i><span>Manage Users</span></a>
                        <ul class="submenu">
                          <li><a href="manage_student.php">Manage Students</a></li>
                          <li><a href="manage_teachers.php">Teachers Assignments</a></li>
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
            <div class="col-md-9 col-lg-10 ml-sm-auto px-4"> <div class="header"> <h3>Manage Student Subjects</h3> <div class="user-info">
    <div class="avatar"><?= $adminInitial ?></div>
    <div>
        <div class="fw-bold"><?= htmlspecialchars($adminName) ?></div>
        <div class="text-muted">Administrator</div>
    </div>
 </div> </div>
            <div class="main-content">
    <?php if (isset($_SESSION['warning'])): ?>
    <div class="alert alert-warning alert-dismissible fade show mx-4 mt-3" role="alert">
        <i class="fas fa-exclamation-triangle"></i> 
        <?= $_SESSION['warning'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>
    
    <!-- Welcome Banner -->
    <div class="welcome-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1>Student Subject Management</h1>
                <p>Enroll and drop students from subjects as an administrator</p>
            </div>
            <div class="col-md-4 text-center">
                <i class="fas fa-user-edit fa-4x"></i>
            </div>
        </div>
    </div>

    <!-- Student Selection -->
    <div class="student-selector">
        <h5><i class="fas fa-user-graduate"></i> Select Student</h5>
        <form method="POST" class="row g-3">
            <div class="col-md-8">
                <select name="student_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Select a Student --</option>
                    <?php foreach($students as $student): ?>
                        <option value="<?= $student['student_id'] ?>" <?= ($selected_student_id == $student['student_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['fullname']) ?> 
                            (<?= $student['level_name'] ?? 'No Year Level' ?>, <?= $student['section_name'] ?? 'No Section' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($selected_student_id): ?>
        <?php
        // Get selected student name for display
        $selected_student_name = '';
        foreach($students as $student) {
            if($student['student_id'] == $selected_student_id) {
                $selected_student_name = $student['fullname'];
                break;
            }
        }
        ?>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            Managing subjects for: <strong><?= htmlspecialchars($selected_student_name) ?></strong>
            (<?= $student_info['level_name'] ?? 'No Year Level' ?>, 
            Section: <?= $student_info['section_name'] ?? 'No Section' ?>)
            <?php if (!$show_available_subjects && isset($student_info['education_stage'])): ?>
            <br><small>Note: Subject enrollment is automatic for <?= $student_info['education_stage'] ?> students</small>
            <?php endif; ?>
        </div>

        <!-- Enrolled Subjects Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-icon blue">
                    <i class="fas fa-bookmark"></i>
                </div>
                <div>
                    <h5 class="mb-0">Enrolled Subjects</h5>
                    <p class="mb-0 text-muted"><?= count($enrolled_subjects) ?> subjects enrolled</p>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($enrolled_subjects)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        This student is not enrolled in any subjects yet.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Subject Name</th>
                                    <th>Lecture Units</th>
                                    <th>Lab Units</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrolled_subjects as $subject): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                                        <td><?= $subject['lecture_units'] ?></td>
                                        <td><?= $subject['lab_units'] ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="subject_id" value="<?= $subject['subject_id'] ?>">
                                                <input type="hidden" name="student_id_hidden" value="<?= $selected_student_id ?>">
                                                <button type="submit" name="drop_subject" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to drop this student from this subject?')">
                                                    <i class="fas fa-times"></i> Drop
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Available Subjects Card - Only show for Senior High students -->
        <?php if ($show_available_subjects): ?>
        <div class="card">
            <div class="card-header">
                <div class="card-icon green">
                    <i class="fas fa-book-open"></i>
                </div>
                <div>
                    <h5 class="mb-0">Available Subjects</h5>
                    <p class="mb-0 text-muted"><?= count($available_subjects) ?> subjects available for enrollment</p>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($available_subjects)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        This student is already enrolled in all available subjects for their year level.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Subject Name</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($available_subjects as $subject): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="subject_id" value="<?= $subject['subject_id'] ?>">
                                                <input type="hidden" name="student_id_hidden" value="<?= $selected_student_id ?>">
                                                <button type="submit" name="enroll_subject" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to enroll this student in this subject?')">
                                                    <i class="fas fa-plus"></i> Enroll
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <!-- Information message for non-Senior High students -->
        <?php if ($selected_student_id && isset($student_info['education_stage']) && $student_info['education_stage'] !== 'Senior High'): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            Subject enrollment is automatically managed for <?= htmlspecialchars($student_info['education_stage']) ?> students.
            Students in Elementary (Grade 1-6) and Junior High (Grade 7-10) are automatically enrolled in all subjects for their year level.
        </div>
        <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
</div> </div> </div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script><script> // Enhanced session management script document.addEventListener('DOMContentLoaded', function() { // Enhanced logout confirmation function confirmLogout() { if (confirm('Are you sure you want to logout?')) { // Clear client-side storage localStorage.clear(); sessionStorage.clear(); return true; } return false; } // Set up logout link handler const logoutLink = document.querySelector('a[href="logout.php"]'); if (logoutLink) { logoutLink.addEventListener('click', function(e) { if (!confirmLogout()) { e.preventDefault(); } }); } // Manage browser history for better navigation if (window.history && window.history.pushState) { // Add a new history entry window.history.pushState(null, null, window.location.href); // Handle back/forward navigation window.addEventListener('popstate', function() { // Check if we should reload (optional) if (performance.navigation.type === 2) { window.location.reload(); } }); } }); // Handle page visibility changes window.onpageshow = function(event) { if (event.persisted) { // Page was loaded from cache (like when using back button) window.location.reload(); } }; 
// toggle submenu
    document.querySelectorAll(".has-submenu > a").forEach(link => {
      link.addEventListener("click", e => {
        e.preventDefault();
        link.parentElement.classList.toggle("open");
      });
    });</script></body> </html> <?php $conn->close(); ?>