<?php
session_start();
require 'db_connect.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['account_id'];
$teacher_name = $_SESSION['fullname'] ?? 'Teacher';

// Optional filters from GET
$semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : null;
$quarter_id  = isset($_GET['quarter_id']) ? intval($_GET['quarter_id']) : null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20; // Number of students to show

// Function to get PH grading remarks
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

// SQL: Fetch students with the lowest grades for this teacher
$sql = "
SELECT 
    s.student_id,
    a.first_name,
    a.last_name,
    subj.subject_name,
    -- Get the latest Q1 grade
    (SELECT g1.grade FROM tbl_grades g1 
     WHERE g1.student_id = s.student_id AND g1.subject_id = subj.subject_id 
     AND g1.grading_period = 'Q1' ORDER BY g1.date_recorded DESC LIMIT 1) as q1,
    
    -- Get the latest Q2 grade
    (SELECT g2.grade FROM tbl_grades g2 
     WHERE g2.student_id = s.student_id AND g2.subject_id = subj.subject_id 
     AND g2.grading_period = 'Q2' ORDER BY g2.date_recorded DESC LIMIT 1) as q2,
    
    -- Get the latest Q3 grade
    (SELECT g3.grade FROM tbl_grades g3 
     WHERE g3.student_id = s.student_id AND g3.subject_id = subj.subject_id 
     AND g3.grading_period = 'Q3' ORDER BY g3.date_recorded DESC LIMIT 1) as q3,
    
    -- Get the latest Q4 grade
    (SELECT g4.grade FROM tbl_grades g4 
     WHERE g4.student_id = s.student_id AND g4.subject_id = subj.subject_id 
     AND g4.grading_period = 'Q4' ORDER BY g4.date_recorded DESC LIMIT 1) as q4,
    
    g_final.grade as final_grade, 
    g_final.remarks,
    sec.section_name,
    yl.level_name
FROM tbl_grades g_final
JOIN tbl_students s ON g_final.student_id = s.student_id
JOIN tbl_accounts a ON s.account_id = a.account_id
JOIN tbl_subjects subj ON g_final.subject_id = subj.subject_id
JOIN tbl_teacher_assignments ta ON ta.subject_id = g_final.subject_id AND ta.section_id = s.section_id
JOIN tbl_sections sec ON s.section_id = sec.section_id
JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id
WHERE ta.teacher_account_id = ? 
AND g_final.grading_period = 'Final'
";

// Add optional filters
if ($semester_id) {
    $sql .= " AND subj.semester_id = ? ";
}
if ($quarter_id) {
    $sql .= " AND g_final.quarter_id = ? ";
}

$sql .= " ORDER BY g_final.grade ASC, yl.level_name, sec.section_name, a.last_name, a.first_name LIMIT ?";

// Prepare statement
$stmt = $conn->prepare($sql);

// Bind parameters dynamically
$params = [$teacher_id];
$types = "i";

if ($semester_id) {
    $params[] = $semester_id;
    $types .= "i";
}
if ($quarter_id) {
    $params[] = $quarter_id;
    $types .= "i";
}

