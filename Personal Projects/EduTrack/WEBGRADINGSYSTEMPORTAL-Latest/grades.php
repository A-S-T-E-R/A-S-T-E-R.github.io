<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

// Get student_id
$account_id = $_SESSION['account_id'];
$stmt = $conn->prepare("SELECT student_id FROM tbl_students WHERE account_id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$student_id = $stmt->get_result()->fetch_assoc()['student_id'];
$stmt->close();

// Handle filter
$year = isset($_GET['year']) ? $_GET['year'] : '';
$quarter = isset($_GET['quarter']) ? $_GET['quarter'] : '';

// Fetch grades
$sql = "SELECT subject_name, midterm_grade, final_grade, remarks, school_year, quarter
        FROM tbl_grades
        JOIN tbl_subjects ON tbl_grades.subject_id = tbl_subjects.subject_id
        WHERE student_id = ?";

$params = [$student_id];
$types = "i";

if ($year !== '') {
    $sql .= " AND school_year = ?";
    $params[] = $year;
    $types .= "s";
}
if ($quarter !== '') {
    $sql .= " AND quarter = ?";
    $params[] = $quarter;
    $types .= "s";
}

$sql .= " ORDER BY school_year DESC, quarter ASC, subject_name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$grades = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Grades</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .filter-form { margin-bottom: 15px; }
        select { padding: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; border-bottom: 1px solid #ccc; text-align: left; }
        .back-link { display: inline-block; margin-bottom: 15px; }
    </style>
</head>
<body>
    <a href="student_dashboard.php" class="back-link">⬅ Back to Dashboard</a>
    <h2>My Grades</h2>

    <!-- Filter Form -->
    <form method="get" class="filter-form">
        <label>
            School Year:
            <select name="year">
                <option value="">All</option>
                <?php
                $years = $conn->query("SELECT DISTINCT school_year FROM tbl_grades WHERE student_id = $student_id ORDER BY school_year DESC");
                while ($y = $years->fetch_assoc()):
                ?>
                    <option value="<?= htmlspecialchars($y['school_year']) ?>" <?= $year == $y['school_year'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($y['school_year']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </label>

        <label>
            Quarter:
            <select name="quarter">
                <option value="">All</option>
                <option value="1st" <?= $quarter == '1st' ? 'selected' : '' ?>>1st Quarter</option>
                <option value="2nd" <?= $quarter == '2nd' ? 'selected' : '' ?>>2nd Quarter</option>
                <option value="3rd" <?= $quarter == '3rd' ? 'selected' : '' ?>>3rd Quarter</option>
                <option value="4th" <?= $quarter == '4th' ? 'selected' : '' ?>>4th Quarter</option>
            </select>
        </label>

        <button type="submit">Filter</button>
    </form>

    <!-- Grades Table -->
    <table>
        <thead>
            <tr>
                <th>School Year</th>
                <th>Quarter</th>
                <th>Subject</th>
                <th>Midterm</th>
                <th>Final</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($grades->num_rows > 0): ?>
                <?php while ($g = $grades->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($g['school_year']) ?></td>
                        <td><?= htmlspecialchars($g['quarter']) ?></td>
                        <td><?= htmlspecialchars($g['subject_name']) ?></td>
                        <td><?= htmlspecialchars($g['midterm_grade']) ?></td>
                        <td><?= htmlspecialchars($g['final_grade']) ?></td>
                        <td><?= htmlspecialchars($g['remarks']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6">No grades found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
