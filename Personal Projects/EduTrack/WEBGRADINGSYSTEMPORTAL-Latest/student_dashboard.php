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

// Fetch totals
$sql_totals = "
    SELECT COALESCE(SUM(lecture_units),0) AS total_lecture,
           COALESCE(SUM(lab_units),0) AS total_lab
    FROM tbl_subject_enrollments
    WHERE student_id = ?";
$stmt2 = $conn->prepare($sql_totals);
$stmt2->bind_param("i", $student_id);
$stmt2->execute();
$totals = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

// Fetch enrolled subjects
$sql_list = "
    SELECT s.subject_name, e.lecture_units, e.lab_units
    FROM tbl_subject_enrollments e
    JOIN tbl_subjects s ON e.subject_id = s.subject_id
    WHERE e.student_id = ?
    ORDER BY s.subject_name";
$stmt3 = $conn->prepare($sql_list);
$stmt3->bind_param("i", $student_id);
$stmt3->execute();
$subjects = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt3->close();

// Fetch career test results for this student
$career_data = [
    'questions_answered' => 0,
    'suggested_strand' => 'Not taken',
    'possible_courses' => 'Take the test to see recommendations'
];

$career_sql = "SELECT recommended_track, date_taken 
               FROM tbl_career_results 
               WHERE student_id = ? 
               ORDER BY date_taken DESC 
               LIMIT 1";
$career_stmt = $conn->prepare($career_sql);
$career_stmt->bind_param("i", $student_id);
$career_stmt->execute();
$career_result = $career_stmt->get_result();

