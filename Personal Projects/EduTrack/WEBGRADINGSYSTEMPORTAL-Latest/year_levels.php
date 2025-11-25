<?php
session_start();
require('db_connect.php');
// adjust path if needed

// Ensure only admins can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Set cache control headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

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
// Add Year Level
if (isset($_POST['add_yearlevel'])) {
    $level_name = $conn->real_escape_string($_POST['level_name']);
    $education_stage = $conn->real_escape_string($_POST['education_stage']);

    // Validate grade level based on education stage
    $is_valid = validateGradeLevel($level_name, $education_stage);
    
    if (!$is_valid) {
        $error_msg = "Invalid grade level for selected education stage!";
    } else {
        // Check for duplicates
        $check = $conn->prepare("SELECT 1 FROM tbl_yearlevels WHERE level_name = ?");
        $check->bind_param("s", $level_name);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            // Duplicate found, show error
            $check->close();
            $error_msg = "Year level '$level_name' already exists!";
        } else {
            $check->close();
            
            // Insert if no duplicate
            $sql = "INSERT INTO tbl_yearlevels (level_name, education_stage) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $level_name, $education_stage);
            
            if ($stmt->execute()) {
                header("Location: year_levels.php?success=1");
                exit();
            } else {
                $error_msg = "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Update Year Level
if (isset($_POST['update_yearlevel'])) {
    $year_level_id = $_POST['year_level_id'];
    $level_name = $conn->real_escape_string($_POST['level_name']);
    $education_stage = $conn->real_escape_string($_POST['education_stage']);

    // Validate grade level based on education stage
    $is_valid = validateGradeLevel($level_name, $education_stage);
    
    if (!$is_valid) {
        $error_msg = "Invalid grade level for selected education stage!";
    } else {
        // Check for duplicates (excluding current year level)
        $check = $conn->prepare("SELECT 1 FROM tbl_yearlevels WHERE level_name = ? AND year_level_id != ?");
        $check->bind_param("si", $level_name, $year_level_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            // Duplicate found, show error
            $check->close();
            $error_msg = "Year level '$level_name' already exists!";
        } else {
            $check->close();
            
            $sql = "UPDATE tbl_yearlevels SET level_name = ?, education_stage = ? WHERE year_level_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $level_name, $education_stage, $year_level_id);
            
            if ($stmt->execute()) {
                header("Location: year_levels.php?success=2");
                exit();
            } else {
                $error_msg = "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Validation function for grade levels
function validateGradeLevel($level_name, $education_stage) {
    // Extract the numeric part from level name (e.g., "Grade 1" -> 1)
    preg_match('/\d+/', $level_name, $matches);
    if (empty($matches)) return false;
    
    $grade = (int)$matches[0];
    
    // Validate based on education stage
    switch($education_stage) {
        case 'Elementary':
            return $grade >= 1 && $grade <= 6;
        case 'Junior High':
            return $grade >= 7 && $grade <= 10;
        case 'Senior High':
            return $grade >= 11 && $grade <= 12;
        default:
            return false;
    }
}

// Delete Year Level
if (isset($_POST['delete_yearlevel'])) {
    $year_level_id = intval($_POST['year_level_id']);

    // Check if year level has sections assigned
    $check = $conn->prepare("SELECT COUNT(*) as section_count FROM tbl_sections WHERE year_level_id = ?");
    $check->bind_param("i", $year_level_id);
    $check->execute();
    $result = $check->get_result();
    $row = $result->fetch_assoc();
    $check->close();
    
    if($row['section_count'] > 0){
        $error_msg = "Cannot delete year level because it has sections assigned to it!";
    } else {
        $sql = "DELETE FROM tbl_yearlevels WHERE year_level_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $year_level_id);
        
        if ($stmt->execute()) {
            header("Location: year_levels.php?success=3");
            exit();
        } else {
            $error_msg = "Error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Show success messages if redirected
if(isset($_GET['success'])){
    if($_GET['success']==1) $success_msg = "Year level added successfully!";
    if($_GET['success']==2) $success_msg = "Year level updated successfully!";
    if($_GET['success']==3) $success_msg = "Year level deleted successfully!";
}

// Fetch all year levels
$result = $conn->query("SELECT * FROM tbl_yearlevels ORDER BY year_level_id ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>EduTrack - Manage Year Levels</title>
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
            display: block;
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
        
        .table th {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 15px 12px;
            font-weight: 600;
        }
        
        .table td {
            padding: 15px 12px;
            vertical-align: middle;
            border: none;
            border-bottom: 1px solid #eee;
        }
        
        .table tbody tr {
            transition: background-color 0.2s;
        }
        
        .table tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .table-container {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
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
                    <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>

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
                          <li><a href="year_levels.php" class="active">Year Levels</a></li>
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
                    <h3>Manage Year Levels</h3>
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
                                <h1>Year Levels Management</h1>
                                <p>Manage educational year levels, organize grade structures, and define academic stages for proper student grouping</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-layer-group fa-4x"></i>
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
                        <h4 class="mb-0">Year Levels</h4>
                        <div class="action-buttons">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addYearLevelModal">
                                <i class="fas fa-plus me-1"></i> Add Year Level
                            </button>
                            <button class="btn btn-primary" id="editSelectedBtn" style="display: none;" data-bs-toggle="modal" data-bs-target="#editYearLevelModal">
                                <i class="fas fa-edit me-1"></i> Edit Year Level
                            </button>
                            <button class="btn btn-danger" id="deleteSelectedBtn" style="display: none;" data-bs-toggle="modal" data-bs-target="#deleteYearLevelModal">
                                <i class="fas fa-trash me-1"></i> Delete Year Level
                            </button>
                        </div>
                    </div>

                    <!-- Year Levels Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i> Year Levels</h5>
                            <span class="badge bg-primary"><?php echo $result->num_rows; ?> records</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-container">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>
                                                <input type="checkbox" id="selectAll" class="form-check-input">
                                            </th>
                                            <th>ID</th>
                                            <th>Level Name</th>
                                            <th>Education Stage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if ($result->num_rows > 0): 
                                        while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input yearlevel-checkbox" 
                                                       data-yearlevel='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>' 
                                                       value="<?= $row['year_level_id'] ?>">
                                            </td>
                                            <td><?php echo $row['year_level_id']; ?></td>
                                            <td><?php echo htmlspecialchars($row['level_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['education_stage']); ?></td>
                                        </tr>
                                    <?php endwhile; 
                                    else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <div class="empty-state">
                                                    <i class="fas fa-layer-group"></i>
                                                    <p class="text-muted">No year levels found.</p>
                                                </div>
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

    <!-- Add Year Level Modal -->
    <div class="modal fade" id="addYearLevelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i> Add Year Level</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Level Name</label>
                            <input type="text" name="level_name" class="form-control" placeholder="Grade 1, Grade 2, etc." required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Education Stage</label>
                          <select name="education_stage" class="form-select" required>
    <option value="">Select Education Stage</option>
    <option value="Elementary">Elementary (Grade 1-6)</option>
    <option value="Junior High">Junior High (Grade 7-10)</option>
    <option value="Senior High">Senior High (Grade 11-12)</option>
</select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="add_yearlevel" class="btn btn-success">
                            <i class="fas fa-check me-1"></i> Add Year Level
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Year Level Modal -->
    <div class="modal fade" id="editYearLevelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Year Level</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="year_level_id" id="edit_year_level_id">
                        <div class="mb-3">
                            <label class="form-label">Level Name</label>
                            <input type="text" name="level_name" id="edit_level_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                        <select name="education_stage" id="edit_education_stage" class="form-select" required>
    <option value="Elementary">Elementary (Grade 1-6)</option>
    <option value="Junior High">Junior High (Grade 7-10)</option>
    <option value="Senior High">Senior High (Grade 11-12)</option>
</select>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="update_yearlevel" class="btn btn-primary">
                            <i class="fas fa-check me-1"></i> Update Year Level
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Year Level Modal -->
    <div class="modal fade" id="deleteYearLevelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header warning-header">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="year_level_id" id="delete_year_level_id">
                        <div class="warning-content">
                            <div class="warning-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="warning-text">
                                Are you sure you want to delete <strong id="delete_year_level_name"></strong>?
                            </div>
                            <div class="warning-subtext">
                                <i class="fas fa-info-circle me-1"></i>
                                This action cannot be undone and will permanently remove this year level.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                        <button type="submit" name="delete_yearlevel" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Delete Year Level
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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


    // Client-side validation for grade levels
function validateGradeLevelInput(levelName, educationStage) {
    // Extract the numeric part from level name
    const matches = levelName.match(/\d+/);
    if (!matches) return false;
    
    const grade = parseInt(matches[0]);
    
    // Validate based on education stage
    switch(educationStage) {
        case 'Elementary':
            return grade >= 1 && grade <= 6;
        case 'Junior High':
            return grade >= 7 && grade <= 10;
        case 'Senior High':
            return grade >= 11 && grade <= 12;
        default:
            return false;
    }
}

// Add validation to the add form
document.querySelector('#addYearLevelModal form').addEventListener('submit', function(e) {
    const levelName = document.querySelector('#addYearLevelModal input[name="level_name"]').value;
    const educationStage = document.querySelector('#addYearLevelModal select[name="education_stage"]').value;
    
    if (!validateGradeLevelInput(levelName, educationStage)) {
        e.preventDefault();
        alert('Invalid grade level for selected education stage!\n\nElementary: Grade 1-6\nJunior High: Grade 7-10\nSenior High: Grade 11-12');
    }
});

// Add validation to the edit form
document.querySelector('#editYearLevelModal form').addEventListener('submit', function(e) {
    const levelName = document.querySelector('#editYearLevelModal input[name="level_name"]').value;
    const educationStage = document.querySelector('#editYearLevelModal select[name="education_stage"]').value;
    
    if (!validateGradeLevelInput(levelName, educationStage)) {
        e.preventDefault();
        alert('Invalid grade level for selected education stage!\n\nElementary: Grade 1-6\nJunior High: Grade 7-10\nSenior High: Grade 11-12');
    }
});
    
    // Check for success messages from PHP and show notifications
    document.addEventListener('DOMContentLoaded', function() {
        <?php if(isset($_GET['success'])): ?>
            <?php if($_GET['success'] == 1): ?>
                showNotification('Year level added successfully!');
            <?php elseif($_GET['success'] == 2): ?>
                showNotification('Year level updated successfully!');
            <?php elseif($_GET['success'] == 3): ?>
                showNotification('Year level deleted successfully!');
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

    let selectedYearLevel = null;

    // Handle checkbox selection
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.yearlevel-checkbox');
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
                const checkedBoxes = document.querySelectorAll('.yearlevel-checkbox:checked');
                selectAll.checked = checkedBoxes.length === checkboxes.length;
                selectAll.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
                updateActionButtons();
            });
        });

        function updateActionButtons() {
            const checkedBoxes = document.querySelectorAll('.yearlevel-checkbox:checked');
            if (checkedBoxes.length === 1) {
                selectedYearLevel = JSON.parse(checkedBoxes[0].getAttribute('data-yearlevel'));
                editBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'inline-block';
            } else if (checkedBoxes.length > 1) {
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'inline-block';
            } else {
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'none';
                selectedYearLevel = null;
            }
        }
    });

    // Pre-fill Edit Modal
    var editModal = document.getElementById('editYearLevelModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        if (selectedYearLevel) {
            document.getElementById('edit_year_level_id').value = selectedYearLevel.year_level_id;
            document.getElementById('edit_level_name').value = selectedYearLevel.level_name;
            document.getElementById('edit_education_stage').value = selectedYearLevel.education_stage;
        }
    });

    // Pre-fill Delete Modal
    var deleteModal = document.getElementById('deleteYearLevelModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        if (selectedYearLevel) {
            document.getElementById('delete_year_level_id').value = selectedYearLevel.year_level_id;
            document.getElementById('delete_year_level_name').textContent = selectedYearLevel.level_name;
        }
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