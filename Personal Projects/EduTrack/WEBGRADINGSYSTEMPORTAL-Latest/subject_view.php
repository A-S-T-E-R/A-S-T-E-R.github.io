<?php
session_start();
require 'db_connect.php';

// Ensure only logged-in students can access
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

// Fetch student information including year level
$account_id = $_SESSION['account_id'];
$stmt = $conn->prepare("
    SELECT s.student_id, s.year_level_id, yl.level_name, yl.education_stage
    FROM tbl_students s
    JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id
    WHERE s.account_id = ?
");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    die("Student record not found.");
}
$student = $result->fetch_assoc();
$student_id = $student['student_id'];
$year_level_id = $student['year_level_id'];
$level_name = $student['level_name'];
$education_stage = $student['education_stage'];
$stmt->close();

// Fetch enrolled subjects for this student
$sql_subjects = "
    SELECT s.subject_id, s.subject_name, s.semester, e.date_enrolled
    FROM tbl_subject_enrollments e
    JOIN tbl_subjects s ON e.subject_id = s.subject_id
    WHERE e.student_id = ?
    ORDER BY s.subject_name
";
$stmt = $conn->prepare($sql_subjects);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count subjects by semester for summary
$first_semester_count = 0;
$second_semester_count = 0;
$full_year_count = 0;

foreach ($subjects as $subject) {
    switch($subject['semester']) {
        case '1': $first_semester_count++; break;
        case '2': $second_semester_count++; break;
        default: $full_year_count++;
    }
}
$total_subjects = count($subjects);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack - My Subjects</title>
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
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0 !important;
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
        
        .table th {
            background-color: var(--light);
            font-weight: 600;
        }
        
        .subject-row:hover {
            background-color: #f8f9fa;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .education-badge {
            font-size: 0.85rem;
            padding: 0.35rem 0.65rem;
        }
        
        .semester-badge {
            font-size: 0.8rem;
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
                    <li><a href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="grades_view.php"><i class="fas fa-chart-line"></i> Grades</a></li>
                    <li><a href="subjects_view.php" class="active"><i class="fas fa-book"></i> Subjects</a></li>
                    <li><a href="careerpath.php"><i class="fas fa-clipboard-check"></i> Career Test</a></li>
                    <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
                <div class="header">
                    <h3>My Subjects</h3>
                    <div class="user-info">
                        <div class="avatar"><?= substr($_SESSION['fullname'], 0, 1) ?></div>
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
                            <div class="text-muted">Student</div>
                        </div>
                    </div>
                </div>

                <div class="main-content">
                    <!-- Welcome Banner -->
                    <div class="welcome-section">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1>My Enrolled Subjects</h1>
                                <p>
                                    Viewing subjects for 
                                    <span class="badge bg-light text-dark education-badge">
                                        <?= htmlspecialchars($level_name) ?> - 
                                        <?= htmlspecialchars($education_stage) ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-book-open fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <i class="fas fa-book"></i>
                                <div class="number"><?= $total_subjects ?></div>
                                <div class="label">Total Subjects</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <i class="fas fa-calendar-alt"></i>
                                <div class="number"><?= $first_semester_count ?></div>
                                <div class="label">1st Semester</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <i class="fas fa-calendar"></i>
                                <div class="number"><?= $second_semester_count ?></div>
                                <div class="label">2nd Semester</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <i class="fas fa-calendar-day"></i>
                                <div class="number"><?= $full_year_count ?></div>
                                <div class="label">Full Year</div>
                            </div>
                        </div>
                    </div>

                    <!-- Subjects Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Enrolled Subjects</h5>
                            <span class="badge bg-primary"><?= htmlspecialchars($level_name) ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($subjects)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">You are not enrolled in any subjects yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Subject Name</th>
                                                <th>Semester</th>
                                                <th>Date Enrolled</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subjects as $subject): ?>
                                                <tr class="subject-row">
                                                    <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                                                    <td>
                                                        <?php 
                                                        $badge_class = "";
                                                        switch($subject['semester']) {
                                                            case '1': 
                                                                echo '<span class="badge bg-info semester-badge">1st Semester</span>'; 
                                                                break;
                                                            case '2': 
                                                                echo '<span class="badge bg-warning text-dark semester-badge">2nd Semester</span>'; 
                                                                break;
                                                            default: 
                                                                echo '<span class="badge bg-success semester-badge">Full Year</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?= date('F j, Y', strtotime($subject['date_enrolled'])) ?></td>
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