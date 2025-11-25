<?php
session_start();

// Database connection
$host = "sql210.infinityfree.com";
$user = "if0_40265243"; // change if needed
$pass = "rjL6bzbfrgcc"; // change if needed
$dbname = "if0_40265243_school_portal";


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
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

// Fetch teacher's assigned sections and subjects with error handling
$assigned_data = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.subject_id, s.subject_name, sec.section_id, sec.section_name, y.level_name 
        FROM tbl_teacher_assignments ta 
        JOIN tbl_subjects s ON ta.subject_id = s.subject_id 
        JOIN tbl_sections sec ON ta.section_id = sec.section_id 
        JOIN tbl_yearlevels y ON sec.year_level_id = y.year_level_id 
        WHERE ta.teacher_account_id = ?
        ORDER BY y.level_name, sec.section_name, s.subject_name
    ");
    $stmt->execute([$teacher_id]);
    $assigned_data = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Error fetching teacher assignments: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_scores'])) {
        $student_id = $_POST['student_id'];
        $subject_id = $_POST['subject_id'];
        
        // Prepare the data for insertion/update
        $score_data = [
            'student_id' => $student_id,
            'subject_id' => $subject_id,
            'quiz1' => !empty($_POST['quiz1']) ? $_POST['quiz1'] : null,
            'quiz2' => !empty($_POST['quiz2']) ? $_POST['quiz2'] : null,
            'midterm' => !empty($_POST['midterm']) ? $_POST['midterm'] : null,
            'activity1' => !empty($_POST['activity1']) ? $_POST['activity1'] : null,
            'activity2' => !empty($_POST['activity2']) ? $_POST['activity2'] : null,
            'project' => !empty($_POST['project']) ? $_POST['project'] : null,
            'assignments' => !empty($_POST['assignments']) ? $_POST['assignments'] : null,
            'final' => !empty($_POST['final']) ? $_POST['final'] : null
        ];
        
        try {
            // Check if record already exists
            $check_stmt = $pdo->prepare("SELECT score_id FROM tbl_scores WHERE student_id = ? AND subject_id = ?");
            $check_stmt->execute([$student_id, $subject_id]);
            
            if ($check_stmt->rowCount() > 0) {
                // Update existing record
                $update_stmt = $pdo->prepare("
                    UPDATE tbl_scores 
                    SET quiz1 = :quiz1, quiz2 = :quiz2, midterm = :midterm, 
                        activity1 = :activity1, activity2 = :activity2, 
                        project = :project, assignments = :assignments, final = :final,
                        updated_at = NOW()
                    WHERE student_id = :student_id AND subject_id = :subject_id
                ");
                $update_stmt->execute($score_data);
                $message = "Scores updated successfully!";
            } else {
                // Insert new record
                $insert_stmt = $pdo->prepare("
                    INSERT INTO tbl_scores 
                    (student_id, subject_id, quiz1, quiz2, midterm, activity1, activity2, project, assignments, final, created_at) 
                    VALUES 
                    (:student_id, :subject_id, :quiz1, :quiz2, :midterm, :activity1, :activity2, :project, :assignments, :final, NOW())
                ");
                $insert_stmt->execute($score_data);
                $message = "Scores added successfully!";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Fetch students based on selected section AND subject enrollment
$students = [];
$selected_section_id = isset($_GET['section_id']) ? $_GET['section_id'] : '';
$selected_subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';

if (!empty($selected_section_id) && !empty($selected_subject_id)) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.student_id, a.first_name, a.last_name 
            FROM tbl_students s 
            JOIN tbl_accounts a ON s.account_id = a.account_id 
            JOIN tbl_subject_enrollments se ON s.student_id = se.student_id
            WHERE s.section_id = ? AND se.subject_id = ?
            ORDER BY a.last_name, a.first_name
        ");
        $stmt->execute([$selected_section_id, $selected_subject_id]);
        $students = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = "Error fetching students: " . $e->getMessage();
    }
} elseif (!empty($selected_section_id)) {
    // If only section is selected, show all students in that section
    try {
        $stmt = $pdo->prepare("
            SELECT s.student_id, a.first_name, a.last_name 
            FROM tbl_students s 
            JOIN tbl_accounts a ON s.account_id = a.account_id 
            WHERE s.section_id = ?
            ORDER BY a.last_name, a.first_name
        ");
        $stmt->execute([$selected_section_id]);
        $students = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = "Error fetching students: " . $e->getMessage();
    }
}

// Get subjects for the selected section
$section_subjects = [];
if (!empty($selected_section_id)) {
    $section_subjects = array_filter($assigned_data, function($item) use ($selected_section_id) {
        return $item['section_id'] == $selected_section_id;
    });
}

// --- Feedback Handling ---
$feedback_success = "";
$feedback_error = "";

if (isset($_POST['submit_feedback'])) {
    $feedback = trim($_POST['feedback']);
    if (!empty($feedback)) {
        $stmt = $pdo->prepare("INSERT INTO tbl_feedbacks (account_id, feedback_text) VALUES (?, ?)");
        $stmt->execute([$teacher_id, $feedback]);
        if ($stmt->rowCount() > 0) {
            $feedback_success = "✅ Feedback submitted successfully!";
        } else {
            $feedback_error = "❌ Failed to submit feedback. Please try again.";
        }
        $stmt->closeCursor();
    } else {
        $feedback_error = "Feedback cannot be empty.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>EduTrack - Student Records Management</title>
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

        /* Submenu Styles */
        .has-submenu {
            position: relative;
        }

        .submenu {
            display: none;
            list-style: none;
            padding-left: 20px;
            background: rgba(0,0,0,0.1);
        }

        .has-submenu:hover .submenu {
            display: block;
        }

        .submenu li a {
            padding: 8px 15px;
            font-size: 0.9rem;
            border-left: 2px solid transparent;
        }

        .submenu li a:hover,
        .submenu li a.active {
            background: rgba(0,0,0,0.2);
            border-left: 2px solid var(--accent);
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

        /* Badges */
        .subject-badge {
            background: var(--accent);
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: .8rem;
            font-weight: 500;
        }

        /* Sections */
        .welcome-section {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: #fff;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
        }

        /* Student Cards */
        .student-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .student-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .student-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-3px);
        }

        .student-card.selected {
            border-color: #4CAF50;
            background-color: #f0fff0;
        }

        .student-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        /* Score Grid */
        .score-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .score-item {
            display: flex;
            flex-direction: column;
        }

        .score-item label {
            margin-bottom: 5px;
        }
        
        /* Feedback button styles */
        .feedback-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(26, 188, 156, 0.4);
            z-index: 1000;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .feedback-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(26, 188, 156, 0.5);
        }
        
        .feedback-btn i {
            font-size: 1.5rem;
        }
        
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .modal-header {
            background-color: var(--light);
            border-bottom: 1px solid #eee;
            border-radius: 12px 12px 0 0;
        }
        
        .alert-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            animation: slideIn 0.5s forwards, fadeOut 0.5s forwards 3s;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; visibility: hidden; }
        }
        
        /* New styles for improvements */
        .score-input {
            width: 100%;
            max-width: 100px;
            text-align: center;
        }
        
        .student-status {
            font-size: 0.85rem;
            margin-top: 5px;
        }
        
        .last-updated {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .section-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--secondary);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 15px;
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .search-box input {
            padding-left: 35px;
        }
        
        .no-results {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
        
        .grade-summary {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .student-cards {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .score-grid, .student-cards {
                grid-template-columns: 1fr 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .score-grid, .student-cards {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .header > div {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Success/Error Notification -->
    <?php if (!empty($feedback_success)): ?>
        <div class="alert alert-success alert-notification alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($feedback_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($feedback_error)): ?>
        <div class="alert alert-danger alert-notification alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($feedback_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="sidebar-header">
                    <h4><i class="fas fa-graduation-cap"></i> EduTrack</h4>
                    <p class="mb-0">Teacher Portal</p>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="teacher_dashboard.php" class="<?= !isset($_GET['view_class']) ?  : '' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="has-submenu <?= isset($_GET['view_class']) ?  : '' ?>">
                        <a href="#"><i class="fas fa-book"></i> My Classes</a>
                        <ul class="submenu">
                            <?php foreach ($assigned_data as $class): ?>
                                <li>
                                    <a href="?view_class=1&subject_id=<?= $class['subject_id'] ?>&section_id=<?= $class['section_id'] ?>" 
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
                    <li><a href="teacher_spreadsheet.php" class="active"><i class="fas fa-chart-bar"></i> Class Record</a></li>
                    <li><a href="teacher_activity.php"><i class="fas fa-tasks"></i> Upload Activity</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="feedbacks.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                    <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>

            <!-- Main -->
            <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
                <div class="header">
                    <h3>Student Records Management</h3>
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
                                <h1>Welcome, <?= htmlspecialchars(explode(" ", $teacher_name)[0]) ?>!</h1>
                                <p>Manage student scores and activities for your classes.</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-chalkboard-teacher fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert <?php echo strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show">
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filters Card -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-filter"></i> Select Class
                        </div>
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="section" class="form-label">Select Section:</label>
                                        <select id="section" name="section_id" class="form-select" onchange="this.form.submit()" required>
                                            <option value="">-- Select Section --</option>
                                            <?php foreach ($assigned_data as $assignment): ?>
                                                <option value="<?= $assignment['section_id'] ?>" 
                                                    <?= ($selected_section_id == $assignment['section_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($assignment['level_name'] . ' - ' . $assignment['section_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <?php if (!empty($selected_section_id) && !empty($section_subjects)): ?>
                                    <div class="col-md-6 mb-3">
                                        <label for="subject" class="form-label">Select Subject:</label>
                                        <select id="subject" name="subject_id" class="form-select" onchange="this.form.submit()" required>
                                            <option value="">-- Select Subject --</option>
                                            <?php foreach ($section_subjects as $subject): ?>
                                                <option value="<?= $subject['subject_id'] ?>" 
                                                    <?= ($selected_subject_id == $subject['subject_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($subject['subject_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (empty($assigned_data)): ?>
                                    <div class="alert alert-info">
                                        No teaching assignments found for your account.
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    
                    <?php if (!empty($selected_section_id) && !empty($selected_subject_id)): 
                        // Get section and subject info
                        $section_info = null;
                        $subject_info = null;
                        foreach ($assigned_data as $item) {
                            if ($item['section_id'] == $selected_section_id && $item['subject_id'] == $selected_subject_id) {
                                $section_info = $item;
                                $subject_info = $item;
                                break;
                            }
                        }
                        
                        // Calculate statistics - now based on subject enrollment
                        $stats = [
                            'total_students' => count($students),
                            'with_scores' => 0,
                            'without_scores' => 0,
                            'average_score' => 0
                        ];
                        
                        foreach ($students as $student) {
                            try {
                                // Check if scores exist for this student in this subject
                                $stmt = $pdo->prepare("
                                    SELECT * FROM tbl_scores 
                                    WHERE student_id = ? AND subject_id = ?
                                ");
                                $stmt->execute([$student['student_id'], $selected_subject_id]);
                                $scores = $stmt->fetch();
                                
                                if ($scores) {
                                    // Check if any score fields have values
                                    $hasScores = false;
                                    $scoreFields = ['quiz1', 'quiz2', 'midterm', 'activity1', 'activity2', 'project', 'assignments', 'final'];
                                    
                                    foreach ($scoreFields as $field) {
                                        if (!empty($scores[$field]) && $scores[$field] > 0) {
                                            $hasScores = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($hasScores) {
                                        $stats['with_scores']++;
                                    } else {
                                        $stats['without_scores']++;
                                    }
                                } else {
                                    $stats['without_scores']++;
                                }
                            } catch (PDOException $e) {
                                // Silently handle error
                                $stats['without_scores']++;
                            }
                        }
                    ?>
                    <!-- Section Info -->
                    <div class="section-info">
                        <h4><?= htmlspecialchars($section_info['level_name'] . ' - ' . $section_info['section_name']) ?></h4>
                        <p class="mb-0">Subject: <strong><?= htmlspecialchars($subject_info['subject_name']) ?></strong></p>
                        <p class="mb-0">Enrolled Students: <strong><?= $stats['total_students'] ?></strong></p>
                    </div>
                    
                    <!-- Statistics -->
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?= $stats['total_students'] ?></div>
                            <div class="stat-label">Total Enrolled</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $stats['with_scores'] ?></div>
                            <div class="stat-label">With Scores</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $stats['without_scores'] ?></div>
                            <div class="stat-label">Without Scores</div>
                        </div>
                    </div>
                    
                    <!-- Students Card -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-user-graduate"></i> Students Enrolled in <?= htmlspecialchars($subject_info['subject_name']) ?></span>
                            <span class="subject-badge"><?= count($students) ?> Students</span>
                        </div>
                        <div class="card-body">
                            <p>Select a student to view/edit their scores:</p>
                            
                            <!-- Search Box -->
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="studentSearch" class="form-control" placeholder="Search student by name...">
                            </div>
                            
                            <div class="student-cards" id="studentCardsContainer">
                                <?php foreach ($students as $student): 
                                    // Fetch existing scores for this student and subject
                                    $existing_scores = [];
                                    $last_updated = null;
                                    $hasScores = false;
                                    
                                    try {
                                        $stmt = $pdo->prepare("
                                            SELECT *, updated_at 
                                            FROM tbl_scores 
                                            WHERE student_id = ? AND subject_id = ?
                                        ");
                                        $stmt->execute([$student['student_id'], $selected_subject_id]);
                                        $existing_scores = $stmt->fetch();
                                        
                                        if ($existing_scores) {
                                            $last_updated = $existing_scores['updated_at'] ? date('M j, Y g:i A', strtotime($existing_scores['updated_at'])) : null;
                                            
                                            // Check if any scores exist
                                            $scoreFields = ['quiz1', 'quiz2', 'midterm', 'activity1', 'activity2', 'project', 'assignments', 'final'];
                                            foreach ($scoreFields as $field) {
                                                if (!empty($existing_scores[$field]) && $existing_scores[$field] > 0) {
                                                    $hasScores = true;
                                                    break;
                                                }
                                            }
                                        }
                                    } catch (PDOException $e) {
                                        // Silently handle error - we'll just show empty form
                                    }
                                ?>
                                <div class="student-card" data-name="<?= htmlspecialchars(strtolower($student['last_name'] . ' ' . $student['first_name'])) ?>" onclick="openScoresForm(<?= $student['student_id'] ?>, '<?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?>')">
                                    <div class="student-name"><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?></div>
                                    <div class="student-status">
                                        <?php if ($hasScores): ?>
                                            <span class="badge bg-success">✓ Scores recorded</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No scores yet</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($last_updated): ?>
                                        <div class="last-updated">Updated: <?= $last_updated ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (empty($students)): ?>
                                <div class="no-results">
                                    <i class="fas fa-user-slash fa-2x mb-2"></i>
                                    <p>No students enrolled in this subject for the selected section.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php elseif (!empty($selected_section_id) && empty($selected_subject_id)): ?>
                        <div class="alert alert-info">
                            Please select a subject to view enrolled students and manage scores.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Scores Form Card (initially hidden) -->
                    <div class="card" id="scoresForm" style="display: none;">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-edit"></i> <span id="formTitle">Enter Scores</span></span>
                            <button type="button" class="btn-close" aria-label="Close" onclick="closeScoresForm()"></button>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="scoreForm">
                                <input type="hidden" name="student_id" id="formStudentId">
                                <input type="hidden" name="subject_id" value="<?= $selected_subject_id ?>">
                                <input type="hidden" name="submit_scores" value="1">
                                
                                <div class="score-grid">
                                    <div class="score-item">
                                        <label for="quiz1" class="form-label">Quiz 1:</label>
                                        <input type="number" class="form-control score-input" id="quiz1" name="quiz1" min="0" max="100" step="0.01" onchange="calculateAverage()">
                                    </div>
                                    
                                    <div class="score-item">
                                        <label for="quiz2" class="form-label">Quiz 2:</label>
                                        <input type="number" class="form-control score-input" id="quiz2" name="quiz2" min="0" max="100" step="0.01" onchange="calculateAverage()">
                                    </div>
                                    
                                    <div class="score-item">
                                        <label for="midterm" class="form-label">Midterm Exam:</label>
                                        <input type="number" class="form-control score-input" id="midterm" name="midterm" min="0" max="100" step="0.01" onchange="calculateAverage()">
                                    </div>
                                    
                                    <div class="score-item">
                                        <label for="activity1" class="form-label">Activity 1:</label>
                                        <input type="number" class="form-control score-input" id="activity1" name="activity1" min="0" max="100" step="0.01" onchange="calculateAverage()">
                                    </div>
                                    
                                    <div class="score-item">
                                        <label for="activity2" class="form-label">Activity 2:</label>
                                        <input type="number" class="form-control score-input" id="activity2" name="activity2" min="0" max="100" step="0.01" onchange="calculateAverage()">
                                    </div>
                                    
                                    <div class="score-item">
                                        <label for="project" class="form-label">Project:</label>
                                        <input type="number" class="form-control score-input" id="project" name="project" min="0" max="100" step="0.01" onchange="calculateAverage()">
                                    </div>
                                    
                                    <div class="score-item">
                                        <label for="assignments" class="form-label">Assignments:</label>
                                        <input type="number" class="form-control score-input" id="assignments" name="assignments" min="0" max="100" step="0.01" onchange="calculateAverage()">
                                    </div>
                                    
                                    <div class="score-item">
                                        <label for="final" class="form-label">Final Exam:</label>
                                        <input type="number" class="form-control score-input" id="final" name="final" min="0" max="100" step="0.01" onchange="calculateAverage()">
                                    </div>
                                </div>
                                
                                <!-- Grade Summary -->
                                <div class="grade-summary">
                                    <h5>Grade Summary</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p>Quizzes Average: <span id="quizAverage">-</span></p>
                                            <p>Activities Average: <span id="activityAverage">-</span></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p>Overall Average: <span id="overallAverage">-</span></p>
                                            <p>Final Grade: <span id="finalGrade">-</span></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Scores
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="closeScoresForm()">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" id="clearScoresBtn">
                                        <i class="fas fa-trash-alt me-1"></i> Clear All
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Button -->
    <div class="feedback-btn" data-bs-toggle="modal" data-bs-target="#feedbackModal">
        <i class="fas fa-comment"></i>
    </div>

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="feedbackModalLabel">Submit Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="feedbackText" class="form-label">Your Feedback</label>
                            <textarea class="form-control" id="feedbackText" name="feedback" rows="4" required placeholder="Please share your feedback about the system..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_feedback" class="btn btn-primary">Submit Feedback</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Student search functionality
        document.getElementById("studentSearch").addEventListener("keyup", function() {
            const query = this.value.toLowerCase();
            const studentCards = document.querySelectorAll(".student-card");
            let visibleCount = 0;
            
            studentCards.forEach(card => {
                const name = card.getAttribute("data-name");
                if (name.includes(query)) {
                    card.style.display = "block";
                    visibleCount++;
                } else {
                    card.style.display = "none";
                }
            });
            
            // Show no results message if needed
            const noResultsElement = document.getElementById("noResultsMessage");
            if (visibleCount === 0 && studentCards.length > 0) {
                if (!noResultsElement) {
                    const container = document.getElementById("studentCardsContainer");
                    const noResults = document.createElement("div");
                    noResults.id = "noResultsMessage";
                    noResults.className = "no-results";
                    noResults.innerHTML = `
                        <i class="fas fa-search fa-2x mb-2"></i>
                        <p>No students found matching "${query}"</p>
                    `;
                    container.appendChild(noResults);
                }
            } else if (noResultsElement) {
                noResultsElement.remove();
            }
        });
        
        function openScoresForm(studentId, studentName) {
            // Fetch student scores via AJAX
            fetch(`get_scores.php?student_id=${studentId}&subject_id=<?= $selected_subject_id ?>`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(scores => {
                    // Populate form fields
                    document.getElementById('quiz1').value = scores.quiz1 || '';
                    document.getElementById('quiz2').value = scores.quiz2 || '';
                    document.getElementById('midterm').value = scores.midterm || '';
                    document.getElementById('activity1').value = scores.activity1 || '';
                    document.getElementById('activity2').value = scores.activity2 || '';
                    document.getElementById('project').value = scores.project || '';
                    document.getElementById('assignments').value = scores.assignments || '';
                    document.getElementById('final').value = scores.final || '';
                    
                    // Set student ID
                    document.getElementById('formStudentId').value = studentId;
                    
                    // Get subject name
                    const subjectSelect = document.getElementById('subject');
                    const subjectName = subjectSelect.options[subjectSelect.selectedIndex].text;
                    
                    // Update form title
                    document.getElementById('formTitle').textContent = `Enter Scores for ${studentName} - ${subjectName}`;
                    
                    // Calculate averages
                    calculateAverage();
                    
                    // Show the form
                    document.getElementById('scoresForm').style.display = 'block';
                    
                    // Scroll to form
                    document.getElementById('scoresForm').scrollIntoView({ behavior: 'smooth' });
                })
                .catch(error => {
                    console.error('Error fetching scores:', error);
                    // Initialize with empty form if fetch fails
                    document.getElementById('formStudentId').value = studentId;
                    
                    // Get subject name
                    const subjectSelect = document.getElementById('subject');
                    const subjectName = subjectSelect.options[subjectSelect.selectedIndex].text;
                    
                    document.getElementById('formTitle').textContent = `Enter Scores for ${studentName} - ${subjectName}`;
                    document.getElementById('scoresForm').style.display = 'block';
                    document.getElementById('scoresForm').scrollIntoView({ behavior: 'smooth' });
                });
        }
        
        function closeScoresForm() {
            document.getElementById('scoresForm').style.display = 'none';
            document.getElementById('scoreForm').reset();
        }
        
        function calculateAverage() {
            // Calculate quiz average
            const quiz1 = parseFloat(document.getElementById('quiz1').value) || 0;
            const quiz2 = parseFloat(document.getElementById('quiz2').value) || 0;
            const quizCount = (quiz1 > 0 ? 1 : 0) + (quiz2 > 0 ? 1 : 0);
            const quizAverage = quizCount > 0 ? (quiz1 + quiz2) / quizCount : 0;
            document.getElementById('quizAverage').textContent = quizCount > 0 ? quizAverage.toFixed(2) : '-';
            
            // Calculate activity average
            const activity1 = parseFloat(document.getElementById('activity1').value) || 0;
            const activity2 = parseFloat(document.getElementById('activity2').value) || 0;
            const activityCount = (activity1 > 0 ? 1 : 0) + (activity2 > 0 ? 1 : 0);
            const activityAverage = activityCount > 0 ? (activity1 + activity2) / activityCount : 0;
            document.getElementById('activityAverage').textContent = activityCount > 0 ? activityAverage.toFixed(2) : '-';
            
            // Calculate overall average (simple average of all scores)
            const midterm = parseFloat(document.getElementById('midterm').value) || 0;
            const project = parseFloat(document.getElementById('project').value) || 0;
            const assignments = parseFloat(document.getElementById('assignments').value) || 0;
            const final = parseFloat(document.getElementById('final').value) || 0;
            
            const allScores = [quiz1, quiz2, midterm, activity1, activity2, project, assignments, final];
            const validScores = allScores.filter(score => score > 0);
            const overallAverage = validScores.length > 0 ? 
                validScores.reduce((sum, score) => sum + score, 0) / validScores.length : 0;
            
            document.getElementById('overallAverage').textContent = validScores.length > 0 ? overallAverage.toFixed(2) : '-';
            
            // Determine final grade (you can customize this weighting)
            const finalGrade = overallAverage; // Simple average for demonstration
            document.getElementById('finalGrade').textContent = validScores.length > 0 ? finalGrade.toFixed(2) : '-';
            
            // Color code the final grade
            const finalGradeElement = document.getElementById('finalGrade');
            if (finalGrade >= 90) {
                finalGradeElement.className = 'text-success fw-bold';
            } else if (finalGrade >= 75) {
                finalGradeElement.className = 'text-primary fw-bold';
            } else if (finalGrade > 0) {
                finalGradeElement.className = 'text-danger fw-bold';
            } else {
                finalGradeElement.className = '';
            }
        }
        
        // Clear scores button functionality
        document.getElementById('clearScoresBtn').addEventListener('click', function() {
            if (confirm('Are you sure you want to clear all scores for this student?')) {
                const scoreInputs = document.querySelectorAll('.score-input');
                scoreInputs.forEach(input => {
                    input.value = '';
                });
                calculateAverage();
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>