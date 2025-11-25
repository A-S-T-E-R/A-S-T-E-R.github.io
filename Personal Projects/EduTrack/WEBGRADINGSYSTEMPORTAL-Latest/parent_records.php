<?php
session_start();
require 'db_connect.php';

// Ensure only logged-in parents can access
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: index.php");
    exit();
}

$parent_account_id = $_SESSION['account_id'];
$selected_grade_level = isset($_GET['grade_level']) ? intval($_GET['grade_level']) : null;
$selected_semester = isset($_GET['semester']) ? $_GET['semester'] : 'all';
$selected_student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;

// Fetch parent information
$sql = "SELECT a.first_name, a.last_name, a.email, a.contact_number
        FROM tbl_accounts a 
        WHERE a.account_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $parent_account_id);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();

if (!$parent) {
    die("Parent record not found.");
}

$parent_name = $parent['first_name'] . ' ' . $parent['last_name'];

// Fetch linked students using the parent-student relationship tables
$students_sql = "
    SELECT s.student_id, s.lrn, s.year_level_id, yl.level_name, 
           a.first_name, a.last_name, a.email,
           sec.section_name, ps.relation
    FROM tbl_parent_student ps
    JOIN tbl_parents p ON ps.parent_id = p.parent_id
    JOIN tbl_students s ON ps.student_id = s.student_id
    JOIN tbl_accounts a ON s.account_id = a.account_id
    JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id
    LEFT JOIN tbl_sections sec ON s.section_id = sec.section_id
    WHERE p.account_id = ?
    ORDER BY yl.year_level_id, a.first_name
";
$stmt = $conn->prepare($students_sql);
$stmt->bind_param("i", $parent_account_id);
$stmt->execute();
$students_result = $stmt->get_result();
$linked_students = [];

while ($row = $students_result->fetch_assoc()) {
    $linked_students[] = $row;
}

if (empty($linked_students)) {
    die("No students linked to your parent account. Please contact the administrator to link your account to your child/children.");
}

// If no specific student is selected, default to the first one
if (!$selected_student_id && !empty($linked_students)) {
    $selected_student_id = $linked_students[0]['student_id'];
}

// Find the selected student
$selected_student = null;
foreach ($linked_students as $student) {
    if ($student['student_id'] == $selected_student_id) {
        $selected_student = $student;
        break;
    }
}

if (!$selected_student) {
    $selected_student = $linked_students[0];
    $selected_student_id = $selected_student['student_id'];
}

$student_name = $selected_student['first_name'] . ' ' . $selected_student['last_name'];
$current_grade_level = $selected_student['year_level_id'];

// Fetch available grade levels for this student (from past and current grades)
$grade_levels_sql = "
    SELECT DISTINCT yl.year_level_id, yl.level_name, yl.education_stage
    FROM tbl_grades g
    JOIN tbl_subjects s ON g.subject_id = s.subject_id
    JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id
    WHERE g.student_id = ?
    UNION
    SELECT yl.year_level_id, yl.level_name, yl.education_stage
    FROM tbl_yearlevels yl
    WHERE yl.year_level_id = ?
    ORDER BY year_level_id
";
$stmt = $conn->prepare($grade_levels_sql);
$stmt->bind_param("ii", $selected_student_id, $current_grade_level);
$stmt->execute();
$grade_levels_result = $stmt->get_result();
$available_grade_levels = [];

while ($row = $grade_levels_result->fetch_assoc()) {
    $available_grade_levels[] = $row;
}

// If no specific grade level is selected, default to current grade level
if (!$selected_grade_level && !empty($available_grade_levels)) {
    $selected_grade_level = $current_grade_level;
} elseif (!$selected_grade_level) {
    $selected_grade_level = 0;
}

// Fetch grades for the selected grade level
$grades_data = [];
$semesters_available = [];

