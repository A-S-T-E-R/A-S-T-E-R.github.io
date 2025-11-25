<?php
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $contact_number = $_POST['contact_number'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $conn->begin_transaction();

    try {
        // 1. Insert into tbl_accounts
        $stmt = $conn->prepare("
            INSERT INTO tbl_accounts (role, first_name, last_name, email, contact_number, username, password) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssss", $role, $first_name, $last_name, $email, $contact_number, $username, $password);
        $stmt->execute();
        $account_id = $conn->insert_id;

        // 2. If role is student, insert into tbl_students
        if ($role === 'student') {
            $lrn = $_POST['lrn'];
            $birth_date = $_POST['birth_date'];
            $gender = $_POST['gender'];
            $year_level_id = $_POST['year_level_id'];
            $section_id = $_POST['section_id'];

            $stmt = $conn->prepare("
                INSERT INTO tbl_students (account_id, lrn, birth_date, gender, year_level_id, section_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssii", $account_id, $lrn, $birth_date, $gender, $year_level_id, $section_id);
            $stmt->execute();
            $student_id = $conn->insert_id;

            // 3. Auto-enroll student in subjects for their year level
            $stmt = $conn->prepare("SELECT subject_id, lecture_units, lab_units FROM tbl_subjects WHERE year_level_id = ?");
            $stmt->bind_param("i", $year_level_id);
            $stmt->execute();
            $subjects_result = $stmt->get_result();

            while ($sub = $subjects_result->fetch_assoc()) {
                $stmt2 = $conn->prepare("
                    INSERT INTO tbl_subjectenrollment (student_id, subject_id, lecture_units, lab_units) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt2->bind_param("iiii", $student_id, $sub['subject_id'], $sub['lecture_units'], $sub['lab_units']);
                $stmt2->execute();
            }
        }

        $conn->commit();
        header("Location: create_account.php?success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
} else {
    header("Location: create_account.php");
    exit();
}
?>
