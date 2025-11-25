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
$host = "localhost";
$user = "root"; // change if needed
$pass = ""; // change if needed
$dbname = "school_portal";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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
        // Update session for future use
        $_SESSION['fullname'] = $adminName;
    }
} catch (Exception $e) {
    // If database fetch fails, use session fallbacks
    if (isset($_SESSION['fullname']) && !empty($_SESSION['fullname'])) {
        $adminName = $_SESSION['fullname'];
        $adminInitial = substr($_SESSION['fullname'], 0, 1);
    } elseif (isset($_SESSION['username'])) {
        $adminName = $_SESSION['username'];
        $adminInitial = substr($_SESSION['username'], 0, 1);
    }
}

// Fetch existing parent accounts for linking
$parentAccounts = [];
$parentResult = $conn->query("
    SELECT a.account_id, a.first_name, a.last_name 
    FROM tbl_accounts a 
    WHERE a.role = 'parent'
");
if ($parentResult && $parentResult->num_rows > 0) {
    while ($row = $parentResult->fetch_assoc()) {
        $parentAccounts[] = $row;
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
    <title>EduTrack - Create Account</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        .form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            padding: 25px;
            margin-top: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-top: 10px;
        }
        
        .form-control, .form-select {
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ddd;
            margin-top: 5px;
            font-size: 14px;
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 4px rgba(52,152,219,0.3);
        }
        
        .success-msg {
            color: #28a745;
            margin-bottom: 15px;
            font-weight: bold;
            padding: 10px;
            background-color: #d4edda;
            border-radius: 4px;
            border: 1px solid #c3e6cb;
        }
        
        .error-msg {
            color: #dc3545;
            margin-bottom: 15px;
            font-weight: bold;
            padding: 10px;
            background-color: #f8d7da;
            border-radius: 4px;
            border: 1px solid #f5c6cb;
        }
        
        .info-msg {
            color: #0c5460;
            margin-bottom: 15px;
            font-weight: bold;
            padding: 10px;
            background-color: #d1ecf1;
            border-radius: 4px;
            border: 1px solid #bee5eb;
        }
        
        #studentFields {
            margin-top: 15px;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 6px;
            background: #fafafa;
        }

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
                            <li><a href="create_account.php" class="active">Create Accounts</a></li>
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
                    <h3>Create Account</h3>
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
                                <h1>Create New Account</h1>
                                <p>Create student, parent, teacher, or administrator accounts with appropriate roles and permissions</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-user-plus fa-4x"></i>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Create New Account</h5>
                        </div>
                        <div class="card-body">
                            <?php if(isset($_GET['success'])): ?>
                                <div class="success-msg">
                                    <i class="fas fa-check-circle me-1"></i> Account created successfully!
                                </div>
                            <?php endif; ?>
                            
                            <?php if(isset($_GET['error'])): ?>
                                <div class="error-msg">
                                    <i class="fas fa-exclamation-circle me-1"></i> Error: <?php echo htmlspecialchars($_GET['error']); ?>
                                </div>
                            <?php endif; ?>

                            <form action="create_account_process.php" method="POST" class="form-container">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Role</label>
                                        <select name="role" id="roleSelect" class="form-select" required>
                                            <option value="">Select Role</option>
                                            <option value="student">Student</option>
                                            <option value="parent">Parent</option>
                                            <option value="teacher">Teacher</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="first_name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="last_name" class="form-control" required>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Contact Number</label>
                                        <input type="text" name="contact_number" class="form-control">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                </div>

                                <!-- Student-specific fields -->
                                <div id="studentFields" style="display:none;">
                                    <h5 class="mb-3 mt-4">Student Information</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">LRN</label>
                                            <input type="text" name="lrn" class="form-control">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Birth Date</label>
                                            <input type="date" name="birth_date" class="form-control">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Gender</label>
                                            <select name="gender" class="form-select">
                                                <option value="">Select</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Link to Parent Account</label>
                                            <select name="parent_id" class="form-select">
                                                <option value="">Select Parent (Optional)</option>
                                                <?php if (!empty($parentAccounts)): ?>
                                                    <?php foreach ($parentAccounts as $parent): ?>
                                                        <option value="<?php echo $parent['account_id']; ?>">
                                                            <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <option value="" disabled>No parent accounts available</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <?php if (empty($parentAccounts)): ?>
                                    <div class="info-msg">
                                        <i class="fas fa-info-circle me-1"></i> No parent accounts found. You can create a parent account first, then link it to this student later.
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Year Level</label>
                                            <select name="year_level_id" class="form-select">
                                                <option value="">Select Year</option>
                                                <?php
                                                $res = $conn->query("SELECT * FROM tbl_yearlevels");
                                                while ($row = $res->fetch_assoc()) {
                                                    echo "<option value='{$row['year_level_id']}'>{$row['level_name']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Section</label>
                                            <select name="section_id" class="form-select">
                                                <option value="">Select Section</option>
                                                <?php
                                                $res = $conn->query("SELECT * FROM tbl_sections");
                                                while ($row = $res->fetch_assoc()) {
                                                    echo "<option value='{$row['section_id']}'>{$row['section_name']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-user-plus me-1"></i> Create Account
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Add this script to handle the year level change and filter sections -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById("roleSelect");
        const studentFields = document.getElementById("studentFields");
        const yearLevelSelect = document.querySelector("select[name='year_level_id']");
        const sectionSelect = document.querySelector("select[name='section_id']");
        
        // Show/hide student fields based on role selection
        roleSelect.addEventListener("change", function() {
            studentFields.style.display = (this.value === "student") ? "block" : "none";
        });

        // Handle year level change to filter sections
        if (yearLevelSelect) {
            yearLevelSelect.addEventListener("change", function() {
                const yearLevelId = this.value;
                console.log("Year level selected:", yearLevelId);
                
                // Clear existing options except the first one
                while (sectionSelect.options.length > 1) {
                    sectionSelect.remove(1);
                }
                
                if (yearLevelId) {
                    // Fetch sections for the selected year level
                    console.log("Fetching sections for year level:", yearLevelId);
                    fetch(`get_section.php?year_level_id=${yearLevelId}`)
                        .then(response => {
                            console.log("Response status:", response.status);
                            return response.json();
                        })
                        .then(data => {
                            console.log("Response data:", data);
                            if (data.success && data.sections.length > 0) {
                                data.sections.forEach(section => {
                                    const option = document.createElement('option');
                                    option.value = section.section_id;
                                    option.textContent = section.section_name;
                                    sectionSelect.appendChild(option);
                                });
                                console.log("Added", data.sections.length, "sections to dropdown");
                            } else {
                                const option = document.createElement('option');
                                option.value = '';
                                option.textContent = 'No sections available for this year level';
                                sectionSelect.appendChild(option);
                                console.log("No sections available");
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching sections:', error);
                        });
                }
            });
        }

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
    });
    </script>

</body>
</html>
<?php $conn->close(); ?>