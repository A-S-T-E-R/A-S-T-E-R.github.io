<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['account_id'];
$teacher_name = $_SESSION['fullname'] ?? 'Teacher';

/* =========================
   PHILIPPINE GRADING SYSTEM HELPERS
========================= */
function get_ph_remarks($grade) {
    if ($grade >= 90 && $grade <= 100) return 'Outstanding';
    if ($grade >= 85 && $grade <= 89) return 'Very Satisfactory';
    if ($grade >= 80 && $grade <= 84) return 'Satisfactory';
    if ($grade >= 75 && $grade <= 79) return 'Fairly Satisfactory';
    return 'Did Not Meet Expectations';
}

function get_ph_remarks_class($remarks) {
    switch ($remarks) {
        case 'Outstanding': return 'bg-success';
        case 'Very Satisfactory': return 'bg-primary';
        case 'Satisfactory': return 'bg-info';
        case 'Fairly Satisfactory': return 'bg-warning';
        default: return 'bg-danger';
    }
}

function normalize_ph_grade($grade) {
    $grade = floatval($grade);
    if ($grade < 0) return 0;
    if ($grade > 100) return 100;
    return round($grade, 2);
}

/* =========================
   FETCH ASSIGNED CLASSES
========================= */
$assigned_classes = [];
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

/* =========================
   SUBMIT QUARTERLY GRADES
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_quarter_grades'])) {
        $student_id = intval($_POST['student_id']);
        $subject_id = intval($_POST['subject_id']);
        
        // Process each quarter grade
        $quarters = ['q1', 'q2', 'q3', 'q4'];
        $success_count = 0;
        
        foreach ($quarters as $quarter) {
            if (!empty($_POST[$quarter]) && is_numeric($_POST[$quarter])) {
                $grade_value = normalize_ph_grade($_POST[$quarter]);
                $grading_period = strtoupper($quarter);
                
                // Check if subject exists and teacher is assigned
                $check = $conn->prepare("
                    SELECT ta.assignment_id 
                    FROM tbl_teacher_assignments ta 
                    WHERE ta.teacher_account_id = ? AND ta.subject_id = ?
                ");
                $check->bind_param("ii", $teacher_id, $subject_id);
                $check->execute();
                $check->store_result();
                
                if ($check->num_rows > 0) {
                    // Insert or update quarter grade using ON DUPLICATE KEY
                    $stmt = $conn->prepare("
                        INSERT INTO tbl_grades (student_id, subject_id, teacher_account_id, grading_period, grade)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                            grade = VALUES(grade),
                            teacher_account_id = VALUES(teacher_account_id),
                            date_recorded = CURRENT_TIMESTAMP
                    ");
                    $stmt->bind_param("iiisd", $student_id, $subject_id, $teacher_id, $grading_period, $grade_value);
                    
                    if ($stmt->execute()) {
                        $success_count++;
                    }
                    $stmt->close();
                }
                $check->close();
            }
            else if (isset($_POST[$quarter]) && $_POST[$quarter] === '') {
        // If explicitly empty, delete the grade
        $stmt = $conn->prepare("
            DELETE FROM tbl_grades 
            WHERE student_id = ? AND subject_id = ? AND grading_period = ?
        ");
        @$stmt->bind_param("iis", $student_id, $subject_id, strtoupper($quarter));
        $stmt->execute();
        $stmt->close();
        }
        }

        // Update final grade if any quarters were updated
        if ($success_count > 0) {
            update_final_grade($conn, $student_id, $subject_id, $teacher_id);
            $success_msg = "Grades updated successfully!";
        } else {
            $error_msg = "No grades were updated.";
        }
    }
    
    // Handle final grade submission (manual override)
    if (isset($_POST['submit_final_grade'])) {
        $student_id = intval($_POST['student_id']);
        $subject_id = intval($_POST['subject_id']);
        $final_grade = normalize_ph_grade($_POST['final_grade']);
        $remarks = get_ph_remarks($final_grade);
        
        // Verify teacher is assigned to this subject
        $check = $conn->prepare("
            SELECT ta.assignment_id 
            FROM tbl_teacher_assignments ta 
            WHERE ta.teacher_account_id = ? AND ta.subject_id = ?
        ");
        $check->bind_param("ii", $teacher_id, $subject_id);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $stmt = $conn->prepare("
                INSERT INTO tbl_grades (student_id, subject_id, teacher_account_id, grading_period, grade, remarks)
                VALUES (?, ?, ?, 'Final', ?, ?)
                ON DUPLICATE KEY UPDATE 
                    grade = VALUES(grade),
                    remarks = VALUES(remarks),
                    teacher_account_id = VALUES(teacher_account_id)
            ");
            $stmt->bind_param("iidsd", $student_id, $subject_id, $teacher_id, $final_grade, $remarks);
            
            if ($stmt->execute()) {
                $success_msg = "Final grade updated successfully!";
            } else {
                $error_msg = "Error updating final grade: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_msg = "You are not assigned to this subject.";
        }
        $check->close();
    }
}

// Function to update final grade based on quarter grades
function update_final_grade($conn, $student_id, $subject_id, $teacher_id) {
    // Get all quarter grades
    $stmt = $conn->prepare("
        SELECT grading_period, grade 
        FROM tbl_grades 
        WHERE student_id = ? AND subject_id = ? AND grading_period IN ('Q1', 'Q2', 'Q3', 'Q4')
    ");
    $stmt->bind_param("ii", $student_id, $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $quarters = ['Q1' => null, 'Q2' => null, 'Q3' => null, 'Q4' => null];
    while ($row = $result->fetch_assoc()) {
        $quarters[$row['grading_period']] = $row['grade'];
    }
    $stmt->close();
    
    // Calculate final grade if all quarters have grades
    if (!in_array(null, $quarters, true)) {
        $final_grade = calculate_final_grade($quarters['Q1'], $quarters['Q2'], $quarters['Q3'], $quarters['Q4']);
        $remarks = get_ph_remarks($final_grade);
        
        $stmt = $conn->prepare("
            INSERT INTO tbl_grades (student_id, subject_id, teacher_account_id, grading_period, grade, remarks)
            VALUES (?, ?, ?, 'Final', ?, ?)
            ON DUPLICATE KEY UPDATE 
                grade = VALUES(grade),
                remarks = VALUES(remarks),
                teacher_account_id = VALUES(teacher_account_id)
        ");
        $stmt->bind_param("iidsd", $student_id, $subject_id, $teacher_id, $final_grade, $remarks);
        $stmt->execute();
        $stmt->close();
    }
}

function calculate_final_grade($q1, $q2, $q3, $q4) {
    $grades = array_filter([$q1, $q2, $q3, $q4], function($g) {
        return $g !== null && $g > 0;
    });
    
    if (empty($grades)) return null;
    
    return array_sum($grades) / count($grades);
}

/* =========================
   FETCH STUDENT GRADES FOR VIEW - CHECK ENROLLMENTS
========================= */
$class_students = [];
$subject_id_for_view = null;
$section_id_for_view = null;
$subject_name = "";
$section_name = "";
$level_name = "";

