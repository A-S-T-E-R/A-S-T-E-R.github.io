<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: index.php");
    exit();
}

$parent_account_id = $_SESSION['account_id'];

// Get parent_id from tbl_parents
$stmt = $conn->prepare("SELECT parent_id FROM tbl_parents WHERE account_id = ?");
$stmt->bind_param("i", $parent_account_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("No parent record found for this account.");
}

$parent = $result->fetch_assoc();
$parent_id = $parent['parent_id'];

// Get all linked students with their grade level information
$stmt = $conn->prepare("
    SELECT 
        s.student_id, 
        a.first_name, 
        a.last_name,
        yl.year_level_id,
        yl.level_name,
        yl.education_stage,
        sec.section_name
    FROM tbl_parent_student ps
    JOIN tbl_students s ON ps.student_id = s.student_id
    JOIN tbl_accounts a ON s.account_id = a.account_id
    JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id
    LEFT JOIN tbl_sections sec ON s.section_id = sec.section_id
    WHERE ps.parent_id = ?
");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

// --- Feedback Handling ---
$feedback_success = "";
$feedback_error = "";

if (isset($_POST['submit_feedback'])) {
    $feedback = trim($_POST['feedback']);
    if (!empty($feedback)) {
        $stmt = $conn->prepare("INSERT INTO tbl_feedbacks (account_id, feedback_text) VALUES (?, ?)");
        $stmt->bind_param("is", $parent_account_id, $feedback);
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack - Parent Dashboard</title>
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
        
        .table th {
            background-color: var(--light);
            font-weight: 600;
        }
        
        .grade-table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .student-card {
            border-left: 4px solid var(--accent);
        }
        
        .student-card .card-header {
            background-color: rgba(26, 188, 156, 0.1);
        }
        
        .no-grades {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }
        
        .no-grades i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        
        .grade-value {
            text-align: center;
            font-weight: 500;
        }
        
        .final-grade {
            text-align: center;
            font-weight: 700;
            color: var(--primary);
        }
        
        .student-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .info-badge {
            background: var(--secondary);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .subject-not-graded {
            color: #6c757d;
            font-style: italic;
        }
        
        .grade-missing {
            color: #dc3545;
            font-weight: 500;
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
    <!-- Feedback Button -->
    <div class="feedback-btn" data-bs-toggle="modal" data-bs-target="#feedbackModal">
        <i class="fas fa-comment-dots"></i>
    </div>

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
                    <p class="mb-0">Parent Portal</p>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="parent_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Grades</a></li>
                    <li><a href="parent_records.php" class=""><i class="fas fa-chart-line"></i> Student Records</a></li>

                    <li><a href="feedbacks.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                    <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="logout.php" class="text-danger" onclick="return confirmLogout()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
                <div class="header">
                    <h3>Parent Dashboard</h3>
                    <div class="user-info">
                        <div class="avatar">P</div>
                        <div>
                            <div class="fw-bold">Parent Account</div>
                            <div class="text-muted">Parent</div>
                        </div>
                    </div>
                </div>

                <div class="main-content">
                    <!-- Welcome Banner -->
                    <div class="welcome-section">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1>Welcome, Parent!</h1>
                                <p>Track your children's academic progress and performance</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-user-friends fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Students and Grades Section -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-user-graduate me-2"></i>My Students' Grades</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($students)): ?>
                                        <?php foreach ($students as $student): 
                                            $student_id = $student['student_id'];
                                            $student_name = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
                                            $grade_level = htmlspecialchars($student['level_name']);
                                            $education_stage = htmlspecialchars($student['education_stage']);
                                            $section = htmlspecialchars($student['section_name'] ?? 'Not assigned');

                                            // Get all subjects in the student's grade level
                                            $subjects_query = "
                                                SELECT subject_id, subject_name, semester 
                                                FROM tbl_subjects 
                                                WHERE year_level_id = ? 
                                                ORDER BY semester, subject_name
                                            ";
                                            $stmt_subjects = $conn->prepare($subjects_query);
                                            $stmt_subjects->bind_param("i", $student['year_level_id']);
                                            $stmt_subjects->execute();
                                            $subjects_result = $stmt_subjects->get_result();
                                            $all_subjects = [];
                                            while ($subject = $subjects_result->fetch_assoc()) {
                                                $all_subjects[] = $subject;
                                            }

                                            // Get student's grades
                                            $grades_query = "
                                                SELECT 
                                                    s.subject_id,
                                                    s.subject_name,
                                                    MAX(CASE WHEN g.grading_period = 'Q1' THEN g.grade END) AS q1,
                                                    MAX(CASE WHEN g.grading_period = 'Q2' THEN g.grade END) AS q2,
                                                    MAX(CASE WHEN g.grading_period = 'Q3' THEN g.grade END) AS q3,
                                                    MAX(CASE WHEN g.grading_period = 'Q4' THEN g.grade END) AS q4,
                                                    MAX(CASE WHEN g.grading_period = 'Midterm' THEN g.grade END) AS midterm,
                                                    MAX(CASE WHEN g.grading_period = 'Final' THEN g.grade END) AS final,
                                                    MAX(g.remarks) AS remarks
                                                FROM tbl_grades g
                                                LEFT JOIN tbl_subjects s ON g.subject_id = s.subject_id
                                                WHERE g.student_id = ?
                                                GROUP BY s.subject_id, s.subject_name
                                            ";
                                            
                                            $stmt_grades = $conn->prepare($grades_query);
                                            $stmt_grades->bind_param("i", $student_id);
                                            $stmt_grades->execute();
                                            $grades_result = $stmt_grades->get_result();
                                            $student_grades = [];
                                            while ($grade = $grades_result->fetch_assoc()) {
                                                $student_grades[$grade['subject_id']] = $grade;
                                            }
                                        ?>
                                            <div class="card student-card mb-4">
                                                <div class="card-header">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h5 class="mb-0">
                                                            <i class="fas fa-user me-2"></i>
                                                            <?php echo $student_name; ?>
                                                        </h5>
                                                        <div class="student-info-badges">
                                                            <span class="info-badge me-2">
                                                                <i class="fas fa-graduation-cap me-1"></i>
                                                                <?php echo $grade_level; ?>
                                                            </span>
                                                            <span class="info-badge me-2">
                                                                <i class="fas fa-layer-group me-1"></i>
                                                                <?php echo $education_stage; ?>
                                                            </span>
                                                            <span class="info-badge">
                                                                <i class="fas fa-users me-1"></i>
                                                                <?php echo $section; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <!-- Student Information -->
                                                    <div class="student-info">
                                                        <div class="row">
                                                            <div class="col-md-4">
                                                                <strong>Grade Level:</strong> <?php echo $grade_level; ?>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <strong>Education Stage:</strong> <?php echo $education_stage; ?>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <strong>Section:</strong> <?php echo $section; ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($all_subjects)): ?>
                                                        <div class="table-responsive grade-table">
                                                            <table class="table table-bordered table-hover mb-0">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Subject</th>
                                                                        <th class="text-center">Semester</th>
                                                                        <th class="text-center">Q1</th>
                                                                        <th class="text-center">Q2</th>
                                                                        <th class="text-center">Q3</th>
                                                                        <th class="text-center">Q4</th>
                                                                        <th class="text-center">Final Grade</th>
                                                                        <th class="text-center">Status</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php 
                                                                    $total_final_grade = 0;
                                                                    $graded_subjects_count = 0;
                                                                    foreach ($all_subjects as $subject): 
                                                                        $subject_id = $subject['subject_id'];
                                                                        $subject_name = htmlspecialchars($subject['subject_name']);
                                                                        $semester = htmlspecialchars($subject['semester'] ?? 'N/A');
                                                                        
                                                                        $grade_data = $student_grades[$subject_id] ?? null;
                                                                        
                                                                        $q1 = $grade_data['q1'] ?? null;
                                                                        $q2 = $grade_data['q2'] ?? null;
                                                                        $q3 = $grade_data['q3'] ?? null;
                                                                        $q4 = $grade_data['q4'] ?? null;
                                                                        $final_grade = $grade_data['final'] ?? null;
                                                                        
                                                                        $has_grades = $q1 !== null || $q2 !== null || $q3 !== null || $q4 !== null || $final_grade !== null;
                                                                        
                                                                        if ($final_grade !== null) {
                                                                            $total_final_grade += $final_grade;
                                                                            $graded_subjects_count++;
                                                                        }
                                                                    ?>
                                                                        <tr class="<?php echo !$has_grades ? 'subject-not-graded' : ''; ?>">
                                                                            <td>
                                                                                <?php echo $subject_name; ?>
                                                                                <?php if (!$has_grades): ?>
                                                                                    <small class="text-muted d-block">(Not yet graded)</small>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="text-center"><?php echo $semester; ?></td>
                                                                            <td class="grade-value">
                                                                                <?php if ($q1 !== null): ?>
                                                                                    <?php echo $q1; ?>
                                                                                <?php else: ?>
                                                                                    <span class="grade-missing">-</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="grade-value">
                                                                                <?php if ($q2 !== null): ?>
                                                                                    <?php echo $q2; ?>
                                                                                <?php else: ?>
                                                                                    <span class="grade-missing">-</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="grade-value">
                                                                                <?php if ($q3 !== null): ?>
                                                                                    <?php echo $q3; ?>
                                                                                <?php else: ?>
                                                                                    <span class="grade-missing">-</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="grade-value">
                                                                                <?php if ($q4 !== null): ?>
                                                                                    <?php echo $q4; ?>
                                                                                <?php else: ?>
                                                                                    <span class="grade-missing">-</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="final-grade">
                                                                                <?php if ($final_grade !== null): ?>
                                                                                    <?php echo $final_grade; ?>
                                                                                <?php else: ?>
                                                                                    <span class="grade-missing">-</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="text-center">
                                                                                <?php if ($has_grades): ?>
                                                                                    <span class="badge bg-success">Graded</span>
                                                                                <?php else: ?>
                                                                                    <span class="badge bg-warning">Pending</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                                <!-- General Average Row -->
                                                                <?php if ($graded_subjects_count > 0): ?>
                                                                    <tfoot>
                                                                        <tr style="background-color: #f8f9fa;">
                                                                            <td colspan="6" class="text-end"><strong>General Average:</strong></td>
                                                                            <td class="final-grade">
                                                                                <strong><?php echo number_format($total_final_grade / $graded_subjects_count, 2); ?></strong>
                                                                            </td>
                                                                            <td class="text-center">
                                                                                <?php 
                                                                                $general_average = $total_final_grade / $graded_subjects_count;
                                                                                $status_class = $general_average >= 75 ? 'bg-success' : 'bg-danger';
                                                                                $status_text = $general_average >= 75 ? 'PASSED' : 'FAILED';
                                                                                ?>
                                                                                <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                                            </td>
                                                                        </tr>
                                                                    </tfoot>
                                                                <?php endif; ?>
                                                            </table>
                                                        </div>
                                                        
                                                        <!-- Summary Information -->
                                                        <div class="row mt-3">
                                                            <div class="col-md-6">
                                                                <div class="alert alert-info">
                                                                    <h6><i class="fas fa-info-circle me-2"></i>Summary</h6>
                                                                    <div class="small">
                                                                        <strong>Total Subjects:</strong> <?php echo count($all_subjects); ?><br>
                                                                        <strong>Graded Subjects:</strong> <?php echo $graded_subjects_count; ?><br>
                                                                        <strong>Pending Grades:</strong> <?php echo count($all_subjects) - $graded_subjects_count; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="alert alert-light">
                                                                    <h6><i class="fas fa-clipboard-check me-2"></i>Legend</h6>
                                                                    <div class="small">
                                                                        <span class="badge bg-success me-2">Graded</span> Grades have been recorded<br>
                                                                        <span class="badge bg-warning me-2">Pending</span> Waiting for teacher input<br>
                                                                        <span class="text-muted italic">Italic subjects</span> No grades recorded yet
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="no-grades">
                                                            <i class="fas fa-books"></i>
                                                            <h5>No subjects found for <?php echo $grade_level; ?></h5>
                                                            <p>Please contact the school administration if this seems incorrect</p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-grades">
                                            <i class="fas fa-users"></i>
                                            <h5>No students linked to your account</h5>
                                            <p>Please contact the school administration to link your children to your parent account</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-comment-dots me-2"></i> Send Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> Your feedback is not anonymous. Administrators will see your name along with your feedback.
                        </div>
                        <div class="mb-3">
                            <label for="feedbackText" class="form-label">Your Feedback</label>
                            <textarea class="form-control" id="feedbackText" name="feedback" rows="5" placeholder="Share your thoughts, suggestions, or concerns..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_feedback" class="btn btn-success">
                            <i class="fas fa-paper-plane me-1"></i> Submit Feedback
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmLogout() {
        return confirm('Are you sure you want to logout?');
    }
    
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