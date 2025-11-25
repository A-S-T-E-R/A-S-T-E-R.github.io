<?php
session_start();
require 'db_connect.php';

// Enhanced cache prevention
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Check if user is logged in
if (!isset($_SESSION['account_id'])) {
    header("Location: index.php");
    exit();
}

$account_id = (int)$_SESSION['account_id'];
$role = $_SESSION['role'] ?? 'guest';
$fullname = $_SESSION['fullname'] ?? 'User';

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

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $feedback_text = trim($_POST['feedback_text']);
    
    if (!empty($feedback_text)) {
        // Check if role column exists
        $check_column = $conn->query("SHOW COLUMNS FROM tbl_feedbacks LIKE 'role'");
        $has_role_column = ($check_column->num_rows > 0);
        
        if ($has_role_column) {
            $stmt = $conn->prepare("INSERT INTO tbl_feedbacks (account_id, feedback_text, role) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $account_id, $feedback_text, $role);
        } else {
            $stmt = $conn->prepare("INSERT INTO tbl_feedbacks (account_id, feedback_text) VALUES (?, ?)");
            $stmt->bind_param("is", $account_id, $feedback_text);
        }
        
        if ($stmt->execute()) {
            $success_message = "Thank you for your feedback!";
        } else {
            $error_message = "Sorry, there was an error submitting your feedback. Please try again.";
        }
        $stmt->close();
    } else {
        $error_message = "Please write your feedback before submitting.";
    }
}