if (isset($_GET['view_class'])) {
    $subject_id_for_view = intval($_GET['subject_id']);
    $section_id_for_view = intval($_GET['section_id']);
    
    // Verify teacher is assigned to this class
    $check_assignment = $conn->prepare("
        SELECT assignment_id 
        FROM tbl_teacher_assignments 
        WHERE teacher_account_id = ? AND subject_id = ? AND section_id = ?
    ");
    $check_assignment->bind_param("iii", $teacher_id, $subject_id_for_view, $section_id_for_view);
    $check_assignment->execute();
    $check_assignment->store_result();
    
    if ($check_assignment->num_rows > 0) {
        // Fetch students enrolled in this subject and section
        $stmt = $conn->prepare("
            SELECT 
                s.student_id,
                a.first_name,
                a.last_name,
                MAX(CASE WHEN g.grading_period = 'Q1' THEN g.grade END) as q1_grade,
                MAX(CASE WHEN g.grading_period = 'Q2' THEN g.grade END) as q2_grade,
                MAX(CASE WHEN g.grading_period = 'Q3' THEN g.grade END) as q3_grade,
                MAX(CASE WHEN g.grading_period = 'Q4' THEN g.grade END) as q4_grade,
                MAX(CASE WHEN g.grading_period = 'Final' THEN g.grade END) as final_grade,
                MAX(CASE WHEN g.grading_period = 'Final' THEN g.remarks END) as remarks
            FROM tbl_students s
            JOIN tbl_accounts a ON s.account_id = a.account_id
            JOIN tbl_subject_enrollments se ON s.student_id = se.student_id 
            LEFT JOIN tbl_grades g ON g.student_id = s.student_id AND g.subject_id = ?
            WHERE s.section_id = ? AND se.subject_id = ?
            GROUP BY s.student_id, a.first_name, a.last_name
            ORDER BY a.last_name, a.first_name
        ");
        $stmt->bind_param("iii", $subject_id_for_view, $section_id_for_view, $subject_id_for_view);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) while ($row = $res->fetch_assoc()) $class_students[] = $row;
        $stmt->close();
        
        // Get subject and section details for display
        $stmt_info = $conn->prepare("
            SELECT s.subject_name, sec.section_name, yl.level_name
            FROM tbl_subjects s
            JOIN tbl_sections sec ON sec.section_id = ?
            JOIN tbl_yearlevels yl ON sec.year_level_id = yl.year_level_id
            WHERE s.subject_id = ?
        ");
        $stmt_info->bind_param("ii", $section_id_for_view, $subject_id_for_view);
        $stmt_info->execute();
        $info_result = $stmt_info->get_result();
        if ($info_row = $info_result->fetch_assoc()) {
            $subject_name = $info_row['subject_name'];
            $section_name = $info_row['section_name'];
            $level_name = $info_row['level_name'];
        }
        $stmt_info->close();
    } else {
        $error_msg = "You are not assigned to this class.";
    }
    $check_assignment->close();
}

// --- Feedback Handling ---
$feedback_success = "";
$feedback_error = "";

if (isset($_POST['submit_feedback'])) {
    $feedback = trim($_POST['feedback']);
    if (!empty($feedback)) {
        $stmt = $conn->prepare("INSERT INTO tbl_feedbacks (account_id, feedback_text) VALUES (?, ?)");
        $stmt->bind_param("is", $account_id, $feedback);
        if ($stmt->execute()) {
            $feedback_success = "✅ Feedback submitted successfully!";
        } else {
            $feedback_error = "❌ Failed to submit feedback. Please try again.";
        }
        $stmt->close();
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
    <title>EduTrack - Teacher Dashboard</title>
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

/* Misc */
.ph-scale {
    font-size: 0.8rem;
    margin-top: 10px;
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
        
        .sidebar-feedback {
            cursor: pointer;
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
                        <?php foreach ($assigned_classes as $class): ?>
                            <li>
                                <a href="?view_class=1&subject_id=<?= $class['subject_id'] ?>&section_id=<?= $class['section_id'] ?>" 
                                   class="<?= (isset($_GET['subject_id']) && $_GET['subject_id'] == $class['subject_id']) ?  : '' ?>">
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
                <li><a href="teacher_activity.php" class=><i class="fas fa-tasks"></i> Upload Activity</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="feedbacks.php" class><i class="fas fa-comment-dots"></i> Feedback</a></li>
                <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <!-- Main -->
        <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
            <div class="header">
                <h3>Teacher Dashboard</h3>
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
                            <p>Manage your classes, input grades, and track student progress using the Philippine grading system.</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="fas fa-chalkboard-teacher fa-4x"></i>
                        </div>
                    </div>
                </div>

                <?php if(isset($success_msg)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
                <?php endif; ?>
                <?php if(isset($error_msg)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
                <?php endif; ?>

                <!-- Philippine Grading Scale Info -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-info-circle"></i> Philippine Grading Scale
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <span class="badge bg-success">90-100</span> Outstanding
                            </div>
                            <div class="col-md-3">
                                <span class="badge bg-primary">85-89</span> Very Satisfactory
                            </div>
                            <div class="col-md-3">
                                <span class="badge bg-info">80-84</span> Satisfactory
                            </div>
                            <div class="col-md-3">
                                <span class="badge bg-warning">75-79</span> Fairly Satisfactory
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <span class="badge bg-danger">Below 75</span> Did Not Meet Expectations
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Classes Overview -->
                <?php if (!isset($_GET['view_class'])): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>My Classes</span>
                            <span class="subject-badge"><?= count($assigned_classes) ?> Classes</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($assigned_classes)): ?>
                                <div class="alert alert-info">You haven't been assigned to any classes yet.</div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($assigned_classes as $class): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body text-center">
                                                    <h5 class="card-title"><?= htmlspecialchars($class['subject_name']) ?></h5>
                                                    <p class="card-text">
                                                        <?= htmlspecialchars($class['level_name']) ?> - 
                                                        <?= htmlspecialchars($class['section_name']) ?>
                                                    </p>
                                                    <a href="?view_class=1&subject_id=<?= $class['subject_id'] ?>&section_id=<?= $class['section_id'] ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        <i class="fas fa-user-graduate"></i> View Class
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Class Grades Management -->
                <?php if (isset($_GET['view_class']) && !empty($class_students)): ?>
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-graduation-cap"></i> 
                                <?= htmlspecialchars($subject_name) ?> - 
                                <?= htmlspecialchars($level_name) ?> 
                                <?= htmlspecialchars($section_name) ?>
                                - Grade Management
                            </span>
                            <div>
                                <span class="badge bg-primary"><?= count($class_students) ?> Students</span>
                                <a href="teacher_dashboard.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th rowspan="2">Student</th>
                                            <th colspan="4" class="text-center quarter-header">Quarterly Grades</th>
                                            <th rowspan="2">Final Grade</th>
                                            <th rowspan="2">Remarks</th>
                                            <th rowspan="2">Actions</th>
                                        </tr>
                                        <tr>
                                            <th class="quarter-header">Q1</th>
                                            <th class="quarter-header">Q2</th>
                                            <th class="quarter-header">Q3</th>
                                            <th class="quarter-header">Q4</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($class_students as $student): ?>
                                            <?php
                                            $final_grade = $student['final_grade'] ?? null;
                                            $remarks = $student['remarks'] ?? ($final_grade ? get_ph_remarks($final_grade) : null);
                                            $remarks_class = $remarks ? get_ph_remarks_class($remarks) : 'bg-light';
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($student['last_name']) ?>, <?= htmlspecialchars($student['first_name']) ?></strong>
                                                </td>
                                                
                                                <!-- Quarterly Grades -->
                                                <?php foreach (['q1_grade', 'q2_grade', 'q3_grade', 'q4_grade'] as $quarter): ?>
                                                    <td class="grade-cell text-center" 
                                                        onclick="openQuarterGradesModal(
                                                            <?= $student['student_id'] ?>, 
                                                            <?= $subject_id_for_view ?>,
                                                            '<?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>',
                                                            <?= $student['q1_grade'] ?? 'null' ?>,
                                                            <?= $student['q2_grade'] ?? 'null' ?>,
                                                            <?= $student['q3_grade'] ?? 'null' ?>,
                                                            <?= $student['q4_grade'] ?? 'null' ?>
                                                        )">
                                                        <?php if ($student[$quarter]): ?>
                                                            <span class="badge <?= get_ph_remarks_class(get_ph_remarks($student[$quarter])) ?>">
                                                                <?= number_format($student[$quarter], 1) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                
                                                <!-- Final Grade -->
                                                <td class="text-center">
                                                    <?php if ($final_grade): ?>
                                                        <span class="badge <?= $remarks_class ?>">
                                                            <?= number_format($final_grade, 1) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <!-- Remarks -->
                                                <td class="text-center">
                                                    <?php if ($remarks): ?>
                                                        <span class="badge <?= $remarks_class ?>">
                                                            <?= $remarks ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <!-- Actions -->
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-outline-primary"
                                                        onclick="openQuarterGradesModal(
                                                            <?= $student['student_id'] ?>,
                                                            <?= $subject_id_for_view ?>,
                                                            '<?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>',
                                                            <?= $student['q1_grade'] ?? 'null' ?>,
                                                            <?= $student['q2_grade'] ?? 'null' ?>,
                                                            <?= $student['q3_grade'] ?? 'null' ?>,
                                                            <?= $student['q4_grade'] ?? 'null' ?>
                                                        )">
                                                        <i class="fas fa-edit"></i> Edit Grades
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="ph-scale">
                                <small class="text-muted">
                                    <strong>Grading Scale:</strong> 
                                    90-100 (Outstanding) | 85-89 (Very Satisfactory) | 80-84 (Satisfactory) | 
                                    75-79 (Fairly Satisfactory) | Below 75 (Did Not Meet Expectations)
                                </small>
                            </div>
                        </div>
                    </div>
                <?php elseif (isset($_GET['view_class']) && empty($class_students)): ?>
                    <div class="alert alert-warning">
                        No students enrolled in this subject or you don't have permission to view this class.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quarterly Grades Modal (All in One) -->
<div class="modal fade" id="quarterGradesModal" tabindex="-1" aria-labelledby="quarterGradesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="student_id" id="modalStudentId">
                <input type="hidden" name="subject_id" id="modalSubjectId">
                <input type="hidden" name="submit_quarter_grades" value="1">

                <div class="modal-header">
                    <h5 class="modal-title" id="quarterGradesModalLabel">Input Quarterly Grades</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Student</label>
                        <input type="text" class="form-control" id="modalStudentName" readonly>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Q1</label>
                            <input type="number" class="form-control quarter-grade" name="q1" id="modalQ1" min="0" max="100" step="0.1">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Q2</label>
                            <input type="number" class="form-control quarter-grade" name="q2" id="modalQ2" min="0" max="100" step="0.1">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Q3</label>
                            <input type="number" class="form-control quarter-grade" name="q3" id="modalQ3" min="0" max="100" step="0.1">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Q4</label>
                            <input type="number" class="form-control quarter-grade" name="q4" id="modalQ4" min="0" max="100" step="0.1">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Final Grade</label>
                        <input type="text" class="form-control" id="modalFinalGrade" readonly>
                    </div>
                    <div class="mt-2">
                        <label class="form-label">Remarks</label>
                        <input type="text" class="form-control" id="modalRemarks" readonly>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Grades</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Submenu toggle
document.querySelectorAll('.has-submenu > a').forEach(a => {
    a.addEventListener('click', e => {
        e.preventDefault();
        a.parentElement.classList.toggle('active');
    });
});

// Quarterly Grades Modal
function openQuarterGradesModal(studentId, subjectId, studentName, q1, q2, q3, q4) {
    document.getElementById('modalStudentId').value = studentId;
    document.getElementById('modalSubjectId').value = subjectId;
    document.getElementById('modalStudentName').value = studentName;

    document.getElementById('modalQ1').value = q1 !== null ? q1 : '';
    document.getElementById('modalQ2').value = q2 !== null ? q2 : '';
    document.getElementById('modalQ3').value = q3 !== null ? q3 : '';
    document.getElementById('modalQ4').value = q4 !== null ? q4 : '';

    calculateFinalGrade(); // initial calculation

    var modal = new bootstrap.Modal(document.getElementById('quarterGradesModal'));
    modal.show();
}

// Calculate final grade & remarks
function calculateFinalGrade() {
    const q1 = parseFloat(document.getElementById('modalQ1').value) || 0;
    const q2 = parseFloat(document.getElementById('modalQ2').value) || 0;
    const q3 = parseFloat(document.getElementById('modalQ3').value) || 0;
    const q4 = parseFloat(document.getElementById('modalQ4').value) || 0;

    const grades = [q1,q2,q3,q4].filter(g => g>0);
    const finalGrade = grades.length > 0 ? (grades.reduce((a,b)=>a+b,0)/grades.length).toFixed(2) : '';

    document.getElementById('modalFinalGrade').value = finalGrade;

    let remarks = '';
    if(finalGrade !== '') {
        const g = parseFloat(finalGrade);
        if(g >= 90) remarks = 'Outstanding';
        else if(g >= 85) remarks = 'Very Satisfactory';
        else if(g >= 80) remarks = 'Satisfactory';
        else if(g >= 75) remarks = 'Fairly Satisfactory';
        else remarks = 'Did Not Meet Expectations';
    }
    document.getElementById('modalRemarks').value = remarks;
}

// Bind input events
document.querySelectorAll('.quarter-grade').forEach(input => {
    input.addEventListener('input', calculateFinalGrade);
});

// Clamp inputs 0-100
document.addEventListener('input', function(e) {
    if (e.target.type === 'number' && e.target.max === '100') {
        const v = parseFloat(e.target.value);
        if (!isNaN(v)) {
            if (v > 100) e.target.value = 100;
            if (v < 0) e.target.value = 0;
        }
    }
});

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert-notification');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Clear form when modal is hidden
document.getElementById('feedbackModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('feedbackText').value = '';
});

// Show modal if there was an error
<?php if (!empty($feedback_error)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
        feedbackModal.show();
    });
<?php endif; ?>

// Prevent default behavior for sidebar feedback link
document.querySelectorAll('.sidebar-feedback').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
    });
});
</script>

</body>
</html>
<?php $conn->close(); ?>