<?php
session_start();
require 'db_connect.php';

// Auth check (only admins can manage sections)
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Set cache control headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
// Fetch admin details for display
$adminName = 'Administrator';
$adminInitial = 'A';
try {
    $adminStmt = $conn->prepare("SELECT first_name, last_name, username FROM tbl_accounts WHERE account_id = ?");
    $adminStmt->bind_param("i", $_SESSION['account_id']);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();
    $adminData = $adminResult->fetch_assoc();
    
    if ($adminData) {
        $adminName = $adminData['first_name'] . ' ' . $adminData['last_name'];
        $adminInitial = strtoupper(substr($adminData['first_name'], 0, 1));
        $_SESSION['fullname'] = $adminName;
    }
} catch (Exception $e) {
    // Fallback handling
}
/* ==========================
   FETCH DATA
========================== */
// Get all year levels
$yearlevels = [];
$res = $conn->query("SELECT * FROM tbl_yearlevels ORDER BY education_stage, year_level_id");
while ($row = $res->fetch_assoc()) {
    $yearlevels[$row['year_level_id']] = $row;
}

// Get all strands
$strands = [];
$res = $conn->query("SELECT * FROM tbl_strands ORDER BY strand_name");
while ($row = $res->fetch_assoc()) {
    $strands[$row['strand_id']] = $row['strand_name'];
}

// Get all sections with year level + strand
$sections = [];
$sql = "
    SELECT s.section_id, s.section_name, s.year_level_id, s.strand_id,
           y.level_name, y.education_stage, st.strand_name
    FROM tbl_sections s
    JOIN tbl_yearlevels y ON s.year_level_id = y.year_level_id
    LEFT JOIN tbl_strands st ON s.strand_id = st.strand_id
    ORDER BY y.education_stage, y.year_level_id, st.strand_name, s.section_name
";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $sections[] = $row;
}

