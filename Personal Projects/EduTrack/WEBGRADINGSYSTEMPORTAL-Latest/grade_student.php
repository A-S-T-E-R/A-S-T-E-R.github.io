<?php
session_start();
require 'db_connect.php';
require 'grading_functions.php';

if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}

$grade_id = $_GET['grade_id'] ?? null;
if (!$grade_id) die("Grade not found.");

// Fetch grade info and student details
$stmt = $conn->prepare("
    SELECT g.*, s.subject_name, st.student_id, a.first_name, a.last_name, sec.section_name
    FROM tbl_grades g
    JOIN tbl_subjects s ON g.subject_id = s.subject_id
    JOIN tbl_students st ON g.student_id = st.student_id
    JOIN tbl_accounts a ON st.account_id = a.account_id
    JOIN tbl_sections sec ON st.section_id = sec.section_id
    WHERE g.grade_id = ?
");
$stmt->bind_param("i", $grade_id);
$stmt->execute();
$grade_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$grade_info) die("Grade not found.");

// Handle grade update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_grade = $_POST['grade'] ?? $grade_info['grade'];
    $remarks = ($new_grade >= 75) ? 'Passed' : 'Failed';

    $stmt_update = $conn->prepare("UPDATE tbl_grades SET grade = ?, remarks = ? WHERE grade_id = ?");
    $stmt_update->bind_param("isi", $new_grade, $remarks, $grade_id);
    $stmt_update->execute();
    $stmt_update->close();

    header("Location: grade_student.php?grade_id=$grade_id&success=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Grade - <?= htmlspecialchars($grade_info['subject_name']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h3>Student Grade Details</h3>
    <div class="card mb-3">
        <div class="card-body">
            <p><strong>Name:</strong> <?= htmlspecialchars($grade_info['first_name'] . ' ' . $grade_info['last_name']) ?></p>
            <p><strong>Section:</strong> <?= htmlspecialchars($grade_info['section_name']) ?></p>
            <p><strong>Subject:</strong> <?= htmlspecialchars($grade_info['subject_name']) ?></p>
            <p><strong>Current Grade:</strong> <?= htmlspecialchars($grade_info['grade']) ?></p>
            <p><strong>Remarks:</strong> <?= htmlspecialchars($grade_info['remarks']) ?></p>
        </div>
    </div>

    <form method="POST">
        <div class="mb-3">
            <label for="grade" class="form-label">Update Grade</label>
            <input type="number" name="grade" id="grade" class="form-control" min="0" max="100" value="<?= htmlspecialchars($grade_info['grade']) ?>" required>
        </div>
        <button type="submit" class="btn btn-success">Save Grade</button>
        <a href="students_dashboard.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
