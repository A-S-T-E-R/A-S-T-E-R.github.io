<?php
session_start();
require 'db_connect.php';

// Only admins can manage sections
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// ADD SECTION
if (isset($_POST['add_section'])) {
    $section_name  = trim($_POST['section_name']);
    $year_level_id = intval($_POST['year_level_id']);
    $strand_id     = isset($_POST['strand_id']) && $_POST['strand_id'] !== '' ? intval($_POST['strand_id']) : NULL;

    // Prevent duplicate section for the same year/strand
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_sections WHERE section_name=? AND year_level_id=? AND (strand_id=? OR (? IS NULL AND strand_id IS NULL))");
    $stmt->bind_param("siii", $section_name, $year_level_id, $strand_id, $strand_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        $_SESSION['error'] = "Section already exists for the selected year/strand.";
        header("Location: manage_sections.php");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO tbl_sections (section_name, year_level_id, strand_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $section_name, $year_level_id, $strand_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Section added successfully!";
    } else {
        $_SESSION['error'] = "Error adding section: " . $stmt->error;
    }
    $stmt->close();
    header("Location: manage_sections.php");
    exit();
}

// EDIT SECTION
if (isset($_POST['update_section'])) {
    $section_id    = intval($_POST['section_id']);
    $section_name  = trim($_POST['section_name']);
    $year_level_id = intval($_POST['year_level_id']);
    $strand_id     = isset($_POST['strand_id']) && $_POST['strand_id'] !== '' ? intval($_POST['strand_id']) : NULL;

    // Prevent duplicate section for the same year/strand
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_sections WHERE section_name=? AND year_level_id=? AND (strand_id=? OR (? IS NULL AND strand_id IS NULL)) AND section_id != ?");
    $stmt->bind_param("siiii", $section_name, $year_level_id, $strand_id, $strand_id, $section_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        $_SESSION['error'] = "Another section already exists for the selected year/strand.";
        header("Location: manage_sections.php");
        exit();
    }

    $stmt = $conn->prepare("UPDATE tbl_sections SET section_name=?, year_level_id=?, strand_id=? WHERE section_id=?");
    $stmt->bind_param("siii", $section_name, $year_level_id, $strand_id, $section_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Section updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating section: " . $stmt->error;
    }
    $stmt->close();
    header("Location: manage_sections.php");
    exit();
}

// DELETE SECTION
if (isset($_POST['delete_section'])) {
    $section_id = intval($_POST['section_id']);

    $stmt = $conn->prepare("DELETE FROM tbl_sections WHERE section_id=?");
    $stmt->bind_param("i", $section_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Section deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting section: " . $stmt->error;
    }
    $stmt->close();
    header("Location: manage_sections.php");
    exit();
}
?>