if ($career_result->num_rows > 0) {
    $career_row = $career_result->fetch_assoc();
    $career_data['suggested_strand'] = $career_row['recommended_track'];
    $career_data['questions_answered'] = 5; // Assuming 5 questions in the test
    
    // Map strands to possible courses
    $courses_mapping = [
        "STEM" => "Engineering, Nursing, IT",
        "ABM" => "Business, Accounting, Management",
        "HUMSS" => "Education, Law, Psychology",
        "TVL" => "Tech-Voc, Hospitality, Automotive"
    ];
    
    $career_data['possible_courses'] = $courses_mapping[$career_row['recommended_track']] ?? "Various fields";
}
$career_stmt->close();

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack - Student Dashboard</title>
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
            cursor: pointer;
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
        
        .card-icon.purple {
            background-color: #f3e5f5;
            color: #9c27b0;
        }
        
        .card-icon.cyan {
            background-color: #e0f7fa;
            color: #00bcd4;
        }
        
        .metric {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .metric-label {
            color: #777;
        }
        
        .metric-value {
            font-weight: 600;
        }
        
        .enrolled-list {
            margin-top: 15px;
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
        
        .hidden {
            display: none;
        }
        
        .nav-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .nav-tab {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: #555;
            border-radius: 4px 4px 0 0;
            margin-right: 5px;
        }
        
        .nav-tab.active {
            background-color: var(--secondary);
            color: white;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
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
                    <p class="mb-0">Student Portal</p>
                </div>
                <ul class="sidebar-menu">
   <li><a href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="student_activities.php"><i class="fas fa-tasks"></i> Activities</a></li>
            <li><a href="student_records.php" class=><i class="fas fa-chart-line"></i> Records</a></li>
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
                    <h3>Student Dashboard</h3>
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
                                <h1>Welcome, <?= htmlspecialchars(explode(" ", $_SESSION['fullname'])[0]) ?>!</h1>
                                <p>Track your academic progress and manage your learning journey</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-user-graduate fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Tabs -->
                    <div class="nav-tabs">
                        <button class="nav-tab active" onclick="showDashboard(this)">Dashboard</button>
                        <button class="nav-tab" onclick="showGrades(this)">Grades</button>
                    </div>

                    <!-- Dashboard Section -->
                    <div id="dashboard-section" class="dashboard-grid">
                        <!-- Enrolled Units Card -->
                        <div class="card" onclick="toggleEnrolledList()">
                            <div class="card-header">
                                <div class="card-icon blue">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">Enrolled Units</h5>
                                    <p class="mb-0 text-muted"><?= $totals['total_lecture'] + $totals['total_lab'] ?> Total Units</p>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="metric">
                                    <div class="metric-label">Lecture Units</div>
                                    <div class="metric-value"><?= $totals['total_lecture'] ?></div>
                                </div>
                                <div class="metric">
                                    <div class="metric-label">Laboratory Units</div>
                                    <div class="metric-value"><?= $totals['total_lab'] ?></div>
                                </div>
                                <div class="enrolled-list" id="enrolledList">
                                    <?php if (empty($subjects)): ?>
                                        <p class="text-muted">No subjects enrolled yet.</p>
                                    <?php else: ?>
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Subject</th>
                                                    <th>Lecture</th>
                                                    <th>Lab</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($subjects as $sub): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                                                        <td><?= $sub['lecture_units'] ?></td>
                                                        <td><?= $sub['lab_units'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- GWA Card -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon purple">
                                    <i class="fas fa-award"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">GWA This Semester</h5>
                                    <p class="mb-0 text-muted">---</p>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="metric">
                                    <div class="metric-label">Highest Grade</div>
                                    <div class="metric-value">---</div>
                                </div>
                                <div class="metric">
                                    <div class="metric-label">Lowest Grade</div>
                                    <div class="metric-value">---</div>
                                </div>
                            </div>
                        </div>

                        <!-- Career Test Card -->
                        <a href="careerpath.php" class="text-decoration-none">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-icon cyan">
                                        <i class="fas fa-clipboard-check"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Career Test</h5>
                                        <p class="mb-0 text-muted">Discover your SHS strand & course</p>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="metric">
                                        <div class="metric-label">Questions Answered</div>
                                        <div class="metric-value"><?= $career_data['questions_answered'] ?></div>
                                    </div>
                                    <div class="metric">
                                        <div class="metric-label">Suggested Strand</div>
                                        <div class="metric-value"><?= $career_data['suggested_strand'] ?></div>
                                    </div>
                                    <div class="metric">
                                        <div class="metric-label">Possible Courses</div>
                                        <div class="metric-value"><?= $career_data['possible_courses'] ?></div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Grades Section -->
                    <div id="grades-section" class="hidden">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">My Grades</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Fetch all semesters with their quarters
                                $semesters_query = "SELECT s.semester_id, s.semester_name, s.academic_year, 
                                                   q.quarter_id, q.quarter_name
                                                   FROM tbl_semesters s
                                                   LEFT JOIN tbl_quarters q ON s.semester_id = q.semester_id
                                                   ORDER BY s.start_date DESC, q.start_date DESC";
                                $semesters_result = $conn->query($semesters_query);
                                
                                if ($semesters_result->num_rows > 0) {
                                    $current_semester = null;
                                    
                                    while ($row = $semesters_result->fetch_assoc()) {
                                        // Start new semester section if this is a new semester
                                        if ($current_semester != $row['semester_id']) {
                                            // Close previous semester if exists
                                            if ($current_semester !== null) {
                                                echo "</div></div>"; // Close card-body and card
                                            }
                                            
                                            $current_semester = $row['semester_id'];
                                            echo '<div class="card mb-4">';
                                            echo '<div class="card-header bg-light">';
                                            echo '<h6>' . htmlspecialchars($row['semester_name']) . ' ' . 
                                                 htmlspecialchars($row['academic_year']) . '</h6>';
                                            echo '</div>';
                                            echo '<div class="card-body">';
                                        }
                                        
                                        // Display quarter section if quarter exists
                                        if ($row['quarter_id']) {
                                            echo '<h6 class="mt-3 mb-3">' . htmlspecialchars($row['quarter_name']) . '</h6>';
                                            
                                            // Fetch grades for this student and quarter
                                            $grades_query = "SELECT s.subject_name, g.grade, g.remarks
                                                           FROM tbl_grades g
                                                           JOIN tbl_subjects s ON g.subject_id = s.subject_id
                                                           WHERE g.student_id = ? AND g.quarter_id = ?
                                                           ORDER BY s.subject_name";
                                            $stmt = $conn->prepare($grades_query);
                                            $stmt->bind_param("ii", $student_id, $row['quarter_id']);
                                            $stmt->execute();
                                            $grades_result = $stmt->get_result();
                                            
                                            if ($grades_result->num_rows > 0) {
                                                echo '<div class="table-responsive">';
                                                echo '<table class="table table-sm table-hover">';
                                                echo '<thead><tr>
                                                        <th>Subject</th>
                                                        <th>Grade</th>
                                                        <th>Remarks</th>
                                                      </tr></thead>';
                                                echo '<tbody>';
                                                
                                                while ($grade = $grades_result->fetch_assoc()) {
                                                    $remarks_class = ($grade['remarks'] == 'Passed') ? 'badge bg-success' : 'badge bg-danger';
                                                    echo '<tr>
                                                            <td>' . htmlspecialchars($grade['subject_name']) . '</td>
                                                            <td>' . htmlspecialchars($grade['grade']) . '</td>
                                                            <td><span class="' . $remarks_class . '">' . 
                                                                htmlspecialchars($grade['remarks']) . '</span></td>
                                                          </tr>';
                                                }
                                                
                                                echo '</tbody></table></div>';
                                            } else {
                                                echo '<p class="text-muted">No grades recorded for this quarter.</p>';
                                            }
                                            
                                            $stmt->close();
                                        }
                                    }
                                    
                                    // Close the last semester if any semesters were found
                                    if ($current_semester !== null) {
                                        echo "</div></div>"; // Close card-body and card
                                    }
                                } else {
                                    echo '<p class="text-muted">No semester/quarter data found.</p>';
                                }
                                ?>
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
        function toggleEnrolledList() {
            let list = document.getElementById("enrolledList");
            list.style.display = (list.style.display === "none" || list.style.display === "") ? "block" : "none";
        }

        function showDashboard(btn) {
            document.getElementById("dashboard-section").classList.remove("hidden");
            document.getElementById("grades-section").classList.add("hidden");
            setActiveTab(btn);
        }

        function showGrades(btn) {
            document.getElementById("dashboard-section").classList.add("hidden");
            document.getElementById("grades-section").classList.remove("hidden");
            setActiveTab(btn);
        }

        function setActiveTab(activeBtn) {
            document.querySelectorAll(".nav-tab").forEach(btn => btn.classList.remove("active"));
            activeBtn.classList.add("active");
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