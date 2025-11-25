<?php
require 'db_connect.php';

$student_id = $_SESSION['student_id']; // assuming you have this from login

$stmt = $conn->prepare("
    SELECT 
        sub.subject_id,
        sub.subject_name,
        sub.grade_level
    FROM tbl_subjectenrollment se
    JOIN tbl_subjects sub ON se.subject_id = sub.subject_id
    JOIN tbl_students stu ON se.student_id = stu.student_id
    WHERE se.student_id = ? 
      AND sub.grade_level = stu.grade_level
    ORDER BY sub.subject_name
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

$stmt->close();
?>

<h2>My Enrolled Subjects</h2>
<?php if (empty($subjects)): ?>
    <p>No subjects found for your grade level.</p>
<?php else: ?>
    <ul>
        <?php foreach ($subjects as $subject): ?>
            <li><?= htmlspecialchars($subject['subject_name']) ?> (Grade <?= $subject['grade_level'] ?>)</li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