// Fetch feedbacks (admin sees all, others don't see any)
if ($role === 'admin') {
    $query = "SELECT f.feedback_id, f.feedback_text, f.created_at, 
                     a.username, CONCAT(a.first_name, ' ', a.last_name) AS fullname,
                     a.role
              FROM tbl_feedbacks f 
              LEFT JOIN tbl_accounts a ON f.account_id = a.account_id
              ORDER BY f.created_at DESC";
              
    $result = $conn->query($query);
    $total_feedbacks = $result->num_rows;

    // Get pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;
    $total_pages = ceil($total_feedbacks / $per_page);
    $offset = ($page - 1) * $per_page;

    // Fetch paginated results
    $query .= " LIMIT $offset, $per_page";
    $result = $conn->query($query);
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
    <title>EduTrack - Feedback System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #1abc9c;
            --light: #ecf0f1;
            --dark: #34495e;
            --parent: #9b59b6;
            --teacher: #e74c3c;
            --student: #3498db;
            --admin: #2c3e50;
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
        
        .feedback-item {
            border-left: 4px solid var(--secondary);
            padding: 15px;
            margin-bottom: 15px;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }
        
        .feedback-item.parent {
            border-left-color: var(--parent);
        }
        
        .feedback-item.teacher {
            border-left-color: var(--teacher);
        }
        
        .feedback-item.student {
            border-left-color: var(--student);
        }
        
        .feedback-item.admin {
            border-left-color: var(--admin);
        }
        
        .role-badge {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .badge-parent {
            background-color: var(--parent);
            color: white;
        }
        
        .badge-teacher {
            background-color: var(--teacher);
            color: white;
        }
        
        .badge-student {
            background-color: var(--student);
            color: white;
        }
        
        .badge-admin {
            background-color: var(--admin);
            color: white;
        }
        
        .feedback-meta {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .feedback-content {
            font-size: 1.1rem;
            line-height: 1.5;
        }
        
        .pagination {
            justify-content: center;
            margin-top: 30px;
        }
        
        .page-link {
            color: var(--primary);
        }
        
        .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #777;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        .filter-section {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .submit-section {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .btn-submit {
            background-color: var(--accent);
            border-color: var(--accent);
            color: white;
        }
        
        .btn-submit:hover {
            background-color: #16a085;
            border-color: #16a085;
            color: white;
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
        
        /* Welcome Section Styles (Copied from previous page) */
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
        
        .confirmation-message {
            display: none;
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
                        <?php elseif ($role === 'parent'): ?>
                            Parent Portal
                        <?php else: ?>
                            User Portal
                        <?php endif; ?>
                    </p>
                </div>

                <ul class="sidebar-menu">
                    <?php if ($role === 'student'): ?>
                     <li><a href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="student_activities.php"><i class="fas fa-tasks"></i> Activities</a></li>
            <li><a href="student_records.php" class=><i class="fas fa-chart-line"></i> Records</a></li>
            <li><a href="grades_view.php"><i class="fas fa-chart-bar"></i> Grades</a></li>
            <li><a href="careerpath.php"><i class="fas fa-clipboard-check"></i> Career Test</a></li>
            <li><a href="feedbacks.php" class="active"><i class="fas fa-comment-dots"></i> Feedback</a></li>
            <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li> 

                    <?php elseif ($role === 'teacher'): ?>
                        <li><a href="teacher_dashboard.php" class="<?= !isset($_GET['view_class']) ?  : '' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li class="has-submenu <?= isset($_GET['view_class']) ? 'active' : '' ?>">
                            <a href="#"><i class="fas fa-book"></i> My Classes</a>
                            <ul class="submenu">
                                <?php foreach ($assigned_classes as $class): ?>
                                    <li>
                                        <a href="teacher_dashboard.php?view_class=1&subject_id=<?= $class['subject_id'] ?>&section_id=<?= $class['section_id'] ?>" 
                                           class="<?= (isset($_GET['subject_id']) && $_GET['subject_id'] == $class['subject_id']) ? 'active' : '' ?>">
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
                        <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                        <li><a href="feedbacks.php" class="active"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                        <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>

                    <?php elseif ($role === 'parent'): ?>
                        <li><a href="parent_dashboard.php"><i class="fas fa-tachometer-alt"></i> Grades</a></li>
                                            <li><a href="parent_records.php" class=""><i class="fas fa-chart-line"></i> Student Records</a></li>

                        <li><a href="feedbacks.php" class="active"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                        <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>

                    <?php elseif ($role === 'admin'): ?>
                        
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
                          <li><a href="feedbacks.php" class="active">View Feedbacks</a></li>
                          <li><a href="archive_manager.php">Archive Manager</a></li>
                          <li><a href="change_pass.php">Settings</a></li>
                        </ul>
                      </li>

                      <li><a href="logout.php" class="text-danger" onclick="return confirmLogout()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
                    </ul>

                

                    <?php else: ?>
                        <li><a href="index.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
                <div class="header">
                    <h3><i class="fas fa-comment-dots"></i> 
                        <?php echo $role === 'admin' ? 'View Feedbacks' : 'Submit Feedback'; ?>
                    </h3>
                    <div class="user-info">
                        <div class="avatar"><?php echo strtoupper(substr($fullname, 0, 1)); ?></div>
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($fullname); ?></div>
                            <div class="text-muted"><?php echo ucfirst($role); ?></div>
                        </div>
                    </div>
                </div>

                <div class="main-content">
                    <!-- Welcome Section (Copied from previous page) -->
                    <div class="welcome-section">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1>
                                    <?php if ($role === 'admin'): ?>
                                        Feedback Management
                                    <?php else: ?>
                                        Share Your Feedback
                                    <?php endif; ?>
                                </h1>
                                <p>
                                    <?php if ($role === 'admin'): ?>
                                        Review all user feedback and suggestions to improve the system
                                    <?php else: ?>
                                        Help us improve by sharing your thoughts and suggestions
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-comments fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Feedback Submission Section (for non-admins) -->
                    <?php if ($role !== 'admin'): ?>
                    <div class="submit-section" id="submitSection">
                        <h3 class="mb-4"><i class="fas fa-pencil-alt me-2"></i>Submit Your Feedback</h3>
                        
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="feedbackText" class="form-label">Your Feedback</label>
                                <textarea class="form-control" id="feedbackText" name="feedback_text" rows="4" placeholder="Please share your thoughts, suggestions, or concerns..." required></textarea>
                            </div>
                            
                            <button type="submit" name="submit_feedback" class="btn btn-submit btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                            </button>
                        </form>
                    </div>
                    
                    <!-- Confirmation message after submission -->
                    <div class="card mt-4 confirmation-message" id="confirmationMessage">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle text-success mb-3" style="font-size: 3rem;"></i>
                            <h4>Thank You for Your Feedback!</h4>
                            <p class="text-muted">Your feedback has been submitted successfully. Administrators will review your comments.</p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Feedback List (Admin Only) -->
                    <?php if ($role === 'admin'): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>All Feedbacks</span>
                            <span class="badge bg-primary">
                                <?php echo $total_feedbacks; ?> feedback(s)
                            </span>
                        </div>
                        <div class="card-body">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <div id="feedbackList">
                                    <?php while ($row = $result->fetch_assoc()): 
                                        $user_role = isset($row['role']) ? $row['role'] : 'user';
                                    ?>
                                        <div class="feedback-item <?php echo $user_role; ?>">
                                            <div class="feedback-meta d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong>
                                                        <?php 
                                                        if (!empty($row['fullname'])) {
                                                            echo htmlspecialchars($row['fullname']);
                                                        } else if (!empty($row['username'])) {
                                                            echo htmlspecialchars($row['username']);
                                                        } else {
                                                            echo 'Anonymous';
                                                        }
                                                        ?>
                                                    </strong> • 
                                                    <span class="role-badge badge-<?php echo $user_role; ?>">
                                                        <?php echo ucfirst($user_role); ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <?php echo date('F j, Y, g:i a', strtotime($row['created_at'])); ?>
                                                </div>
                                            </div>
                                            <div class="feedback-content">
                                                <?php echo htmlspecialchars($row['feedback_text']); ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <nav aria-label="Feedback pagination">
                                    <ul class="pagination">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page-1; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page+1; ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-comment-slash"></i>
                                    <h4>No feedbacks yet</h4>
                                    <p>No one has submitted feedback yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
    
    // Show confirmation message only after form submission
    <?php if (isset($success_message) && $role !== 'admin'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('submitSection').style.display = 'none';
            document.getElementById('confirmationMessage').style.display = 'block';
        });
    <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>