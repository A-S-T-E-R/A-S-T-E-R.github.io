<?php
// Start session and set cache control headers
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
$user = "root"; // change if needed
$pass = ""; // change if needed
$dbname = "school_portal";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Count queries
$total_students = $conn->query("SELECT COUNT(*) AS total FROM tbl_students")->fetch_assoc()['total'];
$total_teachers = $conn->query("SELECT COUNT(*) AS total FROM tbl_accounts WHERE role='teacher'")->fetch_assoc()['total'];
$total_parents = $conn->query("SELECT COUNT(*) AS total FROM tbl_accounts WHERE role='parent'")->fetch_assoc()['total'];
$total_admins = $conn->query("SELECT COUNT(*) AS total FROM tbl_accounts WHERE role='admin'")->fetch_assoc()['total'];
$total_subjects = $conn->query("SELECT COUNT(*) AS total FROM tbl_subjects")->fetch_assoc()['total'];
$total_sections = $conn->query("SELECT COUNT(*) AS total FROM tbl_sections")->fetch_assoc()['total'];
$total_career_tests = $conn->query("SELECT COUNT(*) AS total FROM tbl_career_tests")->fetch_assoc()['total'];
$total_feedbacks = $conn->query("SELECT COUNT(*) AS total FROM tbl_feedbacks")->fetch_assoc()['total'];

// Recent logs
$logs = $conn->query("SELECT l.action, l.created_at, a.username 
                      FROM tbl_audit_logs l 
                      LEFT JOIN tbl_accounts a ON l.account_id = a.account_id 
                      ORDER BY l.created_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>EduTrack - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>

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
                          <li><a href="manage_parents.php" class="active">Parent Accounts</a></li>

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
                    <h3>Admin Dashboard</h3>
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
                                <h1>Welcome, Administrator!</h1>
                                <p>Manage all aspects of the school system from this dashboard</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-user-shield fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Overview -->
                   <div class="row mb-4">
    <?php
    $stats = [
        "Students" => ["icon" => "fa-user-graduate", "count" => $total_students, "link" => "manage_student.php"],
        "Teachers" => ["icon" => "fa-chalkboard-teacher", "count" => $total_teachers, "link" => "manage_teachers.php"],
        "Parents" => ["icon" => "fa-user-friends", "count" => $total_parents, "link" => "manage_parents.php"],
        "Admins" => ["icon" => "fa-user-shield", "count" => $total_admins, "link" => "manage_admins.php"],
        "Subjects" => ["icon" => "fa-book", "count" => $total_subjects, "link" => "admin_subjects_manage.php"],
        "Sections" => ["icon" => "fa-users", "count" => $total_sections, "link" => "manage_sections.php"],
        "Career Tests" => ["icon" => "fa-clipboard-check", "count" => $total_career_tests, "link" => "careerpath.php"],
        "Feedbacks" => ["icon" => "fa-comments", "count" => $total_feedbacks, "link" => "feedbacks.php"],
        "Add Subject" => ["icon" => "fa-plus-circle", "count" => "+", "link" => "subject_adding.php"], // ✅ New card
    ];

    foreach ($stats as $label => $data):
    ?>
    <div class="col-md-3 mb-3">
        <a href="<?= $data['link'] ?>" class="text-decoration-none">
            <div class="card stat-card clickable-card">
                <i class="fas <?= $data['icon'] ?>"></i>
                <div class="number"><?= $data['count'] ?></div>
                <div class="label"><?= $label ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>


                    <!-- Recent Activity Section -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span>Recent Activity Logs</span>
                                    <span class="badge bg-primary">Last 10 activities</span>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Action</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($log = $logs->fetch_assoc()): ?>
                                                    <tr class="log-row">
                                                        <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                                                        <td><?= htmlspecialchars($log['action']) ?></td>
                                                        <td><?= htmlspecialchars($log['created_at']) ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Improved session management script
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
    });

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

    </script>
</body>
</html>
<?php $conn->close(); ?>