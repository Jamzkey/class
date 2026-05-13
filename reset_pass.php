<?php
include('assets/inc/db.php');

// Error or success messages
$err = $success = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize inputs
    $username = isset($_POST['username']) ? trim($_POST['username']) : null;
    $newPassword = isset($_POST['new_password']) ? trim($_POST['new_password']) : null;
    $confirmPassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : null;

    // Check if all required fields are filled
    if ($username && $newPassword && $confirmPassword) {
        try {
            // Check if the username exists in the `users` table
            $stmt = $conn->prepare("
                SELECT user_id, username, role, status 
                FROM users
                WHERE username = :username
            ");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $err = "Your account has been deactivated. Please contact the administrator.";
                } else {
                    // Check if new password matches the confirmation
                    if ($newPassword !== $confirmPassword) {
                        $err = "The new password and confirmation do not match.";
                    } elseif (strlen($newPassword) < 6) {
                        $err = "Password must be at least 6 characters long.";
                    } else {
                        // Hash the new password
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                        // Update the password in the `users` table
                        $stmt = $conn->prepare("UPDATE users SET password = :password WHERE username = :username");
                        $stmt->execute([
                            ':password' => $hashedPassword,
                            ':username' => $username
                        ]);

                        $success = "Your password has been updated successfully. You can now login with your new password.";
                        
                        // Log password reset activity (optional)
                        logPasswordReset($conn, $user['user_id']);
                    }
                }
            } else {
                $err = "The username you entered is not registered.";
            }
        } catch (PDOException $e) {
            $err = "System error. Please try again later.";
            error_log("Password Reset Error: " . $e->getMessage());
        }
    } else {
        $err = "Please fill in all fields.";
    }
}

