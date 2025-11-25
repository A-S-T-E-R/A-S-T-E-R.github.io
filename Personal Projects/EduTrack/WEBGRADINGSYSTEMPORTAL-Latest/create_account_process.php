<?php
session_start();
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?session_expired=1");
    exit();
}

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "school_portal";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = $_POST['role'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $username = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $contact_number = $_POST['contact_number'] ?? '';
    
    // Check if username already exists
    $checkStmt = $conn->prepare("SELECT account_id FROM tbl_accounts WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows > 0) {
        header("Location: create_account.php?error=Username already exists");
        exit();
    }
    $checkStmt->close();
    
    // For students, check if LRN already exists
    if ($role === 'student') {
        $lrn = $_POST['lrn'] ?? '';
        
        if (!empty($lrn)) {
            $lrnCheckStmt = $conn->prepare("SELECT student_id FROM tbl_students WHERE lrn = ?");
            $lrnCheckStmt->bind_param("s", $lrn);
            $lrnCheckStmt->execute();
            $lrnCheckStmt->store_result();
            
            if ($lrnCheckStmt->num_rows > 0) {
                header("Location: create_account.php?error=LRN already exists in the system");
                exit();
            }
            $lrnCheckStmt->close();
        }
    }
    
    // Insert into accounts table
    $stmt = $conn->prepare("INSERT INTO tbl_accounts (username, password, role, first_name, last_name, contact_number, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssss", $username, $password, $role, $first_name, $last_name, $contact_number);
    
    if ($stmt->execute()) {
        $account_id = $stmt->insert_id;
        
        // Handle role-specific data
        if ($role === 'student') {
            $lrn = $_POST['lrn'] ?? '';
            $birth_date = $_POST['birth_date'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $year_level_id = $_POST['year_level_id'] ?? null;
            $section_id = $_POST['section_id'] ?? null;
            $parent_account_id = $_POST['parent_id'] ?? null;
            
            // Insert into students table
            $studentStmt = $conn->prepare("INSERT INTO tbl_students (account_id, lrn, birth_date, gender, year_level_id, section_id, created_at) 
                                         VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $studentStmt->bind_param("isssii", $account_id, $lrn, $birth_date, $gender, $year_level_id, $section_id);
            
            if ($studentStmt->execute()) {
                $student_id = $studentStmt->insert_id;
                
                // AUTO-ENROLL STUDENT IN SUBJECTS FOR THEIR YEAR LEVEL
                if ($year_level_id) {
                    $subjects_query = "SELECT subject_id FROM tbl_subjects WHERE year_level_id = ?";
                    $subjects_stmt = $conn->prepare($subjects_query);
                    $subjects_stmt->bind_param("i", $year_level_id);
                    $subjects_stmt->execute();
                    $subjects_result = $subjects_stmt->get_result();
                    
                    if ($subjects_result->num_rows > 0) {
                        while ($subject = $subjects_result->fetch_assoc()) {
                            $subject_id = $subject['subject_id'];
                            $enroll_stmt = $conn->prepare("INSERT INTO tbl_subject_enrollments (student_id, subject_id) VALUES (?, ?)");
                            $enroll_stmt->bind_param("ii", $student_id, $subject_id);
                            $enroll_stmt->execute();
                            $enroll_stmt->close();
                        }
                    }
                    $subjects_stmt->close();
                }
                
                // Link to parent if provided and valid
                if (!empty($parent_account_id) && $parent_account_id != '') {
                    // Get parent_id from tbl_parents
                    $parentCheck = $conn->prepare("SELECT parent_id FROM tbl_parents WHERE account_id = ?");
                    $parentCheck->bind_param("i", $parent_account_id);
                    $parentCheck->execute();
                    $parentCheck->store_result();
                    
                    if ($parentCheck->num_rows > 0) {
                        $parentCheck->bind_result($actual_parent_id);
                        $parentCheck->fetch();
                        
                        // Insert into parent_student linking table
                        $linkStmt = $conn->prepare("INSERT INTO tbl_parent_student (parent_id, student_id, relation, created_at) 
                                                  VALUES (?, ?, 'Child', NOW())");
                        $linkStmt->bind_param("ii", $actual_parent_id, $student_id);
                        $linkStmt->execute();
                        $linkStmt->close();
                    }
                    $parentCheck->close();
                }
                
                $studentStmt->close();
            }
        } elseif ($role === 'parent') {
            // Insert into parents table
            $parentStmt = $conn->prepare("INSERT INTO tbl_parents (account_id, created_at) VALUES (?, NOW())");
            $parentStmt->bind_param("i", $account_id);
            $parentStmt->execute();
            $parentStmt->close();
        }
        
        $stmt->close();
        header("Location: create_account.php?success=1");
        exit();
    } else {
        header("Location: create_account.php?error=Failed to create account");
        exit();
    }
}
$conn->close();
?>