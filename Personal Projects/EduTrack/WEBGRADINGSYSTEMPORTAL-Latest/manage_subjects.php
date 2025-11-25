<?php
session_start();
require 'db_connect.php';

// Ensure only logged-in students can access
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

// Fetch student_id
$account_id = $_SESSION['account_id'];
$stmt = $conn->prepare("SELECT student_id FROM tbl_students WHERE account_id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    die("Student record not found.");
}
$student_id = $result->fetch_assoc()['student_id'];
$stmt->close();

// Fetch available subjects
$sql_available = "
    SELECT s.subject_id, s.subject_name
    FROM tbl_subjects s
    WHERE s.subject_id NOT IN (
        SELECT subject_id FROM tbl_subject_enrollments WHERE student_id = ?
    )
    ORDER BY s.subject_name";
$stmt_available = $conn->prepare($sql_available);
$stmt_available->bind_param("i", $student_id);
$stmt_available->execute();
$available_subjects = $stmt_available->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_available->close();

// Fetch enrolled subjects
$sql_enrolled = "
    SELECT s.subject_id, s.subject_name, e.lecture_units, e.lab_units
    FROM tbl_subject_enrollments e
    JOIN tbl_subjects s ON e.subject_id = s.subject_id
    WHERE e.student_id = ?
    ORDER BY s.subject_name";
$stmt_enrolled = $conn->prepare($sql_enrolled);
$stmt_enrolled->bind_param("i", $student_id);
$stmt_enrolled->execute();
$enrolled_subjects = $stmt_enrolled->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_enrolled->close();

// Handle subject enrollment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll_subject'])) {
    $subject_id = $_POST['subject_id'];
    
    // Check if already enrolled
    $check_stmt = $conn->prepare("SELECT * FROM tbl_subject_enrollments WHERE student_id = ? AND subject_id = ?");
    $check_stmt->bind_param("ii", $student_id, $subject_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        // Enroll the subject (using default values for units)
        $enroll_stmt = $conn->prepare("INSERT INTO tbl_subject_enrollments (student_id, subject_id, lecture_units, lab_units) VALUES (?, ?, 0, 0)");
        $enroll_stmt->bind_param("ii", $student_id, $subject_id);
        $enroll_stmt->execute();
        $enroll_stmt->close();
        
        // Refresh the page to show updated lists
        header("Location: subjects_manage.php");
        exit();
    }
    $check_stmt->close();
}

// Handle subject dropping
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['drop_subject'])) {
    $subject_id = $_POST['subject_id'];
    
    $drop_stmt = $conn->prepare("DELETE FROM tbl_subject_enrollments WHERE student_id = ? AND subject_id = ?");
    $drop_stmt->bind_param("ii", $student_id, $subject_id);
    $drop_stmt->execute();
    $drop_stmt->close();
    
    // Refresh the page to show updated lists
    header("Location: subjects_manage.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack - Manage Subjects</title>
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
        
        .badge-student {
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="sidebar-header">
                    <h4><i class="fas fa-graduation-cap"></i> EduTrack</h4>
                    <p class="mb-0">Student Portal</p>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="manage_student.php"><i class="fas fa-user-graduate"></i> Manage Students</a></li>
                    <li><a href="manage_teachers.php"><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</a></li>
                    <li><a href="create_account.php"><i class="fas fa-user-friends"></i> Create Accounts</a></li>
                    <li><a href="manage_admins.php"><i class="fas fa-user-shield"></i> Manage Admins</a></li>
                    <li><a href="year_levels.php"><i class="fas fa-layer-group"></i> Year Levels</a></li>
                    <li><a href="manage_sections.php"><i class="fas fa-users"></i> Sections</a></li>
                    <li><a href="admin_subjects_manage.php"><i class="fas fa-user-edit"></i> Manage Student Subjects</a></li>
                    <li><a href="subject_adding.php"><i class="fas fa-book"></i> Add Subjects</a></li>
                    <li><a href="careerpath.php"><i class="fas fa-clipboard-check"></i> Career Result</a></li>
                    <li><a href="feedbacks.php"><i class="fas fa-comment-dots"></i><span>View Feedbacks</span></a></li>
                    <li><a href="archive_manager..php"><i class="fas fa-archive"></i> Archive Manager</a></li>
                    <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="logout.php" class="text-danger" onclick="return confirmLogout()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
                <div class="header">
                    <h3>Manage Subjects</h3>
                    <div class="user-info">
                        <div class="avatar"><?= substr($_SESSION['fullname'], 0, 1) ?></div>
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
                            <div class="text-muted">Student</div>
                        </div>
                    </div>
                </div>

                <div class="main-content">
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
                                <p class="text-muted">You are not enrolled in any subjects yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Subject Name</th>
                                                <th>Lecture Units</th>
                                                <th>Lab Units</th>
                                                
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($enrolled_subjects as $subject): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                                                    <td><?= $subject['lecture_units'] ?></td>
                                                    <td><?= $subject['lab_units'] ?></td>
                                                    <td>
                                                        
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Available Subjects Card -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon green">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <div>
                                <h5 class="mb-0">Available Subjects</h5>
                                <p class="mb-0 text-muted"><?= count($available_subjects) ?> subjects available</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($available_subjects)): ?>
                                <p class="text-muted">No available subjects to enroll in.</p>
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
                                                            <button type="submit" name="enroll_subject" class="btn btn-sm btn-success">
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
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
