<?php
session_start();
include('assets/inc/db.php');

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    redirectBasedOnRole($_SESSION['role']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $remember = isset($_POST['remember']) ? true : false;

    try {
        // Query to fetch user details by username from users table
        $stmt = $conn->prepare("
            SELECT user_id, full_name, username, password, role, status, phone_number, user_pic 
            FROM users 
            WHERE username = :username
        ");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Check if account is active
            if ($user['status'] === 'inactive') {
                $error_message = "Your account has been deactivated. Please contact the administrator.";
            } elseif ($user['status'] === 'pending') {
                $error_message = "Your account is pending approval. Please wait for administrator approval.";
            } else {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['phone_number'] = $user['phone_number'];
                $_SESSION['user_pic'] = $user['user_pic'];

                // Set remember me cookie if checked
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), '/');
                }

                // Log login activity (optional)
                logUserActivity($conn, $user['user_id'], 'login');

                // Redirect based on role
                redirectBasedOnRole($user['role']);
                exit();
            }
        } else {
            $error_message = "Invalid username or password. Please try again.";
        }
    } catch (PDOException $e) {
        $error_message = "System error. Please try again later.";
        error_log("Login Error: " . $e->getMessage());
    }
}

// Function to redirect based on role
function redirectBasedOnRole($role) {
    switch ($role) {
        case 'super_admin':
            header('Location: superadmin/dashboard.php');
            break;
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'faculty':
            header('Location: faculty/dashboard.php');
            break;
        default:
            header('Location: index.php');
    }
    exit();
}