// Function to log password reset activity
function logPasswordReset($conn, $user_id) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO user_logs (user_id, action, ip_address, user_agent, created_at) 
            VALUES (:user_id, 'password_reset', :ip, :agent, NOW())
        ");
        $stmt->execute([
            ':user_id' => $user_id,
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
    <title>Reset Password | Class-Sched</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta content="Reset Your Password" name="description" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <link rel="shortcut icon" href="assets/images/aq.png">
    
    <!-- CSS -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        html, body {
            overflow: auto !important;
            height: auto !important;
            min-height: 100vh;
        }
        
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 20px 0;
        }
        
        /* Animated background elements */
        body::before {
            content: '';
            position: fixed;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 50%, rgba(56, 189, 248, 0.1) 0%, transparent 50%);
            animation: rotate 30s linear infinite;
            pointer-events: none;
        }
        
        body::after {
            content: '';
            position: fixed;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 70% 50%, rgba(168, 85, 247, 0.1) 0%, transparent 50%);
            animation: rotate 25s linear infinite reverse;
            pointer-events: none;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .reset-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
            position: relative;
            z-index: 10;
            margin: 20px auto;
        }
        
        .reset-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: visible;
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
        
        .reset-header {
            padding: 30px 32px 20px;
            text-align: center;
        }
        
        .reset-logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 18px;
            padding: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            margin: 0 auto 20px;
            transition: transform 0.3s ease;
        }
        
        .reset-logo:hover {
            transform: scale(1.05);
        }
        
        .reset-title {
            color: #0f172a;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        
        .reset-subtitle {
            color: #64748b;
            font-size: 14px;
            font-weight: 400;
        }
        
        .reset-body {
            padding: 8px 32px 35px;
        }
        
        .input-group-custom {
            margin-bottom: 18px;
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
            pointer-events: none;
        }
        
        .form-control {
            width: 100%;
            height: 50px;
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
        
        .password-strength {
            margin-top: 8px;
            padding: 0 4px;
        }
        
        .strength-bar {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 4px;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-text {
            font-size: 11px;
            color: #64748b;
            display: flex;
            justify-content: space-between;
        }
        
        .strength-label {
            font-weight: 500;
        }
        
        .btn-reset {
            width: 100%;
            height: 50px;
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
            margin-top: 20px;
        }
        
        .btn-reset::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.4);
        }
        
        .btn-reset:hover::before {
            left: 100%;
        }
        
        .btn-reset:active {
            transform: translateY(0);
        }
        
        .btn-reset.loading {
            pointer-events: none;
            opacity: 0.9;
        }
        
        .btn-reset.loading .btn-text {
            opacity: 0;
        }
        
        .btn-reset.loading::after {
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
        
        .reset-footer {
            text-align: center;
            padding-top: 20px;
        }
        
        .back-login {
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-login:hover {
            color: #0f172a;
            transform: translateX(-4px);
        }
        
        /* System info */
        .system-info {
            text-align: center;
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            font-weight: 400;
        }
        
        .system-info i {
            margin-right: 6px;
        }
        
        /* Password requirements hint */
        .requirements-hint {
            margin-top: 14px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            font-size: 12px;
            color: #64748b;
        }
        
        .requirements-hint i {
            margin-right: 6px;
            color: #3b82f6;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
        }
        
        .requirement-item i {
            font-size: 10px;
            margin-right: 8px;
        }
        
        .requirement-item.valid i {
            color: #22c55e;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            body {
                padding: 15px 0;
                align-items: flex-start;
            }
            
            .reset-container {
                padding: 15px;
                margin: 10px auto;
            }
            
            .reset-header {
                padding: 25px 20px 15px;
            }
            
            .reset-body {
                padding: 5px 20px 30px;
            }
            
            .reset-title {
                font-size: 22px;
            }
            
            .reset-subtitle {
                font-size: 13px;
            }
            
            .reset-logo {
                width: 60px;
                height: 60px;
                padding: 10px;
            }
        }
        
        @media (max-height: 700px) {
            body {
                align-items: flex-start;
                padding-top: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <div class="reset-logo">
                    <img src="assets/images/aq.png" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <h2 class="reset-title">Reset Password</h2>
                <p class="reset-subtitle">Enter your username and new password</p>
            </div>
            
            <div class="reset-body">
                <!-- Reset Password Form -->
                <form method="post" id="resetForm">
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
                                   name="new_password" 
                                   id="new_password" 
                                   placeholder="New Password"
                                   required
                                   autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password', 'toggleIcon1')" aria-label="Toggle password visibility">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                        
                        <!-- Password Strength Indicator -->
                        <div class="password-strength" id="passwordStrength" style="display: none;">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <div class="strength-text">
                                <span class="strength-label" id="strengthLabel">Weak</span>
                                <span id="strengthScore">0%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="input-group-custom">
                        <div class="input-wrapper">
                            <i class="fas fa-check-circle input-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   name="confirm_password" 
                                   id="confirm_password" 
                                   placeholder="Confirm New Password"
                                   required
                                   autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')" aria-label="Toggle password visibility">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Password Requirements Hint -->
                    <div class="requirements-hint">
                        <div class="requirement-item" id="reqLength">
                            <i class="fas fa-circle"></i>
                            <span>At least 6 characters</span>
                        </div>
                        <div class="requirement-item" id="reqUpper">
                            <i class="fas fa-circle"></i>
                            <span>At least one uppercase letter</span>
                        </div>
                        <div class="requirement-item" id="reqLower">
                            <i class="fas fa-circle"></i>
                            <span>At least one lowercase letter</span>
                        </div>
                        <div class="requirement-item" id="reqNumber">
                            <i class="fas fa-circle"></i>
                            <span>At least one number</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-reset" id="resetBtn">
                        <span class="btn-text">Reset Password</span>
                    </button>
                </form>

                <div class="reset-footer">
                    <a href="index.php" class="back-login">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Login</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="system-info">
            <i class="fas fa-shield-alt"></i>
            Aquatic Monitoring System v2.0
            <span style="margin: 0 8px;">•</span>
            <i class="far fa-copyright"></i>
            <?php echo date('Y'); ?>
        </div>
    </div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>
    
    <script>
        // Password visibility toggle
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
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

        // Password strength checker
        const newPassword = document.getElementById('new_password');
        const strengthDiv = document.getElementById('passwordStrength');
        const strengthFill = document.getElementById('strengthFill');
        const strengthLabel = document.getElementById('strengthLabel');
        const strengthScore = document.getElementById('strengthScore');
        
        // Requirement elements
        const reqLength = document.getElementById('reqLength');
        const reqUpper = document.getElementById('reqUpper');
        const reqLower = document.getElementById('reqLower');
        const reqNumber = document.getElementById('reqNumber');

        newPassword.addEventListener('input', function() {
            const password = this.value;
            
            if (password.length > 0) {
                strengthDiv.style.display = 'block';
            } else {
                strengthDiv.style.display = 'none';
                return;
            }
            
            // Check requirements
            const hasLength = password.length >= 6;
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            
            // Update requirement indicators
            updateRequirement(reqLength, hasLength);
            updateRequirement(reqUpper, hasUpper);
            updateRequirement(reqLower, hasLower);
            updateRequirement(reqNumber, hasNumber);
            
            // Calculate strength score
            let score = 0;
            if (hasLength) score += 25;
            if (hasUpper) score += 25;
            if (hasLower) score += 25;
            if (hasNumber) score += 25;
            
            // Bonus for longer passwords
            if (password.length >= 10) score = Math.min(100, score + 10);
            
            // Update strength bar
            strengthFill.style.width = score + '%';
            strengthScore.textContent = score + '%';
            
            // Update strength label and color
            if (score < 40) {
                strengthLabel.textContent = 'Weak';
                strengthFill.style.background = '#ef4444';
            } else if (score < 70) {
                strengthLabel.textContent = 'Medium';
                strengthFill.style.background = '#f59e0b';
            } else {
                strengthLabel.textContent = 'Strong';
                strengthFill.style.background = '#22c55e';
            }
        });
        
        function updateRequirement(element, isValid) {
            const icon = element.querySelector('i');
            if (isValid) {
                element.classList.add('valid');
                icon.classList.remove('fa-circle');
                icon.classList.add('fa-check-circle');
                icon.style.color = '#22c55e';
            } else {
                element.classList.remove('valid');
                icon.classList.remove('fa-check-circle');
                icon.classList.add('fa-circle');
                icon.style.color = '#94a3b8';
            }
        }

        // Password match validation
        const confirmPassword = document.getElementById('confirm_password');
        
        confirmPassword.addEventListener('input', function() {
            if (this.value && this.value === newPassword.value) {
                this.style.borderColor = '#22c55e';
            } else if (this.value) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#e2e8f0';
            }
        });

        // Form submission with loading effect
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const newPass = newPassword.value;
            const confirmPass = confirmPassword.value;
            
            // Basic validation
            if (!username || !newPass || !confirmPass) {
                e.preventDefault();
                
                Swal.fire({
                    title: 'Error!',
                    text: 'Please fill in all fields',
                    icon: 'error',
                    confirmButtonColor: '#3b82f6'
                });
                
                return;
            }
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                
                Swal.fire({
                    title: 'Error!',
                    text: 'Passwords do not match',
                    icon: 'error',
                    confirmButtonColor: '#3b82f6'
                });
                
                return;
            }
            
            if (newPass.length < 6) {
                e.preventDefault();
                
                Swal.fire({
                    title: 'Error!',
                    text: 'Password must be at least 6 characters long',
                    icon: 'error',
                    confirmButtonColor: '#3b82f6'
                });
                
                return;
            }
            
            // Show loading state
            const btn = document.getElementById('resetBtn');
            btn.classList.add('loading');
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
        
        // Remove error class on input
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('error');
            });
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Ensure button is visible on small screens
        window.addEventListener('load', function() {
            const btn = document.getElementById('resetBtn');
            if (btn) {
                btn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    </script>

    <?php if (isset($success)): ?>
    <script>
        Swal.fire({
            title: 'Success!',
            text: '<?php echo $success; ?>',
            icon: 'success',
            confirmButtonColor: '#3b82f6'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'index.php';
            }
        });
        
        // Auto redirect after 5 seconds
        setTimeout(() => {
            window.location.href = 'index.php';
        }, 5000);
    </script>
    <?php endif; ?>

    <?php if (isset($err)): ?>
    <script>
        Swal.fire({
            title: 'Error!',
            text: '<?php echo $err; ?>',
            icon: 'error',
            confirmButtonColor: '#3b82f6'
        });
    </script>
    <?php endif; ?>
</body>
</html>