$params[] = $limit;
$types .= "i";

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Fetch assigned classes for sidebar
$assigned_classes = [];
$class_stmt = $conn->prepare("
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
$class_stmt->bind_param("i", $teacher_id);
$class_stmt->execute();
$class_res = $class_stmt->get_result();
if ($class_res) {
    while ($row = $class_res->fetch_assoc()) $assigned_classes[] = $row;
}
$class_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>EduTrack - Low Grades Report</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        :root{--primary:#2c3e50;--secondary:#3498db;--accent:#1abc9c;--light:#ecf0f1;--dark:#34495e;}
        body{background:#f8f9fa;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;}
        .sidebar{background:var(--primary);color:#fff;min-height:100vh;padding:0;box-shadow:3px 0 10px rgba(0,0,0,.1)}
        .sidebar-header{padding:20px;background:rgba(0,0,0,.2);border-bottom:1px solid rgba(255,255,255,.1)}
        .sidebar-menu{list-style:none;padding:0;margin:0}
        .sidebar-menu a{color:rgba(255,255,255,.8);text-decoration:none;display:block;padding:12px 20px;transition:.3s;border-left:3px solid transparent}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(0,0,0,.2);color:#fff;border-left:3px solid var(--accent)}
        .sidebar-menu i{width:25px;text-align:center;margin-right:10px}
        .submenu{list-style:none;background:rgba(0,0,0,.1);margin-top:0;padding-left:0;display:none}
        .has-submenu.active .submenu{display:block}
        .has-submenu>a::after{content:'\f078';font-family:'Font Awesome 6 Free';font-weight:900;float:right;font-size:.8rem;transition:transform .3s}
        .has-submenu.active>a::after{transform:rotate(180deg)}
        .main-content{padding:20px}
        .header{background:#fff;padding:15px 20px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 4px rgba(0,0,0,.05)}
        .avatar{width:40px;height:40px;border-radius:50%;background:var(--secondary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:bold;margin-right:10px}
        .card{border:none;border-radius:8px;box-shadow:0 4px 8px rgba(0,0,0,.05);transition:transform .3s,box-shadow .3s;margin-bottom:20px}
        .card:hover{transform:translateY(-5px);box-shadow:0 6px 12px rgba(0,0,0,.1)}
        .card-header{background:#fff;border-bottom:1px solid #eee;font-weight:600;padding:15px 20px;border-radius:8px 8px 0 0 !important}
        .stat-card{text-align:center;padding:20px}
        .stat-card i{font-size:2.5rem;margin-bottom:15px;color:var(--secondary)}
        .stat-card .number{font-size:2rem;font-weight:700;color:var(--dark)}
        .label{color:#777;font-size:.9rem;text-transform:uppercase;letter-spacing:1px}
        .subject-badge{background:var(--accent);color:#fff;padding:3px 8px;border-radius:4px;font-size:.8rem;font-weight:500}
        .grade-input{width:100%;max-width:80px;text-align:center}
        .average-badge{font-weight:bold;padding:5px 10px;border-radius:4px}
        .welcome-section{background:linear-gradient(135deg,var(--secondary),var(--primary));color:#fff;border-radius:8px;padding:25px;margin-bottom:25px}
        .grade-level-badge{background:var(--secondary);color:#fff;padding:3px 8px;border-radius:4px;font-size:.9rem;font-weight:500}
        .nav-tabs .nav-link.active{background:var(--secondary);color:#fff}
        .class-section{border:1px solid #dee2e6;border-radius:8px;background:#fff;}
        .grade-cell{position:relative;}
        .grade-cell:hover{background-color:#f8f9fa;cursor:pointer;}
        .quarter-header{background-color:#e9ecef;font-weight:bold;}
        .ph-scale{font-size:0.8rem;margin-top:10px;}
        .filter-section{background:#fff;padding:15px;border-radius:8px;margin-bottom:20px;}
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
                <li><a href="feedbacks.php" class=><i class="fas fa-comment-dots"></i> Feedback</a></li>
                <li><a href="change_pass.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <!-- Main -->
        <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
            <div class="header">
                <h3>Low Grades Report</h3>
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
                            <h1>Low Grades Report</h1>
                            <p>View students with the lowest final grades in your classes.</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="fas fa-chart-line fa-4x"></i>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-section card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-filter"></i> Filter Results</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Results Limit</label>
                                <select name="limit" class="form-select">
                                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>Top 10 Lowest</option>
                                    <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>Top 20 Lowest</option>
                                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>Top 50 Lowest</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Semester</label>
                                <select name="semester_id" class="form-select">
                                    <option value="">All Semesters</option>
                                    <!-- You would populate this from your database -->
                                    <option value="1" <?= $semester_id == 1 ? 'selected' : '' ?>>1st Semester</option>
                                    <option value="2" <?= $semester_id == 2 ? 'selected' : '' ?>>2nd Semester</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Quarter</label>
                                <select name="quarter_id" class="form-select">
                                    <option value="">All Quarters</option>
                                    <!-- You would populate this from your database -->
                                    <option value="1" <?= $quarter_id == 1 ? 'selected' : '' ?>>1st Quarter</option>
                                    <option value="2" <?= $quarter_id == 2 ? 'selected' : '' ?>>2nd Quarter</option>
                                    <option value="3" <?= $quarter_id == 3 ? 'selected' : '' ?>>3rd Quarter</option>
                                    <option value="4" <?= $quarter_id == 4 ? 'selected' : '' ?>>4th Quarter</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                            </div>
                        </form>
                    </div>
                </div>

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

                <!-- Low Grades Report -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas fa-exclamation-triangle"></i> 
                            Students with Lowest Grades
                            <?php if($limit): ?>
                                <span class="badge bg-primary">Top <?= $limit ?> Lowest</span>
                            <?php endif; ?>
                        </span>
                        <span class="subject-badge"><?= $result->num_rows ?> Students</span>
                    </div>
                    <div class="card-body">
                        <?php if($result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Year Level</th>
                                            <th>Section</th>
                                            <th>Subject</th>
                                            <th>Q1</th>
                                            <th>Q2</th>
                                            <th>Q3</th>
                                            <th>Q4</th>
                                            <th>Final Grade</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                       <?php while($row = $result->fetch_assoc()): 
    // Use final_grade instead of grade for remarks calculation
    $final_grade = $row['final_grade'];
    $remarks = $row['remarks'] ?: get_ph_remarks($final_grade);
    $remarks_class = get_ph_remarks_class($remarks);
?>
    <tr>
        <td>
            <strong><?= htmlspecialchars($row['last_name']) ?>, <?= htmlspecialchars($row['first_name']) ?></strong>
        </td>
        <td><?= htmlspecialchars($row['level_name']) ?></td>
        <td><?= htmlspecialchars($row['section_name']) ?></td>
        <td><?= htmlspecialchars($row['subject_name']) ?></td>
        
        <!-- Quarterly Grades -->
        <?php foreach (['q1', 'q2', 'q3', 'q4'] as $quarter): ?>
            <td class="text-center">
                <?php if ($row[$quarter]): ?>
                    <span class="badge <?= get_ph_remarks_class(get_ph_remarks($row[$quarter])) ?>">
                        <?= number_format($row[$quarter], 1) ?>
                    </span>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </td>
        <?php endforeach; ?>
        
        <!-- Final Grade - CHANGED FROM grade TO final_grade -->
        <td class="text-center">
            <span class="badge <?= $remarks_class ?>">
                <?= number_format($row['final_grade'], 1) ?>
            </span>
        </td>
        
        <!-- Remarks -->
        <td class="text-center">
            <span class="badge <?= $remarks_class ?>">
                <?= $remarks ?>
            </span>
        </td>
    </tr>
<?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                No students with low grades found for the selected filters.
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
// Submenu toggle
document.querySelectorAll('.has-submenu > a').forEach(a => {
    a.addEventListener('click', e => {
        e.preventDefault();
        a.parentElement.classList.toggle('active');
    });
});
</script>

</body>
</html>
<?php 
$stmt->close();
$conn->close(); 
?>