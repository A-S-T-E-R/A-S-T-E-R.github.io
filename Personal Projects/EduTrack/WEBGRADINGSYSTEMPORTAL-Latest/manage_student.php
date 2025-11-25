<?php
// Start session and set cache control headers
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Check if user is logged in and is an admin
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?session_expired=1");
    exit();
}

// Database connection
$host = "sql210.infinityfree.com";
$user = "if0_40265243"; // change if needed
$pass = "rjL6bzbfrgcc"; // change if needed
$dbname = "if0_40265243_school_portal";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Create archive tables if they don't exist
function createArchiveTables($conn) {
    $tables = [
        "tbl_accounts_archive" => "
            CREATE TABLE IF NOT EXISTS tbl_accounts_archive (
                archive_id INT AUTO_INCREMENT PRIMARY KEY,
                original_account_id INT NOT NULL,
                username VARCHAR(50) NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin','teacher','student','parent') NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(100),
                contact_number VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_by INT,
                deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reason TEXT,
                INDEX idx_original_account_id (original_account_id),
                INDEX idx_deleted_at (deleted_at)
            )
        ",
        "tbl_students_archive" => "
            CREATE TABLE IF NOT EXISTS tbl_students_archive (
                archive_id INT AUTO_INCREMENT PRIMARY KEY,
                original_student_id INT NOT NULL,
                account_id INT NOT NULL,
                lrn VARCHAR(20),
                birth_date DATE,
                gender ENUM('Male','Female'),
                address TEXT,
                year_level_id INT,
                section_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_by INT,
                deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reason TEXT,
                INDEX idx_original_student_id (original_student_id),
                INDEX idx_account_id (account_id),
                INDEX idx_deleted_at (deleted_at)
            )
        ",
        "tbl_teachers_archive" => "
            CREATE TABLE IF NOT EXISTS tbl_teachers_archive (
                archive_id INT AUTO_INCREMENT PRIMARY KEY,
                original_teacher_id INT NOT NULL,
                account_id INT NOT NULL,
                username VARCHAR(50) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(100),
                contact_number VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_by INT,
                deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reason TEXT,
                INDEX idx_original_teacher_id (original_teacher_id),
                INDEX idx_account_id (account_id),
                INDEX idx_deleted_at (deleted_at)
            )
        ",
        "tbl_subjects_archive" => "
            CREATE TABLE IF NOT EXISTS tbl_subjects_archive (
                archive_id INT AUTO_INCREMENT PRIMARY KEY,
                original_subject_id INT NOT NULL,
                subject_name VARCHAR(100) NOT NULL,
                subject_code VARCHAR(20),
                year_level_id INT,
                semester VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_by INT,
                deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reason TEXT,
                INDEX idx_original_subject_id (original_subject_id),
                INDEX idx_deleted_at (deleted_at)
            )
        ",
        "tbl_grades_archive" => "
            CREATE TABLE IF NOT EXISTS tbl_grades_archive (
                archive_id INT AUTO_INCREMENT PRIMARY KEY,
                original_grade_id INT NOT NULL,
                student_id INT NOT NULL,
                subject_id INT NOT NULL,
                teacher_account_id INT,
                grade DECIMAL(5,2),
                grading_period VARCHAR(20),
                remarks TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_by INT,
                deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reason TEXT,
                INDEX idx_original_grade_id (original_grade_id),
                INDEX idx_student_id (student_id),
                INDEX idx_deleted_at (deleted_at)
            )
        ",
        "tbl_parents_archive" => "
            CREATE TABLE IF NOT EXISTS tbl_parents_archive (
                archive_id INT AUTO_INCREMENT PRIMARY KEY,
                original_parent_id INT NOT NULL,
                account_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_by INT,
                deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reason TEXT,
                INDEX idx_original_parent_id (original_parent_id),
                INDEX idx_account_id (account_id),
                INDEX idx_deleted_at (deleted_at)
            )
        "
    ];

    foreach ($tables as $tableName => $createSQL) {
        // Check if table exists
        $check = $conn->query("SHOW TABLES LIKE '$tableName'");
        if ($check->num_rows == 0) {
            if (!$conn->query($createSQL)) {
                error_log("Failed to create table $tableName: " . $conn->error);
            }
        }
    }
}

// Create archive tables
createArchiveTables($conn);

// --- Handle student archiving ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_student'])) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $reason = $conn->real_escape_string($_POST['reason'] ?? 'No reason provided');
    $deleted_by = $_SESSION['account_id'];

    // Get student data
    $student_query = $conn->prepare("
        SELECT s.*, a.* 
        FROM tbl_students s 
        JOIN tbl_accounts a ON s.account_id = a.account_id 
        WHERE s.student_id = ?
    ");
    $student_query->bind_param("i", $student_id);
    $student_query->execute();
    $student_data = $student_query->get_result()->fetch_assoc();

    if ($student_data) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Archive account
            $archive_account = $conn->prepare("
                INSERT INTO tbl_accounts_archive 
                (original_account_id, username, password, role, first_name, last_name, email, contact_number, created_at, deleted_by, reason)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $archive_account->bind_param(
                "issssssssis", 
                $student_data['account_id'], 
                $student_data['username'], 
                $student_data['password'], 
                $student_data['role'], 
                $student_data['first_name'], 
                $student_data['last_name'], 
                $student_data['email'], 
                $student_data['contact_number'], 
                $student_data['created_at'], 
                $deleted_by, 
                $reason
            );
            $archive_account->execute();
            
            // Archive student
            $archive_student = $conn->prepare("
                INSERT INTO tbl_students_archive 
                (original_student_id, account_id, lrn, birth_date, gender, address, year_level_id, section_id, created_at, deleted_by, reason)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $archive_student->bind_param(
                "iissssiisis", 
                $student_data['student_id'], 
                $student_data['account_id'], 
                $student_data['lrn'], 
                $student_data['birth_date'], 
                $student_data['gender'], 
                $student_data['address'], 
                $student_data['year_level_id'], 
                $student_data['section_id'], 
                $student_data['created_at'], 
                $deleted_by, 
                $reason
            );
            $archive_student->execute();
            
            // Remove parent links first
            $conn->query("DELETE FROM tbl_parent_student WHERE student_id = '$student_id'");
            
            // Remove subject enrollments
            $conn->query("DELETE FROM tbl_subject_enrollments WHERE student_id = '$student_id'");
            
            // Delete student record
            $conn->query("DELETE FROM tbl_students WHERE student_id = '$student_id'");
            
            // Delete account record
            $conn->query("DELETE FROM tbl_accounts WHERE account_id = '{$student_data['account_id']}'");
            
            // Commit transaction
            $conn->commit();
            
            header("Location: manage_student.php?success=5&student=" . urlencode($student_data['first_name'] . ' ' . $student_data['last_name']));
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_msg = "Error archiving student: " . $e->getMessage();
            error_log("Archive error: " . $e->getMessage());
        }
    } else {
        $error_msg = "Student not found!";
    }
}

