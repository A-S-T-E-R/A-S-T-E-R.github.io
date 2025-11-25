<?php
session_start();
require 'db_connect.php';

// Enhanced cache prevention
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect if coming from logout or session expired
if (isset($_GET['logout']) || isset($_GET['session_expired'])) {
    header("Location: index.php");
    exit();
}

$error = '';
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("
        SELECT account_id, username, password, role, first_name, last_name 
        FROM tbl_accounts 
        WHERE username = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row['password'])) {
            $_SESSION['account_id'] = $row['account_id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['fullname'] = $row['first_name'] . " " . $row['last_name'];

            $redirect_pages = [
                'student' => 'student_dashboard.php',
                'parent' => 'parent_dashboard.php',
                'teacher' => 'teacher_dashboard.php',
                'admin' => 'admin_dashboard.php'
            ];
            
            $role = $row['role'];
            $redirect = isset($redirect_pages[$role]) ? $redirect_pages[$role] : 'student_dashboard.php';
            
            header("Location: $redirect");
            exit();
        } else {
            $error = "Invalid username or password!";
        }
    } else {
        $error = "Invalid username or password!";
    }
}

// Handle career test access
if (isset($_POST['take_career_test'])) {
    // Redirect to career test page (guests can access this)
    header("Location: careerpath_anonymous.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>EduTrack Portal - Login</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .login-container {
            background: white;
            padding: 30px 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 420px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary), var(--accent));
        }

        .logo {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        h2 {
            margin-bottom: 5px;
            color: var(--primary);
            font-weight: 600;
        }

        .subtitle {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 25px;
            line-height: 1.4;
        }

        input {
            width: 100%;
            padding: 14px;
            margin: 10px 0;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 1rem;
            color: #666;
            user-select: none;
            transition: color 0.3s;
        }

        .toggle-password:hover {
            color: var(--secondary);
        }

        button {
            width: 100%;
            padding: 14px;
            background-color: var(--secondary);
            border: none;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .career-test-btn {
            background: linear-gradient(135deg, var(--accent), #16a085);
            margin-top: 15px;
        }

        .career-test-btn:hover {
            background: linear-gradient(135deg, #16a085, var(--accent));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 188, 156, 0.3);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: #999;
            font-size: 0.9rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e1e5e9;
        }

        .divider::before {
            margin-right: 10px;
        }

        .divider::after {
            margin-left: 10px;
        }

        .note {
            font-size: 0.85rem;
            color: #777;
            margin-top: 20px;
            line-height: 1.5;
        }

        .error-message {
            background: #ffe5e5;
            color: #d9534f;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #d9534f;
            text-align: left;
        }

        .success-message {
            background: #e6ffed;
            color: #28a745;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
            text-align: left;
        }

        .feature-list {
            text-align: left;
            margin: 20px 0;
            padding: 0;
            list-style: none;
        }

        .feature-list li {
            padding: 8px 0;
            color: #555;
            font-size: 0.9rem;
        }

        .feature-list li::before {
            content: '✓';
            color: var(--accent);
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <h2>Welcome to EduTrack</h2>
        <p class="subtitle">Your comprehensive academic progress tracking platform</p>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <strong>⚠️ Login Failed:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['logout']) && $_GET['logout'] == 1): ?>
            <div class="success-message">
                <strong>✅ Success:</strong> You have been successfully logged out.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['registered']) && $_GET['registered'] == 1): ?>
            <div class="success-message">
                <strong>🎉 Welcome!</strong> Your account has been created successfully. Please log in.
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="text" name="username" placeholder="👤 Enter your username" required autofocus>

            <div class="password-wrapper">
                <input type="password" id="password" name="password" placeholder="🔒 Enter your password" required>
                <span class="toggle-password" onclick="togglePassword()">👁️</span>
            </div>

            <button type="submit" name="login">
                <i class="fas fa-sign-in-alt"></i> Login to Portal
            </button>
        </form>

        <div class="divider">OR</div>

        <form method="post">
            <button type="submit" name="take_career_test" class="career-test-btn">
                <i class="fas fa-clipboard-check"></i> Take Career Test
            </button>
        </form>

      

        <p class="note">
            <strong>New to Career Test?</strong> Take our assessment to discover your ideal career path based on your skills and interests. No login required!
        </p>
    </div>

    <!-- Font Awesome for icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>

    <script>
    // Password visibility toggle
    function togglePassword() {
        const passwordInput = document.getElementById("password");
        const toggle = document.querySelector(".toggle-password");
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            toggle.textContent = "👁️‍🗨️";
        } else {
            passwordInput.type = "password";
            toggle.textContent = "👁️";
        }
    }

    // Enhanced input animations
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    });

    // Enhanced back button prevention
    window.onload = function() {
        if (window.history && window.history.pushState) {
            window.history.pushState(null, null, window.location.href);
            window.onpopstate = function() {
                window.history.pushState(null, null, window.location.href);
            };
        }
        
        if (performance.navigation.type === 2) {
            window.location.reload(true);
        }

        // Add some interactive effects
        const buttons = document.querySelectorAll('button');
        buttons.forEach(button => {
            button.addEventListener('mousedown', function() {
                this.style.transform = 'translateY(0)';
            });
            button.addEventListener('mouseup', function() {
                this.style.transform = 'translateY(-2px)';
            });
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    };

    // Add floating animation to career test button
    document.addEventListener('DOMContentLoaded', function() {
        const careerBtn = document.querySelector('.career-test-btn');
        if (careerBtn) {
            setInterval(() => {
                careerBtn.style.transform = 'translateY(-3px)';
                setTimeout(() => {
                    careerBtn.style.transform = 'translateY(-2px)';
                }, 500);
            }, 2000);
        }
    });
    </script>
</body>
</html>