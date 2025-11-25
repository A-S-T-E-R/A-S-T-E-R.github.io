<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Database connection
define('DB_HOST', 'sql210.infinityfree.com');
define('DB_NAME', 'if0_40265243_school_portal');
define('DB_USER', 'if0_40265243');
define('DB_PASS', 'rjL6bzbfrgcc');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?session_expired=1");
    exit();
}

// Handle delete/archive request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_teacher'])) {
    $teacherId = $_POST['teacher_id'];
    $reason = $_POST['reason'];
    $otherReason = !empty($_POST['other_reason']) ? $_POST['other_reason'] : $reason;
    $deletedBy = $_SESSION['account_id'];
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // 1. Get teacher account details
        $stmt = $pdo->prepare("SELECT * FROM tbl_accounts WHERE account_id = ? AND role = 'teacher'");
        $stmt->execute([$teacherId]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$teacher) {
            throw new Exception("Teacher not found");
        }
        
        // 2. Archive the account
        $archiveStmt = $pdo->prepare("
            INSERT INTO tbl_accounts_archive 
            (original_account_id, username, password, role, first_name, last_name, email, contact_number, created_at, deleted_at, deleted_by, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $archiveStmt->execute([
            $teacher['account_id'],
            $teacher['username'],
            $teacher['password'],
            $teacher['role'],
            $teacher['first_name'],
            $teacher['last_name'],
            $teacher['email'],
            $teacher['contact_number'],
            $teacher['created_at'],
            $deletedBy,
            $otherReason
        ]);
        
        // 3. Delete the account from the main table
        $deleteStmt = $pdo->prepare("DELETE FROM tbl_accounts WHERE account_id = ?");
        $deleteStmt->execute([$teacherId]);
        
        // 4. Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = "Teacher account archived successfully.";
        header("Location: archive_manager.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error archiving teacher: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Handle add teacher request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contactNumber = trim($_POST['contact_number']);
    
    try {
        // Check if username already exists
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_accounts WHERE username = ?");
        $checkStmt->execute([$username]);
        $usernameExists = $checkStmt->fetchColumn();
        
        if ($usernameExists) {
            throw new Exception("Username already exists. Please choose a different username.");
        }
        
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new teacher
        $insertStmt = $pdo->prepare("
            INSERT INTO tbl_accounts (username, password, role, first_name, last_name, email, contact_number)
            VALUES (?, ?, 'teacher', ?, ?, ?, ?)
        ");
        $insertStmt->execute([$username, $hashedPassword, $firstName, $lastName, $email, $contactNumber]);
        
        $_SESSION['success_message'] = "Teacher account created successfully.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error creating teacher: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Handle edit teacher request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_teacher'])) {
    $teacherId = $_POST['teacher_id'];
    $username = trim($_POST['username']);
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contactNumber = trim($_POST['contact_number']);
    $password = $_POST['password'];
    
    try {
        // Check if username already exists (excluding current teacher)
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_accounts WHERE username = ? AND account_id != ?");
        $checkStmt->execute([$username, $teacherId]);
        $usernameExists = $checkStmt->fetchColumn();
        
        if ($usernameExists) {
            throw new Exception("Username already exists. Please choose a different username.");
        }
        
        // Prepare update query based on whether password is being changed
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("
                UPDATE tbl_accounts 
                SET username = ?, password = ?, first_name = ?, last_name = ?, email = ?, contact_number = ?
                WHERE account_id = ? AND role = 'teacher'
            ");
            $updateStmt->execute([$username, $hashedPassword, $firstName, $lastName, $email, $contactNumber, $teacherId]);
        } else {
            $updateStmt = $pdo->prepare("
                UPDATE tbl_accounts 
                SET username = ?, first_name = ?, last_name = ?, email = ?, contact_number = ?
                WHERE account_id = ? AND role = 'teacher'
            ");
            $updateStmt->execute([$username, $firstName, $lastName, $email, $contactNumber, $teacherId]);
        }
        
        $_SESSION['success_message'] = "Teacher account updated successfully.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error updating teacher: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get teacher details for editing
$editTeacherData = null;
if (isset($_GET['edit_id'])) {
    $editId = $_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM tbl_accounts WHERE account_id = ? AND role = 'teacher'");
    $stmt->execute([$editId]);
    $editTeacherData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$editTeacherData) {
        $_SESSION['error_message'] = "Teacher not found.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get all teachers with password field
$search = isset($_GET['search']) ? $_GET['search'] : '';
$whereClause = "WHERE role = 'teacher'";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$stmt = $pdo->prepare("
    SELECT * FROM tbl_accounts 
    $whereClause 
    ORDER BY first_name, last_name
");
$stmt->execute($params);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$teacherCount = count($teachers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>EduTrack - Teacher Account Management</title>
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
        
        .stat-card {
            text-align: center;
            padding: 20px;
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--secondary);
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .stat-card .label {
            color: #777;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
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
        
        .log-row {
            border-left: 4px solid var(--accent);
            transition: all 0.3s;
        }
        
        .log-row:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .clickable-card {
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .clickable-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .teacher-card {
            transition: transform 0.2s;
        }
        .teacher-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .search-box {
            max-width: 400px;
        }
        .action-buttons .btn {
            margin-right: 5px;
        }
        .table-responsive {
            min-height: 400px;
        }
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-card i {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .stats-card.teachers { border-left: 4px solid #e74c3c; }
        
        .password-toggle {
            cursor: pointer;
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
        
        .table-action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .action-header {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .teacher-table th {
            background-color: var(--primary);
            color: white;
        }
        
        .teacher-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .teacher-table tr:hover {
            background-color: #e9f7fe;
        }

        /* Password field styling */
        .password-field {
            font-family: monospace;
            font-size: 0.8rem;
        }

        .input-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .input-group-sm {
            width: 100%;
        }

        .teacher-password .input-group {
            min-width: 200px;
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
                            <li><a href="manage_teacheracc.php" class="active">Teachers Accounts</a></li>
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
                    <h3>Teacher Account Management</h3>
                    <div class="user-info">
                        <div class="avatar"><?= substr($_SESSION['fullname'], 0, 1) ?></div>
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
                            <div class="text-muted">Administrator</div>
                        </div>
                    </div>
                </div>

                <div class="main-content">
                    <!-- Welcome Banner -->
                    <div class="welcome-section">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1>Teacher Account Management</h1>
                                <p>Manage teacher accounts, view details, and archive accounts when necessary</p>
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

                    <!-- Messages -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= $_SESSION['success_message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $_SESSION['error_message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>

                    <!-- Search and Action Section -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="GET" action="">
                                <div class="input-group search-box">
                                    <input type="text" class="form-control" placeholder="Search teachers..." name="search" value="<?= htmlspecialchars($search) ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-danger">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6 text-end">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                                <i class="fas fa-plus-circle"></i> Add New Teacher
                            </button>
                            <?php if ($teacherCount > 0): ?>
                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editTeacherModal">
                                <i class="fas fa-edit"></i> Edit Teacher
                            </button>
                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="fas fa-archive"></i> Archive Teacher
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Teachers Table -->
                    <div class="card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="card-title mb-0">All Teachers</h5>
                                </div>
                                <div class="col-auto">
                                    <span class="badge bg-primary"><?= $teacherCount ?> teacher<?= $teacherCount != 1 ? 's' : '' ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($teacherCount > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover teacher-table" id="teachersTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Contact</th>
                                            <th>Password</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teachers as $teacher): ?>
                                        <tr class="log-row">
                                            <td><?= $teacher['account_id'] ?></td>
                                            <td><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></td>
                                            <td><?= htmlspecialchars($teacher['username']) ?></td>
                                            <td><?= htmlspecialchars($teacher['email']) ?></td>
                                            <td><?= htmlspecialchars($teacher['contact_number']) ?></td>
                                            <td class="teacher-password">
                                                <div class="input-group input-group-sm">
                                                    <input type="password" class="form-control form-control-sm password-field" 
                                                           value="<?= htmlspecialchars($teacher['password']) ?>" readonly 
                                                           id="password-<?= $teacher['account_id'] ?>">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm toggle-password" 
                                                            data-target="password-<?= $teacher['account_id'] ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm edit-password" 
                                                            data-teacher-id="<?= $teacher['account_id'] ?>"
                                                            data-current-password="<?= htmlspecialchars($teacher['password']) ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-success">Active</span></td>
                                            <td>
                                                <a href="?edit_id=<?= $teacher['account_id'] ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h4>No teachers found</h4>
                                <p class="text-muted"><?= !empty($search) ? 'Try adjusting your search terms' : 'Get started by adding a new teacher' ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Archive Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="deleteModalLabel">Confirm Archival</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="teacherSelect" class="form-label">Select Teacher to Archive:</label>
                            <select class="form-select" id="teacherSelect" name="teacher_id" required>
                                <option value="">Select a teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= $teacher['account_id'] ?>">
                                    <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?> (<?= $teacher['username'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="deleteReason" class="form-label">Reason for archival:</label>
                            <select class="form-select" id="deleteReason" name="reason" required>
                                <option value="">Select a reason</option>
                                <option value="resigned">Teacher resigned</option>
                                <option value="transferred">Teacher transferred</option>
                                <option value="duplicate">Duplicate account</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="otherReasonContainer" style="display: none;">
                            <label for="otherReason" class="form-label">Please specify:</label>
                            <textarea class="form-control" id="otherReason" name="other_reason" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning" name="delete_teacher">Archive Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Teacher Modal -->
    <div class="modal fade" id="addTeacherModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Add New Teacher</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="generatePassword('password')">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="contact_number" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="contact_number" name="contact_number">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" name="add_teacher">Add Teacher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Teacher Modal with Password Fields -->
    <div class="modal fade" id="editTeacherModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">Edit Teacher</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editTeacherSelect" class="form-label">Select Teacher to Edit:</label>
                            <select class="form-select" id="editTeacherSelect" name="teacher_id" required>
                                <option value="">Select a teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= $teacher['account_id'] ?>" <?= isset($editTeacherData) && $editTeacherData['account_id'] == $teacher['account_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?> (<?= $teacher['username'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="teacherEditForm" style="<?= isset($editTeacherData) ? '' : 'display: none;' ?>">
                            <div class="mb-3">
                                <label for="edit_username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="edit_username" name="username" value="<?= isset($editTeacherData) ? htmlspecialchars($editTeacherData['username']) : '' ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="edit_password" name="password">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('edit_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="generatePassword('edit_password')">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_current_password_display" class="form-label">Current Password</label>
                                <div class="input-group">
                                    <input type="password" id="edit_current_password_display" class="form-control" readonly>
                                    <button type="button" class="btn btn-outline-info" onclick="togglePassword('edit_current_password_display')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="form-text text-muted">Hashed password from database</small>
                            </div>
                            <div class="mb-3">
                                <label for="edit_first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" value="<?= isset($editTeacherData) ? htmlspecialchars($editTeacherData['first_name']) : '' ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" value="<?= isset($editTeacherData) ? htmlspecialchars($editTeacherData['last_name']) : '' ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email" value="<?= isset($editTeacherData) ? htmlspecialchars($editTeacherData['email']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label for="edit_contact_number" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="edit_contact_number" name="contact_number" value="<?= isset($editTeacherData) ? htmlspecialchars($editTeacherData['contact_number']) : '' ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-warning" name="edit_teacher">Update Teacher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password management functions
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function generatePassword(fieldId) {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            let password = '';
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = password;
            }
        }

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

        document.addEventListener('DOMContentLoaded', function() {
            // Enhanced logout confirmation
            function confirmLogout() {
                if (confirm('Are you sure you want to logout?')) {
                    // Clear client-side storage
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
            
            // Show/hide other reason textarea
            document.getElementById('deleteReason').addEventListener('change', function() {
                const otherReasonContainer = document.getElementById('otherReasonContainer');
                otherReasonContainer.style.display = this.value === 'other' ? 'block' : 'none';
            });

            // Toggle password visibility in table
            document.addEventListener('click', function(e) {
                if (e.target.closest('.toggle-password')) {
                    const button = e.target.closest('.toggle-password');
                    const targetId = button.getAttribute('data-target');
                    const field = document.getElementById(targetId);
                    
                    if (field) {
                        const icon = button.querySelector('i');
                        if (field.type === 'password') {
                            field.type = 'text';
                            icon.classList.remove('fa-eye');
                            icon.classList.add('fa-eye-slash');
                        } else {
                            field.type = 'password';
                            icon.classList.remove('fa-eye-slash');
                            icon.classList.add('fa-eye');
                        }
                    }
                }
                
                // Quick password edit
                if (e.target.closest('.edit-password')) {
                    const button = e.target.closest('.edit-password');
                    const teacherId = button.getAttribute('data-teacher-id');
                    
                    // Find the teacher in the table
                    const teacherOption = document.querySelector(`#editTeacherSelect option[value="${teacherId}"]`);
                    if (teacherOption) {
                        document.getElementById('editTeacherSelect').value = teacherId;
                        document.getElementById('editTeacherSelect').dispatchEvent(new Event('change'));
                        
                        // Open edit modal
                        const editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
                        editModal.show();
                    }
                }
            });

            // Manage browser history for better navigation
            if (window.history && window.history.pushState) {
                // Add a new history entry
                window.history.pushState(null, null, window.location.href);
                
                // Handle back/forward navigation
                window.addEventListener('popstate', function() {
                    // Check if we should reload (optional)
                    if (performance.navigation.type === 2) {
                        window.location.reload();
                    }
                });
            }
            
            // Handle page visibility changes
            window.onpageshow = function(event) {
                if (event.persisted) {
                    // Page was loaded from cache (like when using back button)
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
            
            // Auto-open edit modal if edit_id parameter is present
            <?php if (isset($_GET['edit_id'])): ?>
                var editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
                editModal.show();
            <?php endif; ?>
            
            // Load teacher data when selection changes
            document.getElementById('editTeacherSelect').addEventListener('change', function() {
                const teacherId = this.value;
                if (teacherId) {
                    // Show loading state
                    const form = document.getElementById('teacherEditForm');
                    form.style.display = 'block';
                    
                    // Find the teacher data
                    const teacherRow = document.querySelector(`tr td:first-child:contains("${teacherId}")`)?.closest('tr');
                    if (teacherRow) {
                        const passwordField = teacherRow.querySelector('.password-field');
                        if (passwordField) {
                            document.getElementById('edit_current_password_display').value = passwordField.value;
                        }
                    }
                    
                    // You could also fetch the data via AJAX here for more accurate data
                    // For now, we'll redirect to load the data properly
                    window.location.href = '?edit_id=' + teacherId;
                } else {
                    document.getElementById('teacherEditForm').style.display = 'none';
                }
            });

            // Show success notifications
            <?php if (isset($_SESSION['success_message'])): ?>
                showNotification('<?= addslashes($_SESSION['success_message']) ?>');
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                showNotification('<?= addslashes($_SESSION['error_message']) ?>', 'error');
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            // Pre-fill current password when edit modal opens
            const editModal = document.getElementById('editTeacherModal');
            editModal.addEventListener('show.bs.modal', function() {
                <?php if (isset($editTeacherData)): ?>
                    document.getElementById('edit_current_password_display').value = '<?= addslashes($editTeacherData['password']) ?>';
                <?php endif; ?>
            });
        });
    </script>
</body>
</html>