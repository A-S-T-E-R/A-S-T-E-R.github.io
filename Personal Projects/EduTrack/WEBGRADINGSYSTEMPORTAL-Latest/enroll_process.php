<?php
// enroll_process.php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: enroll_subjects.php");
    exit();
}

$student_id = intval($_POST['student_id'] ?? 0);
$subjects = $_POST['subjects'] ?? [];
$lecture_units = $_POST['lecture_units'] ?? [];
$lab_units = $_POST['lab_units'] ?? [];

if ($student_id <= 0 || empty($subjects)) {
    $_SESSION['enroll_error'] = "Please select a student and at least one subject.";
    header("Location: enroll_subjects.php");
    exit();
}

// Prepare insert statement (use ON DUPLICATE KEY UPDATE to avoid duplicate errors)
$stmt = $conn->prepare("INSERT INTO tbl_subject_enrollments
    (student_id, subject_id, lecture_units, lab_units)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE lecture_units = VALUES(lecture_units), lab_units = VALUES(lab_units)");

foreach ($subjects as $subject_id) {
    $subject_id = intval($subject_id);
    $lv = isset($lecture_units[$subject_id]) ? intval($lecture_units[$subject_id]) : 0;
    $lab = isset($lab_units[$subject_id]) ? intval($lab_units[$subject_id]) : 0;
    $stmt->bind_param("iiii", $student_id, $subject_id, $lv, $lab);
    $stmt->execute();
}

$stmt->close();
$_SESSION['enroll_success'] = "Subjects enrolled successfully.";
header("Location: enroll_subjects.php");
exit();
