<?php
// student_activities.php
session_start();

// Database connection
$host = "sql210.infinityfree.com";
$user = "if0_40265243"; // change if needed
$pass = "rjL6bzbfrgcc"; // change if needed
$dbname = "if0_40265243_school_portal";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is logged in and is a student
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['account_id'];

// Get student's information including name
$student_info = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.year_level_id, s.section_id, 
               yl.level_name, sec.section_name,
               a.first_name, a.last_name
        FROM tbl_students s
        JOIN tbl_accounts a ON s.account_id = a.account_id
        JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id
        LEFT JOIN tbl_sections sec ON s.section_id = sec.section_id
        WHERE s.account_id = ?
    ");
    $stmt->execute([$student_id]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student_info) {
        die("Student information not found.");
    }
    
    $student_name = $student_info['first_name'] . ' ' . $student_info['last_name'];
} catch (PDOException $e) {
    die("Error fetching student information: " . $e->getMessage());
}

// Get activities for the student
$activities = [];
try {
    // Check if the activities table exists
    $table_exists = $pdo->query("SHOW TABLES LIKE 'tbl_teacher_activities'")->rowCount() > 0;
    
    if ($table_exists) {
        // Get activities for the student's year level and section (or all sections)
        $stmt = $pdo->prepare("
            SELECT a.*, CONCAT(t.first_name, ' ', t.last_name) as teacher_name, 
                   yl.level_name, sec.section_name, sub.subject_name
            FROM tbl_teacher_activities a
            JOIN tbl_accounts t ON a.teacher_account_id = t.account_id
            JOIN tbl_yearlevels yl ON a.year_level_id = yl.year_level_id
            LEFT JOIN tbl_sections sec ON a.section_id = sec.section_id
            LEFT JOIN tbl_subjects sub ON a.subject_id = sub.subject_id
            WHERE a.year_level_id = ? 
            AND (a.section_id IS NULL OR a.section_id = ?)
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$student_info['year_level_id'], $student_info['section_id']]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Error fetching activities: " . $e->getMessage());
}

// Process comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $activity_id = $_POST['activity_id'];
    $comment_text = trim($_POST['comment_text']);
    $comment_type = $_POST['comment_type'];
    
    // Validate input
    if (!empty($comment_text)) {
        try {
            // Handle file upload if present
            $uploaded_file_path = null;
            if (isset($_FILES['comment_file']) && $_FILES['comment_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/student_submissions/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = time() . '_' . basename($_FILES['comment_file']['name']);
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['comment_file']['tmp_name'], $target_path)) {
                    $uploaded_file_path = $target_path;
                }
            }
            
            // Insert comment into database
            $stmt = $pdo->prepare("
                INSERT INTO tbl_activity_comments 
                (activity_id, student_id, comment_text, comment_type, file_path, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $activity_id, 
                $student_info['student_id'], 
                $comment_text, 
                $comment_type, 
                $uploaded_file_path
            ]);
            
            $_SESSION['success_message'] = "Your " . ($comment_type === 'private' ? 'private ' : '') . "comment has been submitted successfully!";
            header("Location: student_activities.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error submitting comment: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Comment text cannot be empty.";
    }
}

// Get comments and grades for each activity
$activity_comments = [];
$student_grades = [];
try {
    $comments_table_exists = $pdo->query("SHOW TABLES LIKE 'tbl_activity_comments'")->rowCount() > 0;
    
    if ($comments_table_exists) {
        foreach ($activities as $activity) {
            // Get comments
            $stmt = $pdo->prepare("
                SELECT c.*, a.first_name, a.last_name 
                FROM tbl_activity_comments c
                JOIN tbl_students s ON c.student_id = s.student_id
                JOIN tbl_accounts a ON s.account_id = a.account_id
                WHERE c.activity_id = ? 
                AND (c.comment_type = 'public' OR (c.comment_type = 'private' AND c.student_id = ?))
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$activity['activity_id'], $student_info['student_id']]);
            $activity_comments[$activity['activity_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get student's grade for this activity
            $stmt = $pdo->prepare("
                SELECT c.grade, c.max_points, c.feedback, c.graded_at
                FROM tbl_activity_comments c
                WHERE c.activity_id = ? AND c.student_id = ? AND c.grade IS NOT NULL
                ORDER BY c.graded_at DESC
                LIMIT 1
            ");
            $stmt->execute([$activity['activity_id'], $student_info['student_id']]);
            $grade_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($grade_data) {
                $student_grades[$activity['activity_id']] = $grade_data;
            }
        }
    }
} catch (PDOException $e) {
    // Silently fail if comments/grades can't be loaded
    error_log("Error loading comments/grades: " . $e->getMessage());
}

// Filter activities by type if requested
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
if ($filter !== 'all') {
    $filtered_activities = [];
    foreach ($activities as $activity) {
        if ($activity['activity_type'] === $filter) {
            $filtered_activities[] = $activity;
        }
    }
    $activities = $filtered_activities;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack - Student Activities</title>
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
        
        .filter-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            background-color: #e9ecef;
            color: #495057;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background-color: var(--secondary);
            color: white;
        }
        
        .activity-type {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .type-assignment {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        
        .type-activity {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .type-reviewer {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        .type-announcement {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        
        .attachment a {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--secondary);
            text-decoration: none;
        }
        
        .attachment a:hover {
            text-decoration: underline;
        }
        
        .comments-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .comment {
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            background-color: #f9f9f9;
            border-left: 4px solid #ddd;
        }
        
        .comment.private {
            border-left-color: #ff9800;
            background-color: #fff3e0;
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
            color: #666;
        }
        
        .comment-type {
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 4px;
            background-color: #e0e0e0;
        }
        
        .comment-type.private {
            background-color: #ffe0b2;
            color: #e65100;
        }
        
        .alert-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            animation: slideIn 0.5s forwards, fadeOut 0.5s forwards 3s;
        }
        
        /* Grade Display Styles */
        .grade-display {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .grade-score {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .grade-percentage {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .grade-feedback {
            background-color: #f8f9fa;
            border-left: 4px solid var(--accent);
            padding: 12px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .grade-meta {
            font-size: 0.85rem;
            color: #666;
            margin-top: 8px;
        }
        
        .no-grade {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            color: #666;
            border: 2px dashed #ddd;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; visibility: hidden; }
        }
        
        @media (max-width: 768px) {
            .comment-header {
                flex-direction: column;
                gap: 5px;
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
                    <p class="mb-0">Student Portal</p>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="student_activities.php"><i class="fas fa-tasks"></i> Activities</a></li>
                    <li><a href="student_records.php"><i class="fas fa-chart-line"></i> Records</a></li>
                    <li><a href="grades_view.php"><i class="fas fa-chart-bar"></i> Grades</a></li>
                    <li><a href="careerpath.php"><i class="fas fa-clipboard-check"></i> Career Test</a></li>
                    <li><a href="feedbacks.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                    <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>        
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
                <div class="header">
                    <h3>Student Activities</h3>
                    <div class="user-info">
                        <div class="avatar"><?= substr($student_name, 0, 1) ?></div>
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($student_name) ?></div>
                            <div class="text-muted">Student</div>
                        </div>
                    </div>
                </div>

                <div class="main-content">
                    <!-- Welcome Banner -->
                    <div class="welcome-section">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1>Welcome, <?= htmlspecialchars(explode(" ", $student_name)[0]) ?>!</h1>
                                <p>View and submit your activities and assignments</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-tasks fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Display messages -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-notification alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php 
                            echo $_SESSION['success_message']; 
                            unset($_SESSION['success_message']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-notification alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php 
                            echo $_SESSION['error_message']; 
                            unset($_SESSION['error_message']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">My Activities</h5>
                        </div>
                        <div class="card-body">
                            <div class="filters mb-4">
                                <a href="student_activities.php?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All Activities</a>
                                <a href="student_activities.php?filter=assignment" class="filter-btn <?php echo $filter === 'assignment' ? 'active' : ''; ?>">Assignments</a>
                                <a href="student_activities.php?filter=activity" class="filter-btn <?php echo $filter === 'activity' ? 'active' : ''; ?>">Activities</a>
                                <a href="student_activities.php?filter=reviewer" class="filter-btn <?php echo $filter === 'reviewer' ? 'active' : ''; ?>">Reviewers</a>
                                <a href="student_activities.php?filter=announcement" class="filter-btn <?php echo $filter === 'announcement' ? 'active' : ''; ?>">Announcements</a>
                            </div>

                            <div class="activities-list">
                                <?php if (count($activities) > 0): ?>
                                    <?php foreach ($activities as $activity): ?>
                                        <div class="card mb-4">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <div class="d-flex align-items-center">
                                                    <span class="activity-type type-<?php echo $activity['activity_type']; ?> me-3">
                                                        <?php echo ucfirst($activity['activity_type']); ?>
                                                    </span>
                                                    <h5 class="mb-0"><?php echo htmlspecialchars($activity['title']); ?></h5>
                                                </div>
                                                <span><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></span>
                                            </div>
                                            
                                            <div class="card-body">
                                                <!-- Grade Display -->
                                                <?php if (isset($student_grades[$activity['activity_id']])): 
                                                    $grade_data = $student_grades[$activity['activity_id']];
                                                    $percentage = $grade_data['max_points'] > 0 ? round(($grade_data['grade'] / $grade_data['max_points']) * 100, 1) : 0;
                                                    ?>
                                                    <div class="grade-display">
                                                        <div class="grade-score">
                                                            <?php echo $grade_data['grade']; ?>/<?php echo $grade_data['max_points']; ?>
                                                        </div>
                                                        <div class="grade-percentage">
                                                            <?php echo $percentage; ?>%
                                                        </div>
                                                        <?php if (!empty($grade_data['feedback'])): ?>
                                                            <div class="grade-feedback mt-3">
                                                                <strong>Teacher Feedback:</strong>
                                                                <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($grade_data['feedback'])); ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="grade-meta">
                                                            Graded on <?php echo date('M j, Y g:i A', strtotime($grade_data['graded_at'])); ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="no-grade">
                                                        <i class="fas fa-clock fa-2x mb-2"></i>
                                                        <p class="mb-0">No grade yet. Your submission is pending review.</p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="activity-description mb-3">
                                                    <?php echo nl2br(htmlspecialchars($activity['description'])); ?>
                                                </div>
                                                
                                                <div class="activity-meta d-flex justify-content-between text-muted mb-3">
                                                    <span>
                                                        Subject: <?php echo htmlspecialchars($activity['subject_name'] ?? 'N/A'); ?> | 
                                                        Posted by: <?php echo htmlspecialchars($activity['teacher_name']); ?>
                                                    </span>
                                                    <span>For: <?php echo htmlspecialchars($activity['level_name']); ?>
                                                        <?php if (!empty($activity['section_name'])): ?>
                                                            - Section <?php echo htmlspecialchars($activity['section_name']); ?>
                                                        <?php else: ?>
                                                            (All Sections)
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                
                                                <?php if (!empty($activity['due_date'])): ?>
                                                    <div class="due-date mb-3">
                                                        <strong>Due Date:</strong> 
                                                        <?php echo date('M j, Y g:i A', strtotime($activity['due_date'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($activity['points'])): ?>
                                                    <div class="points mb-3">
                                                        <strong>Max Points:</strong> <?php echo $activity['points']; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($activity['attachment'])): ?>
                                                    <div class="attachment mb-3">
                                                        <a href="<?php echo htmlspecialchars($activity['attachment']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-download me-1"></i>
                                                            Download Attachment
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Comments Section -->
                                                <div class="comments-section">
                                                    <div class="comments-header d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="mb-0">Student Responses</h6>
                                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#comments-<?php echo $activity['activity_id']; ?>">
                                                            <span>Show Comments</span>
                                                            <i class="fas fa-chevron-down ms-1"></i>
                                                        </button>
                                                    </div>
                                                    
                                                    <div class="collapse" id="comments-<?php echo $activity['activity_id']; ?>">
                                                        <?php if (isset($activity_comments[$activity['activity_id']]) && count($activity_comments[$activity['activity_id']]) > 0): ?>
                                                            <?php foreach ($activity_comments[$activity['activity_id']] as $comment): ?>
                                                                <div class="comment <?php echo $comment['comment_type']; ?> mb-3">
                                                                    <div class="comment-header d-flex justify-content-between">
                                                                        <span><?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?></span>
                                                                        <span class="comment-type <?php echo $comment['comment_type']; ?>">
                                                                            <?php echo ucfirst($comment['comment_type']); ?>
                                                                        </span>
                                                                        <span><?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?></span>
                                                                    </div>
                                                                    <div class="comment-text">
                                                                        <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                                                                    </div>
                                                                    <?php if (!empty($comment['file_path'])): ?>
                                                                        <div class="comment-file mt-2">
                                                                            <a href="<?php echo htmlspecialchars($comment['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                                                <i class="fas fa-download me-1"></i>
                                                                                Download File
                                                                            </a>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <p class="text-muted">No comments yet.</p>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Comment Form -->
                                                        <div class="comment-form mt-4">
                                                            <form method="POST" enctype="multipart/form-data">
                                                                <input type="hidden" name="activity_id" value="<?php echo $activity['activity_id']; ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label for="comment-text-<?php echo $activity['activity_id']; ?>" class="form-label">Your Comment:</label>
                                                                    <textarea id="comment-text-<?php echo $activity['activity_id']; ?>" name="comment_text" class="form-control" required></textarea>
                                                                </div>
                                                                
                                                                <div class="comment-type-selector mb-3">
                                                                    <div class="form-check form-check-inline">
                                                                        <input class="form-check-input" type="radio" name="comment_type" id="public-<?php echo $activity['activity_id']; ?>" value="public" checked>
                                                                        <label class="form-check-label" for="public-<?php echo $activity['activity_id']; ?>">
                                                                            Public Comment (visible to everyone)
                                                                        </label>
                                                                    </div>
                                                                    <div class="form-check form-check-inline">
                                                                        <input class="form-check-input" type="radio" name="comment_type" id="private-<?php echo $activity['activity_id']; ?>" value="private">
                                                                        <label class="form-check-label" for="private-<?php echo $activity['activity_id']; ?>">
                                                                            Private Comment (only visible to you and teacher)
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="file-upload mb-3">
                                                                    <label for="comment-file-<?php echo $activity['activity_id']; ?>" class="form-label">Upload File (optional):</label>
                                                                    <input type="file" name="comment_file" class="form-control" id="comment-file-<?php echo $activity['activity_id']; ?>">
                                                                </div>
                                                                
                                                                <button type="submit" name="submit_comment" class="btn btn-success">
                                                                    <i class="fas fa-paper-plane me-1"></i> Submit Comment
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No activities found</h5>
                                        <p class="text-muted">No activities found for your year level and section. Check back later or contact your teacher if you believe this is an error.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-notification');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>