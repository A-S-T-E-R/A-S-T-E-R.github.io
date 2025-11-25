<?php
session_start();
require 'db_connect.php';

// Allow both students and admins to access
if (!isset($_SESSION['account_id']) || ($_SESSION['role'] !== 'student' && $_SESSION['role'] !== 'admin')) {
    header("Location: index.php");
    exit();
}

$account_id = $_SESSION['account_id'];
$role       = $_SESSION['role'];
$fullname   = $_SESSION['fullname'];

// Strand definitions
$strands = [
    "STEM" => 0,
    "ABM" => 0,
    "HUMSS" => 0,
    "TVL" => 0
];

// Validated questions based on RIASEC career theory and DepEd guidelines
$questions = [
    "Your preferred learning style is:" => [
        "Hands-on experimentation and labs" => "STEM",
        "Case studies and financial simulations" => "ABM",
        "Group discussions and debates" => "HUMSS",
        "Practical demonstrations and workshops" => "TVL"
    ],
    "In group projects, you typically:" => [
        "Design technical solutions" => "STEM",
        "Manage budgets and resources" => "ABM",
        "Facilitate discussions and mediate" => "HUMSS",
        "Build/prototype physical components" => "TVL"
    ],
    "Your ideal work environment:" => [
        "Research lab or engineering firm" => "STEM",
        "Corporate office or bank" => "ABM",
        "Community center or media agency" => "HUMSS",
        "Workshop or industrial site" => "TVL"
    ],
    "Which task excites you most?" => [
        "Solving complex equations" => "STEM",
        "Developing business strategies" => "ABM",
        "Analyzing social issues" => "HUMSS",
        "Repairing mechanical systems" => "TVL"
    ],
    "Your strongest skill set:" => [
        "Quantitative analysis" => "STEM",
        "Financial planning" => "ABM",
        "Verbal communication" => "HUMSS",
        "Technical craftsmanship" => "TVL"
    ]
];

// CHED-aligned college programs (Philippine-specific)
$courses = [
    "STEM" => ["Computer Engineering", "Medical Technology", "Aeronautical Engineering", "Pharmacy", "Applied Physics"],
    "ABM" => ["Accountancy", "Financial Management", "Business Economics", "Banking and Finance", "Real Estate Management"],
    "HUMSS" => ["Journalism", "Community Development", "International Studies", "Filipino", "Anthropology"],
    "TVL" => ["Industrial Technology", "Food Technology", "Electronics Engineering", "Furniture Design", "Tourism Management"]
];

$strand_descriptions = [
    "STEM" => "Focuses on advanced sciences, technology, and mathematical applications",
    "ABM" => "Prepares for careers in business management, entrepreneurship, and accountancy",
    "HUMSS" => "Develops critical thinking for social sciences, humanities, and communication fields",
    "TVL" => "Provides technical-vocational proficiency for industry-ready skills"
];

// Initialize variables
$top_strand = "";
$form_submitted = false;
$previous_results = [];
$has_previous_result = false;
$all_questions_answered = true;
$save_success = false;

// Get previous results for students
if ($role === 'student') {
    $stmt = $conn->prepare("
        SELECT r.recommended_track, r.date_taken, r.result_text 
        FROM tbl_career_results r 
        INNER JOIN tbl_students s ON r.student_id = s.student_id 
        WHERE s.account_id = ? 
        ORDER BY r.date_taken DESC
    ");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $has_previous_result = true;
        while ($row = $result->fetch_assoc()) {
            $previous_results[] = $row;
        }
    }
    $stmt->close();
}

