    <?php
    session_start();
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Database connection
define('DB_HOST', 'sql210.infinityfree.com');
define('DB_NAME', 'if0_40265243_school_portal');
define('DB_USER', 'if0_40265243');
define('DB_PASS', 'rjL6bzbfrgcc');

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }

    // Check if user is logged in and is an admin
    if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: index.php?session_expired=1");
        exit();
    }

    // Handle delete/archive request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_parent'])) {
        $parentId = $_POST['parent_id'];
        $reason = $_POST['reason'];
        $otherReason = !empty($_POST['other_reason']) ? $_POST['other_reason'] : $reason;
        $deletedBy = $_SESSION['account_id'];
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // 1. Get parent account details
            $stmt = $pdo->prepare("SELECT * FROM tbl_accounts WHERE account_id = ? AND role = 'parent'");
            $stmt->execute([$parentId]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$parent) {
                throw new Exception("Parent not found");
            }
            
            // 2. Archive the account
            $archiveStmt = $pdo->prepare("
                INSERT INTO tbl_accounts_archive 
                (original_account_id, email, password, role, first_name, last_name, contact_number, created_at, deleted_at, deleted_by, reason)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            $archiveStmt->execute([
                $parent['account_id'],
                $parent['email'],
                $parent['password'],
                $parent['role'],
                $parent['first_name'],
                $parent['last_name'],
                $parent['contact_number'],
                $parent['created_at'],
                $deletedBy,
                $otherReason
            ]);
            
            // 3. Delete the account from the main table
            $deleteStmt = $pdo->prepare("DELETE FROM tbl_accounts WHERE account_id = ?");
            $deleteStmt->execute([$parentId]);
            
            // 4. Commit transaction
            $pdo->commit();
            
            $_SESSION['success_message'] = "Parent account archived successfully.";
            header("Location: archive_manager.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error archiving parent: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Handle add parent request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_parent'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $contactNumber = trim($_POST['contact_number']);
        
        try {
            // Check if email already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_accounts WHERE email = ?");
            $checkStmt->execute([$email]);
            $emailExists = $checkStmt->fetchColumn();
            
            if ($emailExists) {
                throw new Exception("Email already exists. Please use a different email address.");
            }
            
            // Hash the password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new parent
            $insertStmt = $pdo->prepare("
                INSERT INTO tbl_accounts (email, password, role, first_name, last_name, contact_number)
                VALUES (?, ?, 'parent', ?, ?, ?)
            ");
            $insertStmt->execute([$email, $hashedPassword, $firstName, $lastName, $contactNumber]);
            
            $_SESSION['success_message'] = "Parent account created successfully.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error creating parent: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Handle edit parent request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_parent'])) {
        $parentId = $_POST['parent_id'];
        $email = trim($_POST['email']);
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $contactNumber = trim($_POST['contact_number']);
        $password = $_POST['password'];
        
        try {
            // Check if email already exists (excluding current parent)
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_accounts WHERE email = ? AND account_id != ?");
            $checkStmt->execute([$email, $parentId]);
            $emailExists = $checkStmt->fetchColumn();
            
            if ($emailExists) {
                throw new Exception("Email already exists. Please use a different email address.");
            }
            
            // Prepare update query based on whether password is being changed
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("
                    UPDATE tbl_accounts 
                    SET email = ?, password = ?, first_name = ?, last_name = ?, contact_number = ?
                    WHERE account_id = ? AND role = 'parent'
                ");
                $updateStmt->execute([$email, $hashedPassword, $firstName, $lastName, $contactNumber, $parentId]);
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE tbl_accounts 
                    SET email = ?, first_name = ?, last_name = ?, contact_number = ?
                    WHERE account_id = ? AND role = 'parent'
                ");
                $updateStmt->execute([$email, $firstName, $lastName, $contactNumber, $parentId]);
            }
            
            $_SESSION['success_message'] = "Parent account updated successfully.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error updating parent: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Get parent details for editing
    $editParentData = null;
    if (isset($_GET['edit_id'])) {
        $editId = $_GET['edit_id'];
        $stmt = $pdo->prepare("SELECT * FROM tbl_accounts WHERE account_id = ? AND role = 'parent'");
        $stmt->execute([$editId]);
        $editParentData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$editParentData) {
            $_SESSION['error_message'] = "Parent not found.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Get all parents
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $whereClause = "WHERE role = 'parent'";
    $params = [];

    if (!empty($search)) {
        $whereClause .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }

    $stmt = $pdo->prepare("
        SELECT * FROM tbl_accounts 
        $whereClause 
        ORDER BY first_name, last_name
    ");
    $stmt->execute($params);
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $parentCount = count($parents);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
        <meta http-equiv="Pragma" content="no-cache">
        <meta http-equiv="Expires" content="0">
        <title>EduTrack - Parent Account Management</title>
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
            
            .stat-card {
                text-align: center;
                padding: 20px;
            }
            
            .stat-card i {
                font-size: 2.5rem;
                margin-bottom: 15px;
                color: var(--secondary);
            }
            
            .stat-card .number {
                font-size: 2rem;
                font-weight: 700;
                color: var(--dark);
            }
            
            .stat-card .label {
                color: #777;
                font-size: 0.9rem;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .badge-admin {
                background-color: var(--accent);
            }
            
            .table th {
                background-color: var(--light);
                font-weight: 600;
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
            
            .log-row {
                border-left: 4px solid var(--accent);
                transition: all 0.3s;
            }
            
            .log-row:hover {
                background-color: rgba(0,0,0,0.02);
            }
            
            .clickable-card {
                transition: all 0.3s;
                cursor: pointer;
            }
            
            .clickable-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            }
            
            .parent-card {
                transition: transform 0.2s;
            }
            .parent-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            .search-box {
                max-width: 400px;
            }
            .action-buttons .btn {
                margin-right: 5px;
            }
            .table-responsive {
                min-height: 400px;
            }
            .stats-card {
                background: white;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                text-align: center;
                transition: transform 0.3s;
            }
            .stats-card:hover {
                transform: translateY(-5px);
            }
            .stats-card i {
                font-size: 2.5rem;
                margin-bottom: 10px;
            }
            .stats-card.parents { border-left: 4px solid #e74c3c; }
            
            .password-toggle {
                cursor: pointer;
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
            
            .table-action-btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
            
            .action-header {
                display: flex;
                gap: 10px;
                margin-bottom: 15px;
            }
            
            .parent-table th {
                background-color: var(--primary);
                color: white;
            }
            
            .parent-table tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            .parent-table tr:hover {
                background-color: #e9f7fe;
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
                                <li><a href="manage_parents.php" class="active">Parent Accounts</a></li>
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
                        <h3>Parent Account Management</h3>
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
                                    <h1>Parent Account Management</h1>
                                    <p>Manage parent accounts, view details, and archive accounts when necessary</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <i class="fas fa-user-friends fa-4x"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Messages -->
                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?= $_SESSION['success_message'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['success_message']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $_SESSION['error_message'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['error_message']); ?>
                        <?php endif; ?>

                        

                        <!-- Search and Action Section -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <form method="GET" action="">
                                    <div class="input-group search-box">
                                        <input type="text" class="form-control" placeholder="Search parents..." name="search" value="<?= htmlspecialchars($search) ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <?php if (!empty($search)): ?>
                                            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-danger">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-6 text-end">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addParentModal">
                                    <i class="fas fa-plus-circle"></i> Add New Parent
                                </button>
                                <?php if ($parentCount > 0): ?>
                                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editParentModal">
                                    <i class="fas fa-edit"></i> Edit Parent
                                </button>
                                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="fas fa-archive"></i> Archive Parent
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Parents Table -->
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="card-title mb-0">All Parents</h5>
                                    </div>
                                    <div class="col-auto">
                                        <span class="badge bg-primary"><?= $parentCount ?> parent<?= $parentCount != 1 ? 's' : '' ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($parentCount > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover parent-table" id="parentsTable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Contact</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($parents as $parent): ?>
                                            <tr class="log-row">
                                                <td><?= $parent['account_id'] ?></td>
                                                <td><?= htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']) ?></td>
                                                <td><?= htmlspecialchars($parent['email']) ?></td>
                                                <td><?= htmlspecialchars($parent['contact_number']) ?></td>
                                                <td><span class="badge bg-success">Active</span></td>
                                              
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                                    <h4>No parents found</h4>
                                    <p class="text-muted"><?= !empty($search) ? 'Try adjusting your search terms' : 'Get started by adding a new parent' ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Archive Confirmation Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title" id="deleteModalLabel">Confirm Archival</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="parentSelect" class="form-label">Select Parent to Archive:</label>
                                <select class="form-select" id="parentSelect" name="parent_id" required>
                                    <option value="">Select a parent</option>
                                    <?php foreach ($parents as $parent): ?>
                                    <option value="<?= $parent['account_id'] ?>">
                                        <?= htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']) ?> (<?= $parent['email'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="deleteReason" class="form-label">Reason for archival:</label>
                                <select class="form-select" id="deleteReason" name="reason" required>
                                    <option value="">Select a reason</option>
                                    <option value="no_longer_needed">No longer needed</option>
                                    <option value="duplicate">Duplicate account</option>
                                    <option value="inactive">Inactive account</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="otherReasonContainer" style="display: none;">
                                <label for="otherReason" class="form-label">Please specify:</label>
                                <textarea class="form-control" id="otherReason" name="other_reason" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning" name="delete_parent">Archive Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Parent Modal -->
        <div class="modal fade" id="addParentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">Add New Parent</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="contact_number" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="contact_number" name="contact_number">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" name="add_parent">Add Parent</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Parent Modal -->
        <div class="modal fade" id="editParentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title">Edit Parent</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="editParentSelect" class="form-label">Select Parent to Edit:</label>
                                <select class="form-select" id="editParentSelect" name="parent_id" required>
                                    <option value="">Select a parent</option>
                                    <?php foreach ($parents as $parent): ?>
                                    <option value="<?= $parent['account_id'] ?>" <?= isset($editParentData) && $editParentData['account_id'] == $parent['account_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']) ?> (<?= $parent['email'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div id="parentEditForm" style="<?= isset($editParentData) ? '' : 'display: none;' ?>">
                                <div class="mb-3">
                                    <label for="edit_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="edit_email" name="email" value="<?= isset($editParentData) ? htmlspecialchars($editParentData['email']) : '' ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_password" class="form-label">Password (leave blank to keep current)</label>
                                    <input type="password" class="form-control" id="edit_password" name="password">
                                </div>
                                <div class="mb-3">
                                    <label for="edit_first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="edit_first_name" name="first_name" value="<?= isset($editParentData) ? htmlspecialchars($editParentData['first_name']) : '' ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="edit_last_name" name="last_name" value="<?= isset($editParentData) ? htmlspecialchars($editParentData['last_name']) : '' ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_contact_number" class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" id="edit_contact_number" name="contact_number" value="<?= isset($editParentData) ? htmlspecialchars($editParentData['contact_number']) : '' ?>">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-warning" name="edit_parent">Update Parent</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Enhanced logout confirmation
                function confirmLogout() {
                    if (confirm('Are you sure you want to logout?')) {
                        // Clear client-side storage
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
                
                // Show/hide other reason textarea
                document.getElementById('deleteReason').addEventListener('change', function() {
                    const otherReasonContainer = document.getElementById('otherReasonContainer');
                    otherReasonContainer.style.display = this.value === 'other' ? 'block' : 'none';
                });

                // Manage browser history for better navigation
                if (window.history && window.history.pushState) {
                    // Add a new history entry
                    window.history.pushState(null, null, window.location.href);
                    
                    // Handle back/forward navigation
                    window.addEventListener('popstate', function() {
                        // Check if we should reload (optional)
                        if (performance.navigation.type === 2) {
                            window.location.reload();
                        }
                    });
                }
                
                // Handle page visibility changes
                window.onpageshow = function(event) {
                    if (event.persisted) {
                        // Page was loaded from cache (like when using back button)
                        window.location.reload();
                    }
                };

                // toggle submenu
                document.querySelectorAll(".has-submenu > a").forEach(link => {
                    link.addEventListener("click", e => {
                        e.preventDefault();
                        link.parentElement.classList.toggle("open");
                    });
                });
                
                // Auto-open edit modal if edit_id parameter is present
                <?php if (isset($_GET['edit_id'])): ?>
                    var editModal = new bootstrap.Modal(document.getElementById('editParentModal'));
                    editModal.show();
                <?php endif; ?>
                
                // Load parent data when selection changes
                document.getElementById('editParentSelect').addEventListener('change', function() {
                    const parentId = this.value;
                    if (parentId) {
                        // Redirect to page with edit_id parameter
                        window.location.href = '?edit_id=' + parentId;
                    }
                });
            });
        </script>
    </body>
    </html>