<?php
// teacher_activity.php
session_start();

// Database connection
$host = 'localhost';
$dbname = 'school_portal';
$username = 'root'; // Change if needed
$password = ''; // Change if needed

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is logged in and is a teacher
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['account_id'];
$teacher_name = $_SESSION['fullname'] ?? 'Teacher';
$message = '';
$error = '';

// Fetch assigned classes (subjects and sections) for the teacher
$assigned_classes = [];
try {
    $stmt = $pdo->prepare("
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
        ORDER BY yl.year_level_id, s.subject_name, sec.section_name
    ");
    $stmt->execute([$teacher_id]);
    $assigned_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Group assigned classes by year level and subject
$grouped_classes = [];
foreach ($assigned_classes as $class) {
    $year_level_id = $class['year_level_id'];
    $subject_id = $class['subject_id'];
    
    if (!isset($grouped_classes[$year_level_id])) {
        $grouped_classes[$year_level_id] = [
            'level_name' => $class['level_name'],
            'subjects' => []
        ];
    }
    
    if (!isset($grouped_classes[$year_level_id]['subjects'][$subject_id])) {
        $grouped_classes[$year_level_id]['subjects'][$subject_id] = [
            'subject_name' => $class['subject_name'],
            'sections' => []
        ];
    }
    
    $grouped_classes[$year_level_id]['subjects'][$subject_id]['sections'][] = [
        'section_id' => $class['section_id'],
        'section_name' => $class['section_name']
    ];
}

// Get activities posted by this teacher
$teacher_activities = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, yl.level_name, s.section_name, sub.subject_name
        FROM tbl_teacher_activities a
        JOIN tbl_yearlevels yl ON a.year_level_id = yl.year_level_id
        LEFT JOIN tbl_sections s ON a.section_id = s.section_id
        JOIN tbl_subjects sub ON a.subject_id = sub.subject_id
        WHERE a.teacher_account_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$teacher_id]);
    $teacher_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silently fail if activities can't be loaded
}

