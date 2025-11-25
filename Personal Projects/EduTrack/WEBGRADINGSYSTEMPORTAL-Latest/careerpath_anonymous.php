<?php
session_start();
require 'db_connect.php';

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
$all_questions_answered = true;

// Process form submission for anonymous users
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
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack - Career Path Assessment (Guest Access)</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .navbar {
            background-color: var(--primary);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 600;
            color: white !important;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--secondary), var(--accent));
            color: white;
            border-bottom: none;
            font-weight: 600;
            padding: 20px;
            text-align: center;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--secondary), #2980b9);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .question-card {
            margin-bottom: 25px;
            border-left: 5px solid var(--secondary);
            transition: all 0.3s;
        }
        
        .question-card:hover {
            border-left-color: var(--accent);
        }
        
        .result-card {
            border-left: 5px solid var(--accent);
        }
        
        .strand-badge {
            font-size: 1.4rem;
            padding: 12px 25px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
        }
        
        .stem-badge {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #1565c0;
            border: 2px solid #1565c0;
        }
        
        .abm-badge {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            color: #2e7d32;
            border: 2px solid #2e7d32;
        }
        
        .humss-badge {
            background: linear-gradient(135deg, #f3e5f5, #e1bee7);
            color: #7b1fa2;
            border: 2px solid #7b1fa2;
        }
        
        .tvl-badge {
            background: linear-gradient(135deg, #fff3e0, #ffcc80);
            color: #e65100;
            border: 2px solid #e65100;
        }
        
        .course-item {
            padding: 12px 20px;
            margin-bottom: 8px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid var(--accent);
            transition: all 0.3s;
        }
        
        .course-item:hover {
            background-color: #e9ecef;
            transform: translateX(5px);
        }
        
        .form-check-input:checked {
            background-color: var(--accent);
            border-color: var(--accent);
        }
        
        .guest-banner {
            background: linear-gradient(135deg, #ffeaa7, #fab1a0);
            border: 2px solid #e17055;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .login-prompt {
            background: rgba(255,255,255,0.9);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            text-align: center;
            border: 2px dashed var(--secondary);
        }
        
        .score-bar {
            height: 8px;
            border-radius: 4px;
            margin: 5px 0;
            background: linear-gradient(90deg, var(--accent), var(--secondary));
        }
        
        .error-message {
            background: #ffe5e5;
            color: #d9534f;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #d9534f;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-graduation-cap me-2"></i>EduTrack - Career Assessment (Guest)
            </a>
            <div class="navbar-nav">
                <a href="index.php" class="nav-link text-white">
                    <i class="fas fa-sign-in-alt me-1"></i> Login to Save Results
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Guest Banner -->
                <div class="guest-banner">
                    <h4><i class="fas fa-user-clock me-2"></i>Guest Access Mode</h4>
                    <p class="mb-0">You're taking this assessment as a guest. Your results will not be saved. <a href="index.php" class="fw-bold">Login</a> to save your results and track your progress!</p>
                </div>

                <?php if ($form_submitted && !$all_questions_answered): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Please answer all questions before submitting.</strong>
                    </div>
                <?php endif; ?>

                <?php if ($form_submitted && $all_questions_answered): ?>
                    <!-- Results Display -->
                    <div class="card result-card">
                        <div class="card-header">
                            <h3><i class="fas fa-clipboard-check me-2"></i>Your Career Assessment Results</h3>
                        </div>
                        <div class="card-body">
                            <?php 
                            $badge_class = strtolower($top_strand) . '-badge';
                            echo "<div class='strand-badge $badge_class'>";
                            echo "<i class='fas fa-award me-2'></i>Recommended Strand: $top_strand";
                            echo "</div>";
                            ?>
                            
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle me-2"></i>About <?= $top_strand ?>:</h5>
                                <p class="mb-0"><?= $strand_descriptions[$top_strand] ?></p>
                            </div>
                            
                            <h4 class="mt-4"><i class="fas fa-graduation-cap me-2"></i>Suggested College Programs:</h4>
                            <div class="row mt-3">
                                <?php foreach ($courses[$top_strand] as $course): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="course-item">
                                            <i class="fas fa-book me-2"></i><?= $course ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-4">
                                <h4><i class="fas fa-chart-bar me-2"></i>Your Strand Scores:</h4>
                                <div class="table-responsive mt-3">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Strand</th>
                                                <th>Score</th>
                                                <th>Percentage</th>
                                                <th>Visual</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total = array_sum($strands);
                                            foreach ($strands as $strand => $score): 
                                                $percentage = ($total > 0) ? round(($score / $total) * 100) : 0;
                                                $is_top = ($strand == $top_strand);
                                            ?>
                                                <tr class="<?= $is_top ? 'table-success' : '' ?>">
                                                    <td>
                                                        <strong><?= $strand ?></strong>
                                                        <?php if ($is_top): ?>
                                                            <span class="badge bg-success ms-2">Top Match</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= $score ?>/<?= $total ?></td>
                                                    <td><?= $percentage ?>%</td>
                                                    <td style="width: 30%">
                                                        <div class="score-bar" style="width: <?= $percentage ?>%"></div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Login Prompt -->
                            <div class="login-prompt mt-4">
                                <h5><i class="fas fa-user-plus me-2"></i>Want to save your results?</h5>
                                <p class="mb-3">Create an account to track your progress, view your test history, and access personalized recommendations!</p>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                    <a href="index.php" class="btn btn-success btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login / Register
                                    </a>
                                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-primary btn-lg">
                                        <i class="fas fa-redo me-2"></i>Retake Assessment
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Assessment Form -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-clipboard-list me-2"></i>Career Path Assessment - Guest Mode</h3>
                        </div>
                        
                            
                            <p class="lead text-center">Answer all questions honestly to get accurate results.</p>
                            
                            <form method="POST" class="mt-4">
                                <?php $question_num = 1; ?>
                                <?php foreach ($questions as $question => $options): ?>
                                    <div class="card question-card">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <span class="badge bg-primary me-2"><?= $question_num ?></span>
                                                <?= $question ?>
                                            </h5>
                                            <div class="form-group">
                                                <?php foreach ($options as $option => $strand): ?>
                                                    <div class="form-check mb-3">
                                                        <input class="form-check-input" type="radio" 
                                                               name="<?= md5($question) ?>" 
                                                               id="<?= md5($question.$option) ?>" 
                                                               value="<?= $strand ?>" 
                                                               <?= ($form_submitted && isset($_POST[md5($question)]) && $_POST[md5($question)] == $strand) ? 'checked' : '' ?>>
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
                                
                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" name="submit_assessment" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Assessment (Guest Mode)
                                    </button>
                                </div>
                            </form>
                            
                            <div class="login-prompt mt-4">
                                <h5><i class="fas fa-star me-2"></i>Benefits of Creating an Account:</h5>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Save your assessment results</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Track your progress over time</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Compare with previous results</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Get personalized recommendations</li>
                                </ul>
                                <a href="index.php" class="btn btn-success">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="text-center text-white mt-5 py-3">
        <div class="container">
            <p>&copy; 2025 EduTrack. Career Assessment - Guest Access</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php if (isset($conn)) $conn->close(); ?>