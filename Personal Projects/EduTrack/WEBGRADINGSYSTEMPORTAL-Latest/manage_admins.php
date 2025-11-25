<?php
session_start();
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

// Handle Add Admin
if (isset($_POST['add_admin'])) {
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $contact_number = $conn->real_escape_string($_POST['contact_number']);

    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $role = 'admin';

    // Check for duplicate email
    $check = $conn->prepare("SELECT * FROM tbl_accounts WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if($result->num_rows > 0){
        $error_msg = "Email '$email' already exists!";
    } else {
        // Use email as username
        $username = $email;
        
        $stmt = $conn->prepare("INSERT INTO tbl_accounts (username, password, role, first_name, last_name, email, contact_number) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $username, $password, $role, $first_name, $last_name, $email, $contact_number);
        
        if ($stmt->execute()) {
            header("Location: manage_admins.php?success=1");
            exit();
        } else {
            $error_msg = "Error: " . $conn->error;
        }
    }
}

// Handle Update Admin
if (isset($_POST['update_admin'])) {
    $account_id = $conn->real_escape_string($_POST['account_id']);
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $contact_number = $conn->real_escape_string($_POST['contact_number']);

    // Check for duplicate email (excluding current admin)
    $check = $conn->prepare("SELECT * FROM tbl_accounts WHERE email=? AND account_id != ?");
    $check->bind_param("si", $email, $account_id);
    $check->execute();
    $result = $check->get_result();

    if($result->num_rows > 0){
        $error_msg = "Email '$email' already exists!";
    } else {
        // Update both username and email to the same value
        $stmt = $conn->prepare("UPDATE tbl_accounts SET username=?, first_name=?, last_name=?, email=?, contact_number=? WHERE account_id=?");
        $stmt->bind_param("sssssi", $email, $first_name, $last_name, $email, $contact_number, $account_id);
        
        if ($stmt->execute()) {
            header("Location: manage_admins.php?success=2");
            exit();
        } else {
            $error_msg = "Error: " . $conn->error;
        }
    }
}

// Handle Delete Admin
if (isset($_POST['delete_admin'])) {
    $account_id = $conn->real_escape_string($_POST['account_id']);
    
    // Prevent deleting own account
    if ($account_id == $_SESSION['account_id']) {
        $error_msg = "You cannot delete your own account!";
    } else {
        $stmt = $conn->prepare("DELETE FROM tbl_accounts WHERE account_id=?");
        $stmt->bind_param("i", $account_id);
        
        if ($stmt->execute()) {
            header("Location: manage_admins.php?success=3");
            exit();
        } else {
            $error_msg = "Error: " . $conn->error;
        }
    }
}

// Show success messages if redirected
if(isset($_GET['success'])){
    if($_GET['success']==1) $success_msg = "Admin added successfully!";
    if($_GET['success']==2) $success_msg = "Admin updated successfully!";
    if($_GET['success']==3) $success_msg = "Admin deleted successfully!";
}

// Fetch all admins
$admins = $conn->query("SELECT account_id, username, first_name, last_name, email, contact_number, created_at 
                       FROM tbl_accounts 
                       WHERE role = 'admin'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduTrack - Manage Admins</title>
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
.table-responsive { 
    overflow-x: auto; 
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
                    <li><a href="manage_admins.php" class="active">Manage Admins</a></li>
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
            <h3>Manage Admin Accounts</h3>
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
                        <h1>Admin Accounts Management</h1>
                        <p>Manage administrator accounts, control system access, and maintain administrative privileges</p>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-user-shield fa-4x"></i>
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
                <h4 class="mb-0">Admin Accounts</h4>
                <div class="action-buttons">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                        <i class="fas fa-plus me-1"></i> Add Admin
                    </button>
                    <button class="btn btn-primary" id="editSelectedBtn" style="display: none;" data-bs-toggle="modal" data-bs-target="#editAdminModal">
                        <i class="fas fa-edit me-1"></i> Edit Admin
                    </button>
                    <button class="btn btn-danger" id="deleteSelectedBtn" style="display: none;" data-bs-toggle="modal" data-bs-target="#deleteAdminModal">
                        <i class="fas fa-trash me-1"></i> Delete Admin
                    </button>
                </div>
            </div>

            <!-- Admins Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>ID</th>
                                    <th>Email</th>
                                    <th>Full Name</th>
                                    <th>Contact</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($admins->num_rows > 0): 
                                    while($admin = $admins->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input admin-checkbox" 
                                                   data-admin='<?= json_encode($admin) ?>' 
                                                   value="<?= $admin['account_id'] ?>">
                                        </td>
                                        <td><?= $admin['account_id'] ?></td>
                                        <td><?= htmlspecialchars($admin['email']) ?></td>
                                        <td><?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></td>
                                        <td><?= htmlspecialchars($admin['contact_number']) ?></td>
                                        <td><?= $admin['created_at'] ?></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="fas fa-user-shield fa-2x text-muted mb-2"></i>
                                            <p class="text-muted">No admin accounts found.</p>
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

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Add Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if(isset($error_msg)) echo "<div class='error'>$error_msg</div>"; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Default password will be <strong>"admin123"</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_admin" class="btn btn-success">Add Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1" aria-labelledby="editAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="account_id" id="edit_account_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" id="edit_contact_number" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_admin" class="btn btn-primary">Update Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Admin Modal -->
<div class="modal fade" id="deleteAdminModal" tabindex="-1" aria-labelledby="deleteAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header warning-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="account_id" id="delete_account_id">
                    <div class="warning-content">
                        <div class="warning-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="warning-text">
                            Are you sure you want to delete <strong id="delete_admin_name"></strong>?
                        </div>
                        <div class="warning-subtext">
                            <i class="fas fa-info-circle me-1"></i>
                            This action cannot be undone and will permanently remove this admin account.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="submit" name="delete_admin" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i> Delete Admin
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

// Check for success messages from PHP and show notifications
document.addEventListener('DOMContentLoaded', function() {
    <?php if(isset($_GET['success'])): ?>
        <?php if($_GET['success'] == 1): ?>
            showNotification('Admin added successfully!');
        <?php elseif($_GET['success'] == 2): ?>
            showNotification('Admin updated successfully!');
        <?php elseif($_GET['success'] == 3): ?>
            showNotification('Admin deleted successfully!');
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

let selectedAdmin = null;

// Handle checkbox selection
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.admin-checkbox');
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
            const checkedBoxes = document.querySelectorAll('.admin-checkbox:checked');
            selectAll.checked = checkedBoxes.length === checkboxes.length;
            selectAll.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
            updateActionButtons();
        });
    });

    function updateActionButtons() {
        const checkedBoxes = document.querySelectorAll('.admin-checkbox:checked');
        if (checkedBoxes.length === 1) {
            selectedAdmin = JSON.parse(checkedBoxes[0].getAttribute('data-admin'));
            editBtn.style.display = 'inline-block';
            deleteBtn.style.display = 'inline-block';
        } else if (checkedBoxes.length > 1) {
            editBtn.style.display = 'none';
            deleteBtn.style.display = 'inline-block';
        } else {
            editBtn.style.display = 'none';
            deleteBtn.style.display = 'none';
            selectedAdmin = null;
        }
    }
});

// Pre-fill Edit Modal
var editModal = document.getElementById('editAdminModal');
editModal.addEventListener('show.bs.modal', function (event) {
    if (selectedAdmin) {
        document.getElementById('edit_account_id').value = selectedAdmin.account_id;
        document.getElementById('edit_email').value = selectedAdmin.email;
        document.getElementById('edit_first_name').value = selectedAdmin.first_name;
        document.getElementById('edit_last_name').value = selectedAdmin.last_name;
        document.getElementById('edit_contact_number').value = selectedAdmin.contact_number;
    }
});

// Pre-fill Delete Modal
var deleteModal = document.getElementById('deleteAdminModal');
deleteModal.addEventListener('show.bs.modal', function (event) {
    if (selectedAdmin) {
        document.getElementById('delete_account_id').value = selectedAdmin.account_id;
        document.getElementById('delete_admin_name').textContent = selectedAdmin.first_name + ' ' + selectedAdmin.last_name;
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