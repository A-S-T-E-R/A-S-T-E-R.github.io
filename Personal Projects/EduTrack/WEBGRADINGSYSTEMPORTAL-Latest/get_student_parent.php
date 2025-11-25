<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "school_portal";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

if (isset($_GET['student_id'])) {
    $student_id = $conn->real_escape_string($_GET['student_id']);
    
    $result = $conn->query("
        SELECT p.parent_id 
        FROM tbl_parent_student ps 
        JOIN tbl_parents p ON ps.parent_id = p.parent_id 
        WHERE ps.student_id = '$student_id'
    ");
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['parent_id' => $row['parent_id']]);
    } else {
        echo json_encode(['parent_id' => null]);
    }
} else {
    echo json_encode(['error' => 'No student ID provided']);
}

$conn->close();
?>