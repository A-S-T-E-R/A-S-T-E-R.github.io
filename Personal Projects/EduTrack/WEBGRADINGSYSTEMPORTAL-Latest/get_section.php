<?php
session_start();
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "school_portal";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

if (isset($_GET['year_level_id'])) {
    $year_level_id = intval($_GET['year_level_id']);
    
    // Debug: Log the received year_level_id
    error_log("Received year_level_id: " . $year_level_id);
    
    // Check if year level exists
    $checkYear = $conn->prepare("SELECT year_level_id FROM tbl_yearlevels WHERE year_level_id = ?");
    $checkYear->bind_param("i", $year_level_id);
    $checkYear->execute();
    $yearResult = $checkYear->get_result();
    
    if ($yearResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid year level']);
        exit();
    }
    $checkYear->close();
    
    // Fetch sections for this year level
    $stmt = $conn->prepare("SELECT section_id, section_name FROM tbl_sections WHERE year_level_id = ? ORDER BY section_name");
    $stmt->bind_param("i", $year_level_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }
    
    // Debug: Log the sections found
    error_log("Sections found for year_level_id $year_level_id: " . count($sections));
    
    // Return as JSON
    echo json_encode(['success' => true, 'sections' => $sections]);
} else {
    echo json_encode(['success' => false, 'message' => 'Year level ID not provided']);
}

$conn->close();
?>