// Get unique education stages for dropdown
$education_stages = [];
foreach ($yearlevels as $yl) {
    if (!in_array($yl['education_stage'], $education_stages)) {
        $education_stages[] = $yl['education_stage'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>EduTrack - Manage Sections</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #1abc9c;
            --light: #ecf0f1;
            --dark: #34495e;
        }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background-color: var(--primary); color: white; min-height: 100vh; padding: 0; box-shadow: 3px 0 10px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 20px; background-color: rgba(0,0,0,0.2); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li { width: 100%; }
        .sidebar-menu a { color: rgba(255,255,255,0.8); text-decoration: none; display: block; padding: 12px 20px; transition: all 0.3s; border-left: 3px solid transparent; }
        .sidebar-menu a:hover { background-color: rgba(0,0,0,0.2); color: white; border-left: 3px solid var(--accent); }
        .sidebar-menu a.active { background-color: rgba(0,0,0,0.2); color: white; border-left: 3px solid var(--accent); }
        .sidebar-menu i { width: 25px; text-align: center; margin-right: 10px; }
        .main-content { padding: 20px; }
        .header { background-color: white; padding: 15px 20px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .user-info { display: flex; align-items: center; }
        .user-info .avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--secondary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 10px; }
        .card { border: none; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.05); transition: transform 0.3s, box-shadow 0.3s; margin-bottom: 20px; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 6px 12px rgba(0,0,0,0.1); }
        .card-header { background-color: white; border-bottom: 1px solid #eee; font-weight: 600; padding: 15px 20px; border-radius: 8px 8px 0 0 !important; }
        .card-body { padding: 20px; }
        .badge-admin { background-color: var(--accent); }
        .btn-primary { background-color: #1abc9c; border-color: var(--secondary); }
        .btn-primary:hover { background-color: #1abc9c; border-color: #2980b9; }
        .btn-warning{ background-color: #3498db; }
        .btn-warning:hover { background-color: #0d6efd; border-color: #2980b9; }
        .btn-success { background-color: var(--accent); border-color: var(--accent); }
        .btn-success:hover { background-color: #1abc9c; border-color: #16a085; }
        .section-card { border-left: 4px solid var(--accent); transition: all 0.3s; }
        .section-card:hover { background-color: rgba(0,0,0,0.02); }
        .action-buttons { display: flex; gap: 10px; }
        .section-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        
        /* Welcome Section Styling */
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
        
        /* Filter styles */
        .filters-section { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
        }
        
        /* Section card with checkbox on left */
        .section-card-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .section-checkbox {
            flex-shrink: 0;
        }
        
        .section-details {
            flex-grow: 1;
        }
        
        .section-card.selected {
            background-color: rgba(26, 188, 156, 0.1) !important;
            border-left-color: var(--accent);
            border-left-width: 6px;
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
                    <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>

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
                          <li><a href="manage_sections.php" class="active">Sections</a></li>
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
                    <h3>Manage Sections</h3>
                    <div class="user-info">
    <div class="avatar"><?= $adminInitial ?></div>
    <div>
        <div class="fw-bold"><?= htmlspecialchars($adminName) ?></div>
        <div class="text-muted">Administrator</div>
    </div>
</div> 
                </div>

                <div class="main-content">
                    <!-- Welcome Banner -->
                    <div class="welcome-section">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1>Sections Management</h1>
                                <p>Organize class sections, assign students to appropriate groups, and manage academic divisions across different year levels</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-object-group fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Page Header -->
                    <div class="section-actions">
                        <h2>Manage Sections</h2>
                        <div class="action-buttons">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                                <i class="fas fa-plus"></i> Add Section
                            </button>
                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editSectionModal">
                                <i class="fas fa-edit"></i> Edit Section
                            </button>
                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteSectionModal">
                                <i class="fas fa-trash"></i> Delete Section
                            </button>
                        </div>
                    </div>

                    <!-- Filters Section -->
                    <div class="filters-section">
                        <h5><i class="fas fa-filter"></i> Filter Sections</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Education Stage</label>
                                <select id="filter_stage" class="form-select">
                                    <option value="">All Education Stages</option>
                                    <?php foreach ($education_stages as $stage): ?>
                                        <option value="<?= htmlspecialchars($stage) ?>"><?= htmlspecialchars($stage) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Grade Level</label>
                                <select id="filter_grade" class="form-select">
                                    <option value="">All Grade Levels</option>
                                    <?php foreach ($yearlevels as $yl): ?>
                                        <option value="<?= $yl['year_level_id'] ?>" data-stage="<?= htmlspecialchars($yl['education_stage']) ?>">
                                            <?= htmlspecialchars($yl['level_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Section</label>
                                <select id="filter_section" class="form-select">
                                    <option value="">All Sections</option>
                                    <?php foreach ($sections as $sec): ?>
                                        <option value="<?= $sec['section_id'] ?>" 
                                                data-stage="<?= htmlspecialchars($sec['education_stage']) ?>"
                                                data-grade="<?= $sec['year_level_id'] ?>">
                                            <?= htmlspecialchars($sec['section_name']) ?>
                                            <?php if ($sec['strand_name']): ?>
                                                - <?= htmlspecialchars($sec['strand_name']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <button class="btn btn-secondary" onclick="clearFilters()">
                                    <i class="fas fa-times"></i> Clear Filters
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Sections Display -->
                    <div id="sections-container">
                        <!-- Sections Grouped -->
                        <?php
                        $current_stage = "";
                        $current_year = "";
                        $current_year_id = "";
                        
                        foreach ($sections as $sec) {
                            if ($current_stage !== $sec['education_stage']) {
                                if ($current_stage !== "") {
                                    echo "</div></div>"; // close previous grade-level and education-stage
                                }
                                $current_stage = $sec['education_stage'];
                                echo "<div class='education-stage' data-stage='" . htmlspecialchars($current_stage) . "'>";
                                echo "<h3 class='mt-4'>" . htmlspecialchars($current_stage) . "</h3>";
                                $current_year = "";
                                $current_year_id = "";
                            }
                            
                            if ($current_year !== $sec['level_name']) {
                                if ($current_year !== "") {
                                    echo "</div>"; // close previous grade-level
                                }
                                $current_year = $sec['level_name'];
                                $current_year_id = $sec['year_level_id'];
                                echo "<div class='grade-level ms-3' data-grade='" . $current_year_id . "'>";
                                echo "<h5 class='mt-3'>" . htmlspecialchars($current_year) . "</h5>";
                            }
                            
                            echo "<div class='card p-3 mb-2 ms-4 section-card section-item' 
                                        data-section-id='" . $sec['section_id'] . "'
                                        data-stage='" . htmlspecialchars($sec['education_stage']) . "'
                                        data-grade='" . $sec['year_level_id'] . "'>
                                    <div class='section-card-content'>
                                        <div class='section-checkbox'>
                                            <input type='radio' name='selected_section' value='{$sec['section_id']}' 
                                                data-name='" . htmlspecialchars($sec['section_name']) . "' 
                                                data-year='{$sec['year_level_id']}' 
                                                data-strand='{$sec['strand_id']}' 
                                                class='form-check-input'>
                                        </div>
                                        <div class='section-details'>
                                            <strong>" . htmlspecialchars($sec['section_name']) . "</strong>";
                            if ($sec['education_stage'] === "Senior High" && $sec['strand_name']) {
                                echo " - <em>" . htmlspecialchars($sec['strand_name']) . "</em>";
                            }
                            echo "          </div>
                                    </div>
                                  </div>";
                        }
                        
                        if ($current_stage !== "") {
                            echo "</div></div>"; // close final grade-level and education-stage
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Section Modal -->
    <div class="modal fade" id="addSectionModal" tabindex="-1">
      <div class="modal-dialog">
        <form action="process_sections.php" method="POST" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add Section</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Section Name</label>
                <input type="text" name="section_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Year Level</label>
                <select name="year_level_id" id="add_year_level_id" class="form-select" required>
                    <?php foreach ($yearlevels as $yl): ?>
                        <option value="<?= $yl['year_level_id'] ?>"><?= $yl['level_name'] ?> (<?= $yl['education_stage'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3" id="add_strand_container" style="display:none;">
                <label class="form-label">Strand (for SHS only)</label>
                <select name="strand_id" class="form-select">
                    <option value="">-- None --</option>
                    <?php foreach ($strands as $id => $name): ?>
                        <option value="<?= $id ?>"><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="add_section" class="btn btn-primary">Add Section</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Section Modal -->
    <div class="modal fade" id="editSectionModal" tabindex="-1">
      <div class="modal-dialog">
        <form action="process_sections.php" method="POST" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit Section</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="section_id" id="edit_section_id">
            <div class="mb-3">
                <label class="form-label">Section Name</label>
                <input type="text" name="section_name" id="edit_section_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Year Level</label>
                <select name="year_level_id" id="edit_year_level_id" class="form-select" required>
                    <?php foreach ($yearlevels as $yl): ?>
                        <option value="<?= $yl['year_level_id'] ?>"><?= $yl['level_name'] ?> (<?= $yl['education_stage'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3" id="edit_strand_container" style="display:none;">
                <label class="form-label">Strand (for SHS only)</label>
                <select name="strand_id" id="edit_strand_id" class="form-select">
                    <option value="">-- None --</option>
                    <?php foreach ($strands as $id => $name): ?>
                        <option value="<?= $id ?>"><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="update_section" class="btn btn-warning">Update Section</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Section Modal -->
    <div class="modal fade" id="deleteSectionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="process_sections.php">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Delete Section</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="section_id" id="delete_section_id">
                        <p>Are you sure you want to delete this section?</p>
                        <p class="fw-bold" id="delete_section_name"></p>
                        <p class="text-danger">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_section" class="btn btn-danger">Delete Section</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmLogout() {
        if (confirm('Are you sure you want to logout?')) {
            localStorage.clear(); sessionStorage.clear(); return true;
        }
        return false;
    }

    let selectedSection = null;
    
    // Handle section selection
    document.addEventListener('change', function(e) {
        if (e.target.name === 'selected_section') {
            // Remove previous selection styling
            document.querySelectorAll('.section-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            selectedSection = {
                id: e.target.value,
                name: e.target.getAttribute('data-name'),
                year: e.target.getAttribute('data-year'),
                strand: e.target.getAttribute('data-strand') || ""
            };
            
            // Add selection styling to current selection
            e.target.closest('.section-card').classList.add('selected');
        }
    });

    // Filter functionality
    const filterStage = document.getElementById('filter_stage');
    const filterGrade = document.getElementById('filter_grade');
    const filterSection = document.getElementById('filter_section');

    // Update grade dropdown based on selected stage
    filterStage.addEventListener('change', function() {
        const selectedStage = this.value;
        const gradeOptions = filterGrade.querySelectorAll('option');
        
        filterGrade.value = '';
        filterSection.value = '';
        
        gradeOptions.forEach(option => {
            if (option.value === '') {
                option.style.display = 'block';
                return;
            }
            
            const optionStage = option.getAttribute('data-stage');
            option.style.display = (!selectedStage || optionStage === selectedStage) ? 'block' : 'none';
        });
        
        updateSectionDropdown();
        filterSections();
    });

    // Update section dropdown based on selected stage and grade
    filterGrade.addEventListener('change', function() {
        updateSectionDropdown();
        filterSections();
    });

    filterSection.addEventListener('change', filterSections);

    function updateSectionDropdown() {
        const selectedStage = filterStage.value;
        const selectedGrade = filterGrade.value;
        const sectionOptions = filterSection.querySelectorAll('option');
        
        filterSection.value = '';
        
        sectionOptions.forEach(option => {
            if (option.value === '') {
                option.style.display = 'block';
                return;
            }
            
            const optionStage = option.getAttribute('data-stage');
            const optionGrade = option.getAttribute('data-grade');
            
            let show = true;
            if (selectedStage && optionStage !== selectedStage) show = false;
            if (selectedGrade && optionGrade !== selectedGrade) show = false;
            
            option.style.display = show ? 'block' : 'none';
        });
    }

    function filterSections() {
        const selectedStage = filterStage.value;
        const selectedGrade = filterGrade.value;
        const selectedSection = filterSection.value;
        
        console.log('Filtering - Stage:', selectedStage, 'Grade:', selectedGrade, 'Section:', selectedSection);
        
        const sectionItems = document.querySelectorAll('.section-item');
        const educationStages = document.querySelectorAll('.education-stage');
        
        // First, show all items if no filters are selected
        if (!selectedStage && !selectedGrade && !selectedSection) {
            sectionItems.forEach(item => item.style.display = 'block');
            educationStages.forEach(stage => stage.style.display = 'block');
            document.querySelectorAll('.grade-level').forEach(grade => grade.style.display = 'block');
            return;
        }
        
        // Show/hide individual sections
        sectionItems.forEach(item => {
            const itemStage = item.getAttribute('data-stage');
            const itemGrade = item.getAttribute('data-grade');
            const itemSection = item.getAttribute('data-section-id');
            
            console.log('Item - Stage:', itemStage, 'Grade:', itemGrade, 'Section:', itemSection);
            
            let show = true;
            if (selectedStage && itemStage !== selectedStage) show = false;
            if (selectedGrade && itemGrade !== selectedGrade) show = false;
            if (selectedSection && itemSection !== selectedSection) show = false;
            
            item.style.display = show ? 'block' : 'none';
        });
        
        // Show/hide education stage headers and their content
        educationStages.forEach(stage => {
            const stageValue = stage.getAttribute('data-stage');
            const visibleSections = stage.querySelectorAll('.section-item[style*="display: block"], .section-item:not([style*="display: none"])');
            const hasVisibleSections = Array.from(visibleSections).some(section => 
                section.style.display !== 'none'
            );
            
            console.log('Stage:', stageValue, 'Visible sections count:', visibleSections.length, 'Has visible:', hasVisibleSections);
            
            if (!selectedStage || stageValue === selectedStage) {
                stage.style.display = hasVisibleSections ? 'block' : 'none';
            } else {
                stage.style.display = 'none';
            }
        });
        
        // Show/hide grade level headers within each education stage
        document.querySelectorAll('.grade-level').forEach(grade => {
            const gradeValue = grade.getAttribute('data-grade');
            const visibleSections = grade.querySelectorAll('.section-item[style*="display: block"], .section-item:not([style*="display: none"])');
            const hasVisibleSections = Array.from(visibleSections).some(section => 
                section.style.display !== 'none'
            );
            
            if (!selectedGrade || gradeValue === selectedGrade) {
                grade.style.display = hasVisibleSections ? 'block' : 'none';
            } else {
                grade.style.display = 'none';
            }
        });
    }

    function clearFilters() {
        filterStage.value = '';
        filterGrade.value = '';
        filterSection.value = '';
        
        // Reset grade and section dropdowns
        filterGrade.querySelectorAll('option').forEach(option => {
            option.style.display = 'block';
        });
        filterSection.querySelectorAll('option').forEach(option => {
            option.style.display = 'block';
        });
        
        // Show all sections
        document.querySelectorAll('.section-item, .education-stage, .grade-level').forEach(item => {
            item.style.display = 'block';
        });
    }

    // Prefill edit/delete modals
    var editModal = document.getElementById('editSectionModal');
    editModal.addEventListener('show.bs.modal', function () {
        if (!selectedSection) { 
            alert('Please select a section to edit'); 
            event.preventDefault(); 
            return; 
        }
        document.getElementById('edit_section_id').value = selectedSection.id;
        document.getElementById('edit_section_name').value = selectedSection.name;
        document.getElementById('edit_year_level_id').value = selectedSection.year;
        document.getElementById('edit_strand_id').value = selectedSection.strand;
        toggleStrand(document.getElementById('edit_year_level_id'), 'edit_strand_container');
    });

    var deleteModal = document.getElementById('deleteSectionModal');
    deleteModal.addEventListener('show.bs.modal', function () {
        if (!selectedSection) { 
            alert('Please select a section to delete'); 
            event.preventDefault(); 
            return; 
        }
        document.getElementById('delete_section_id').value = selectedSection.id;
        document.getElementById('delete_section_name').textContent = selectedSection.name;
    });

    // Show/hide strand dynamically
    function toggleStrand(selectElem, containerId) {
        const value = parseInt(selectElem.value);
        const container = document.getElementById(containerId);
        if (value === 11 || value === 12) {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
            container.querySelector('select').value = '';
        }
    }

    const addYearLevel = document.getElementById('add_year_level_id');
    addYearLevel.addEventListener('change', () => toggleStrand(addYearLevel, 'add_strand_container'));
    window.addEventListener('DOMContentLoaded', () => toggleStrand(addYearLevel, 'add_strand_container'));

    const editYearLevel = document.getElementById('edit_year_level_id');
    editYearLevel.addEventListener('change', () => toggleStrand(editYearLevel, 'edit_strand_container'));

    // Toggle submenu
    document.querySelectorAll(".has-submenu > a").forEach(link => {
      link.addEventListener("click", e => {
        e.preventDefault();
        link.parentElement.classList.toggle("open");
      });
    });
    </script>
</body>
</html>