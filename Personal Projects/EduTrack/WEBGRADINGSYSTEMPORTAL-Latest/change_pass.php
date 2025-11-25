<?php
declare(strict_types=1);
session_start();
require 'db_connect.php';

// -- GET vs POST split -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Make sure the response is *only* JSON (no stray echoes/warnings)
    while (ob_get_level()) { ob_end_clean(); }
    ini_set('display_errors', '0'); // avoid warning HTML breaking JSON
    header('Content-Type: application/json; charset=utf-8');

    try {
        if (!isset($_SESSION['account_id'])) {
            http_response_code(401);
            echo json_encode(['status'=>'error','message'=>'Not authenticated.']);
            exit;
        }

        // safer mysqli error handling
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn->set_charset('utf8mb4');

        $account_id   = (int)$_SESSION['account_id'];
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass     = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if ($new_pass !== $confirm_pass) {
            echo json_encode(['status'=>'error','message'=>'New password and confirmation do not match!']);
            exit;
        }
        if (strlen($new_pass) < 8) {
            echo json_encode(['status'=>'error','message'=>'Password must be at least 8 characters.']);
            exit;
        }

        // get current hash
        $stmt = $conn->prepare('SELECT password FROM tbl_accounts WHERE account_id = ?');
        $stmt->bind_param('i', $account_id);
        $stmt->execute();
        $stmt->bind_result($db_password);
        $found = $stmt->fetch();
        $stmt->close();

        if (!$found) {
            echo json_encode(['status'=>'error','message'=>'Account not found.']);
            exit;
        }
        if (!password_verify($current_pass, $db_password)) {
            echo json_encode(['status'=>'error','message'=>'Current password is incorrect!']);
            exit;
        }

        // update password
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $upd = $conn->prepare('UPDATE tbl_accounts SET password = ? WHERE account_id = ?');
        $upd->bind_param('si', $hashed, $account_id);
        $upd->execute();
        $upd->close();

        // ✅ audit log
        $action = 'Password Changed';
        $meta   = json_encode(['changed_at'=>date('Y-m-d H:i:s')], JSON_UNESCAPED_SLASHES);
        $log = $conn->prepare('INSERT INTO tbl_audit_logs (account_id, action, meta) VALUES (?, ?, ?)');
        $log->bind_param('iss', $account_id, $action, $meta);
        $log->execute();
        $log->close();

        echo json_encode(['status'=>'success','message'=>'Password updated successfully!']);
    } catch (Throwable $e) {
        // Return clean JSON even on errors (helpful while debugging)
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>'Server error: '.$e->getMessage()]);
    }
    exit;
}

// -------------------------- GET (page render) -------------------------------
if (!isset($_SESSION['account_id'])) {
    header('Location: index.php');
    exit();
}

$account_id = (int)$_SESSION['account_id'];
$role       = $_SESSION['role']     ?? 'guest';
$fullname   = $_SESSION['fullname'] ?? 'Guest User';

// FETCH ASSIGNED CLASSES (For Teachers)
$assigned_classes = [];
if ($role === 'teacher') {
    $teacher_id = $_SESSION['account_id'];
    $stmt = $conn->prepare("
        SELECT 
            a.assignment_id, 
            s.subject_id, 
            s.subject_name, 
            sec.section_id, 
            sec.section_name,
            yl.level_name,
            yl.year_level_id
        FROM tbl_teacher_assignments a
        JOIN tbl_subjects s ON a.subject_id = s.subject_id
        JOIN tbl_sections sec ON a.section_id = sec.section_id
        JOIN tbl_yearlevels yl ON sec.year_level_id = yl.year_level_id
        WHERE a.teacher_account_id = ?
    ");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) $assigned_classes[] = $row;
    }
    $stmt->close();
}