if ($selected_grade_level > 0) {
    $grades_sql = "
        SELECT 
            s.subject_id,
            s.subject_name,
            s.semester,
            MAX(CASE WHEN g.grading_period = 'Q1' THEN g.grade END) AS q1,
            MAX(CASE WHEN g.grading_period = 'Q2' THEN g.grade END) AS q2,
            MAX(CASE WHEN g.grading_period = 'Q3' THEN g.grade END) AS q3,
            MAX(CASE WHEN g.grading_period = 'Q4' THEN g.grade END) AS q4,
            MAX(CASE WHEN g.grading_period = 'Midterm' THEN g.grade END) AS midterm,
            MAX(CASE WHEN g.grading_period = 'Final' THEN g.grade END) AS final,
            MAX(g.remarks) AS remarks,
            MAX(CONCAT(COALESCE(a.first_name,''),' ',COALESCE(a.last_name,''))) AS teacher_name,
            MAX(g.date_recorded) AS date_recorded
        FROM tbl_grades g
        JOIN tbl_subjects s ON g.subject_id = s.subject_id
        LEFT JOIN tbl_accounts a ON g.teacher_account_id = a.account_id
        WHERE g.student_id = ? AND s.year_level_id = ?
        GROUP BY s.subject_id, s.subject_name, s.semester
        ORDER BY s.semester, s.subject_name ASC
    ";
    
    $stmt = $conn->prepare($grades_sql);
    $stmt->bind_param("ii", $selected_student_id, $selected_grade_level);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $grades_data[] = $row;
        if ($row['semester'] && !in_array($row['semester'], $semesters_available)) {
            $semesters_available[] = $row['semester'];
        }
    }
}

