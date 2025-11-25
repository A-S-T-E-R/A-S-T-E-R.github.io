<?php
session_start();
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
// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}
define('DB_HOST', 'localhost');
define('DB_NAME', 'school_portal');
define('DB_USER', 'root'); // Default XAMPP username
define('DB_PASS', '');     // Default XAMPP password (empty)

// Get archive data
$archived_students = [];
$archived_teachers = [];
$archived_subjects = [];
$archived_grades = [];
$archived_parents = [];
$archived_accounts = [];
$error = '';

// Get filter value if set
$account_filter = isset($_GET['account_filter']) ? $_GET['account_filter'] : 'all';

try {
    // Fixed database connection string
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if archive tables exist
    $tables_exist = true;
    $required_tables = [
        'tbl_students_archive', 'tbl_teachers_archive', 
        'tbl_subjects_archive', 'tbl_grades_archive', 'tbl_parents_archive',
        'tbl_accounts_archive'
    ];
    
    foreach ($required_tables as $table) {
        $check = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if (!$check) {
            $tables_exist = false;
            break;
        }
    }

     if (!$tables_exist) {
        $error = "Archive tables not found. Please run the archive table creation script first.";
    } else {
        // Get archived students
        $stmt = $pdo->query("
            SELECT s.*, 
                   aa.username, aa.email, aa.contact_number, 
                   y.level_name, sec.section_name,
                   CONCAT(deleted_by.first_name, ' ', deleted_by.last_name) as deleted_by_name
            FROM tbl_students_archive s
            LEFT JOIN tbl_accounts_archive aa ON s.account_id = aa.original_account_id
            LEFT JOIN tbl_yearlevels y ON s.year_level_id = y.year_level_id
            LEFT JOIN tbl_sections sec ON s.section_id = sec.section_id
            LEFT JOIN tbl_accounts deleted_by ON s.deleted_by = deleted_by.account_id
            ORDER BY s.deleted_at DESC
        ");
        $archived_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get archived teachers - FIXED QUERY
        $stmt = $pdo->query("
            SELECT t.*, 
                   CONCAT(deleted_by.first_name, ' ', deleted_by.last_name) as deleted_by_name
            FROM tbl_teachers_archive t
            LEFT JOIN tbl_accounts deleted_by ON t.deleted_by = deleted_by.account_id
            ORDER BY t.deleted_at DESC
        ");
        $archived_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get archived accounts with optional filtering
        $account_filter_sql = "";
        if ($account_filter !== 'all') {
            $account_filter_sql = "WHERE a.role = :role";
        }
        
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   CONCAT(deleted_by.first_name, ' ', deleted_by.last_name) as deleted_by_name
            FROM tbl_accounts_archive a
            LEFT JOIN tbl_accounts deleted_by ON a.deleted_by = deleted_by.account_id
            $account_filter_sql
            ORDER BY a.deleted_at DESC
        ");
        
        if ($account_filter !== 'all') {
            $stmt->bindParam(':role', $account_filter);
        }
        
        $stmt->execute();
        $archived_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get archived subjects
        $stmt = $pdo->query("
            SELECT s.*, y.level_name, 
                   CONCAT(deleted_by.first_name, ' ', deleted_by.last_name) as deleted_by_name
            FROM tbl_subjects_archive s
            LEFT JOIN tbl_yearlevels y ON s.year_level_id = y.year_level_id
            LEFT JOIN tbl_accounts deleted_by ON s.deleted_by = deleted_by.account_id
            ORDER BY s.deleted_at DESC
        ");
        $archived_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get archived grades - fixed query
        $stmt = $pdo->query("
            SELECT g.*, 
                   CONCAT(s_acc.first_name, ' ', s_acc.last_name) as student_name,
                   sub.subject_name,
                   CONCAT(t_acc.first_name, ' ', t_acc.last_name) as teacher_name,
                   CONCAT(deleted_by.first_name, ' ', deleted_by.last_name) as deleted_by_name
            FROM tbl_grades_archive g
            LEFT JOIN tbl_students_archive std ON g.student_id = std.original_student_id
            LEFT JOIN tbl_accounts_archive s_acc ON std.account_id = s_acc.original_account_id
            LEFT JOIN tbl_subjects sub ON g.subject_id = sub.subject_id
            LEFT JOIN tbl_accounts t_acc ON g.teacher_account_id = t_acc.account_id
            LEFT JOIN tbl_accounts deleted_by ON g.deleted_by = deleted_by.account_id
            ORDER BY g.deleted_at DESC
        ");
        $archived_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get archived parents
        $stmt = $pdo->query("
            SELECT p.*, 
                   CONCAT(a.first_name, ' ', a.last_name) as parent_name,
                   a.email, a.contact_number,
                   CONCAT(deleted_by.first_name, ' ', deleted_by.last_name) as deleted_by_name
            FROM tbl_parents_archive p
            LEFT JOIN tbl_accounts a ON p.account_id = a.account_id
            LEFT JOIN tbl_accounts deleted_by ON p.deleted_by = deleted_by.account_id
            ORDER BY p.deleted_at DESC
        ");
        $archived_parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Manager - School Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            background-color: var(--primary);
            color: white;
            width: 250px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 0;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
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
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
        }
        
        .header {
            background-color: white;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
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
        
        /* Welcome Section Styles (Added from previous pages) */
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
        
        /* Archive specific styles */
        .archive-section {
            margin-bottom: 3rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1.5rem;
            background-color: white;
        }
        
        .archive-header {
            background-color: #f8f9fa;
            padding: 1rem;
            margin: -1.5rem -1.5rem 1.5rem -1.5rem;
            border-bottom: 1px solid #dee2e6;
            border-radius: 0.375rem 0.375rem 0 0;
        }
        
        .badge-deleted {
            background-color: #dc3545;
        }
        
        .table-archive th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
        }
        
        .nav-tabs .nav-link.active {
            font-weight: 600;
            border-bottom: 3px solid #0d6efd;
        }
        
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .filter-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .filter-label {
            font-weight: 500;
            margin-bottom: 0;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .menu-text {
                display: none;
            }
            
            .sidebar-menu i {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .filter-container {
                flex-direction: column;
                align-items: flex-start;
            }
        }

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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
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
                    <li><a href="archive_manager.php" class="active">Archive Manager</a></li>
                    <li><a href="change_pass.php">Settings</a></li>
                </ul>
            </li>

            <li><a href="logout.php" class="text-danger" onclick="return confirmLogout()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h4 class="mb-0">Archive Manager</h4>
            <div class="user-info">
                <div class="avatar"><?= $adminInitial ?></div>
                <div>
                    <div class="fw-bold"><?= htmlspecialchars($adminName) ?></div>
                    <div class="text-muted">Administrator</div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="container-fluid mt-4">
            <!-- Welcome Section (Added from previous pages) -->
            <div class="welcome-section">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1>Archive Manager</h1>
                        <p>View and manage all archived records including students, teachers, accounts, subjects, grades, and parents</p>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-archive fa-4x"></i>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if (empty($error)): ?>
                    <ul class="nav nav-tabs mb-4" id="archiveTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab">
                                <i class="fas fa-user-graduate me-1"></i> Students (<?php echo count($archived_students); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="teachers-tab" data-bs-toggle="tab" data-bs-target="#teachers" type="button" role="tab">
                                <i class="fas fa-chalkboard-teacher me-1"></i> Teachers (<?php echo count($archived_teachers); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="accounts-tab" data-bs-toggle="tab" data-bs-target="#accounts" type="button" role="tab">
                                <i class="fas fa-users me-1"></i> Accounts (<?php echo count($archived_accounts); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjects" type="button" role="tab">
                                <i class="fas fa-book me-1"></i> Subjects (<?php echo count($archived_subjects); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="grades-tab" data-bs-toggle="tab" data-bs-target="#grades" type="button" role="tab">
                                <i class="fas fa-chart-line me-1"></i> Grades (<?php echo count($archived_grades); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="parents-tab" data-bs-toggle="tab" data-bs-target="#parents" type="button" role="tab">
                                <i class="fas fa-users me-1"></i> Parents (<?php echo count($archived_parents); ?>)
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="archiveTabsContent">
                        <!-- Students Tab -->
                        <div class="tab-pane fade show active" id="students" role="tabpanel">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Archived Students</h5>
                                    <span class="badge bg-secondary"><?php echo count($archived_students); ?> records</span>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (!empty($archived_students)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-archive mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Username</th>
                                                        <th>LRN</th>
                                                        <th>Level & Section</th>
                                                        <th>Gender</th>
                                                        <th>Birth Date</th>
                                                        <th>Deleted By</th>
                                                        <th>Reason</th>
                                                        <th>Deleted At</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($archived_students as $student): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($student['username'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($student['lrn'] ?? 'N/A'); ?></td>
                                                            <td>
                                                                <?php echo htmlspecialchars($student['level_name'] ?? 'N/A'); ?>
                                                                <?php if (!empty($student['section_name'])): ?>
                                                                    - <?php echo htmlspecialchars($student['section_name']); ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></td>
                                                            <td><?php echo !empty($student['birth_date']) ? date('M j, Y', strtotime($student['birth_date'])) : 'N/A'; ?></td>
                                                            <td><?php echo htmlspecialchars($student['deleted_by_name'] ?? 'System'); ?></td>
                                                            <td><?php echo htmlspecialchars($student['reason'] ?? 'No reason provided'); ?></td>
                                                            <td><?php echo date('M j, Y g:i A', strtotime($student['deleted_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p class="text-muted">No archived students found.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Teachers Tab -->
                        <div class="tab-pane fade" id="teachers" role="tabpanel">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Archived Teachers</h5>
                                    <span class="badge bg-secondary"><?php echo count($archived_teachers); ?> records</span>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (!empty($archived_teachers)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-archive mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Username</th>
                                                        <th>Teacher Name</th>
                                                        <th>Email</th>
                                                        <th>Contact</th>
                                                        <th>Deleted By</th>
                                                        <th>Reason</th>
                                                        <th>Deleted At</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($archived_teachers as $teacher): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                                            <td><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($teacher['email'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($teacher['contact_number'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($teacher['deleted_by_name'] ?? 'System'); ?></td>
                                                            <td><?php echo htmlspecialchars($teacher['reason'] ?? 'No reason provided'); ?></td>
                                                            <td><?php echo date('M j, Y g:i A', strtotime($teacher['deleted_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p class="text-muted">No archived teachers found.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Accounts Tab -->
                        <div class="tab-pane fade" id="accounts" role="tabpanel">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Archived Accounts</h5>
                                    <span class="badge bg-secondary"><?php echo count($archived_accounts); ?> records</span>
                                </div>
                                <div class="card-body">
                                    <!-- Filter Form -->
                                    <form method="GET" action="" class="mb-3">
                                        <div class="filter-container">
                                            <label for="account_filter" class="filter-label">Filter by Role:</label>
                                            <select name="account_filter" id="account_filter" class="form-select" style="width: auto;" onchange="this.form.submit()">
                                                <option value="all" <?php echo $account_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                                <option value="admin" <?php echo $account_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                <option value="teacher" <?php echo $account_filter === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                                <option value="student" <?php echo $account_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                                                <option value="parent" <?php echo $account_filter === 'parent' ? 'selected' : ''; ?>>Parent</option>
                                            </select>
                                        </div>
                                    </form>
                                    
                                    <?php if (!empty($archived_accounts)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-archive mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Username</th>
                                                        <th>Name</th>
                                                        <th>Role</th>
                                                        <th>Email</th>
                                                        <th>Contact</th>
                                                        <th>Deleted By</th>
                                                        <th>Reason</th>
                                                        <th>Deleted At</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($archived_accounts as $account): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($account['username']); ?></td>
                                                            <td><?php echo htmlspecialchars($account['first_name'] . ' ' . $account['last_name']); ?></td>
                                                            <td>
                                                                <span class="badge 
                                                                    <?php 
                                                                    switch($account['role']) {
                                                                        case 'admin': echo 'bg-danger'; break;
                                                                        case 'teacher': echo 'bg-primary'; break;
                                                                        case 'student': echo 'bg-success'; break;
                                                                        case 'parent': echo 'bg-info'; break;
                                                                        default: echo 'bg-secondary';
                                                                    }
                                                                    ?>">
                                                                    <?php echo htmlspecialchars($account['role']); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($account['email'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($account['contact_number'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($account['deleted_by_name'] ?? 'System'); ?></td>
                                                            <td><?php echo htmlspecialchars($account['reason'] ?? 'No reason provided'); ?></td>
                                                            <td><?php echo date('M j, Y g:i A', strtotime($account['deleted_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p class="text-muted">No archived accounts found.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Subjects Tab -->
                        <div class="tab-pane fade" id="subjects" role="tabpanel">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Archived Subjects</h5>
                                    <span class="badge bg-secondary"><?php echo count($archived_subjects); ?> records</span>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (!empty($archived_subjects)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-archive mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Subject Name</th>
                                                        <th>Year Level</th>
                                                        <th>Semester</th>
                                                        <th>Deleted By</th>
                                                        <th>Reason</th>
                                                        <th>Deleted At</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($archived_subjects as $subject): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($subject['level_name'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($subject['semester']); ?></td>
                                                            <td><?php echo htmlspecialchars($subject['deleted_by_name'] ?? 'System'); ?></td>
                                                            <td><?php echo htmlspecialchars($subject['reason'] ?? 'No reason provided'); ?></td>
                                                            <td><?php echo date('M j, Y g:i A', strtotime($subject['deleted_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p class="text-muted">No archived subjects found.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Grades Tab -->
                        <div class="tab-pane fade" id="grades" role="tabpanel">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Archived Grades</h5>
                                    <span class="badge bg-secondary"><?php echo count($archived_grades); ?> records</span>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (!empty($archived_grades)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-archive mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Student</th>
                                                        <th>Subject</th>
                                                        <th>Teacher</th>
                                                        <th>Period</th>
                                                        <th>Grade</th>
                                                        <th>Remarks</th>
                                                        <th>Deleted By</th>
                                                        <th>Reason</th>
                                                        <th>Deleted At</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($archived_grades as $grade): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($grade['student_name'] ?? 'Unknown'); ?></td>
                                                            <td><?php echo htmlspecialchars($grade['subject_name'] ?? 'Unknown'); ?></td>
                                                            <td><?php echo htmlspecialchars($grade['teacher_name'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($grade['grading_period']); ?></td>
                                                            <td><strong><?php echo htmlspecialchars($grade['grade']); ?></strong></td>
                                                            <td><?php echo htmlspecialchars($grade['remarks'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($grade['deleted_by_name'] ?? 'System'); ?></td>
                                                            <td><?php echo htmlspecialchars($grade['reason'] ?? 'No reason provided'); ?></td>
                                                            <td><?php echo date('M j, Y g:i A', strtotime($grade['deleted_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p class="text-muted">No archived grades found.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Parents Tab -->
                        <div class="tab-pane fade" id="parents" role="tabpanel">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Archived Parents</h5>
                                    <span class="badge bg-secondary"><?php echo count($archived_parents); ?> records</span>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (!empty($archived_parents)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-archive mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Parent Name</th>
                                                        <th>Email</th>
                                                        <th>Contact</th>
                                                        <th>Deleted By</th>
                                                        <th>Reason</th>
                                                        <th>Deleted At</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($archived_parents as $parent): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($parent['parent_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($parent['email'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($parent['contact_number'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($parent['deleted_by_name'] ?? 'System'); ?></td>
                                                            <td><?php echo htmlspecialchars($parent['reason'] ?? 'No reason provided'); ?></td>
                                                            <td><?php echo date('M j, Y g:i A', strtotime($parent['deleted_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p class="text-muted">No archived parents found.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activate the first tab
        document.addEventListener('DOMContentLoaded', function() {
            const firstTab = new bootstrap.Tab(document.getElementById('students-tab'));
            firstTab.show();
            
            // Preserve filter selection when switching tabs
            const urlParams = new URLSearchParams(window.location.search);
            const accountFilter = urlParams.get('account_filter');
            if (accountFilter) {
                // Switch to accounts tab if filter is set
                const accountsTab = new bootstrap.Tab(document.getElementById('accounts-tab'));
                accountsTab.show();
            }
        });
        
        function confirmLogout() {
            return confirm('Are you sure you want to logout?');
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