// Only run heavy admin counts for admin role to avoid warnings during GET
if ($role === 'admin') {
    $total_students     = $conn->query("SELECT COUNT(*) AS total FROM tbl_students")->fetch_assoc()['total'];
    $total_teachers     = $conn->query("SELECT COUNT(*) AS total FROM tbl_accounts WHERE role='teacher'")->fetch_assoc()['total'];
    $total_parents      = $conn->query("SELECT COUNT(*) AS total FROM tbl_accounts WHERE role='parent'")->fetch_assoc()['total'];
    $total_admins       = $conn->query("SELECT COUNT(*) AS total FROM tbl_accounts WHERE role='admin'")->fetch_assoc()['total'];
    $total_subjects     = $conn->query("SELECT COUNT(*) AS total FROM tbl_subjects")->fetch_assoc()['total'];
    $total_sections     = $conn->query("SELECT COUNT(*) AS total FROM tbl_sections")->fetch_assoc()['total'];
    $total_career_tests = $conn->query("SELECT COUNT(*) AS total FROM tbl_career_tests")->fetch_assoc()['total'];
} else {
    $total_students = $total_teachers = $total_parents = $total_admins = $total_subjects = $total_sections = $total_career_tests = 0;
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
    <title>EduTrack - Change Password</title>
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
            min-height: 100vh;
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
        
        .password-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            padding: 2rem;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .password-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .password-icon {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }
        
        .btn-primary {
            background-color: var(--secondary);
            border-color: var(--secondary);
            padding: 10px 20px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            border-color: #95a5a6;
            padding: 10px 20px;
            font-weight: 600;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
            border-color: #7f8c8d;
        }
        
        .form-control {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        .alert {
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
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
        
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            transition: all 0.3s;
        }
        
        .password-requirements {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .input-group-text {
            background-color: var(--light);
            border: 2px solid #e0e0e0;
            border-right: none;
        }
        
        /* Welcome Section Styles (Added for Admin) */
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
        
        /* Submenu Styles */
        .submenu {
            display: none;
            list-style: none;
            padding-left: 0;
            background: rgba(0, 0, 0, .1);
            margin-top: 0;
        }

        .has-submenu.active .submenu {
            display: block;
        }

        .has-submenu > a::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            float: right;
            font-size: .8rem;
            transition: transform .3s;
        }

        .has-submenu.active > a::after {
            transform: rotate(180deg);
        }

        .submenu li a {
            padding-left: 40px;
            font-size: 0.9rem;
            border-left: 3px solid transparent;
        }

        .submenu li a:hover,
        .submenu li a.active {
            background: rgba(0, 0, 0, .15);
            border-left: 3px solid var(--accent);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .password-container {
                padding: 1.5rem;
                margin: 1rem;
            }
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
                    <p class="mb-0">
                        <?php if ($role === 'student'): ?>
                            Student Portal
                        <?php elseif ($role === 'teacher'): ?>
                            Teacher Portal
                        <?php elseif ($role === 'admin'): ?>
                            Admin Portal
                        <?php else: ?>
                            User Portal
                        <?php endif; ?>
                    </p>
                </div>

                <ul class="sidebar-menu">
                    <?php if ($role === 'student'): ?>
                        <li><a href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="student_activities.php"><i class="fas fa-tasks"></i> Activities</a></li>
                        <li><a href="student_records.php"><i class="fas fa-chart-line"></i> Records</a></li>
                        <li><a href="grades_view.php"><i class="fas fa-chart-bar"></i> Grades</a></li>
                        <li><a href="careerpath.php"><i class="fas fa-clipboard-check"></i> Career Test</a></li>
                        <li><a href="feedbacks.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                        <li><a href="change_pass.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li> 
                    <?php elseif ($role === 'teacher'): ?>
                        <li><a href="teacher_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li class="has-submenu">
                            <a href="#"><i class="fas fa-book"></i> My Classes</a>
                            <ul class="submenu">
                                <?php foreach ($assigned_classes as $class): ?>
                                    <li>
                                        <a href="teacher_dashboard.php?view_class=1&subject_id=<?= $class['subject_id'] ?>&section_id=<?= $class['section_id'] ?>">
                                            <i class="fas fa-graduation-cap"></i> 
                                            <?= htmlspecialchars($class['subject_name']) ?> - 
                                            <?= htmlspecialchars($class['level_name']) ?> 
                                            <?= htmlspecialchars($class['section_name']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                        <li><a href="teacher_spreadsheet.php"><i class="fas fa-chart-bar"></i> Class Record</a></li>
                        <li><a href="teacher_activity.php"><i class="fas fa-tasks"></i> Upload Activity</a></li>
                        <li><a href="reports.php"><i class="fas fa-clipboard-check"></i> Reports</a></li>
                        <li><a href="feedbacks.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                        <li><a href="change_pass.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    <?php elseif ($role === 'parent'): ?>
                        <li><a href="parent_dashboard.php"><i class="fas fa-tachometer-alt"></i> Grades</a></li>
                                            <li><a href="parent_records.php" class=""><i class="fas fa-chart-line"></i> Student Records</a></li>

                        <li><a href="feedbacks.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                        <li><a href="change_pass.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    <?php elseif ($role === 'admin'): ?>
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
                                <li><a href="change_pass.php" class="active">Settings</a></li>
                            </ul>
                        </li>
                        <li><a href="logout.php" class="text-danger" onclick="return confirmLogout()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
                    <?php else: ?>
                        <li><a href="index.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ml-sm-auto px-0">
                <div class="header">
                    <h3>Change Password</h3>
                    <div class="user-info">
                        <div class="avatar">
                            <?php
                            // Get initials from fullname
                            $initials = '';
                            $names = explode(' ', $fullname);
                            if (count($names) > 0) {
                                $initials = strtoupper(substr($names[0], 0, 1));
                                if (count($names) > 1) {
                                    $initials .= strtoupper(substr(end($names), 0, 1));
                                }
                            } else {
                                $initials = 'U'; // Default if no name
                            }
                            echo $initials;
                            ?>
                        </div>
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($fullname); ?></div>
                            <div class="text-muted"><?php echo ucfirst(htmlspecialchars($role)); ?></div>
                        </div>
                    </div>
                </div>

                <div class="main-content">
                    <!-- Welcome Section for Admin Only -->
                    <?php if ($role === 'admin'): ?>
                    <div class="welcome-section">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1>Account Security</h1>
                                <p>Manage your account settings and keep your information secure</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-user-shield fa-4x"></i>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 200px);">
                        <div class="password-container">
                            <div class="password-header">
                                <div class="password-icon">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <h3>Change Password</h3>
                                <p class="text-muted">Secure your account with a new password</p>
                            </div>
                            
                            <div id="alert"></div>
                            
                            <form id="changeForm">
                                <div class="mb-4">
                                    <label class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                                        <input type="password" name="current_password" class="form-control" required 
                                               placeholder="Enter your current password">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" name="new_password" id="newPassword" class="form-control" required 
                                               placeholder="Enter your new password" oninput="checkPasswordStrength()">
                                    </div>
                                    <div class="password-strength" id="passwordStrength"></div>
                                    <div class="password-requirements">
                                        <small>Must be at least 8 characters with uppercase, lowercase, number, and special character</small>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                                        <input type="password" name="confirm_password" class="form-control" required 
                                               placeholder="Confirm your new password">
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-sync-alt me-2"></i> Change Password
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Password strength indicator
    function checkPasswordStrength() {
        const password = document.getElementById('newPassword').value;
        const strengthBar = document.getElementById('passwordStrength');
        
        // Reset strength bar
        strengthBar.style.width = '0%';
        strengthBar.style.backgroundColor = '#dc3545';
        
        if (password.length === 0) return;
        
        let strength = 0;
        
        // Length check
        if (password.length >= 8) strength += 25;
        
        // Uppercase check
        if (/[A-Z]/.test(password)) strength += 25;
        
        // Lowercase check
        if (/[a-z]/.test(password)) strength += 25;
        
        // Number/Special character check
        if (/[0-9]/.test(password) || /[^A-Za-z0-9]/.test(password)) strength += 25;
        
        // Update strength bar
        strengthBar.style.width = strength + '%';
        
        // Change color based on strength
        if (strength >= 75) {
            strengthBar.style.backgroundColor = '#28a745';
        } else if (strength >= 50) {
            strengthBar.style.backgroundColor = '#ffc107';
        } else if (strength >= 25) {
            strengthBar.style.backgroundColor = '#fd7e14';
        }
    }
    
    // Form submission
    document.getElementById('changeForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Changing...';
        submitBtn.disabled = true;
        
        let formData = new FormData(this);

        try {
            let response = await fetch("change_pass.php", {
                method: "POST",
                body: formData
            });
            
            let result = await response.json();

            document.getElementById("alert").innerHTML =
                `<div class="alert alert-${result.status === 'success' ? 'success' : 'danger'} alert-dismissible fade show">
                    <i class="fas ${result.status === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                    ${result.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
                
            // If success, clear the form
            if (result.status === 'success') {
                this.reset();
                document.getElementById('passwordStrength').style.width = '0%';
            }
            
        } catch (error) {
            document.getElementById("alert").innerHTML =
                `<div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Network error. Please try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
        } finally {
            // Restore button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
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

    // Toggle submenu functionality
    document.querySelectorAll(".has-submenu > a").forEach(link => {
      link.addEventListener("click", e => {
        e.preventDefault();
        link.parentElement.classList.toggle("active");
      });
    });
    </script>
</body>
</html>