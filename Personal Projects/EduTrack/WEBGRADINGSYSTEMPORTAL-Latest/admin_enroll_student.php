<?php
session_start();
require 'db_connect.php';

// Only admin can access
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch all students (role=student)
$students = $conn->query("
    SELECT st.student_id, CONCAT(ac.first_name, ' ', ac.last_name) AS fullname
    FROM tbl_students st
    JOIN tbl_accounts ac ON st.account_id = ac.account_id
    ORDER BY fullname ASC
")->fetch_all(MYSQLI_ASSOC);

// Fetch year levels
$year_levels = $conn->query("
    SELECT year_level_id, level_name FROM tbl_yearlevels ORDER BY year_level_id
")->fetch_all(MYSQLI_ASSOC);

// Fetch sections
$sections = $conn->query("
    SELECT section_id, section_name, year_level_id FROM tbl_sections ORDER BY section_name
")->fetch_all(MYSQLI_ASSOC);

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id']);
    $year_level_id = intval($_POST['year_level_id']);
    $section_id = intval($_POST['section_id']);
    $subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];

    // Update student's grade level & section
    $stmt = $conn->prepare("UPDATE tbl_students SET year_level_id=?, section_id=? WHERE student_id=?");
    $stmt->bind_param("iii", $year_level_id, $section_id, $student_id);
    $stmt->execute();
    $stmt->close();

    // Remove old subject enrollments
    $stmt = $conn->prepare("DELETE FROM tbl_subject_enrollments WHERE student_id=?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->close();

    // Insert new subject enrollments
    $stmt = $conn->prepare("INSERT INTO tbl_subject_enrollments (student_id, subject_id, lecture_units, lab_units) VALUES (?, ?, ?, ?)");
    foreach ($subjects as $sub_id) {
        // Get units from tbl_subjects (set defaults if null)
        $get_units = $conn->prepare("SELECT 3 AS lecture_units, 0 AS lab_units FROM tbl_subjects WHERE subject_id=?");
        $get_units->bind_param("i", $sub_id);
        $get_units->execute();
        $units = $get_units->get_result()->fetch_assoc();
        $get_units->close();

        $stmt->bind_param("iiii", $student_id, $sub_id, $units['lecture_units'], $units['lab_units']);
        $stmt->execute();
    }
    $stmt->close();

    $success_msg = "Enrollment updated successfully!";
}

// Handle AJAX subject fetch
if (isset($_GET['fetch_subjects']) && isset($_GET['year_level_id'])) {
    $year_level_id = intval($_GET['year_level_id']);
    $subjects = $conn->query("SELECT subject_id, subject_name FROM tbl_subjects WHERE year_level_id = $year_level_id OR year_level_id IS NULL ORDER BY subject_name")->fetch_all(MYSQLI_ASSOC);
    echo json_encode($subjects);
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin - Enroll Student</title>
<style>
body { font-family: Arial; margin: 20px; }
select, button { padding: 6px; margin-top: 5px; }
fieldset { margin-top: 15px; }
</style>
<script>
function fetchSubjects(yearLevelId) {
    fetch("?fetch_subjects=1&year_level_id=" + yearLevelId)
    .then(res => res.json())
    .then(data => {
        let subjectList = document.getElementById("subjectList");
        subjectList.innerHTML = "";
        data.forEach(sub => {
            subjectList.innerHTML += `<label><input type='checkbox' name='subjects[]' value='${sub.subject_id}'> ${sub.subject_name}</label><br>`;
        });
    });
}

function filterSections(yearLevelId) {
    let allSections = document.querySelectorAll("#sectionSelect option");
    allSections.forEach(opt => {
        if (opt.dataset.year != yearLevelId && opt.value != "") {
            opt.style.display = "none";
        } else {
            opt.style.display = "block";
        }
    });
}
</script>
</head>
<body>

<h2>Admin - Enroll Student</h2>
<?php if (!empty($success_msg)): ?>
<p style="color:green;"><?= $success_msg ?></p>
<?php endif; ?>

<form method="POST">
    <label>Student:</label><br>
    <select name="student_id" required>
        <option value="">-- Select Student --</option>
        <?php foreach ($students as $st): ?>
            <option value="<?= $st['student_id'] ?>"><?= htmlspecialchars($st['fullname']) ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Year Level:</label><br>
    <select name="year_level_id" required onchange="fetchSubjects(this.value); filterSections(this.value);">
        <option value="">-- Select Year Level --</option>
        <?php foreach ($year_levels as $yl): ?>
            <option value="<?= $yl['year_level_id'] ?>"><?= htmlspecialchars($yl['level_name']) ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Section:</label><br>
    <select name="section_id" id="sectionSelect" required>
        <option value="">-- Select Section --</option>
        <?php foreach ($sections as $sc): ?>
            <option value="<?= $sc['section_id'] ?>" data-year="<?= $sc['year_level_id'] ?>"><?= htmlspecialchars($sc['section_name']) ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <fieldset>
        <legend>Subjects</legend>
        <div id="subjectList">Select a year level to load subjects.</div>
    </fieldset><br>

    <button type="submit">Save Enrollment</button>
</form>

</body>
</html>
