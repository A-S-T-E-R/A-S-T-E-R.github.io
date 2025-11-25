<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require 'db_connect.php';
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
// Ensure only logged-in admins can access
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Fetch all year levels for dropdown
$year_levels = $conn->query("
    SELECT year_level_id, level_name, education_stage 
    FROM tbl_yearlevels 
    ORDER BY year_level_id
")->fetch_all(MYSQLI_ASSOC);

// Fetch all strands for filtering
$strands = $conn->query("
    SELECT strand_id, strand_name, strand_code 
    FROM tbl_strands 
    ORDER BY strand_name
")->fetch_all(MYSQLI_ASSOC);

// Get filter parameters
$selected_strand = $_GET['strand'] ?? '';
$selected_year_level = $_GET['year_level'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];
$types = '';

if ($selected_strand) {
    $where_conditions[] = "s.strand_id = ?";
    $params[] = $selected_strand;
    $types .= 'i';
}

if ($selected_year_level) {
    $where_conditions[] = "s.year_level_id = ?";
    $params[] = $selected_year_level;
    $types .= 'i';
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Fetch all subjects with year level and strand information
$subjects_query = "
    SELECT DISTINCT s.subject_id, s.subject_name, s.year_level_id, s.semester, s.strand_id,
           COALESCE(yl.level_name, 'Ungrouped') as level_name, 
           COALESCE(yl.education_stage, 'Ungrouped') as education_stage,
           COALESCE(str.strand_name, 'General') as strand_name,
           COALESCE(str.strand_code, 'GEN') as strand_code
    FROM tbl_subjects s
    LEFT JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id
    LEFT JOIN tbl_strands str ON s.strand_id = str.strand_id
    $where_clause
    ORDER BY yl.year_level_id, str.strand_name, s.subject_name
";

// Prepare and execute query with parameters if any
if ($params) {
    $stmt = $conn->prepare($subjects_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $subjects = $conn->query($subjects_query)->fetch_all(MYSQLI_ASSOC);
}

// Handle subject addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_subject'])) {
    $subject_name = trim($_POST['subject_name']);
    $year_level_id = $_POST['year_level_id'];
    $strand_id = !empty($_POST['strand_id']) ? $_POST['strand_id'] : null; // Get strand if provided
    
    // Set semester based on grade level
    $semester = 'Full Year'; // Default value
    if ($year_level_id == 11 || $year_level_id == 12) {
        $semester = $_POST['semester'] ?? 'Full Year';
    }
    
    // Validate input
    if (empty($subject_name)) {
        $_SESSION['error'] = "Subject name is required.";
    } else {
        // Check if subject already exists for this year level and strand
        if ($strand_id) {
            $check_stmt = $conn->prepare("SELECT * FROM tbl_subjects WHERE subject_name = ? AND year_level_id = ? AND strand_id = ?");
            $check_stmt->bind_param("sii", $subject_name, $year_level_id, $strand_id);
        } else {
            $check_stmt = $conn->prepare("SELECT * FROM tbl_subjects WHERE subject_name = ? AND year_level_id = ? AND (strand_id IS NULL OR strand_id = '')");
            $check_stmt->bind_param("si", $subject_name, $year_level_id);
        }
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $_SESSION['error'] = "Subject '$subject_name' already exists for this year level" . ($strand_id ? " and strand" : "") . ".";
        } else {
            // Insert new subject with strand
            $insert_stmt = $conn->prepare("INSERT INTO tbl_subjects (subject_name, year_level_id, semester, strand_id) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("sisi", $subject_name, $year_level_id, $semester, $strand_id);
            
            if ($insert_stmt->execute()) {
                $strand_text = $strand_id ? " for strand" : "";
                $_SESSION['success'] = "Subject '$subject_name' added successfully$strand_text.";
                
                // Log the action
                $log_stmt = $conn->prepare("INSERT INTO tbl_audit_logs (account_id, action) VALUES (?, ?)");
                $log_action = "Added subject: $subject_name for year level ID $year_level_id" . ($strand_id ? " and strand ID $strand_id" : "");
                $log_stmt->bind_param("is", $_SESSION['account_id'], $log_action);
                $log_stmt->execute();
                $log_stmt->close();
                
                // Refresh the page to show updated list
                header("Location: subject_adding.php");
                exit();
            } else {
                $_SESSION['error'] = "Error adding subject: " . $conn->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle subject deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_subject'])) {
    $subject_id = $_POST['subject_id'];
    
    // Get subject name for confirmation message
    $subject_stmt = $conn->prepare("SELECT subject_name FROM tbl_subjects WHERE subject_id = ?");
    $subject_stmt->bind_param("i", $subject_id);
    $subject_stmt->execute();
    $subject_result = $subject_stmt->get_result();
    $subject_name = $subject_result->fetch_assoc()['subject_name'];
    $subject_stmt->close();
    
    // Delete subject
    $delete_stmt = $conn->prepare("DELETE FROM tbl_subjects WHERE subject_id = ?");
    $delete_stmt->bind_param("i", $subject_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['success'] = "Subject '$subject_name' deleted successfully.";
        
        // Log the action
        $log_stmt = $conn->prepare("INSERT INTO tbl_audit_logs (account_id, action) VALUES (?, ?)");
        $log_action = "Deleted subject ID $subject_id: $subject_name";
        $log_stmt->bind_param("is", $_SESSION['account_id'], $log_action);
        $log_stmt->execute();
        $log_stmt->close();
        
        // Refresh the page to show updated list
        header("Location: subject_adding.php");
        exit();
    } else {
        $_SESSION['error'] = "Error deleting subject: " . $conn->error;
    }
    $delete_stmt->close();
}

// Handle subject editing - FIXED VERSION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_subject'])) {
    $subject_id = intval($_POST['subject_id']);
    $subject_name = trim($_POST['edit_subject_name']);
    
    // Get the current year_level_id and semester from the database
    $current_stmt = $conn->prepare("SELECT year_level_id, semester FROM tbl_subjects WHERE subject_id = ?");
    $current_stmt->bind_param("i", $subject_id);
    $current_stmt->execute();
    $current_result = $current_stmt->get_result();
    $current_data = $current_result->fetch_assoc();
    $year_level_id = $current_data['year_level_id'];
    $current_semester = $current_data['semester'];
    $current_stmt->close();

    // Set semester based on grade level
    $semester = $current_semester; // Keep the current semester by default
    if ($year_level_id == 11 || $year_level_id == 12) {
        $semester = $_POST['edit_semester'] ?? $current_semester;
    }

    // Validate input
    if (empty($subject_name)) {
        $_SESSION['error'] = "Subject name is required.";
    } else {
        // Check if subject already exists for this year level (excluding current subject)
        $check_stmt = $conn->prepare("SELECT * FROM tbl_subjects WHERE subject_name = ? AND year_level_id = ? AND subject_id != ?");
        $check_stmt->bind_param("sii", $subject_name, $year_level_id, $subject_id);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $_SESSION['error'] = "Subject '$subject_name' already exists for this year level.";
        } else {
            // Update subject
            $update_stmt = $conn->prepare("
                UPDATE tbl_subjects 
                SET subject_name = ?, semester = ? 
                WHERE subject_id = ?
            ");
            $update_stmt->bind_param("ssi", $subject_name, $semester, $subject_id);

            if ($update_stmt->execute()) {
                $_SESSION['success'] = "Subject '$subject_name' updated successfully.";

                // Log the action
                $log_stmt = $conn->prepare("INSERT INTO tbl_audit_logs (account_id, action) VALUES (?, ?)");
                $log_action = "Updated subject ID $subject_id: $subject_name";
                $log_stmt->bind_param("is", $_SESSION['account_id'], $log_action);
                $log_stmt->execute();
                $log_stmt->close();

                // Refresh the page to show updated list
                header("Location: subject_adding.php");
                exit();
            } else {
                $_SESSION['error'] = "Error updating subject: " . $update_stmt->error;
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
}

// Function to group subjects by education stage, year level, and strand
function groupSubjectsByEducationStage($subjects) {
    $grouped = [];
    
    foreach ($subjects as $subject) {
        // Use null coalescing to provide default values if keys don't exist
        $education_stage = $subject['education_stage'] ?? 'Ungrouped';
        $year_level = $subject['level_name'] ?? 'General';
        $strand = $subject['strand_name'] ?? 'General';
        
        if (!isset($grouped[$education_stage])) {
            $grouped[$education_stage] = [];
        }
        
        if (!isset($grouped[$education_stage][$year_level])) {
            $grouped[$education_stage][$year_level] = [];
        }
        
        if (!isset($grouped[$education_stage][$year_level][$strand])) {
            $grouped[$education_stage][$year_level][$strand] = [];
        }
        
        $grouped[$education_stage][$year_level][$strand][] = $subject;
    }
    
    return $grouped;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="google" content="notranslate">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta http-equiv="Content-Language" content="en">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>EduTrack - Add Subjects by Grade</title>
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
        
        .education-stage-header {
            background-color: #e9ecef;
            padding: 10px 15px;
            margin-top: 20px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .year-level-header {
            background-color: #f8f9fa;
            padding: 8px 15px;
            margin-top: 15px;
            border-left: 4px solid var(--secondary);
            border-radius: 4px;
            font-weight: 600;
        }
        
        .semester-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        
        .subject-card {
            transition: all 0.3s ease;
        }
        
        .subject-card:hover {
            background-color: #f8f9fa !important;
            transform: translateY(-2px);
        }
        
        .action-buttons {
            transition: opacity 0.3s ease;
        }
        
        .edit-mode .subject-card {
            background-color: #e8f5e9 !important;
        }
        
        .delete-mode .subject-card {
            background-color: #ffe6e6 !important;
        }
        
        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* Hide semester field by default */
        .semester-field {
            display: none;
        }
        
        /* Hide strand field by default */
        .strand-field {
            display: none;
        }
        
        /* Action buttons styling */
        .action-buttons {
            display: none;
        }
        
        .delete-mode .action-buttons,
        .edit-mode .action-buttons {
            display: flex !important;
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
            <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
                <div class="header">
                    <h3>Add Subjects by Grade</h3>
                    <div class="user-info">
    <div class="avatar"><?= $adminInitial ?></div>
    <div>
        <div class="fw-bold"><?= htmlspecialchars($adminName) ?></div>
        <div class="text-muted">Administrator</div>
    </div>
</div>
                </div>

                <div class="main-content">
                    <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show mx-4 mt-3" role="alert">
                        <i class="fas fa-exclamation-circle"></i> 
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show mx-4 mt-3" role="alert">
                        <i class="fas fa-check-circle"></i> 
                        <?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    
                    <!-- Welcome Banner -->
                    <div class="welcome-section">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1>Subject Management</h1>
                                <p>Add and manage subjects by grade level</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-book fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    

                    <!-- Add Subject Form -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon blue">
                                <i class="fas fa-plus"></i>
                            </div>
                            <div>
                                <h5 class="mb-0">Add New Subject</h5>
                                <p class="mb-0 text-muted">Create a new subject for a specific grade level</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="subject_name" class="form-label">Subject Name</label>
                                        <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="year_level_id" class="form-label">Grade Level</label>
                                        <select class="form-select" id="year_level_id" name="year_level_id" required onchange="toggleStrandField()">
                                            <option value="">-- Select Grade Level --</option>
                                            <?php foreach($year_levels as $level): ?>
                                                <option value="<?= $level['year_level_id'] ?>">
                                                    <?= htmlspecialchars($level['level_name']) ?> (<?= $level['education_stage'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 strand-field">
                                        <label for="strand_id" class="form-label">Strand</label>
                                        <select class="form-select" id="strand_id" name="strand_id">
                                            <option value="">-- Select Strand --</option>
                                            <?php foreach($strands as $strand): ?>
                                                <option value="<?= $strand['strand_id'] ?>">
                                                    <?= htmlspecialchars($strand['strand_name']) ?> (<?= $strand['strand_code'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 semester-field">
                                        <label for="semester" class="form-label">Semester</label>
                                        <select class="form-select" id="semester" name="semester">
                                            <option value="Full Year">Full Year</option>
                                            <option value="1">1st Semester</option>
                                            <option value="2">2nd Semester</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" name="add_subject" class="btn btn-success w-100">
                                            <i class="fas fa-plus"></i> Add Subject
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Existing Subjects Card -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">Existing Subjects</h5>
                                <p class="mb-0 text-muted">Organized by education stage, grade level, and strand</p>
                            </div>
                            <!-- Toggle buttons -->
                            <div>
                                <button id="toggleEdit" class="btn btn-sm btn-outline-primary me-2">
                                    <i class="fas fa-edit"></i> Edit Mode
                                </button>
                                <button id="toggleDelete" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i> Delete Mode
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($subjects)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    No subjects found. Add your first subject using the form above.
                                </div>
                            <?php else: ?>
                                <div class="accordion" id="subjectsAccordion">
                                    <?php 
                                    $grouped_subjects = groupSubjectsByEducationStage($subjects);
                                    $stageIndex = 0;

                                    foreach ($grouped_subjects as $education_stage => $year_levels): 
                                        $stageIndex++;
                                    ?>
                                        <div class="accordion-item mb-2">
                                            <h2 class="accordion-header" id="heading<?= $stageIndex ?>">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $stageIndex ?>">
                                                    <strong><?= $education_stage ?> Subjects</strong>
                                                </button>
                                            </h2>
                                            <div id="collapse<?= $stageIndex ?>" class="accordion-collapse collapse" data-bs-parent="#subjectsAccordion">
                                                <div class="accordion-body">
                                                    <?php foreach ($year_levels as $year_level => $strands): ?>
                                                        <h6 class="mt-3 text-primary"><?= $year_level ?></h6>
                                                        <?php foreach ($strands as $strand => $subjects_list): ?>
                                                            <?php if ($strand !== 'General'): ?>
                                                                <h6 class="mt-2 text-secondary">
                                                                    <small><i class="fas fa-graduation-cap"></i> Strand: <?= $strand ?></small>
                                                                </h6>
                                                            <?php endif; ?>
                                                            <div class="row">
                                                                <?php foreach ($subjects_list as $subject): ?>
                                                                    <div class="col-md-4 mb-2">
                                                                        <div class="subject-card border rounded p-2 bg-light d-flex justify-content-between align-items-center">
                                                                            <span>
                                                                                <i class="fas fa-book text-secondary"></i>
                                                                                <?= htmlspecialchars($subject['subject_name']) ?>
                                                                                <?php if ($subject['year_level_id'] == 11 || $subject['year_level_id'] == 12): ?>
                                                                                <span class="badge bg-info ms-1">
                                                                                    <?= $subject['semester'] === '1' ? '1st Sem' : 
                                                                                        ($subject['semester'] === '2' ? '2nd Sem' : 'Full Year') ?>
                                                                                </span>
                                                                                <?php endif; ?>
                                                                                <?php if ($subject['strand_name'] !== 'General'): ?>
                                                                                <span class="badge bg-warning ms-1">
                                                                                    <?= $subject['strand_code'] ?>
                                                                                </span>
                                                                                <?php endif; ?>
                                                                            </span>

                                                                            <!-- Action buttons -->
                                                                            <div class="action-buttons">
                                                                                <button type="button" class="btn btn-sm btn-primary me-1 edit-btn" 
                                                                                    data-subject-id="<?= $subject['subject_id'] ?>"
                                                                                    data-subject-name="<?= htmlspecialchars($subject['subject_name']) ?>"
                                                                                    data-year-level="<?= $subject['year_level_id'] ?>"
                                                                                    data-semester="<?= $subject['semester'] ?>">
                                                                                    <i class="fas fa-edit"></i>
                                                                                </button>
                                                                                <form method="POST" class="d-inline">
                                                                                    <input type="hidden" name="subject_id" value="<?= $subject['subject_id'] ?>">
                                                                                    <button type="submit" name="delete_subject" class="btn btn-sm btn-danger"
                                                                                        onclick="return confirm('Are you sure you want to delete this subject? This action cannot be undone.')">
                                                                                        <i class="fas fa-trash"></i>
                                                                                    </button>
                                                                                </form>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Subject Modal -->
    <div class="modal fade" id="editSubjectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="subject_id" id="edit_subject_id">
                        <div class="mb-3">
                            <label for="edit_subject_name" class="form-label">Subject Name</label>
                            <input type="text" class="form-control" id="edit_subject_name" name="edit_subject_name" required>
                        </div>
                        <div class="mb-3 semester-field" id="edit_semester_container">
                            <label for="edit_semester" class="form-label">Semester</label>
                            <select class="form-select" id="edit_semester" name="edit_semester">
                                <option value="Full Year">Full Year</option>
                                <option value="1">1st Semester</option>
                                <option value="2">2nd Semester</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_subject" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Extension error handling - placed at the very beginning
    (function() {
        // Suppress extension-related errors
        const originalError = console.error;
        console.error = function() {
            const args = Array.from(arguments);
            const errorString = args.join(' ').toLowerCase();
            
            if (errorString.includes('message port closed') || 
                errorString.includes('translate-page') ||
                errorString.includes('content-all.js') ||
                errorString.includes('extension')) {
                return; // Suppress these errors
            }
            
            originalError.apply(console, args);
        };
        
        // Handle uncaught exceptions
        window.addEventListener('error', function(e) {
            const errorMsg = (e.message || '').toLowerCase();
            const errorFile = (e.filename || '').toLowerCase();
            
            if (errorMsg.includes('message port closed') || 
                errorMsg.includes('translate-page') ||
                errorFile.includes('content-all.js') ||
                errorFile.includes('extension') ||
                errorFile.includes('chrome-extension')) {
                e.stopImmediatePropagation();
                e.preventDefault();
                return false;
            }
        });
        
        // Handle unhandled promise rejections
        window.addEventListener('unhandledrejection', function(e) {
            const reason = (e.reason || '').toString().toLowerCase();
            
            if (reason.includes('message port closed') || 
                reason.includes('translate-page')) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
        
        // Prevent Chrome extension interference
        if (typeof chrome !== 'undefined' && chrome.runtime) {
            // Override to prevent extension errors
            const originalSendMessage = chrome.runtime.sendMessage;
            if (originalSendMessage) {
                chrome.runtime.sendMessage = function() {
                    try {
                        return originalSendMessage.apply(this, arguments);
                    } catch (e) {
                        // Silently catch extension errors
                        return Promise.reject(e);
                    }
                };
            }
        }
    })();

    document.addEventListener('DOMContentLoaded', function() {
        const toggleEdit = document.getElementById('toggleEdit');
        const toggleDelete = document.getElementById('toggleDelete');
        const actionButtons = document.querySelectorAll('.action-buttons');
        const editModal = new bootstrap.Modal(document.getElementById('editSubjectModal'));
        
        let editMode = false;
        let deleteMode = false;

        // Toggle Edit Mode
        toggleEdit.addEventListener('click', function() {
            editMode = !editMode;
            deleteMode = false;
            
            // Update UI states
            this.classList.toggle('btn-primary', editMode);
            this.classList.toggle('btn-outline-primary', !editMode);
            toggleDelete.classList.remove('btn-danger');
            toggleDelete.classList.add('btn-outline-danger');
            
            document.body.classList.toggle('edit-mode', editMode);
            document.body.classList.remove('delete-mode');
        });

        // Toggle Delete Mode
        toggleDelete.addEventListener('click', function() {
            deleteMode = !deleteMode;
            editMode = false;
            
            // Update UI states
            this.classList.toggle('btn-danger', deleteMode);
            this.classList.toggle('btn-outline-danger', !deleteMode);
            toggleEdit.classList.remove('btn-primary');
            toggleEdit.classList.add('btn-outline-primary');
            
            document.body.classList.toggle('delete-mode', deleteMode);
            document.body.classList.remove('edit-mode');
        });

        // Edit button click handler
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const subjectId = this.getAttribute('data-subject-id');
                const subjectName = this.getAttribute('data-subject-name');
                const yearLevel = this.getAttribute('data-year-level');
                const semester = this.getAttribute('data-semester');
                
                document.getElementById('edit_subject_id').value = subjectId;
                document.getElementById('edit_subject_name').value = subjectName;
                document.getElementById('edit_semester').value = semester;
                
                // Show/hide semester field based on grade level
                if (yearLevel == 11 || yearLevel == 12) {
                    document.getElementById('edit_semester_container').style.display = 'block';
                } else {
                    document.getElementById('edit_semester_container').style.display = 'none';
                }
                
                editModal.show();
            });
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

        // Manage browser history
        if (window.history && window.history.pushState) {
            window.history.pushState(null, null, window.location.href);
            
            window.addEventListener('popstate', function() {
                if (performance.navigation.type === 2) {
                    window.location.reload();
                }
            });
        }
        
        // Function to check if grade is 11 or 12 (Senior High)
        function isSeniorHigh(gradeLevelId) {
            return gradeLevelId == 11 || gradeLevelId == 12;
        }
        
        // Function to toggle semester and strand field visibility
        function toggleStrandField() {
            const yearLevelSelect = document.getElementById('year_level_id');
            const semesterContainer = document.querySelector('.semester-field');
            const strandContainer = document.querySelector('.strand-field');
            const selectedGradeId = yearLevelSelect.value;
            
            if (selectedGradeId && isSeniorHigh(selectedGradeId)) {
                // Show both semester and strand fields for Senior High
                semesterContainer.style.display = 'block';
                strandContainer.style.display = 'block';
                document.getElementById('strand_id').setAttribute('required', 'required');
            } else if (selectedGradeId) {
                // Show only semester field for other grades
                semesterContainer.style.display = 'none';
                strandContainer.style.display = 'none';
                document.getElementById('strand_id').removeAttribute('required');
            } else {
                // Hide both if no grade selected
                semesterContainer.style.display = 'none';
                strandContainer.style.display = 'none';
                document.getElementById('strand_id').removeAttribute('required');
            }
        }
        
        // Initial check on page load
        toggleStrandField();
        
        // Add event listener for changes to the year level dropdown
        document.getElementById('year_level_id').addEventListener('change', function() {
            toggleStrandField();
        });
    });

    // Handle page visibility changes
    window.onpageshow = function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    };

    // toggle submenu
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