// --- NEW: Handle student section update (if form submitted) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_student'])) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $new_section_id = (int)($_POST['section_id'] ?? 0);

    // Validate: check section belongs to some year level
    $check = $conn->prepare("SELECT year_level_id FROM tbl_sections WHERE section_id = ?");
    $check->bind_param("i", $new_section_id);
    $check->execute();
    $result = $check->get_result();
    $sec = $result->fetch_assoc();

    if (!$sec) {
        $msg = "Selected section not found.";
    } else {
        // update student's year_level_id and section_id to match the selected section's year level
        $update = $conn->prepare("UPDATE tbl_students s
                                 JOIN tbl_sections sec ON sec.section_id = ?
                                 SET s.section_id = ?, s.year_level_id = sec.year_level_id
                                 WHERE s.student_id = ?");
        $update->bind_param("iii", $new_section_id, $new_section_id, $student_id);
        if ($update->execute()) {
            $msg = "Student moved successfully.";
            // reload students
            header("Location: ".$_SERVER['PHP_SELF']."?view=sections&success=4");
            exit;
        } else {
            $msg = "Error moving student: " . $conn->error;
        }
    }
}



// Handle Update Student with password
if(isset($_POST['update_student'])){
    $student_id = $conn->real_escape_string($_POST['student_id']);
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $username = $conn->real_escape_string($_POST['username']);
    $lrn = $conn->real_escape_string($_POST['lrn']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $year_level_id = $conn->real_escape_string($_POST['year_level_id']);
    $section_id = $conn->real_escape_string($_POST['section_id']);
    $contact_number = $conn->real_escape_string($_POST['contact_number']);
    $parent_id = isset($_POST['parent_id']) ? $conn->real_escape_string($_POST['parent_id']) : null;

    // Check duplicate username for other accounts
    $check = $conn->prepare("SELECT * FROM tbl_accounts WHERE username=? AND account_id!=(SELECT account_id FROM tbl_students WHERE student_id=?)");
    $check->bind_param("si",$username,$student_id);
    $check->execute();
    $result = $check->get_result();

    if($result->num_rows > 0){
        $error_msg = "Username '$username' already exists!";
    } else {
        $acc = $conn->query("SELECT account_id FROM tbl_students WHERE student_id='$student_id'")->fetch_assoc();
        $account_id = $acc['account_id'];

        // Get current year level before update
        $current_year_info = $conn->query("SELECT year_level_id FROM tbl_students WHERE student_id='$student_id'")->fetch_assoc();
        $current_year_level_id = $current_year_info['year_level_id'];
        
        // Handle password update
        $password_update = "";
        if (!empty($_POST['password'])) {
            if ($_POST['password'] === $_POST['confirm_password']) {
                $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $password_update = ", password='$new_password'";
            } else {
                $error_msg = "Passwords do not match!";
                header("Location: manage_student.php?error=1");
                exit();
            }
        }
        
        $conn->query("UPDATE tbl_accounts SET username='$username', first_name='$first_name', last_name='$last_name', contact_number='$contact_number' $password_update WHERE account_id='$account_id'");
        $conn->query("UPDATE tbl_students SET lrn='$lrn', gender='$gender', year_level_id='$year_level_id', section_id='$section_id' WHERE student_id='$student_id'");
        
        // Update parent link
        if ($parent_id) {
            // Remove any existing parent link
            $conn->query("DELETE FROM tbl_parent_student WHERE student_id='$student_id'");
            // Add new parent link
            $conn->query("INSERT INTO tbl_parent_student (parent_id, student_id) VALUES ('$parent_id', '$student_id')");
        }
        
        // UPDATE SUBJECT ENROLLMENTS IF YEAR LEVEL CHANGED
        if ($current_year_level_id != $year_level_id) {
            // Remove existing subject enrollments
            $conn->query("DELETE FROM tbl_subject_enrollments WHERE student_id='$student_id'");
            
            // Add new subject enrollments for the new year level
            $subjects_query = "SELECT subject_id FROM tbl_subjects WHERE year_level_id = '$year_level_id'";
            $subjects_result = $conn->query($subjects_query);
            
            if ($subjects_result->num_rows > 0) {
                while ($subject = $subjects_result->fetch_assoc()) {
                    $subject_id = $subject['subject_id'];
                    $conn->query("INSERT INTO tbl_subject_enrollments (student_id, subject_id) 
                                  VALUES ('$student_id', '$subject_id')");
                }
            }
        }
        
        header("Location: manage_student.php?success=2");
        exit();
    }
}

// Handle Delete Student
if(isset($_POST['delete_student'])){
    $student_id = $conn->real_escape_string($_POST['student_id']);
    $acc = $conn->query("SELECT account_id FROM tbl_students WHERE student_id='$student_id'")->fetch_assoc();
    $account_id = $acc['account_id'];

    // Remove parent links first
    $conn->query("DELETE FROM tbl_parent_student WHERE student_id='$student_id'");
    
    // Remove subject enrollments
    $conn->query("DELETE FROM tbl_subject_enrollments WHERE student_id='$student_id'");
    
    $conn->query("DELETE FROM tbl_students WHERE student_id='$student_id'");
    $conn->query("DELETE FROM tbl_accounts WHERE account_id='$account_id'");
    header("Location: manage_student.php?success=3");
    exit();
}

// Show success messages if redirected
if(isset($_GET['success'])){
    if($_GET['success']==2) $success_msg = "Student updated successfully!";
    if($_GET['success']==3) $success_msg = "Student deleted successfully!";
    if($_GET['success']==4) $success_msg = "Student moved successfully!";
    if($_GET['success']==5) $success_msg = "Student archived successfully!";
}

// Determine which view to show
$current_view = isset($_GET['view']) && $_GET['view'] === 'sections' ? 'sections' : 'list';

// Update the students query to include password
$students = $conn->query("SELECT s.student_id, a.username, a.password, a.first_name, a.last_name, s.lrn, s.gender, yl.level_name, yl.year_level_id, sec.section_name, sec.section_id, a.contact_number
                          FROM tbl_students s
                          JOIN tbl_accounts a ON s.account_id = a.account_id
                          LEFT JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id
                          LEFT JOIN tbl_sections sec ON s.section_id = sec.section_id
                          ORDER BY yl.year_level_id, a.last_name, a.first_name");

// Fetch dropdowns
$year_levels = $conn->query("SELECT * FROM tbl_yearlevels");
$sections = $conn->query("SELECT s.*, yl.level_name 
                          FROM tbl_sections s 
                          JOIN tbl_yearlevels yl ON s.year_level_id = yl.year_level_id 
                          ORDER BY yl.year_level_id, s.section_name");

// Group sections by year level
$sections_by_year = [];
if ($sections->num_rows > 0) {
    while ($section = $sections->fetch_assoc()) {
        $year_level_id = $section['year_level_id'];
        if (!isset($sections_by_year[$year_level_id])) {
            $sections_by_year[$year_level_id] = [
                'level_name' => $section['level_name'],
                'sections' => []
            ];
        }
        $sections_by_year[$year_level_id]['sections'][] = $section;
    }
}

// Fetch parents for linking
$parents = $conn->query("SELECT p.parent_id, a.account_id, a.first_name, a.last_name, a.username 
                         FROM tbl_parents p 
                         JOIN tbl_accounts a ON p.account_id = a.account_id");

// --- NEW: Data for section management view ---
// fetch year levels (ordered by year_level_id so Grade 1, Grade 2, ...)
$yearlevels_result = $conn->query("SELECT year_level_id, level_name, education_stage FROM tbl_yearlevels ORDER BY year_level_id ASC");
$yearlevels = [];
while ($row = $yearlevels_result->fetch_assoc()) {
    $yearlevels[] = $row;
}

// fetch sections grouped by year_level_id, ordered by section_name alphabetically
$sectionsRaw_result = $conn->query("SELECT section_id, year_level_id, section_name, IFNULL(strand_id,'') AS strand_id FROM tbl_sections ORDER BY year_level_id ASC, section_name ASC");
$sectionsRaw = [];
while ($row = $sectionsRaw_result->fetch_assoc()) {
    $sectionsRaw[] = $row;
}

// reorganize sections by year_level_id for easy lookup
$sectionsByYear = [];
foreach ($sectionsRaw as $s) {
    $sectionsByYear[(int)$s['year_level_id']][] = $s;
}

// fetch students for section view (simple list; you can join to accounts for names)
$students_section_view = $conn->query("
    SELECT s.student_id, s.account_id, s.lrn, a.first_name, a.last_name, s.year_level_id, s.section_id
    FROM tbl_students s
    LEFT JOIN tbl_accounts a ON s.account_id = a.account_id
    ORDER BY s.year_level_id ASC, a.last_name ASC, a.first_name ASC
");
$students_section = [];
while ($row = $students_section_view->fetch_assoc()) {
    $students_section[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>EduTrack - Manage Students</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
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
        
        .btn-success {
            background-color: var(--accent);
            border-color: var(--accent);
        }
        
        .btn-success:hover {
            background-color: #16a085;
            border-color: #16a085;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            border-color: #e74c3c;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
            border-color: #c0392b;
        }
        
        .btn-warning {
            background-color: #f39c12;
            border-color: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
            border-color: #e67e22;
            color: white;
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
        
        .table th {
            background-color: var(--light);
            font-weight: 600;
        }
        
           .table thead th {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 15px 12px;
            font-weight: 600;
        }
        
        .modal-header {
            background-color: var(--primary);
            color: white;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        /* Action Buttons Container */
        .actions-container {
            background-color: white;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        /* Delete Warning Modal Custom Styling */
        .modal-header.warning-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--dark) 100%);
            color: white;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .warning-icon {
            color: #f39c12;
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .warning-content {
            text-align: center;
            padding: 20px 0;
        }

        .warning-text {
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .warning-subtext {
            color: #e74c3c;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Enhanced notification styles */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 350px;
        }
        
        .notification {
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 8px;
            margin-bottom: 15px;
            transform: translateX(100%);
            opacity: 0;
            transition: transform 0.4s ease-out, opacity 0.4s ease-out;
        }
        
        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .notification.hide {
            transform: translateX(100%);
            opacity: 0;
        }
        
        .notification-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            background-color: rgba(255,255,255,0.7);
            width: 100%;
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 5s linear;
        }
        
        .notification-progress.running {
            transform: scaleX(1);
        }

        /* Select2 customization */
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
            padding: 5px;
        }
        
        /* Tab navigation */
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--dark);
            font-weight: 500;
            padding: 0.75rem 1.5rem;
        }
        
        .nav-tabs .nav-link.active {
            border-bottom: 3px solid var(--accent);
            color: var(--accent);
            background: transparent;
        }
        
        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: var(--accent);
        }
        
        /* Section view styling */
        .year-block {
            margin-bottom: 2rem;
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        
        .year-title {
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--primary);
            font-size: 1.25rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--light);
        }
        
        .section-list {
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: var(--light);
            border-radius: 6px;
        }
        
        .section-list span {
            display: inline-block;
            background: white;
            padding: 0.25rem 0.75rem;
            margin: 0.25rem;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        .move-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .move-form select {
            flex: 1;
            min-width: 180px;
        }
        
        /* Archive specific styling */
        .archive-icon {
            color: #f39c12;
        }
        
        .archive-header {
            background: linear-gradient(135deg, var(--primary) 0%, #f39c12 100%);
            color: white;
        }

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

        /* Search Box Styling */
        .search-container {
            background-color: white;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box .form-control {
            padding-left: 40px;
            border-radius: 25px;
        }
        
        .search-box .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 5;
        }
        
        .search-results-info {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .no-results {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .highlight {
            background-color: #fff3cd;
            font-weight: 600;
        }

        /* Add to the existing CSS */
        .password-field {
            font-family: monospace;
            font-size: 0.8rem;
        }

        .input-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .input-group-sm {
            width: 100%;
        }

        .student-password .input-group {
            min-width: 200px;
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
                    <p class="mb-0">Admin Portal</p>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>

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
                          <li><a href="careerpath.php">Career Result</a></li>
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

            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ml-sm-auto px-4">
                <div class="header">
                    <h3>Manage Students</h3>
                    <div class="user-info">
                        <div class="avatar"><?= substr($_SESSION['fullname'], 0, 1) ?></div>
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
                            <div class="text-muted">Administrator</div>
                        </div>
                    </div>
                </div>

                <div class="main-content">
                    <!-- Welcome Banner -->
                    <div class="welcome-section">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1>Student Management</h1>
                                <p>Manage student accounts, view details, assign sections, and archive accounts when necessary</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-user-graduate fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Container -->
                    <div class="notification-container" id="notificationContainer">
                        <!-- Notifications will be dynamically inserted here -->
                    </div>

                    <!-- Success/Error Messages (Kept for backward compatibility) -->
                    <?php if(isset($success_msg)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> <?= $success_msg ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(isset($error_msg)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= $error_msg ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(isset($msg)): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="fas fa-info-circle me-2"></i> <?= $msg ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs mb-4" id="studentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $current_view === 'list' ? 'active' : '' ?>" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-view" type="button" role="tab">
                                <i class="fas fa-list me-1"></i> Student List
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $current_view === 'sections' ? 'active' : '' ?>" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sections-view" type="button" role="tab">
                                <i class="fas fa-exchange-alt me-1"></i> Manage Sections
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="studentTabsContent">
                        <!-- List View Tab -->
                        <div class="tab-pane fade <?= $current_view === 'list' ? 'show active' : '' ?>" id="list-view" role="tabpanel">
                            <!-- Action Buttons Container -->
                            <div class="actions-container">
                                <h4 class="mb-0">Student List</h4>
                                <div class="action-buttons">
                                    <button class="btn btn-primary" id="editSelectedBtn" style="display: none;" data-bs-toggle="modal" data-bs-target="#editStudentModal">
                                        <i class="fas fa-edit me-1"></i> Edit Student
                                    </button>
                                    <button class="btn btn-warning" id="archiveSelectedBtn" style="display: none;" data-bs-toggle="modal" data-bs-target="#archiveStudentModal">
                                        <i class="fas fa-archive me-1"></i> Archive Student
                                    </button>
                                    <button class="btn btn-danger" id="deleteSelectedBtn" style="display: none;" data-bs-toggle="modal" data-bs-target="#deleteStudentModal">
                                        <i class="fas fa-trash me-1"></i> Delete Student
                                    </button>
                                </div>
                            </div>

                            <!-- Search Box -->
                            <div class="search-container">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="search-box">
                                            <i class="fas fa-search search-icon"></i>
                                            <input type="text" id="searchInput" class="form-control" placeholder="Search students...">
                                        </div>
                                        <div class="search-results-info" id="searchResultsInfo">
                                            Showing all students
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex gap-2">
                                            <select id="yearLevelFilter" class="form-select">
                                                <option value="">All Year Levels</option>
                                                <?php 
                                                $year_levels->data_seek(0); 
                                                while($yl = $year_levels->fetch_assoc()): ?>
                                                    <option value="<?= $yl['year_level_id'] ?>"><?= htmlspecialchars($yl['level_name']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                            <button id="clearFilters" class="btn btn-outline-secondary">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Students Table -->
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="studentsTable">
                                            <thead>
                                                <tr>
                                                    <th>
                                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                                    </th>
                                                    <th>#</th>
                                                    <th>Full Name</th>
                                                    <th>LRN</th>
                                                    <th>Gender</th>
                                                    <th>Year Level</th>
                                                    <th>Section</th>
                                                    <th>Contact</th>
                                                    <th>Password</th>
                                                    <th>Parent</th>
                                                </tr>
                                            </thead>
                                          <tbody id="studentsTableBody">
                                                <?php if($students->num_rows > 0): 
                                                    $count = 1; 
                                                    while($row = $students->fetch_assoc()): 
                                                    // Get parent info for this student
                                                    $student_id = $row['student_id'];
                                                    $parent_info = $conn->query("
                                                        SELECT a.first_name, a.last_name 
                                                        FROM tbl_parent_student ps 
                                                        JOIN tbl_parents p ON ps.parent_id = p.parent_id 
                                                        JOIN tbl_accounts a ON p.account_id = a.account_id 
                                                        WHERE ps.student_id = '$student_id'
                                                    ");
                                                    $parent_name = $parent_info->num_rows > 0 ? $parent_info->fetch_assoc() : null;
                                                    ?>
                                                    <tr class="student-row" 
                                                        data-name="<?= htmlspecialchars(strtolower($row['first_name'] . ' ' . $row['last_name'])) ?>"
                                                        data-lrn="<?= htmlspecialchars(strtolower($row['lrn'])) ?>"
                                                        data-year-level="<?= htmlspecialchars(strtolower($row['level_name'])) ?>"
                                                        data-section="<?= htmlspecialchars(strtolower($row['section_name'])) ?>"
                                                        data-contact="<?= htmlspecialchars(strtolower($row['contact_number'])) ?>"
                                                        data-year-level-id="<?= $row['year_level_id'] ?>">
                                                        <td>
                                                            <input type="checkbox" class="form-check-input student-checkbox" 
                                                                   data-student='<?= json_encode($row) ?>' 
                                                                   value="<?= $row['student_id'] ?>">
                                                        </td>
                                                        <td><?= $count++; ?></td>
                                                        <td class="student-name"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                                        <td class="student-lrn"><?= htmlspecialchars($row['lrn']) ?></td>
                                                        <td class="student-gender"><?= htmlspecialchars($row['gender']) ?></td>
                                                        <td class="student-year-level"><?= htmlspecialchars($row['level_name']) ?></td>
                                                        <td class="student-section"><?= htmlspecialchars($row['section_name']) ?></td>
                                                        <td class="student-contact"><?= htmlspecialchars($row['contact_number']) ?></td>
                                                        <td class="student-password">
                                                            <div class="input-group input-group-sm">
                                                                <input type="password" class="form-control form-control-sm password-field" 
                                                                       value="<?= htmlspecialchars($row['password']) ?>" readonly 
                                                                       id="password-<?= $row['student_id'] ?>">
                                                                <button type="button" class="btn btn-outline-secondary btn-sm toggle-password" 
                                                                        data-target="password-<?= $row['student_id'] ?>">
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-primary btn-sm edit-password" 
                                                                        data-student-id="<?= $row['student_id'] ?>"
                                                                        data-current-password="<?= htmlspecialchars($row['password']) ?>">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                        <td class="student-parent"><?= $parent_name ? htmlspecialchars($parent_name['first_name'] . ' ' . $parent_name['last_name']) : 'Not linked' ?></td>
                                                    </tr>
                                                <?php endwhile; else: ?>
                                                    <tr>
                                                        <td colspan="10" class="text-center py-4">
                                                            <i class="fas fa-user-graduate fa-2x text-muted mb-2"></i>
                                                            <p class="text-muted">No students found.</p>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sections View Tab -->
                        <div class="tab-pane fade <?= $current_view === 'sections' ? 'show active' : '' ?>" id="sections-view" role="tabpanel">
                            <div class="actions-container">
                                <h4 class="mb-0">Manage Student Sections</h4>
                                <div>
                                    <a href="manage_sections.php" class="btn btn-outline-primary">
                                        <i class="fas fa-cog me-1"></i> Manage Sections
                                    </a>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <?php foreach ($yearlevels as $yl): ?>
                                        <div class="year-block">
                                            <div class="year-title"><?= htmlspecialchars($yl['level_name']) ?> (<?= htmlspecialchars($yl['education_stage']) ?>)</div>

                                            <div class="section-list">
                                                <strong>Sections:</strong>
                                                <?php
                                                    $yid = (int)$yl['year_level_id'];
                                                    if (!empty($sectionsByYear[$yid])):
                                                        foreach ($sectionsByYear[$yid] as $sec):
                                                ?>
                                                    <span>[<?= htmlspecialchars($sec['section_name']) ?>]</span>
                                                <?php
                                                        endforeach;
                                                    else:
                                                ?>
                                                    <em>No sections</em>
                                                <?php endif; ?>
                                            </div>

                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>Student</th>
                                                        <th>LRN</th>
                                                        <th>Current Section</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($students_section as $st): 
                                                    if ((int)$st['year_level_id'] !== (int)$yl['year_level_id']) continue;
                                                    $currentSecName = '';
                                                    if ($st['section_id']) {
                                                        foreach ($sectionsByYear[$yid] ?? [] as $ss) {
                                                            if ((int)$ss['section_id'] === (int)$st['section_id']) {
                                                                $currentSecName = $ss['section_name'];
                                                                break;
                                                            }
                                                        }
                                                    }
                                                ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></td>
                                                        <td><?= htmlspecialchars($st['lrn']) ?></td>
                                                        <td><?= htmlspecialchars($currentSecName ?: '—') ?></td>
                                                        <td>
                                                            <form method="post" class="move-form">
                                                                <input type="hidden" name="student_id" value="<?= (int)$st['student_id'] ?>">
                                                                <select name="section_id" class="form-select form-select-sm" required>
                                                                    <option value="">Select section</option>
                                                                    <?php foreach ($sectionsByYear[$yid] ?? [] as $opt): ?>
                                                                        <option value="<?= (int)$opt['section_id'] ?>" <?= ((int)$st['section_id'] === (int)$opt['section_id']) ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($opt['section_name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <button type="submit" name="move_student" class="btn btn-sm btn-primary">Move</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    

    <!-- Edit Student Modal -->
    <!-- Edit Student Modal with Password Fields -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="student_id" id="edit_student_id">
                    <input type="hidden" name="current_password" id="edit_current_password">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">LRN</label>
                            <input type="text" name="lrn" id="edit_lrn" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" id="edit_gender" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">New Password (Leave blank to keep current)</label>
                            <div class="input-group">
                                <input type="password" name="password" id="edit_password" class="form-control" placeholder="Leave blank to keep current password">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('edit_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="generatePassword('edit_password')">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="edit_confirm_password" class="form-control">
                            <div class="invalid-feedback" id="edit_password_error">Passwords do not match</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" id="edit_current_password_display" class="form-control" readonly>
                                <button type="button" class="btn btn-outline-info" onclick="togglePassword('edit_current_password_display')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Hashed password from database</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Year Level</label>
                            <select name="year_level_id" id="edit_year_level_id" class="form-select" required>
                                <option value="">Select Year Level</option>
                                <?php 
                                $year_levels->data_seek(0); 
                                while($yl = $year_levels->fetch_assoc()): ?>
                                    <option value="<?= $yl['year_level_id'] ?>"><?= htmlspecialchars($yl['level_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Section</label>
                            <select name="section_id" id="edit_section_id" class="form-select" required>
                                <option value="">Select Section</option>
                                <?php foreach($sections_by_year as $year_level_id => $year_data): ?>
                                    <optgroup label="<?= htmlspecialchars($year_data['level_name']) ?>">
                                        <?php foreach($year_data['sections'] as $section): ?>
                                            <option value="<?= $section['section_id'] ?>" data-year-level="<?= $year_level_id ?>">
                                                <?= htmlspecialchars($section['section_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_number" id="edit_contact_number" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Link to Parent (Optional)</label>
                            <select name="parent_id" id="parentSelectEdit" class="form-select">
                                <option value="">Select a parent</option>
                                <?php 
                                $parents->data_seek(0);
                                if($parents->num_rows > 0): 
                                    while($parent = $parents->fetch_assoc()): ?>
                                    <option value="<?= $parent['parent_id'] ?>">
                                        <?= htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name'] . ' (' . $parent['username'] . ')') ?>
                                    </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_student" class="btn btn-primary">Update Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Archive Student Modal -->
    <div class="modal fade" id="archiveStudentModal" tabindex="-1" aria-labelledby="archiveStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header archive-header">
                    <h5 class="modal-title"><i class="fas fa-archive me-2"></i> Archive Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="student_id" id="archive_student_id">
                        <div class="warning-content">
                            <div class="warning-icon">
                                <i class="fas fa-archive archive-icon"></i>
                            </div>
                            <div class="warning-text">
                                Are you sure you want to archive <strong id="archive_student_name"></strong>?
                            </div>
                            <div class="mb-3">
                                <label for="archive_reason" class="form-label">Reason for archiving (optional):</label>
                                <textarea class="form-control" id="archive_reason" name="reason" rows="3" placeholder="Enter reason for archiving this student"></textarea>
                            </div>
                            <div class="warning-subtext">
                                <i class="fas fa-info-circle me-1"></i>
                                Archived students will be moved to the archive manager and can be restored if needed.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                        <button type="submit" name="archive_student" class="btn btn-warning">
                            <i class="fas fa-archive me-1"></i> Archive Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Student Modal -->
    <div class="modal fade" id="deleteStudentModal" tabindex="-1" aria-labelledby="deleteStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header warning-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="student_id" id="delete_student_id">
                        <div class="warning-content">
                            <div class="warning-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="warning-text">
                                Are you sure you want to delete <strong id="delete_student_name"></strong>?
                            </div>
                            <div class="warning-subtext">
                                <i class="fas fa-info-circle me-1"></i>
                                This action cannot be undone and will permanently remove all student data.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                        <button type="submit" name="delete_student" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Delete Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
// Initialize Select2 for parent dropdowns
$(document).ready(function() {
    $('#parentSelectEdit').select2({
        theme: 'bootstrap-5',
        placeholder: "Search for a parent...",
        allowClear: true
    });

    // Filter sections based on selected year level in Edit modal
    $('#edit_year_level_id').change(function() {
        var yearLevelId = $(this).val();
        $('#edit_section_id option').show();
        if (yearLevelId) {
            $('#edit_section_id option').not('[data-year-level="' + yearLevelId + '"]').hide();
            $('#edit_section_id option[data-year-level="' + yearLevelId + '"]').show();
            $('#edit_section_id').val('').trigger('change');
        }
    });
    
    // Initialize tab functionality
    $('#studentTabs button').on('click', function (e) {
        e.preventDefault();
        $(this).tab('show');
        
        // Update URL with view parameter without reloading page
        const view = $(this).attr('id') === 'sections-tab' ? 'sections' : 'list';
        const url = new URL(window.location);
        url.searchParams.set('view', view);
        window.history.replaceState({}, '', url);
    });

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const yearLevelFilter = document.getElementById('yearLevelFilter');
    const clearFilters = document.getElementById('clearFilters');
    const studentsTableBody = document.getElementById('studentsTableBody');
    const searchResultsInfo = document.getElementById('searchResultsInfo');
    const studentRows = document.querySelectorAll('.student-row');

    function performSearch() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const yearLevelValue = yearLevelFilter.value;
        
        let visibleCount = 0;
        let hasResults = false;

        studentRows.forEach(row => {
            const name = row.getAttribute('data-name');
            const lrn = row.getAttribute('data-lrn');
            const yearLevel = row.getAttribute('data-year-level');
            const section = row.getAttribute('data-section');
            const contact = row.getAttribute('data-contact');
            const yearLevelId = row.getAttribute('data-year-level-id');
            
            const matchesSearch = !searchTerm || 
                name.includes(searchTerm) || 
                lrn.includes(searchTerm) ||
                yearLevel.includes(searchTerm) ||
                section.includes(searchTerm) ||
                contact.includes(searchTerm);
            
            const matchesYearLevel = !yearLevelValue || yearLevelId === yearLevelValue;
            
            if (matchesSearch && matchesYearLevel) {
                row.style.display = '';
                visibleCount++;
                hasResults = true;
                
                // Highlight matching text
                if (searchTerm) {
                    highlightText(row, searchTerm);
                } else {
                    removeHighlights(row);
                }
            } else {
                row.style.display = 'none';
                removeHighlights(row);
            }
        });

        // Update results info
        if (!hasResults) {
            searchResultsInfo.textContent = 'No students found matching your search criteria';
        } else if (searchTerm || yearLevelValue) {
            searchResultsInfo.textContent = `Showing ${visibleCount} student(s)`;
        } else {
            searchResultsInfo.textContent = 'Showing all students';
        }
    }

    function highlightText(row, searchTerm) {
        const textCells = row.querySelectorAll('.student-name, .student-lrn, .student-year-level, .student-section, .student-contact');
        
        textCells.forEach(cell => {
            const originalText = cell.textContent;
            const lowerOriginal = originalText.toLowerCase();
            const lowerSearch = searchTerm.toLowerCase();
            
            if (lowerOriginal.includes(lowerSearch)) {
                const startIndex = lowerOriginal.indexOf(lowerSearch);
                const endIndex = startIndex + searchTerm.length;
                
                const before = originalText.substring(0, startIndex);
                const match = originalText.substring(startIndex, endIndex);
                const after = originalText.substring(endIndex);
                
                cell.innerHTML = `${before}<span class="highlight">${match}</span>${after}`;
            }
        });
    }

    function removeHighlights(row) {
        const textCells = row.querySelectorAll('.student-name, .student-lrn, .student-year-level, .student-section, .student-contact');
        
        textCells.forEach(cell => {
            cell.innerHTML = cell.textContent;
        });
    }

    // Event listeners for search and filters
    searchInput.addEventListener('input', performSearch);
    yearLevelFilter.addEventListener('change', performSearch);
    
    clearFilters.addEventListener('click', function() {
        searchInput.value = '';
        yearLevelFilter.value = '';
        performSearch();
    });

    // Initialize search on page load
    performSearch();
});

// Password management functions
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function generatePassword(fieldId) {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    
    const field = document.getElementById(fieldId);
    if (field) {
        field.value = password;
        
        // Also update confirm password field if it exists
        const confirmFieldId = fieldId.replace('password', 'confirm_password');
        const confirmField = document.getElementById(confirmFieldId);
        if (confirmField) {
            confirmField.value = password;
        }
    }
}

// Add event listeners for inline password toggling
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility in table
    document.addEventListener('click', function(e) {
        if (e.target.closest('.toggle-password')) {
            const button = e.target.closest('.toggle-password');
            const targetId = button.getAttribute('data-target');
            const field = document.getElementById(targetId);
            
            if (field) {
                const icon = button.querySelector('i');
                if (field.type === 'password') {
                    field.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    field.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        }
        
        // Quick password edit
        if (e.target.closest('.edit-password')) {
            const button = e.target.closest('.edit-password');
            const studentId = button.getAttribute('data-student-id');
            
            // Find the student in the table
            const studentCheckbox = document.querySelector(`.student-checkbox[value="${studentId}"]`);
            if (studentCheckbox) {
                const studentData = JSON.parse(studentCheckbox.getAttribute('data-student'));
                selectedStudent = studentData;
                
                // Open edit modal
                const editModal = new bootstrap.Modal(document.getElementById('editStudentModal'));
                editModal.show();
            }
        }
    });
});

// Update the edit modal show event to include password
var editModal = document.getElementById('editStudentModal');
editModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var student = null;
    
    // Check if triggered by direct button click (original functionality)
    if (button && button.getAttribute('data-student')) {
        student = JSON.parse(button.getAttribute('data-student'));
    }
    // Or if triggered by checkbox selection
    else if (selectedStudent) {
        student = selectedStudent;
    }
    
    if (student) {
        document.getElementById('edit_student_id').value = student.student_id;
        document.getElementById('edit_first_name').value = student.first_name;
        document.getElementById('edit_last_name').value = student.last_name;
        document.getElementById('edit_username').value = student.username;
        document.getElementById('edit_lrn').value = student.lrn;
        document.getElementById('edit_gender').value = student.gender;
        document.getElementById('edit_year_level_id').value = student.year_level_id;
        document.getElementById('edit_contact_number').value = student.contact_number;
        document.getElementById('edit_current_password').value = student.password;
        document.getElementById('edit_current_password_display').value = student.password;
        
        // Clear password fields
        document.getElementById('edit_password').value = '';
        document.getElementById('edit_confirm_password').value = '';
        
        // Trigger the change event to filter sections
        $('#edit_year_level_id').trigger('change');
        
        // Set the section value after a small delay to ensure filtering is done
        setTimeout(function() {
            document.getElementById('edit_section_id').value = student.section_id;
        }, 100);
        
        // Fetch current parent for this student
        fetch('get_student_parent.php?student_id=' + student.student_id)
            .then(response => response.json())
            .then(data => {
                if (data.parent_id) {
                    $('#parentSelectEdit').val(data.parent_id).trigger('change');
                } else {
                    $('#parentSelectEdit').val('').trigger('change');
                }
            })
            .catch(error => console.error('Error fetching parent data:', error));
    }
});

// Notification system
function showNotification(message, type = 'success') {
    const notificationContainer = document.getElementById('notificationContainer');
    const notificationId = 'notification-' + Date.now();
    
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    
    const notification = document.createElement('div');
    notification.id = notificationId;
    notification.className = `notification alert ${alertClass} alert-dismissible fade show`;
    notification.innerHTML = `
        <i class="fas ${icon} me-2"></i> ${message}
        <button type="button" class="btn-close" onclick="dismissNotification('${notificationId}')"></button>
        <div class="notification-progress"></div>
    `;
    
    notificationContainer.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
        notification.classList.add('show');
        
        // Start progress bar
        setTimeout(() => {
            const progressBar = notification.querySelector('.notification-progress');
            progressBar.classList.add('running');
        }, 50);
    }, 10);
    
    // Auto-dismiss after 5 seconds
    const autoDismissTimer = setTimeout(() => {
        dismissNotification(notificationId);
    }, 5000);
    
    // Store timer reference for potential cleanup
    notification.autoDismissTimer = autoDismissTimer;
    
    // Pause dismissal on hover
    notification.addEventListener('mouseenter', () => {
        clearTimeout(autoDismissTimer);
        const progressBar = notification.querySelector('.notification-progress');
        progressBar.classList.remove('running');
        progressBar.style.width = progressBar.offsetWidth + 'px';
    });
    
    // Resume dismissal when mouse leaves
    notification.addEventListener('mouseleave', () => {
        const progressBar = notification.querySelector('.notification-progress');
        progressBar.classList.add('running');
        
        notification.autoDismissTimer = setTimeout(() => {
            dismissNotification(notificationId);
        }, 5000);
    });
}

function dismissNotification(notificationId) {
    const notification = document.getElementById(notificationId);
    if (notification) {
        // Clear any active timer
        if (notification.autoDismissTimer) {
            clearTimeout(notification.autoDismissTimer);
        }
        
        // Start hide animation
        notification.classList.remove('show');
        notification.classList.add('hide');
        
        // Remove from DOM after animation completes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 400);
    }
}

// Check for success messages from PHP and show notifications
document.addEventListener('DOMContentLoaded', function() {
    <?php if(isset($_GET['success'])): ?>
        <?php if($_GET['success'] == 2): ?>
            showNotification('Student updated successfully!');
        <?php elseif($_GET['success'] == 3): ?>
            showNotification('Student deleted successfully!');
        <?php elseif($_GET['success'] == 4): ?>
            showNotification('Student moved successfully!');
        <?php elseif($_GET['success'] == 5): ?>
            showNotification('Student archived successfully!');
        <?php endif; ?>
        
        // Clean URL without reloading
        const url = new URL(window.location);
        url.searchParams.delete('success');
        window.history.replaceState({}, '', url);
    <?php endif; ?>
    
    <?php if(isset($error_msg)): ?>
        showNotification('<?= addslashes($error_msg) ?>', 'error');
    <?php endif; ?>
});

let selectedStudent = null;

// Handle checkbox selection
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    const editBtn = document.getElementById('editSelectedBtn');
    const archiveBtn = document.getElementById('archiveSelectedBtn');
    const deleteBtn = document.getElementById('deleteSelectedBtn');

    // Select all functionality
    selectAll.addEventListener('change', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateActionButtons();
    });

    // Individual checkbox handling
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
            selectAll.checked = checkedBoxes.length === checkboxes.length;
            selectAll.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
            updateActionButtons();
        });
    });

    function updateActionButtons() {
        const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
        if (checkedBoxes.length === 1) {
            selectedStudent = JSON.parse(checkedBoxes[0].getAttribute('data-student'));
            editBtn.style.display = 'inline-block';
            archiveBtn.style.display = 'inline-block';
            deleteBtn.style.display = 'inline-block';
        } else if (checkedBoxes.length > 1) {
            editBtn.style.display = 'none';
            archiveBtn.style.display = 'inline-block';
            deleteBtn.style.display = 'inline-block';
        } else {
            editBtn.style.display = 'none';
            archiveBtn.style.display = 'none';
            deleteBtn.style.display = 'none';
            selectedStudent = null;
        }
    }
});

// Pre-fill Archive Modal
var archiveModal = document.getElementById('archiveStudentModal');
archiveModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var student = null;
    
    // Check if triggered by direct button click
    if (button && button.getAttribute('data-student')) {
        student = JSON.parse(button.getAttribute('data-student'));
        document.getElementById('archive_student_id').value = student.student_id;
        document.getElementById('archive_student_name').textContent = student.first_name + ' ' + student.last_name;
    }
    // Or if triggered by checkbox selection
    else {
        const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
        if (checkedBoxes.length === 1 && selectedStudent) {
            document.getElementById('archive_student_id').value = selectedStudent.student_id;
            document.getElementById('archive_student_name').textContent = selectedStudent.first_name + ' ' + selectedStudent.last_name;
        } else if (checkedBoxes.length > 1) {
            // Handle multiple archives
            document.getElementById('archive_student_name').textContent = checkedBoxes.length + ' students';
        }
    }
});

// Pre-fill Delete Modal - Handle both checkbox selection and direct button click
var deleteModal = document.getElementById('deleteStudentModal');
deleteModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var student = null;
    
    // Check if triggered by direct button click (original functionality)
    if (button && button.getAttribute('data-student')) {
        student = JSON.parse(button.getAttribute('data-student'));
        document.getElementById('delete_student_id').value = student.student_id;
        document.getElementById('delete_student_name').textContent = student.first_name + ' ' + student.last_name;
    }
    // Or if triggered by checkbox selection
    else {
        const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
        if (checkedBoxes.length === 1 && selectedStudent) {
            document.getElementById('delete_student_id').value = selectedStudent.student_id;
            document.getElementById('delete_student_name').textContent = selectedStudent.first_name + ' ' + selectedStudent.last_name;
        } else if (checkedBoxes.length > 1) {
            // Handle multiple deletions (you may need to modify PHP to handle this)
            document.getElementById('delete_student_name').textContent = checkedBoxes.length + ' students';
        }
    }
});

// Enhanced logout confirmation
function confirmLogout() {
    if (confirm('Are you sure you want to logout?')) {
        localStorage.clear();
        sessionStorage.clear();
        return true;
    }
    return false;
}

// Set up logout link handler
const logoutLink = document.querySelector('a[href="logout.php"]');
if (logoutLink) {
    logoutLink.addEventListener('click', function(e) {
        if (!confirmLogout()) {
            e.preventDefault();
        }
    });
}

// toggle submenu
document.querySelectorAll(".has-submenu > a").forEach(link => {
  link.addEventListener("click", e => {
    e.preventDefault();
    link.parentElement.classList.toggle("open");
  });
});
</script>
</body>
</html>
<?php $conn->close(); ?>