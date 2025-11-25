<?php
session_start();
require 'db_connect.php';

// Ensure only logged-in students can access
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

$account_id = $_SESSION['account_id'];

// Fetch student_id and current grade level (year_level_id)
$sql = "SELECT s.student_id, s.year_level_id, yl.level_name 
        FROM tbl_students s 
        LEFT JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id 
        WHERE s.account_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $account_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if ($student) {
    $student_id = $student['student_id'];
    $current_grade_level = $student['year_level_id'];
    $current_level_name = $student['level_name'];

    // Get current academic year
    $current_year = date('Y');
    $current_month = date('n');
    if ($current_month >= 6) {
        $academic_year = $current_year . '-' . ($current_year + 1);
    } else {
        $academic_year = ($current_year - 1) . '-' . $current_year;
    }

    // Check if grade_level and academic_year columns exist
    $check_columns_sql = "SHOW COLUMNS FROM tbl_grades LIKE 'grade_level'";
    $result = $conn->query($check_columns_sql);
    $grade_level_column_exists = $result->num_rows > 0;

    $check_columns_sql = "SHOW COLUMNS FROM tbl_grades LIKE 'academic_year'";
    $result = $conn->query($check_columns_sql);
    $academic_year_column_exists = $result->num_rows > 0;

    // DEBUG: Let's see what data actually exists for this student
    $debug_sql = "SELECT g.*, s.subject_name, s.year_level_id 
                  FROM tbl_grades g 
                  JOIN tbl_subjects s ON g.subject_id = s.subject_id 
                  WHERE g.student_id = ? 
                  ORDER BY g.date_recorded DESC";
    $debug_stmt = $conn->prepare($debug_sql);
    $debug_stmt->bind_param("i", $student_id);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    
    echo "<!-- DEBUG: Found " . $debug_result->num_rows . " total grade records for student $student_id -->";
    
    // Build the main query to get the LATEST grades for each subject and grading period
    if ($grade_level_column_exists && $academic_year_column_exists) {
        // If columns exist, use them to filter
        $query = "
            SELECT 
                s.subject_id,
                s.subject_name,
                -- Get the latest Q1 grade
                (SELECT g1.grade FROM tbl_grades g1 
                 WHERE g1.student_id = g.student_id 
                 AND g1.subject_id = g.subject_id 
                 AND g1.grading_period = 'Q1'
                 AND g1.grade_level = ?
                 AND g1.academic_year = ?
                 ORDER BY g1.date_recorded DESC LIMIT 1) AS q1,
                
                -- Get the latest Q2 grade
                (SELECT g2.grade FROM tbl_grades g2 
                 WHERE g2.student_id = g.student_id 
                 AND g2.subject_id = g.subject_id 
                 AND g2.grading_period = 'Q2'
                 AND g2.grade_level = ?
                 AND g2.academic_year = ?
                 ORDER BY g2.date_recorded DESC LIMIT 1) AS q2,
                
                -- Get the latest Q3 grade
                (SELECT g3.grade FROM tbl_grades g3 
                 WHERE g3.student_id = g.student_id 
                 AND g3.subject_id = g.subject_id 
                 AND g3.grading_period = 'Q3'
                 AND g3.grade_level = ?
                 AND g3.academic_year = ?
                 ORDER BY g3.date_recorded DESC LIMIT 1) AS q3,
                
                -- Get the latest Q4 grade
                (SELECT g4.grade FROM tbl_grades g4 
                 WHERE g4.student_id = g.student_id 
                 AND g4.subject_id = g.subject_id 
                 AND g4.grading_period = 'Q4'
                 AND g4.grade_level = ?
                 AND g4.academic_year = ?
                 ORDER BY g4.date_recorded DESC LIMIT 1) AS q4,
                
                -- Get the latest Midterm grade
                (SELECT g5.grade FROM tbl_grades g5 
                 WHERE g5.student_id = g.student_id 
                 AND g5.subject_id = g.subject_id 
                 AND g5.grading_period = 'Midterm'
                 AND g5.grade_level = ?
                 AND g5.academic_year = ?
                 ORDER BY g5.date_recorded DESC LIMIT 1) AS midterm,
                
                -- Get the latest Final grade
                (SELECT g6.grade FROM tbl_grades g6 
                 WHERE g6.student_id = g.student_id 
                 AND g6.subject_id = g.subject_id 
                 AND g6.grading_period = 'Final'
                 AND g6.grade_level = ?
                 AND g6.academic_year = ?
                 ORDER BY g6.date_recorded DESC LIMIT 1) AS final,
                
                MAX(CONCAT(COALESCE(a.first_name,''),' ',COALESCE(a.last_name,''))) AS teacher_name,
                MAX(g.date_recorded) AS date_recorded
            FROM tbl_grades g
            LEFT JOIN tbl_subjects s ON g.subject_id = s.subject_id
            LEFT JOIN tbl_accounts a ON g.teacher_account_id = a.account_id
            WHERE g.student_id = ?
            AND g.grade_level = ?
            AND g.academic_year = ?
            GROUP BY s.subject_id, s.subject_name
            ORDER BY s.subject_name ASC
        ";
        $stmt = $conn->prepare($query);
        // Bind parameters for all the subqueries and main query
        $stmt->bind_param("iisiisiisiisiisii", 
            $current_grade_level, $academic_year,  // Q1
            $current_grade_level, $academic_year,  // Q2
            $current_grade_level, $academic_year,  // Q3
            $current_grade_level, $academic_year,  // Q4
            $current_grade_level, $academic_year,  // Midterm
            $current_grade_level, $academic_year,  // Final
            $student_id, $current_grade_level, $academic_year  // Main query
        );
    } else {
        // If columns don't exist, use the student's current subjects
        $query = "
            SELECT 
                s.subject_id,
                s.subject_name,
                -- Get the latest Q1 grade
                (SELECT g1.grade FROM tbl_grades g1 
                 WHERE g1.student_id = g.student_id 
                 AND g1.subject_id = g.subject_id 
                 AND g1.grading_period = 'Q1'
                 ORDER BY g1.date_recorded DESC LIMIT 1) AS q1,
                
                -- Get the latest Q2 grade
                (SELECT g2.grade FROM tbl_grades g2 
                 WHERE g2.student_id = g.student_id 
                 AND g2.subject_id = g.subject_id 
                 AND g2.grading_period = 'Q2'
                 ORDER BY g2.date_recorded DESC LIMIT 1) AS q2,
                
                -- Get the latest Q3 grade
                (SELECT g3.grade FROM tbl_grades g3 
                 WHERE g3.student_id = g.student_id 
                 AND g3.subject_id = g.subject_id 
                 AND g3.grading_period = 'Q3'
                 ORDER BY g3.date_recorded DESC LIMIT 1) AS q3,
                
                -- Get the latest Q4 grade
                (SELECT g4.grade FROM tbl_grades g4 
                 WHERE g4.student_id = g.student_id 
                 AND g4.subject_id = g.subject_id 
                 AND g4.grading_period = 'Q4'
                 ORDER BY g4.date_recorded DESC LIMIT 1) AS q4,
                
                -- Get the latest Midterm grade
                (SELECT g5.grade FROM tbl_grades g5 
                 WHERE g5.student_id = g.student_id 
                 AND g5.subject_id = g.subject_id 
                 AND g5.grading_period = 'Midterm'
                 ORDER BY g5.date_recorded DESC LIMIT 1) AS midterm,
                
                -- Get the latest Final grade
                (SELECT g6.grade FROM tbl_grades g6 
                 WHERE g6.student_id = g.student_id 
                 AND g6.subject_id = g.subject_id 
                 AND g6.grading_period = 'Final'
                 ORDER BY g6.date_recorded DESC LIMIT 1) AS final,
                
                MAX(CONCAT(COALESCE(a.first_name,''),' ',COALESCE(a.last_name,''))) AS teacher_name,
                MAX(g.date_recorded) AS date_recorded
            FROM tbl_grades g
            LEFT JOIN tbl_subjects s ON g.subject_id = s.subject_id
            LEFT JOIN tbl_accounts a ON g.teacher_account_id = a.account_id
            WHERE g.student_id = ?
            AND s.year_level_id = ?
            GROUP BY s.subject_id, s.subject_name
            ORDER BY s.subject_name ASC
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $student_id, $current_grade_level);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $grades_data = [];
    while ($row = $result->fetch_assoc()) {
        $grades_data[] = $row;
    }
    
    echo "<!-- DEBUG: After filtering, found " . count($grades_data) . " grade records -->";
    
} else {
    $grades_data = [];
    $current_level_name = "Unknown";
    $academic_year = date('Y') . '-' . (date('Y') + 1);
}

