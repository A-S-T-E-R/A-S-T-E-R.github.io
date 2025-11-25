<?php
include 'db_connect.php';

/* ========================
   ADD SECTION
======================== */
if (isset($_POST['add_section'])) {
    $year_level_id = $_POST['year_level_id'];
    $section_name = $_POST['section_name'];
    $strand_id = isset($_POST['strand_id']) && $_POST['strand_id'] !== '' ? $_POST['strand_id'] : NULL;

    if ($strand_id === NULL) {
        $stmt = $conn->prepare("INSERT INTO tbl_sections (year_level_id, section_name) VALUES (?, ?)");
        $stmt->bind_param("is", $year_level_id, $section_name);
    } else {
        $stmt = $conn->prepare("INSERT INTO tbl_sections (year_level_id, strand_id, section_name) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $year_level_id, $strand_id, $section_name);
    }

    if ($stmt->execute()) {
        header("Location: manage_sections.php?success=added");
        exit();
    } else {
        die("Error adding section: " . $conn->error);
    }
}

/* ========================
   UPDATE SECTION
======================== */
if (isset($_POST['edit_section'])) {
    $section_id = $_POST['section_id'];
    $year_level_id = $_POST['year_level_id'];
    $section_name = $_POST['section_name'];
    $strand_id = isset($_POST['strand_id']) && $_POST['strand_id'] !== '' ? $_POST['strand_id'] : NULL;

    if ($strand_id === NULL) {
        $stmt = $conn->prepare("UPDATE tbl_sections SET year_level_id=?, strand_id=NULL, section_name=? WHERE section_id=?");
        $stmt->bind_param("isi", $year_level_id, $section_name, $section_id);
    } else {
        $stmt = $conn->prepare("UPDATE tbl_sections SET year_level_id=?, strand_id=?, section_name=? WHERE section_id=?");
        $stmt->bind_param("iisi", $year_level_id, $strand_id, $section_name, $section_id);
    }

    if ($stmt->execute()) {
        header("Location: manage_sections.php?success=updated");
        exit();
    } else {
        die("Error updating section: " . $conn->error);
    }
}

/* ========================
   DELETE SECTION
======================== */
if (isset($_GET['delete_section'])) {
    $section_id = $_GET['delete_section'];

    $stmt = $conn->prepare("DELETE FROM tbl_sections WHERE section_id=?");
    $stmt->bind_param("i", $section_id);

    if ($stmt->execute()) {
        header("Location: manage_sections.php?success=deleted");
        exit();
    } else {
        die("Error deleting section: " . $conn->error);
    }
}