// Process form submission for ALL users (students take test, admins view results)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assessment'])) {
    $form_submitted = true;
    
    // Check if all questions are answered
    foreach ($questions as $question => $options) {
        $question_key = md5($question);
        if (!isset($_POST[$question_key])) {
            $all_questions_answered = false;
            break;
        }
    }
    
    if ($all_questions_answered) {
        // Process each question's answer
        foreach ($questions as $question => $options) {
            $question_key = md5($question);
            if (isset($_POST[$question_key])) {
                $selected_answer = $_POST[$question_key];
                if (isset($strands[$selected_answer])) {
                    $strands[$selected_answer]++;
                }
            }
        }

        arsort($strands);
        
        // Get the first key (top strand)
        $top_strand = '';
        foreach ($strands as $strand => $score) {
            $top_strand = $strand;
            break;
        }
        
        // Save results to database for students only
        if ($role === 'student') {
            $student_id = null;
            $stmt = $conn->prepare("SELECT student_id FROM tbl_students WHERE account_id = ?");
            $stmt->bind_param("i", $account_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $student_id = $row['student_id'];
                
                // Prepare result text
                $result_text = "Recommended strand: $top_strand. " . $strand_descriptions[$top_strand];
                
                // Get or create test ID
                $test_id = null;
                $test_check = $conn->query("SELECT test_id FROM tbl_career_tests LIMIT 1");
                if ($test_check && $test_check->num_rows > 0) {
                    $test_row = $test_check->fetch_assoc();
                    $test_id = $test_row['test_id'];
                } else {
                    $insert_test = $conn->prepare("INSERT INTO tbl_career_tests (test_name, test_description) VALUES (?, ?)");
                    $test_name = "Career Path Assessment";
                    $test_desc = "Assessment to determine suitable SHS strand";
                    $insert_test->bind_param("ss", $test_name, $test_desc);
                    if ($insert_test->execute()) {
                        $test_id = $conn->insert_id;
                    }
                    $insert_test->close();
                }
                $test_check->close();
                
                // Check if student already has a result
                $check_stmt = $conn->prepare("SELECT result_id FROM tbl_career_results WHERE student_id = ?");
                $check_stmt->bind_param("i", $student_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Update existing result
                    $update_stmt = $conn->prepare("UPDATE tbl_career_results SET result_text = ?, recommended_track = ?, date_taken = NOW() WHERE student_id = ?");
                    $update_stmt->bind_param("ssi", $result_text, $top_strand, $student_id);
                    if ($update_stmt->execute()) {
                        $save_success = true;
                    }
                    $update_stmt->close();
                } else {
                    // Insert new result
                    if ($test_id) {
                        $insert_stmt = $conn->prepare("INSERT INTO tbl_career_results (student_id, test_id, result_text, recommended_track) VALUES (?, ?, ?, ?)");
                        $insert_stmt->bind_param("iiss", $student_id, $test_id, $result_text, $top_strand);
                        if ($insert_stmt->execute()) {
                            $save_success = true;
                        }
                        $insert_stmt->close();
                    }
                }
                
                $check_stmt->close();
                
                // Refresh previous results
                $stmt = $conn->prepare("SELECT recommended_track, date_taken, result_text FROM tbl_career_results WHERE student_id = ? ORDER BY date_taken DESC");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $previous_results = [];
                while ($row = $result->fetch_assoc()) {
                    $previous_results[] = $row;
                }
                $has_previous_result = true;
                $stmt->close();
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack - Career Path Assessment</title>
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
        
        .btn-primary {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .question-card {
            margin-bottom: 20px;
            border-left: 4px solid var(--secondary);
        }
        
        .result-card {
            border-left: 4px solid var(--accent);
        }
        
        .strand-badge {
            font-size: 1.2rem;
            padding: 8px 15px;
            margin-bottom: 15px;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
        }
        
        .stem-badge {
            background-color: #e3f2fd;
            color: #1565c0;
            border: 2px solid #1565c0;
        }
        
        .abm-badge {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 2px solid #2e7d32;
        }
        
        .humss-badge {
            background-color: #f3e5f5;
            color: #7b1fa2;
            border: 2px solid #7b1fa2;
        }
        
        .tvl-badge {
            background-color: #fff3e0;
            color: #e65100;
            border: 2px solid #e65100;
        }
        
        .course-item {
            padding: 8px 15px;
            margin-bottom: 5px;
            background-color: #f5f5f5;
            border-radius: 4px;
            border-left: 3px solid var(--accent);
        }
        
        .history-item {
            border-left: 4px solid var(--secondary);
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .error-message {
            background: #ffe5e5;
            color: #d9534f;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #d9534f;
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
        
        .welcome-section {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .form-check-input:checked {
            background-color: var(--accent);
            border-color: var(--accent);
        }
        
        .form-check-label {
            padding-left: 10px;
            cursor: pointer;
        }
        
        /* Submenu Styles */
        .submenu {
            display: none;
            list-style: none;
            padding-left: 20px;
        }

        .has-submenu > a::after {
            content: "▸";
            float: right;
        }

        .has-submenu.open > a::after {
            content: "▾";
        }

        .has-submenu.open .submenu {
            display: block;
        }
        
        .premium-badge {
            background: linear-gradient(45deg, #FFD700, #FFA500);
            color: #000;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
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
                    <p class="mb-0"><?= ucfirst($role) ?> Portal</p>
                </div>
                
                <?php if ($role === 'admin'): ?>
                <!-- Admin Sidebar with Submenus -->
                <ul class="sidebar-menu">
                    <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>

                    <li class="has-submenu">
                        <a href="#"><i class="fas fa-users-cog"></i><span>Manage Users</span></a>
                        <ul class="submenu">
                            <li><a href="manage_student.php">Manage Students</a></li>
                            <li><a href="manage_teachers.php">Teachers Assignments</a></li>
                            <li><a href="manage_teacheracc.php">Teachers Accounts</a></li>
                            <li><a href="manage_parents.php">Parent Accounts</a></li>
                            <li><a href="create_account.php">Create Accounts</a></li>
                            <li><a href="manage_admins.php">Manage Admins</a></li>
                        </ul>
                    </li>

                    <li class="has-submenu">
                        <a href="#"><i class="fas fa-book-open"></i><span>Academics</span></a>
                        <ul class="submenu">
                            <li><a href="year_levels.php">Year Levels</a></li>
                            <li><a href="manage_sections.php">Sections</a></li>
                            <li><a href="admin_subjects_manage.php">Student Subjects</a></li>
                            <li><a href="subject_adding.php">Add Subjects</a></li>
                            <li><a href="careerpath.php" class="active">Career Result</a></li>
                        </ul>
                    </li>

                    <li class="has-submenu">
                        <a href="#"><i class="fas fa-cogs"></i><span>System</span></a>
                        <ul class="submenu">
                            <li><a href="feedbacks.php">View Feedbacks</a></li>
                            <li><a href="archive_manager.php">Archive Manager</a></li>
                            <li><a href="change_pass.php">Settings</a></li>
                        </ul>
                    </li>

                    <li><a href="logout.php" class="text-danger" onclick="return confirmLogout()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
                </ul>
                
                <?php else: ?>
                <!-- Student Sidebar -->
                <ul class="sidebar-menu">
                    <li><a href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="student_activities.php"><i class="fas fa-tasks"></i> Activities</a></li>
                    <li><a href="student_records.php"><i class="fas fa-chart-line"></i> Records</a></li>
                    <li><a href="grades_view.php"><i class="fas fa-chart-bar"></i> Grades</a></li>
                    <li><a href="careerpath.php" class="active"><i class="fas fa-clipboard-check"></i> Career Test</a></li>
                    <li><a href="feedbacks.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                    <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="logout.php" class="text-danger" onclick="return confirmLogout()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
                <?php endif; ?>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
                <div class="header">
                    <h3>Career Path Assessment</h3>
                    <div class="user-info">
                        <div class="avatar"><?= substr($fullname, 0, 1) ?></div>
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($fullname) ?></div>
                            <div class="text-muted"><?= ucfirst($role) ?></div>
                        </div>
                    </div>
                </div>

                <div class="main-content">
                    <?php if ($role === 'admin'): ?>
                        <!-- Admin View: Show all results -->
                        <div class="welcome-section">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h1>Career Test Results</h1>
                                    <p>View all student career assessment results and recommendations</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <i class="fas fa-clipboard-list fa-4x"></i>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon blue">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">Student Career Results</h5>
                                    <p class="mb-0 text-muted">All career assessment submissions</p>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php
                                $sql = "
                                    SELECT r.result_id, r.result_text, r.recommended_track, r.date_taken,
                                           s.student_id, a.first_name, a.last_name
                                    FROM tbl_career_results r
                                    INNER JOIN tbl_students s ON r.student_id = s.student_id
                                    INNER JOIN tbl_accounts a ON s.account_id = a.account_id
                                    ORDER BY r.date_taken DESC
                                ";
                                $result = $conn->query($sql);

                                if ($result && $result->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Student Name</th>
                                                    <th>Recommended Strand</th>
                                                    <th>Result Details</th>
                                                    <th>Date Taken</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($row = $result->fetch_assoc()):
                                                    $studentName = htmlspecialchars($row['first_name'] . " " . $row['last_name']);
                                                    $resultText  = htmlspecialchars($row['result_text']);
                                                    $track       = htmlspecialchars($row['recommended_track']);
                                                    $date        = htmlspecialchars($row['date_taken']);
                                                ?>
                                                    <tr>
                                                        <td><?= $studentName ?></td>
                                                        <td>
                                                            <span class="badge 
                                                                <?= strtolower($track) == 'stem' ? 'bg-primary' : '' ?>
                                                                <?= strtolower($track) == 'abm' ? 'bg-success' : '' ?>
                                                                <?= strtolower($track) == 'humss' ? 'bg-info' : '' ?>
                                                                <?= strtolower($track) == 'tvl' ? 'bg-warning' : '' ?>">
                                                                <?= $track ?>
                                                            </span>
                                                        </td>
                                                        <td><?= $resultText ?></td>
                                                        <td><?= $date ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">No career assessment results found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    
                    <?php elseif ($form_submitted && !$all_questions_answered): ?>
                        <!-- Error Message for Incomplete Form -->
                        <div class="welcome-section">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h1>Career Assessment</h1>
                                    <p>Complete the assessment to discover your ideal career path</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <i class="fas fa-clipboard-check fa-4x"></i>
                                </div>
                            </div>
                        </div>

                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Please answer all questions before submitting.</strong>
                        </div>
                        
                        <!-- Show the form again with previous selections -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon blue">
                                    <i class="fas fa-question-circle"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">Career Path Assessment</h5>
                                    <p class="mb-0 text-muted">Answer all questions to get your results</p>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="mt-4">
                                    <?php $question_num = 1; ?>
                                    <?php foreach ($questions as $question => $options): ?>
                                        <div class="card question-card mb-3">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    <span class="badge bg-primary me-2"><?= $question_num ?></span>
                                                    <?= $question ?>
                                                </h5>
                                                <div class="form-group">
                                                    <?php foreach ($options as $option => $strand): ?>
                                                        <div class="form-check mb-2">
                                                            <input class="form-check-input" type="radio" 
                                                                   name="<?= md5($question) ?>" 
                                                                   id="<?= md5($question.$option) ?>" 
                                                                   value="<?= $strand ?>"
                                                                   <?= (isset($_POST[md5($question)]) && $_POST[md5($question)] == $strand) ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="<?= md5($question.$option) ?>">
                                                                <?= $option ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php $question_num++; ?>
                                    <?php endforeach; ?>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="submit_assessment" class="btn btn-primary btn-lg">
                                            <i class="fas fa-paper-plane me-2"></i>Submit & Save Results
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    
                    <?php elseif ($form_submitted && $all_questions_answered): ?>
                        <!-- Student Results Display -->
                        <div class="welcome-section">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h1>Assessment Results</h1>
                                    <p>Your career path recommendation is ready!</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <i class="fas fa-award fa-4x"></i>
                                </div>
                            </div>
                        </div>

                        <div class="card result-card">
                            <div class="card-header">
                                <div class="card-icon blue">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">Your Career Assessment Results</h5>
                                    <p class="mb-0 text-muted">Based on your interests and skills</p>
                                </div>
                                <?php if ($save_success): ?>
                                    <span class="premium-badge ms-auto"><i class="fas fa-save me-1"></i>Results Saved</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php 
                                $badge_class = strtolower($top_strand) . '-badge';
                                echo "<div class='strand-badge $badge_class'><i class='fas fa-award me-2'></i>Recommended Strand: $top_strand</div>";
                                echo "<p class='lead'>{$strand_descriptions[$top_strand]}</p>";
                                ?>
                                
                                <h5 class="mt-4"><i class="fas fa-graduation-cap me-2"></i>Suggested College Programs:</h5>
                                <div class="row mt-3">
                                    <?php foreach ($courses[$top_strand] as $course): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="course-item">
                                                <i class="fas fa-book me-2"></i><?= $course ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-4">
                                    <h5><i class="fas fa-chart-bar me-2"></i>Your Strand Scores:</h5>
                                    <div class="table-responsive mt-3">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Strand</th>
                                                    <th>Score</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $total = array_sum($strands);
                                                foreach ($strands as $strand => $score): 
                                                    $percentage = ($total > 0) ? round(($score / $total) * 100) : 0;
                                                ?>
                                                    <tr class="<?= ($strand == $top_strand) ? 'table-active' : '' ?>">
                                                        <td><strong><?= $strand ?></strong></td>
                                                        <td><?= $score ?>/<?= $total ?></td>
                                                        <td><?= $percentage ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <?php if ($save_success): ?>
                                    <div class="alert alert-success mt-4">
                                        <h6><i class="fas fa-save me-2"></i>Results Successfully Saved!</h6>
                                        <p class="mb-0">Your assessment results have been saved to your account. You can view your test history anytime.</p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-4">
                                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-primary">
                                        <i class="fas fa-redo me-2"></i>Retake Assessment
                                    </a>
                                    <a href="student_dashboard.php" class="btn btn-outline-primary ms-2">
                                        <i class="fas fa-tachometer-alt me-2"></i>Back to Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Student Assessment Form -->
                        <div class="welcome-section">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h1>Career Path Assessment</h1>
                                    <p>Discover your ideal Senior High School strand and future career path</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <i class="fas fa-clipboard-list fa-4x"></i>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon blue">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">Career Path Assessment</h5>
                                    <p class="mb-0 text-muted">Answer honestly for accurate results</p>
                                </div>
                                
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h5><i class="fas fa-user-check me-2"></i>Welcome, <?= htmlspecialchars($fullname) ?>!</h5>
                                    <p class="mb-0">Your results will be automatically saved to your account. You can track your progress over time.</p>
                                </div>
                                
                                <!-- Previous Results -->
                                <?php if ($has_previous_result): ?>
                                    <div class="card mb-4">
                                        <div class="card-header bg-light">
                                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Your Previous Results</h5>
                                        </div>
                                        <div class="card-body">
                                            <?php foreach ($previous_results as $index => $result): ?>
                                                <div class="history-item">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-1">
                                                            <span class="badge bg-primary me-2"><?= $index + 1 ?></span>
                                                            <?= $result['recommended_track'] ?>
                                                        </h6>
                                                        <small class="text-muted"><?= date('M j, Y g:i A', strtotime($result['date_taken'])) ?></small>
                                                    </div>
                                                    <p class="mb-0 text-muted"><?= $result['result_text'] ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <p class="lead">This assessment will help determine the most suitable Senior High School strand for you based on your interests and skills.</p>
                                <p>Answer all questions honestly to get accurate results.</p>
                                
                                <form method="POST" class="mt-4">
                                    <?php $question_num = 1; ?>
                                    <?php foreach ($questions as $question => $options): ?>
                                        <div class="card question-card mb-3">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    <span class="badge bg-primary me-2"><?= $question_num ?></span>
                                                    <?= $question ?>
                                                </h5>
                                                <div class="form-group">
                                                    <?php foreach ($options as $option => $strand): ?>
                                                        <div class="form-check mb-2">
                                                            <input class="form-check-input" type="radio" 
                                                                   name="<?= md5($question) ?>" 
                                                                   id="<?= md5($question.$option) ?>" 
                                                                   value="<?= $strand ?>" required>
                                                            <label class="form-check-label" for="<?= md5($question.$option) ?>">
                                                                <?= $option ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php $question_num++; ?>
                                    <?php endforeach; ?>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="submit_assessment" class="btn btn-primary btn-lg">
                                            <i class="fas fa-paper-plane me-2"></i>Submit & Save Results
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced session management script
        document.addEventListener('DOMContentLoaded', function() {
            // Enhanced logout confirmation
            function confirmLogout() {
                if (confirm('Are you sure you want to logout?')) {
                    // Clear client-side storage
                    localStorage.clear();
                    sessionStorage.clear();
                    return true;
                }
                return false;
            }

            // Set up logout link handler
            const logoutLinks = document.querySelectorAll('a[href="logout.php"]');
            logoutLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirmLogout()) {
                        e.preventDefault();
                    }
                });
            });

            // Manage browser history for better navigation
            if (window.history && window.history.pushState) {
                // Add a new history entry
                window.history.pushState(null, null, window.location.href);
                
                // Handle back/forward navigation
                window.addEventListener('popstate', function() {
                    // Check if we should reload (optional)
                    if (performance.navigation.type === 2) {
                        window.location.reload();
                    }
                });
            }

            // Toggle submenu functionality
            document.querySelectorAll(".has-submenu > a").forEach(link => {
                link.addEventListener("click", e => {
                    e.preventDefault();
                    link.parentElement.classList.toggle("open");
                });
            });
        });

        // Handle page visibility changes
        window.onpageshow = function(event) {
            if (event.persisted) {
                // Page was loaded from cache (like when using back button)
                window.location.reload();
            }
        };
    </script>
</body>
</html>
<?php $conn->close(); ?>