// Rest of your HTML code remains the same...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack - My Grades</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing CSS styles */
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #1abc9c;
            --light: #ecf0f1;
            --dark: #34495e;
        }

        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        .sidebar { background-color: var(--primary); color: white; min-height: 100vh; padding: 0; box-shadow: 3px 0 10px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 20px; background-color: rgba(0,0,0,0.2); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li { width: 100%; }
        .sidebar-menu a { color: rgba(255,255,255,0.8); text-decoration: none; display: block; padding: 12px 20px; transition: all 0.3s; border-left: 3px solid transparent; }
        .sidebar-menu a:hover { background-color: rgba(0,0,0,0.2); color: white; border-left: 3px solid var(--accent); }
        .sidebar-menu a.active { background-color: rgba(0,0,0,0.2); color: white; border-left: 3px solid var(--accent); }
        .sidebar-menu i { width: 25px; text-align: center; margin-right: 10px; }

        .main-content { padding: 20px; }
        .header { background-color: white; padding: 15px 20px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .user-info { display: flex; align-items: center; }
        .user-info .avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--secondary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 10px; }
        .card { border: none; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.05); transition: transform 0.3s, box-shadow 0.3s; margin-bottom: 20px; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 6px 12px rgba(0,0,0,0.1); }
        .card-header { background-color: white; border-bottom: 1px solid #eee; font-weight: 600; padding: 15px 20px; border-radius: 8px 8px 0 0 !important; display: flex; align-items: center; }
        .card-body { padding: 20px; }
        .badge-student { background-color: var(--accent); }
        .table th { background-color: var(--light); font-weight: 600; }
        .table-responsive { border-radius: 8px; overflow: hidden; }
        .welcome-section { background: linear-gradient(135deg, var(--secondary), var(--primary)); color: white; border-radius: 8px; padding: 25px; margin-bottom: 25px; }
        .card-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 1.5rem; }
        .card-icon.purple { background-color: #f3e5f5; color: #9c27b0; }
        .btn-primary { background-color: var(--secondary); border-color: var(--secondary); }
        .btn-primary:hover { background-color: #2980b9; border-color: #2980b9; }
        .btn-success { background-color: var(--accent); border-color: var(--accent); }
        .btn-success:hover { background-color: #16a085; border-color: #16a085; }
        .badge-passed { background-color: var(--accent); }
        .badge-failed { background-color: #e74c3c; }
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
                    <li><a href="grades_view.php" class="active"><i class="fas fa-chart-bar"></i> Grades</a></li>
                    <li><a href="careerpath.php"><i class="fas fa-clipboard-check"></i> Career Test</a></li>
                    <li><a href="feedbacks.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                    <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
                <div class="header">
                    <h3>My Grades</h3>
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
                                <h1>Your Academic Performance</h1>
                                <p>View and track your grades by subject</p>
                                <?php if (isset($current_level_name) && isset($academic_year)): ?>
                                    <small>Showing grades for <?= htmlspecialchars($current_level_name) ?> - Academic Year <?= $academic_year ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-chart-line fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Grades Card -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon purple">
                                <i class="fas fa-award"></i>
                            </div>
                            <div>
                                <h5 class="mb-0">Grade Records</h5>
                                <p class="mb-0 text-muted">All subjects with quarterly, midterm, final, and computed final grade</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($grades_data)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Q1</th>
                                                <th>Q2</th>
                                                <th>Q3</th>
                                                <th>Q4</th>
                                                <th>Final</th>
                                                <th>Final Grade</th>
                                                <th>Remarks</th>
                                                <th>Teacher</th>
                                                <th>Date Recorded</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        foreach ($grades_data as $grade):
                                            // Collect numeric quarters
                                            $grades_array = array_filter([
                                                $grade['q1'], $grade['q2'], $grade['q3'], $grade['q4']
                                            ], fn($v) => $v !== null && $v !== '');

                                            $quarter_avg = !empty($grades_array) ? array_sum($grades_array) / count($grades_array) : null;

                                            $final_grade = $grade['final'] ?? $quarter_avg;
                                            $final_grade = $final_grade !== null ? round($final_grade, 2) : '-';

                                            $remarks = ($final_grade !== '-' && $final_grade >= 75) ? 'Passed' : 'Failed';
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($grade['subject_name']) ?></td>
                                                <td><?= htmlspecialchars($grade['q1'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($grade['q2'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($grade['q3'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($grade['q4'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($grade['final'] ?? '-') ?></td>
                                                <td><?= $final_grade ?></td>
                                                <td>
                                                    <?php if ($remarks === 'Passed'): ?>
                                                        <span class="badge badge-passed">Passed</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-failed">Failed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($grade['teacher_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($grade['date_recorded']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No grades recorded for <?= $current_level_name ?? 'current grade level' ?> in Academic Year <?= $academic_year ?? 'current' ?>.
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