// Get the name of the selected grade level
$selected_level_name = "No Grades Available";
if ($selected_grade_level > 0) {
    $level_name_sql = "SELECT level_name FROM tbl_yearlevels WHERE year_level_id = ?";
    $stmt = $conn->prepare($level_name_sql);
    $stmt->bind_param("i", $selected_grade_level);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $selected_level_name = $row['level_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack - Parent Records</title>
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
        
        .btn-primary {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
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
        
        .student-selector {
            margin: 10px 0;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 8px;
        }
        
        .student-btn {
            margin: 5px;
            padding: 10px 20px;
            border: 2px solid var(--secondary);
            background: white;
            border-radius: 8px;
            font-weight: 500;
            color: var(--secondary);
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .student-btn:hover, .student-btn.active {
            background: var(--secondary);
            color: white;
            transform: translateY(-2px);
        }
        
        .level-btn {
            margin: 3px;
            padding: 8px 15px;
            border: 1px solid var(--secondary);
            background: white;
            border-radius: 5px;
            font-size: 14px;
            text-decoration: none;
            color: var(--secondary);
            transition: all 0.3s;
            font-weight: 500;
        }

        .level-btn:hover, .level-btn.active {
            background: var(--secondary);
            color: white;
            transform: translateY(-1px);
        }

        .semester-btn {
            margin: 2px;
            padding: 6px 12px;
            border: 1px solid #6c757d;
            background: white;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
            color: #6c757d;
            transition: all 0.3s;
        }

        .semester-btn:hover, .semester-btn.active {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }

        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 14px;
        }

        .grades-table th {
            background: var(--primary);
            color: white;
            padding: 12px 8px;
            text-align: center;
            border: 1px solid #ddd;
            font-weight: 600;
        }

        .grades-table td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            text-align: center;
        }

        .subject-name {
            text-align: left;
            font-weight: 600;
            background: var(--light);
            color: var(--dark);
        }

        .quarter-header {
            background: var(--secondary);
            color: white;
        }

        .final-grade {
            background: #e3f2fd;
            font-weight: bold;
            color: var(--dark);
        }

        .remarks-col {
            background: #f5f5f5;
        }

        .passed { 
            color: var(--accent); 
            font-weight: bold; 
        }
        .failed { 
            color: #e74c3c; 
            font-weight: bold; 
        }

        .semester-section {
            margin: 15px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
        }

        .semester-title {
            background: var(--accent);
            color: white;
            padding: 10px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--secondary);
        }

        .empty-state h5 {
            color: var(--dark);
            margin-bottom: 10px;
        }

        .student-info-badge {
            background: var(--accent);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            margin-left: 10px;
        }

        @media print {
            .student-selector, .level-selector, .semester-filter, .print-btn, .sidebar, .header { display: none; }
            body { background: white; }
            .main-content { padding: 0; }
            .col-md-9 { width: 100% !important; margin-left: 0 !important; }
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
                    <p class="mb-0">Parent Portal</p>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="parent_dashboard.php"><i class="fas fa-tachometer-alt"></i> Grades</a></li>
                    <li><a href="parent_records.php" class="active"><i class="fas fa-chart-line"></i> Student Records</a></li>
                    <li><a href="feedbacks.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                    <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
                <div class="header">
                    <h3><i class="fas fa-file-alt me-2"></i> Student Academic Records</h3>
                    <div class="user-info">
                        <button class="btn btn-primary me-3" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Records
                        </button>
                        <div class="avatar"><?= substr($parent_name, 0, 1) ?></div>
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($parent_name) ?></div>
                            <div class="text-muted">Parent</div>
                        </div>
                    </div>
                </div>

                <div class="main-content">
                    <!-- Welcome Banner -->
                    <div class="welcome-section">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1>Welcome, <?= htmlspecialchars(explode(" ", $parent_name)[0]) ?>!</h1>
                                <p>Monitor your child's academic progress and records</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-user-graduate fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Academic Records</h5>
                        </div>
                        <div class="card-body">
                            <!-- Student Selector -->
                            <div class="student-selector mb-4">
                                <strong><i class="fas fa-users me-2"></i>Select Student:</strong>
                                <?php foreach ($linked_students as $student): ?>
                                    <a href="parent_records.php?student_id=<?= $student['student_id'] ?>" 
                                       class="student-btn <?= $selected_student_id == $student['student_id'] ? 'active' : '' ?>">
                                        <i class="fas fa-user-graduate me-1"></i>
                                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                        <span class="student-info-badge">
                                            <?= htmlspecialchars($student['level_name']) ?>
                                            <?= $student['relation'] ? ' - ' . $student['relation'] : '' ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- Grade Level Selector -->
                            <div class="level-selector mb-4 p-3" style="background: #e3f2fd; border-radius: 8px;">
                                <strong><i class="fas fa-filter me-2"></i>Select Grade Level:</strong>
                                <?php if (!empty($available_grade_levels)): ?>
                                    <?php foreach ($available_grade_levels as $level): ?>
                                        <a href="parent_records.php?student_id=<?= $selected_student_id ?>&grade_level=<?= $level['year_level_id'] ?>" 
                                           class="level-btn <?= $selected_grade_level == $level['year_level_id'] ? 'active' : '' ?>">
                                            <?= htmlspecialchars($level['level_name']) ?>
                                            <?= $level['year_level_id'] == $current_grade_level ? ' (Current)' : '' ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">No grade records available yet.</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($selected_grade_level > 0): ?>
                            <!-- Semester Filter -->
                            <?php if (!empty($semesters_available)): ?>
                            <div class="semester-filter mb-4 p-3" style="background: #f8f9fa; border-radius: 8px;">
                                <strong><i class="fas fa-calendar me-2"></i>Filter by Semester:</strong>
                                <a href="parent_records.php?student_id=<?= $selected_student_id ?>&grade_level=<?= $selected_grade_level ?>&semester=all" 
                                   class="semester-btn <?= $selected_semester == 'all' ? 'active' : '' ?>">
                                    All Semesters
                                </a>
                                <?php foreach ($semesters_available as $semester): ?>
                                    <a href="parent_records.php?student_id=<?= $selected_student_id ?>&grade_level=<?= $selected_grade_level ?>&semester=<?= urlencode($semester) ?>" 
                                       class="semester-btn <?= $selected_semester == $semester ? 'active' : '' ?>">
                                        <?= htmlspecialchars($semester) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Student Information Card -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-user-graduate me-2"></i>Student Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Student Name:</strong> <?= htmlspecialchars(strtoupper($selected_student['last_name'] . ', ' . $selected_student['first_name'])) ?></p>
                                            <p><strong>LRN:</strong> <?= htmlspecialchars($selected_student['lrn'] ?? 'N/A') ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Grade Level:</strong> <?= htmlspecialchars($selected_level_name) ?></p>
                                            <p><strong>Section:</strong> <?= htmlspecialchars($selected_student['section_name'] ?? 'N/A') ?></p>
                                            <?php if ($selected_student['relation']): ?>
                                            <p><strong>Relationship:</strong> <?= htmlspecialchars($selected_student['relation']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Grades Display -->
                            <?php if (!empty($grades_data)): ?>
                                <?php
                                // Group by semester and filter by selected semester
                                $grouped_by_semester = [];
                                foreach ($grades_data as $grade) {
                                    $semester = $grade['semester'] ?: 'Full Year';
                                    
                                    // Apply semester filter
                                    if ($selected_semester !== 'all' && $semester !== $selected_semester) {
                                        continue;
                                    }
                                    
                                    $grouped_by_semester[$semester][] = $grade;
                                }
                                ?>
                                
                                <?php if (empty($grouped_by_semester)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-search"></i>
                                        <h5>No grades found for the selected semester</h5>
                                        <p>Try selecting a different semester or view all semesters.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($grouped_by_semester as $semester => $semester_grades): ?>
                                        <div class="semester-section">
                                            <?php if (count($grouped_by_semester) > 1): ?>
                                                <div class="semester-title">
                                                    <i class="fas fa-calendar-alt me-2"></i>
                                                    <?= htmlspecialchars($semester) ?> GRADING
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="table-responsive">
                                                <table class="grades-table">
                                                    <thead>
                                                        <tr>
                                                            <th rowspan="2">LEARNING AREAS</th>
                                                            <th colspan="4" class="quarter-header">QUARTERLY GRADES</th>
                                                            <th rowspan="2">FINAL GRADE</th>
                                                            <th rowspan="2">REMARKS</th>
                                                        </tr>
                                                        <tr>
                                                            <th class="quarter-header">1</th>
                                                            <th class="quarter-header">2</th>
                                                            <th class="quarter-header">3</th>
                                                            <th class="quarter-header">4</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $total_final_grade = 0;
                                                        $subjects_count = 0;
                                                        ?>
                                                        
                                                        <?php foreach ($semester_grades as $grade): ?>
                                                            <?php
                                                            // Calculate final grade
                                                            $grades_array = array_filter([
                                                                $grade['q1'], $grade['q2'], $grade['q3'], $grade['q4']
                                                            ], function($v) { 
                                                                return $v !== null && $v !== '' && is_numeric($v); 
                                                            });
                                                            
                                                            $quarter_avg = !empty($grades_array) ? array_sum($grades_array) / count($grades_array) : null;
                                                            $final_grade = $grade['final'] ?? $quarter_avg;
                                                            $final_grade = $final_grade !== null ? round($final_grade, 2) : '';
                                                            
                                                            $remarks = '';
                                                            if ($final_grade !== '') {
                                                                $remarks = ($final_grade >= 75) ? 'PASSED' : 'FAILED';
                                                                $total_final_grade += $final_grade;
                                                                $subjects_count++;
                                                            }
                                                            ?>
                                                            <tr>
                                                                <td class="subject-name">
                                                                    <?= htmlspecialchars($grade['subject_name']) ?>
                                                                </td>
                                                                <td><?= htmlspecialchars($grade['q1'] ?? '') ?></td>
                                                                <td><?= htmlspecialchars($grade['q2'] ?? '') ?></td>
                                                                <td><?= htmlspecialchars($grade['q3'] ?? '') ?></td>
                                                                <td><?= htmlspecialchars($grade['q4'] ?? '') ?></td>
                                                                <td class="final-grade"><?= $final_grade ?></td>
                                                                <td class="remarks-col <?= $remarks === 'PASSED' ? 'passed' : 'failed' ?>">
                                                                    <?= $remarks ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                        
                                                        <!-- Summary Row -->
                                                        <?php if ($subjects_count > 0): ?>
                                                        <tr style="background: #e0e0e0;">
                                                            <td class="subject-name"><strong>GENERAL AVERAGE</strong></td>
                                                            <td colspan="4"></td>
                                                            <td class="final-grade">
                                                                <strong>
                                                                    <?= round($total_final_grade / $subjects_count, 2) ?>
                                                                </strong>
                                                            </td>
                                                            <td class="remarks-col">
                                                                <strong class="<?= ($total_final_grade / $subjects_count) >= 75 ? 'passed' : 'failed' ?>">
                                                                    <?= ($total_final_grade / $subjects_count) >= 75 ? 'PASSED' : 'FAILED' ?>
                                                                </strong>
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-file-alt"></i>
                                    <h5>No grade records found for <?= htmlspecialchars($selected_level_name) ?></h5>
                                    <p>Grades will appear here once they are recorded by the teachers.</p>
                                </div>
                            <?php endif; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-graduation-cap"></i>
                                    <h5>Select a Grade Level</h5>
                                    <p>Choose a grade level from the options above to view academic records.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
<?php $conn->close(); ?>