// Function to log user activity
function logUserActivity($conn, $user_id, $action) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO user_logs (user_id, action, ip_address, user_agent, created_at) 
            VALUES (:user_id, :action, :ip, :agent, NOW())
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':action' => $action,
            ':ip' => $_SERVER['REMOTE_ADDR'],
            ':agent' => $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch (Exception $e) {
        // Silent fail - logging is optional
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Login | Class-Sched</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Professional Login Portal" name="description" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <link rel="shortcut icon" href="assets/images/aq.png">
    
    <!-- CSS -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 50%, rgba(56, 189, 248, 0.1) 0%, transparent 50%);
            animation: rotate 30s linear infinite;
        }
        
        body::after {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 70% 50%, rgba(168, 85, 247, 0.1) 0%, transparent 50%);
            animation: rotate 25s linear infinite reverse;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .login-container {
            max-width: 420px;
            width: 100%;
            padding: 20px;
            position: relative;
            z-index: 10;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            padding: 40px 32px 20px;
            text-align: center;
        }
        
        .login-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 20px;
            padding: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            margin: 0 auto 24px;
            transition: transform 0.3s ease;
        }
        
        .login-logo:hover {
            transform: scale(1.05);
        }
        
        .login-title {
            color: #0f172a;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .login-subtitle {
            color: #64748b;
            font-size: 15px;
            font-weight: 400;
        }
        
        .login-body {
            padding: 8px 32px 40px;
        }
        
        .input-group-custom {
            margin-bottom: 20px;
        }
        
        .input-wrapper {
            position: relative;
            width: 100%;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            transition: all 0.3s ease;
            z-index: 1;
            font-size: 16px;
        }
        
        .form-control {
            width: 100%;
            height: 52px;
            padding: 0 16px 0 48px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 400;
            color: #0f172a;
            background: #ffffff;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .form-control::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }
        
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            background: #ffffff;
        }
        
        .form-control:focus + .input-icon {
            color: #3b82f6;
        }
        
        .form-control.error {
            border-color: #ef4444;
        }
        
        .form-control.error + .input-icon {
            color: #ef4444;
        }
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            z-index: 2;
            transition: all 0.3s ease;
            padding: 8px;
            background: transparent;
            border: none;
            outline: none;
        }
        
        .password-toggle:hover {
            color: #3b82f6;
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0 24px;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            cursor: pointer;
            accent-color: #3b82f6;
            border-radius: 4px;
        }
        
        .checkbox-wrapper span {
            color: #475569;
            font-size: 14px;
            font-weight: 500;
            user-select: none;
        }
        
        .forgot-link {
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .forgot-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: #3b82f6;
            transition: width 0.3s ease;
        }
        
        .forgot-link:hover::after {
            width: 100%;
        }
        
        .btn-login {
            width: 100%;
            height: 52px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.3);
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.4);
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.9;
        }
        
        .btn-login.loading .btn-text {
            opacity: 0;
        }
        
        .btn-login.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        }
        
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        .login-footer {
            text-align: center;
            padding-top: 24px;
        }
        
        .back-home {
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-home:hover {
            color: #0f172a;
            transform: translateX(-4px);
        }
        
        .signup-link {
            margin-top: 16px;
            text-align: center;
        }
        
        .signup-link a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .signup-link a:hover {
            color: #2563eb;
            text-decoration: underline;
        }
        
        .signup-link span {
            color: #64748b;
            font-size: 14px;
        }
        
        .alert {
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 24px;
            border: none;
            animation: shake 0.5s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-left: 4px solid #22c55e;
        }
        
        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }
        
        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.95);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }
        
        .loading-spinner {
            width: 48px;
            height: 48px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        /* System info */
        .system-info {
            text-align: center;
            margin-top: 24px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            font-weight: 400;
        }
        
        .system-info i {
            margin-right: 6px;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 16px;
            }
            
            .login-header {
                padding: 32px 24px 16px;
            }
            
            .login-body {
                padding: 8px 24px 32px;
            }
            
            .login-title {
                font-size: 24px;
            }
            
            .login-subtitle {
                font-size: 14px;
            }
            
            .form-options {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <img src="assets/images/aq.png" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <h2 class="login-title">Welcome Back</h2>
                <p class="login-subtitle">Sign in to access your account</p>
            </div>
            
            <div class="login-body">
                <!-- Error/Success Messages -->
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['timeout']) && $_GET['timeout'] == 1): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-clock"></i>
                        <span>Your session has expired. Please login again.</span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['logout']) && $_GET['logout'] == 1): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i>
                        <span>You have been successfully logged out.</span>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['registered']) && $_GET['registered'] == 1): ?>
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-clock"></i>
                        <span>Registration successful! Please wait for admin approval before logging in.</span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] == 'user_not_found'): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-user-slash"></i>
                        <span>User account not found.</span>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="post" id="loginForm">
                    <div class="input-group-custom">
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" 
                                   class="form-control" 
                                   name="username" 
                                   id="username" 
                                   placeholder="Username"
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                   required
                                   autocomplete="username"
                                   autofocus>
                        </div>
                    </div>
                    
                    <div class="input-group-custom">
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   name="password" 
                                   id="password" 
                                   placeholder="Password"
                                   required
                                   autocomplete="current-password">
                            <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" name="remember" id="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="reset_pass.php" class="forgot-link">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn-login" id="loginBtn">
                        <span class="btn-text">Sign In</span>
                    </button>
                </form>

                <div class="signup-link">
                    <span>Don't have an account? </span>
                    <a href="signup.php">Sign up as Faculty</a>
                </div>

                <div class="login-footer">
                    
                </div>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="system-info">
            <i class="fas fa-shield-alt"></i>
            Class Scheduling System v2.0
            <span style="margin: 0 8px;">•</span>
            <i class="far fa-copyright"></i>
            <?php echo date('Y'); ?>
        </div>
    </div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const loadingOverlay = document.getElementById('loadingOverlay');
        
        // Password visibility toggle
        function togglePassword() {
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form submission with loading effect
        loginForm.addEventListener('submit', function(e) {
            const username = usernameInput.value.trim();
            const password = passwordInput.value;
            
            // Basic validation
            if (!username || !password) {
                e.preventDefault();
                
                if (!username) {
                    usernameInput.classList.add('error');
                }
                if (!password) {
                    passwordInput.classList.add('error');
                }
                
                Swal.fire({
                    title: 'Error!',
                    text: 'Please enter both username and password',
                    icon: 'error',
                    confirmButtonColor: '#3b82f6'
                });
                
                return;
            }
            
            // Show loading state
            loginBtn.classList.add('loading');
            loadingOverlay.style.display = 'flex';
        });

        // Remove error class on input
        usernameInput.addEventListener('input', function() {
            this.classList.remove('error');
        });
        
        passwordInput.addEventListener('input', function() {
            this.classList.remove('error');
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Check for saved username in localStorage
        const savedUsername = localStorage.getItem('rememberedUsername');
        if (savedUsername) {
            usernameInput.value = savedUsername;
            document.getElementById('remember').checked = true;
        }

        // Save username if remember me is checked
        loginForm.addEventListener('submit', function() {
            if (document.getElementById('remember').checked) {
                localStorage.setItem('rememberedUsername', usernameInput.value);
            } else {
                localStorage.removeItem('rememberedUsername');
            }
        });

        // Show welcome message
        <?php if (!isset($_POST['username']) && !isset($_GET['logout']) && !isset($_GET['timeout']) && !isset($_GET['error']) && !isset($_GET['registered'])): ?>
        setTimeout(() => {
            Swal.fire({
                title: 'Welcome!',
                text: 'Please sign in to continue',
                icon: 'info',
                confirmButtonColor: '#0f172a',
                timer: 2000,
                showConfirmButton: false,
                backdrop: false
            });
        }, 300);
        <?php endif; ?>
        
        // Keyboard shortcut for submit (Ctrl/Cmd + Enter)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                loginForm.dispatchEvent(new Event('submit'));
            }
        });
        
        // Focus effect on input wrapper
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.01)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>