// Get comments for each activity
$activity_comments = [];
try {
    $comments_table_exists = $pdo->query("SHOW TABLES LIKE 'tbl_activity_comments'")->rowCount() > 0;
    
    if ($comments_table_exists) {
        foreach ($teacher_activities as $activity) {
            $stmt = $pdo->prepare("
                SELECT c.*, 
                       CONCAT(acc.first_name, ' ', acc.last_name) as student_name,
                       st.year_level_id,
                       yl.level_name,
                       sec.section_name
                FROM tbl_activity_comments c
                JOIN tbl_students st ON c.student_id = st.student_id
                JOIN tbl_accounts acc ON st.account_id = acc.account_id
                JOIN tbl_yearlevels yl ON st.year_level_id = yl.year_level_id
                LEFT JOIN tbl_sections sec ON st.section_id = sec.section_id
                WHERE c.activity_id = ?
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$activity['activity_id']]);
            $activity_comments[$activity['activity_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    // Silently fail if comments can't be loaded
}

// Handle form submission for creating activities
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $year_level_id = (int)$_POST['year_level_id'];
    $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
    $subject_id = (int)$_POST['subject_id'];
    $activity_type = $_POST['activity_type'];
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $points = !empty($_POST['points']) ? (int)$_POST['points'] : null;
    
    // Validate inputs
    if (empty($title) || empty($description) || empty($year_level_id) || empty($subject_id)) {
        $error = "Please fill in all required fields.";
    } else {
        // Verify the teacher is assigned to this subject and section
        $is_assigned = false;
        
        // If section is not specified (All Sections), check if teacher is assigned to any section for this subject
        if ($section_id === null) {
            foreach ($assigned_classes as $class) {
                if ($class['subject_id'] == $subject_id && $class['year_level_id'] == $year_level_id) {
                    $is_assigned = true;
                    break;
                }
            }
        } 
        // If section is specified, check if teacher is assigned to that specific section
        else {
            foreach ($assigned_classes as $class) {
                if ($class['subject_id'] == $subject_id && 
                    $class['section_id'] == $section_id && 
                    $class['year_level_id'] == $year_level_id) {
                    $is_assigned = true;
                    break;
                }
            }
        }
        
        if (!$is_assigned) {
            $error = "You are not assigned to teach this subject or section.";
        } else {
            // Handle file upload
            $attachment = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/teacher_activities/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid() . '_' . time() . '.' . $file_ext;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $file_path)) {
                    $attachment = $file_path;
                } else {
                    $error = "Failed to upload file.";
                }
            }
            
            if (empty($error)) {
                try {
                    // Check if the table exists, if not create it
                    $table_exists = $pdo->query("SHOW TABLES LIKE 'tbl_teacher_activities'")->rowCount() > 0;

                    if ($table_exists) {
                        // Check if the subject_id column exists
                        $columns = $pdo->query("SHOW COLUMNS FROM tbl_teacher_activities");
                        $column_names = array();
                        while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
                            $column_names[] = $col['Field'];
                        }
                        
                        // Add missing columns if they don't exist
                        if (!in_array('subject_id', $column_names)) {
                            $pdo->exec("ALTER TABLE tbl_teacher_activities ADD COLUMN subject_id INT UNSIGNED NOT NULL AFTER section_id");
                            $pdo->exec("ALTER TABLE tbl_teacher_activities ADD FOREIGN KEY (subject_id) REFERENCES tbl_subjects(subject_id) ON DELETE CASCADE");
                        }
                        
                        if (!in_array('due_date', $column_names)) {
                            $pdo->exec("ALTER TABLE tbl_teacher_activities ADD COLUMN due_date DATETIME NULL AFTER attachment");
                        }
                        
                        if (!in_array('points', $column_names)) {
                            $pdo->exec("ALTER TABLE tbl_teacher_activities ADD COLUMN points INT UNSURNED NULL AFTER due_date");
                        }
                    } else {
                        // Create the table if it doesn't exist
                        $pdo->exec("
                            CREATE TABLE tbl_teacher_activities (
                                activity_id INT AUTO_INCREMENT PRIMARY KEY,
                                teacher_account_id INT UNSIGNED NOT NULL,
                                year_level_id INT UNSIGNED NOT NULL,
                                section_id INT UNSIGNED NULL,
                                subject_id INT UNSIGNED NOT NULL,
                                activity_type ENUM('assignment', 'activity', 'reviewer', 'announcement') DEFAULT 'assignment',
                                title VARCHAR(200) NOT NULL,
                                description TEXT NOT NULL,
                                attachment VARCHAR(255) DEFAULT NULL,
                                due_date DATETIME NULL,
                                points INT UNSIGNED NULL,
                                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (teacher_account_id) REFERENCES tbl_accounts(account_id) ON DELETE CASCADE,
                                FOREIGN KEY (year_level_id) REFERENCES tbl_yearlevels(year_level_id) ON DELETE CASCADE,
                                FOREIGN KEY (section_id) REFERENCES tbl_sections(section_id) ON DELETE SET NULL,
                                FOREIGN KEY (subject_id) REFERENCES tbl_subjects(subject_id) ON DELETE CASCADE
                            )
                        ");
                    }
                    
                    // Insert into database
                    $stmt = $pdo->prepare("
                        INSERT INTO tbl_teacher_activities 
                        (teacher_account_id, year_level_id, section_id, subject_id, title, description, attachment, activity_type, due_date, points, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $teacher_id, 
                        $year_level_id, 
                        $section_id, 
                        $subject_id,
                        $title, 
                        $description, 
                        $attachment,
                        $activity_type,
                        $due_date,
                        $points
                    ]);
                    
                    $message = "Activity posted successfully!";
                    
                    // Reset form values
                    $title = $description = '';
                    $year_level_id = $subject_id = $section_id = '';
                    $activity_type = 'assignment';
                    $due_date = $points = '';
                    
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_grade'])) {
    $comment_id = (int)$_POST['comment_id'];
    $grade = !empty($_POST['grade']) ? (float)$_POST['grade'] : null;
    $max_points = !empty($_POST['max_points']) ? (int)$_POST['max_points'] : null;
    $feedback = trim($_POST['feedback']);
    
    try {
        // Check if the grade columns exist in the comments table
        $columns = $pdo->query("SHOW COLUMNS FROM tbl_activity_comments");
        $column_names = array();
        while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
            $column_names[] = $col['Field'];
        }
        
        // Add grade columns if they don't exist
        if (!in_array('grade', $column_names)) {
            $pdo->exec("ALTER TABLE tbl_activity_comments 
                ADD COLUMN grade DECIMAL(5,2) NULL AFTER file_path,
                ADD COLUMN graded_at DATETIME NULL AFTER grade,
                ADD COLUMN graded_by INT UNSIGNED NULL AFTER graded_at,
                ADD COLUMN feedback TEXT NULL AFTER grade,
                ADD COLUMN max_points INT UNSIGNED NULL AFTER grade");
                
            $pdo->exec("ALTER TABLE tbl_activity_comments
                ADD CONSTRAINT fk_comment_grader 
                FOREIGN KEY (graded_by) REFERENCES tbl_accounts(account_id) 
                ON DELETE SET NULL");
        }
        
        $stmt = $pdo->prepare("
            UPDATE tbl_activity_comments 
            SET grade = ?, max_points = ?, feedback = ?, graded_by = ?, graded_at = NOW()
            WHERE comment_id = ?
        ");
        $stmt->execute([$grade, $max_points, $feedback, $teacher_id, $comment_id]);
        
        $_SESSION['success_message'] = "Grade submitted successfully!";
        header("Location: teacher_activity.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error submitting grade: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack - Upload Activity</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #1abc9c;
            --light: #ecf0f1;
            --dark: #34495e;
        }

        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar */
        .sidebar {
            background: var(--primary);
            color: #fff;
            min-height: 100vh;
            padding: 0;
            box-shadow: 3px 0 10px rgba(0, 0, 0, .1);
        }

        .sidebar-header {
            padding: 20px;
            background: rgba(0, 0, 0, .2);
            border-bottom: 1px solid rgba(255, 255, 255, .1);
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu a {
            color: rgba(255, 255, 255, .8);
            text-decoration: none;
            display: block;
            padding: 12px 20px;
            transition: .3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(0, 0, 0, .2);
            color: #fff;
            border-left: 3px solid var(--accent);
        }

        .sidebar-menu i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }

        /* Submenu */
        .submenu {
            list-style: none;
            background: rgba(0, 0, 0, .1);
            margin-top: 0;
            padding-left: 0;
            display: none;
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

        /* Main content */
        .main-content {
            padding: 20px;
        }

        /* Header */
        .header {
            background: #fff;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .05);
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, .05);
            transition: transform .3s, box-shadow .3s;
            margin-bottom: 20px;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, .1);
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0 !important;
        }

        /* Stat Cards */
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

        .label {
            color: #777;
            font-size: .9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Badges */
        .subject-badge {
            background: var(--accent);
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: .8rem;
            font-weight: 500;
        }

        .grade-level-badge {
            background: var(--secondary);
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: .9rem;
            font-weight: 500;
        }

        .average-badge {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 4px;
        }

        /* Inputs */
        .grade-input {
            width: 100%;
            max-width: 80px;
            text-align: center;
        }

        /* Sections */
        .welcome-section {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: #fff;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .nav-tabs .nav-link.active {
            background: var(--secondary);
            color: #fff;
        }

        .class-section {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #fff;
        }

        /* Grades Table */
        .grade-cell {
            position: relative;
        }

        .grade-cell:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }

        .quarter-header {
            background-color: #e9ecef;
            font-weight: bold;
        }

        /* Activity Cards */
        .activity-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #fff;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .activity-title {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .activity-meta {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .activity-description {
            margin-bottom: 15px;
        }

        .activity-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #dee2e6;
            padding-top: 10px;
            margin-top: 10px;
        }

        .activity-type {
            background: var(--secondary);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .attachment-link {
            color: var(--accent);
            text-decoration: none;
        }

        .attachment-link:hover {
            text-decoration: underline;
        }

        /* Form Styles */
        .form-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 10px 12px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .btn-primary {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }

        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        .btn-outline-secondary {
            border-color: #ced4da;
        }

        /* Alert Styles */
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

        .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }

        /* Comment Section */
        .comment-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }

        .comment {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .comment-author {
            font-weight: 500;
            font-size: 0.9rem;
        }

        .comment-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .comment-text {
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* Grading Section */
        .grading-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--secondary);
        }
        
        .grade-display {
            font-size: 18px;
            font-weight: bold;
            color: var(--secondary);
        }
        
        .grade-form {
            margin-top: 15px;
            padding: 15px;
            background-color: white;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .graded-badge {
            background-color: var(--accent);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .activity-header {
                flex-direction: column;
            }
            
            .activity-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .comment-header {
                flex-direction: column;
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
                    <p class="mb-0">Teacher Portal</p>
                </div>
                <ul class="sidebar-menu">
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
                    <li><a href="teacher_activity.php" class="active"><i class="fas fa-tasks"></i> Upload Activity</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="feedbacks.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                    <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>

            <!-- Main -->
            <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
                <div class="header">
                    <h3>Upload Activity</h3>
                    <div class="d-flex align-items-center">
                        <div class="avatar"><?= htmlspecialchars(substr($teacher_name,0,1)) ?></div>
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($teacher_name) ?></div>
                            <div class="text-muted">Teacher</div>
                        </div>
                    </div>
                </div>

                <div class="main-content">
                    <!-- Welcome -->
                    <div class="welcome-section">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1>Create Activities, <?= htmlspecialchars(explode(" ", $teacher_name)[0]) ?>!</h1>
                                <p>Post assignments, activities, reviewers, or announcements for your classes.</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-tasks fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php 
                            echo $_SESSION['success_message']; 
                            unset($_SESSION['success_message']);
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php 
                            echo $_SESSION['error_message']; 
                            unset($_SESSION['error_message']);
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <!-- Create Activity Form -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Create New Activity</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($assigned_classes)): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> You haven't been assigned to any classes yet. Please contact the administrator.
                                        </div>
                                    <?php else: ?>
                                        <form action="teacher_activity.php" method="POST" enctype="multipart/form-data">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label" for="activity_type">Activity Type</label>
                                                    <select id="activity_type" name="activity_type" class="form-select" required>
                                                        <option value="assignment" <?php echo (isset($activity_type) && $activity_type === 'assignment') ? 'selected' : ''; ?>>Assignment</option>
                                                        <option value="activity" <?php echo (isset($activity_type) && $activity_type === 'activity') ? 'selected' : ''; ?>>Activity</option>
                                                        <option value="reviewer" <?php echo (isset($activity_type) && $activity_type === 'reviewer') ? 'selected' : ''; ?>>Reviewer</option>
                                                        <option value="announcement" <?php echo (isset($activity_type) && $activity_type === 'announcement') ? 'selected' : ''; ?>>Announcement</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <label class="form-label" for="year_level_id">Grade Level</label>
                                                    <select id="year_level_id" name="year_level_id" class="form-select" required>
                                                        <option value="">Select Grade Level</option>
                                                        <?php foreach ($grouped_classes as $year_id => $year_data): ?>
                                                            <option value="<?php echo $year_id; ?>" 
                                                                <?php echo (isset($year_level_id) && $year_level_id == $year_id) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($year_data['level_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label" for="subject_id">Subject</label>
                                                    <select id="subject_id" name="subject_id" class="form-select" required>
                                                        <option value="">Select Subject</option>
                                                        <?php if (isset($year_level_id) && isset($grouped_classes[$year_level_id])): ?>
                                                            <?php foreach ($grouped_classes[$year_level_id]['subjects'] as $subject_id_val => $subject_data): ?>
                                                                <option value="<?php echo $subject_id_val; ?>" 
                                                                    <?php echo (isset($subject_id) && $subject_id == $subject_id_val) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($subject_data['subject_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <label class="form-label" for="section_id">Section (Optional)</label>
                                                    <select id="section_id" name="section_id" class="form-select">
                                                        <option value="">All Sections</option>
                                                        <?php if (isset($year_level_id) && isset($subject_id) && 
                                                                 isset($grouped_classes[$year_level_id]['subjects'][$subject_id])): ?>
                                                            <?php foreach ($grouped_classes[$year_level_id]['subjects'][$subject_id]['sections'] as $section): ?>
                                                                <option value="<?php echo $section['section_id']; ?>" 
                                                                    <?php echo (isset($section_id) && $section_id == $section['section_id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($section['section_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label" for="due_date">Due Date (Optional)</label>
                                                    <input type="datetime-local" id="due_date" name="due_date" class="form-control" 
                                                           value="<?php echo isset($due_date) ? htmlspecialchars($due_date) : ''; ?>">
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <label class="form-label" for="points">Max Points (Optional)</label>
                                                    <input type="number" id="points" name="points" class="form-control" min="0" 
                                                           value="<?php echo isset($points) ? htmlspecialchars($points) : '100'; ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label" for="title">Title</label>
                                                <input type="text" id="title" name="title" class="form-control" 
                                                       value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label" for="description">Description</label>
                                                <textarea id="description" name="description" class="form-control" rows="5" required><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label" for="attachment">Attachment (Optional)</label>
                                                <input type="file" id="attachment" name="attachment" class="form-control">
                                            </div>
                                            
                                            <div class="d-flex gap-2 mt-4">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-plus"></i> Create Activity
                                                </button>
                                                <button type="reset" class="btn btn-outline-secondary">Clear Form</button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Your Classes -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-book"></i> Your Classes</h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($grouped_classes as $year_id => $year_data): ?>
                                        <h6 class="mt-3 mb-2 text-muted">
                                            <?php echo htmlspecialchars($year_data['level_name']); ?>
                                        </h6>
                                        
                                        <?php foreach ($year_data['subjects'] as $subject_id_val => $subject_data): ?>
                                            <div class="class-section p-3 mb-2">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($subject_data['subject_name']); ?></h6>
                                                <p class="mb-1 small text-muted">
                                                    Sections:
                                                    <?php foreach ($subject_data['sections'] as $index => $section): ?>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($section['section_name']); ?></span>
                                                        <?php if ($index < count($subject_data['sections']) - 1): ?>, <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Student Submissions & Grading Section -->
                    <?php if (!empty($teacher_activities)): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> Student Submissions & Grading</h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $has_comments = false;
                            foreach ($teacher_activities as $activity): 
                                if (isset($activity_comments[$activity['activity_id']]) && 
                                    count($activity_comments[$activity['activity_id']]) > 0): 
                                    $has_comments = true;
                            ?>
                                <div class="activity-card mb-4">
                                    <div class="activity-header">
                                        <h5 class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></h5>
                                        <span class="activity-type"><?php echo ucfirst($activity['activity_type']); ?></span>
                                    </div>
                                    
                                    <div class="activity-meta">
                                        <span>
                                            <?php echo htmlspecialchars($activity['subject_name']); ?> | 
                                            <?php echo htmlspecialchars($activity['level_name']); ?>
                                            <?php if (!empty($activity['section_name'])): ?>
                                                - Section <?php echo htmlspecialchars($activity['section_name']); ?>
                                            <?php else: ?>
                                                (All Sections)
                                            <?php endif; ?>
                                        </span>
                                        <span><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></span>
                                    </div>
                                    
                                    <div class="comments-list">
                                        <?php foreach ($activity_comments[$activity['activity_id']] as $comment): ?>
                                            <div class="comment mb-3">
                                                <div class="comment-header">
                                                    <div>
                                                        <span class="comment-author"><?php echo htmlspecialchars($comment['student_name']); ?></span>
                                                        <span class="comment-meta">
                                                            (<?php echo htmlspecialchars($comment['level_name']); ?>
                                                            <?php if (!empty($comment['section_name'])): ?>
                                                                - Section <?php echo htmlspecialchars($comment['section_name']); ?>
                                                            <?php endif; ?>)
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <?php if (!empty($comment['grade'])): ?>
                                                            <span class="graded-badge">Graded: <?php echo $comment['grade']; ?>/<?php echo $comment['max_points'] ? $comment['max_points'] : '100'; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="comment-text">
                                                    <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                                                </div>
                                                <?php if (!empty($comment['file_path'])): ?>
                                                    <div class="mt-2">
                                                        <a href="<?php echo htmlspecialchars($comment['file_path']); ?>" class="attachment-link" target="_blank">
                                                            <i class="fas fa-download"></i>
                                                            Download Student File
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Grading Section -->
                                                <div class="grading-section">
                                                    <?php if (!empty($comment['grade'])): ?>
                                                        <div class="grade-display">
                                                            Grade: <?php echo $comment['grade']; ?>/<?php echo $comment['max_points'] ? $comment['max_points'] : '100'; ?>
                                                        </div>
                                                        <?php if (!empty($comment['feedback'])): ?>
                                                            <div class="feedback mt-2">
                                                                <strong>Feedback:</strong>
                                                                <?php echo nl2br(htmlspecialchars($comment['feedback'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <small class="text-muted">
                                                            Graded on <?php echo date('M j, Y g:i A', strtotime($comment['graded_at'])); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Grade Form -->
                                                    <div class="grade-form">
                                                        <form method="POST">
                                                            <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                                                            <div class="row">
                                                                <div class="col-md-3">
                                                                    <div class="mb-3">
                                                                        <label class="form-label" for="grade-<?php echo $comment['comment_id']; ?>">Grade</label>
                                                                        <input type="number" id="grade-<?php echo $comment['comment_id']; ?>" name="grade" 
                                                                               class="form-control" min="0" max="100" step="0.01" 
                                                                               value="<?php echo !empty($comment['grade']) ? $comment['grade'] : ''; ?>" required>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <div class="mb-3">
                                                                        <label class="form-label" for="max_points-<?php echo $comment['comment_id']; ?>">Max Points</label>
                                                                        <input type="number" id="max_points-<?php echo $comment['comment_id']; ?>" name="max_points" 
                                                                               class="form-control" min="1" value="<?php echo !empty($comment['max_points']) ? $comment['max_points'] : '100'; ?>" required>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label" for="feedback-<?php echo $comment['comment_id']; ?>">Feedback</label>
                                                                <textarea id="feedback-<?php echo $comment['comment_id']; ?>" name="feedback" class="form-control" rows="3"><?php echo !empty($comment['feedback']) ? htmlspecialchars($comment['feedback']) : ''; ?></textarea>
                                                            </div>
                                                            <button type="submit" name="submit_grade" class="btn btn-primary">
                                                                <i class="fas fa-check"></i> Submit Grade
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                                
                                                <div class="comment-date text-muted small mt-2">
                                                    Submitted on <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            
                            if (!$has_comments): 
                            ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No student submissions yet for your activities.</p>
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
        // Update subjects when year level changes
        document.getElementById('year_level_id').addEventListener('change', function() {
            const yearId = this.value;
            const subjectSelect = document.getElementById('subject_id');
            const sectionSelect = document.getElementById('section_id');
            
            // Reset subjects and sections
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            sectionSelect.innerHTML = '<option value="">All Sections</option>';
            
            if (yearId) {
                // This would typically be done with AJAX, but for simplicity we'll reload the page
                // In a real application, you would fetch the subjects via AJAX
                window.location.href = 'teacher_activity.php?year_level_id=' + yearId;
            }
        });
        
        // Update sections when subject changes
        document.getElementById('subject_id').addEventListener('change', function() {
            const subjectId = this.value;
            const yearId = document.getElementById('year_level_id').value;
            const sectionSelect = document.getElementById('section_id');
            
            // Reset sections
            sectionSelect.innerHTML = '<option value="">All Sections</option>';
            
            if (subjectId && yearId) {
                // This would typically be done with AJAX, but for simplicity we'll reload the page
                // In a real application, you would fetch the sections via AJAX
                window.location.href = 'teacher_activity.php?year_level_id=' + yearId + '&subject_id=' + subjectId;
            }
        });
        
        // Initialize form based on URL parameters
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const yearLevelId = urlParams.get('year_level_id');
            const subjectId = urlParams.get('subject_id');
            
            if (yearLevelId) {
                document.getElementById('year_level_id').value = yearLevelId;
            }
            
            if (subjectId) {
                document.getElementById('subject_id').value = subjectId;
            }
            
            // Submenu toggle
            document.querySelectorAll('.has-submenu > a').forEach(a => {
                a.addEventListener('click', e => {
                    e.preventDefault();
                    a.parentElement.classList.toggle('active');
                });
            });
        });
    </script>
</body>
</html>