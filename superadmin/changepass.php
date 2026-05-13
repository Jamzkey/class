<?php
session_start();
include('assets/inc/db.php');

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$success = '';
$err = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['current_password']) && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
            $current_password = trim($_POST['current_password']);
            $new_password = trim($_POST['new_password']);
            $confirm_password = trim($_POST['confirm_password']);

            // Validation
            if (empty($current_password)) {
                $err = "Current password is required.";
            } elseif (empty($new_password)) {
                $err = "New password is required.";
            } elseif (empty($confirm_password)) {
                $err = "Please confirm your new password.";
            } elseif ($new_password !== $confirm_password) {
                $err = "New password and confirm password do not match.";
            } elseif (strlen($new_password) < 6) {
                $err = "Password must be at least 6 characters long.";
            } else {
                // Fetch current password from users table
                $query = "SELECT password FROM users WHERE user_id = :user_id";
                $stmt = $conn->prepare($query);
                $stmt->execute([':user_id' => $user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $err = "User not found.";
                } elseif (!password_verify($current_password, $user['password'])) {
                    $err = "Current password is incorrect.";
                } else {
                    // Check if new password is same as current
                    if (password_verify($new_password, $user['password'])) {
                        $err = "New password cannot be the same as current password.";
                    } else {
                        // Hash the new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                        // Update the password in the users table
                        $update_query = "UPDATE users SET password = :password WHERE user_id = :user_id";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->execute([
                            ':password' => $hashed_password,
                            ':user_id' => $user_id,
                        ]);

                        if ($update_stmt->rowCount() > 0) {
                            $success = "Password changed successfully!";
                        } else {
                            $err = "Password update failed. Please try again.";
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        $err = "Error: " . $e->getMessage();
    }
}

// Fetch user profile data
try {
    $query = "SELECT full_name, username, role, user_pic FROM users WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        header('Location: index.php');
        exit();
    }
} catch (Exception $e) {
    $err = "Error fetching profile: " . $e->getMessage();
}

// Get current time for greeting
$hour = date('H');
if ($hour < 12) {
    $greeting = 'Good Morning';
    $greeting_icon = 'sun';
} elseif ($hour < 17) {
    $greeting = 'Good Afternoon';
    $greeting_icon = 'sun';
} else {
    $greeting = 'Good Evening';
    $greeting_icon = 'moon';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Change Password | Class Scheduling System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/aq.png">
    
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        body { background: #f1f5f9; }
        
        .page-wrapper { padding: 24px 32px; }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 28px;
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        
        .page-subtitle {
            font-size: 15px;
            opacity: 0.9;
        }
        
        .greeting-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 16px;
            display: inline-block;
        }
        
        .password-card {
            background: white;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .password-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .password-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 32px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .password-title {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }
        
        .password-subtitle {
            color: #64748b;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            font-weight: 500;
            color: #475569;
            margin-bottom: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .form-label i {
            width: 24px;
            color: #667eea;
        }
        
        .password-field-wrapper {
            position: relative;
        }
        
        .form-control {
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            padding: 14px 16px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: #fafbfc;
            padding-right: 45px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
        }
        
        .form-control.is-invalid {
            border-color: #ef4444;
        }
        
        .form-control.is-valid {
            border-color: #22c55e;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #64748b;
            transition: color 0.2s;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: #667eea;
        }
        
        .password-strength {
            margin-top: 10px;
            height: 6px;
            border-radius: 6px;
            background: #e2e8f0;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background 0.3s ease;
            border-radius: 6px;
        }
        
        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #22c55e; width: 100%; }
        
        .password-requirements {
            margin-top: 12px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
            font-size: 13px;
        }
        
        .password-requirements ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }
        
        .password-requirements li {
            color: #64748b;
            margin-bottom: 4px;
        }
        
        .password-requirements li.valid {
            color: #22c55e;
        }
        
        .password-requirements li.invalid {
            color: #ef4444;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            width: 100%;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-save:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            border-radius: 16px;
            border: none;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .alert .close {
            color: white;
            opacity: 0.8;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
            margin-right: 12px;
        }
        
        .user-details {
            text-align: left;
        }
        
        .user-name {
            font-weight: 600;
            color: #0f172a;
        }
        
        .user-role {
            font-size: 12px;
            color: #64748b;
        }
        
        .invalid-feedback {
            display: block;
            color: #ef4444;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .match-feedback {
            font-size: 12px;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .page-wrapper { padding: 16px; }
            .page-header { padding: 20px; }
            .page-title { font-size: 22px; }
            .password-card { padding: 24px; }
        }
    </style>
</head>
<body>
    <div id="wrapper">
        <?php include("assets/inc/nav.php"); ?>
        <?php include("assets/inc/sidebar.php"); ?>

        <div class="content-page">
            <div class="content">
                <div class="container-fluid">
                    <div class="page-wrapper">
                        
                        <!-- Page Header -->
                        <div class="page-header">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h1 class="page-title">
                                        <i class="fas fa-lock mr-2"></i>
                                        Change Password
                                    </h1>
                                    <p class="page-subtitle mb-2">Update your account password for security</p>
                                </div>
                                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                    <span class="greeting-badge">
                                        <i class="fas fa-<?php echo $greeting_icon; ?> mr-2"></i>
                                        <?php echo $greeting; ?>, <?php echo htmlspecialchars(explode(' ', $profile['full_name'])[0]); ?>!
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Alert Messages -->
                        <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?php echo $success; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php elseif ($err): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php echo $err; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php endif; ?>

                        <!-- Password Change Card -->
                        <div class="password-card">
                            <div class="password-header">
                                <div class="password-icon">
                                    <i class="fas fa-key"></i>
                                </div>
                                <h2 class="password-title">Change Your Password</h2>
                                <p class="password-subtitle">Choose a strong, unique password to keep your account secure</p>
                            </div>
                            
                            <div class="user-info">
                                <?php 
                                $name_parts = explode(' ', $profile['full_name']);
                                $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                ?>
                                <div class="user-avatar"><?php echo $initials; ?></div>
                                <div class="user-details">
                                    <div class="user-name"><?php echo htmlspecialchars($profile['full_name']); ?></div>
                                    <div class="user-role">
                                        <i class="fas fa-<?php echo $profile['role'] === 'super_admin' ? 'crown' : 'shield-alt'; ?> mr-1"></i>
                                        <?php echo $profile['role'] === 'super_admin' ? 'Super Administrator' : 'Administrator'; ?>
                                    </div>
                                </div>
                            </div>

                            <form method="POST" id="passwordForm">
                                <div class="form-group">
                                    <label for="current_password" class="form-label">
                                        <i class="fas fa-lock"></i>Current Password
                                    </label>
                                    <div class="password-field-wrapper">
                                        <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Enter your current password" required>
                                        <i class="fas fa-eye password-toggle" onclick="togglePassword('current_password', this)"></i>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="new_password" class="form-label">
                                        <i class="fas fa-key"></i>New Password
                                    </label>
                                    <div class="password-field-wrapper">
                                        <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Enter new password" onkeyup="checkPasswordStrength(); checkPasswordRequirements()" required>
                                        <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password', this)"></i>
                                    </div>
                                    <div class="password-strength">
                                        <div id="passwordStrengthBar" class="password-strength-bar"></div>
                                    </div>
                                    <small id="passwordHelp" class="text-muted"></small>
                                    
                                    <div class="password-requirements" id="passwordRequirements" style="display: none;">
                                        <strong><i class="fas fa-shield-alt mr-1"></i>Password Requirements:</strong>
                                        <ul>
                                            <li id="req-length">At least 6 characters</li>
                                            <li id="req-upper">At least one uppercase letter</li>
                                            <li id="req-lower">At least one lowercase letter</li>
                                            <li id="req-number">At least one number</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-check-circle"></i>Confirm New Password
                                    </label>
                                    <div class="password-field-wrapper">
                                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password" onkeyup="checkPasswordMatch()" required>
                                        <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password', this)"></i>
                                    </div>
                                    <small id="passwordMatch" class="match-feedback"></small>
                                </div>

                                <button type="submit" class="btn-save" id="submitBtn">
                                    <i class="fas fa-save mr-2"></i>Update Password
                                </button>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <div class="rightbar-overlay"></div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId, icon) {
            var field = document.getElementById(fieldId);
            if (field.type === "password") {
                field.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Check password strength
        function checkPasswordStrength() {
            var password = document.getElementById('new_password').value;
            var strengthBar = document.getElementById('passwordStrengthBar');
            var helpText = document.getElementById('passwordHelp');
            
            strengthBar.className = 'password-strength-bar';
            
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                helpText.textContent = '';
            } else if (password.length < 6) {
                strengthBar.classList.add('strength-weak');
                helpText.textContent = '⚠️ Password is too short';
                helpText.style.color = '#ef4444';
            } else if (password.length < 8) {
                strengthBar.classList.add('strength-medium');
                helpText.textContent = '👍 Password strength: Medium';
                helpText.style.color = '#f59e0b';
            } else {
                var hasUpper = /[A-Z]/.test(password);
                var hasLower = /[a-z]/.test(password);
                var hasNumber = /[0-9]/.test(password);
                var hasSpecial = /[!@#$%^&*]/.test(password);
                
                if (hasUpper && hasLower && hasNumber) {
                    strengthBar.classList.add('strength-strong');
                    helpText.textContent = '💪 Password strength: Strong';
                    helpText.style.color = '#22c55e';
                } else {
                    strengthBar.classList.add('strength-medium');
                    helpText.textContent = '👍 Password strength: Good';
                    helpText.style.color = '#f59e0b';
                }
            }
        }
        
        // Check password requirements
        function checkPasswordRequirements() {
            var password = document.getElementById('new_password').value;
            var reqDiv = document.getElementById('passwordRequirements');
            
            if (password.length > 0) {
                reqDiv.style.display = 'block';
            } else {
                reqDiv.style.display = 'none';
                return;
            }
            
            // Length check
            var reqLength = document.getElementById('req-length');
            if (password.length >= 6) {
                reqLength.classList.add('valid');
                reqLength.classList.remove('invalid');
                reqLength.innerHTML = '✓ At least 6 characters';
            } else {
                reqLength.classList.add('invalid');
                reqLength.classList.remove('valid');
                reqLength.innerHTML = '✗ At least 6 characters';
            }
            
            // Uppercase check
            var reqUpper = document.getElementById('req-upper');
            if (/[A-Z]/.test(password)) {
                reqUpper.classList.add('valid');
                reqUpper.classList.remove('invalid');
                reqUpper.innerHTML = '✓ At least one uppercase letter';
            } else {
                reqUpper.classList.add('invalid');
                reqUpper.classList.remove('valid');
                reqUpper.innerHTML = '✗ At least one uppercase letter';
            }
            
            // Lowercase check
            var reqLower = document.getElementById('req-lower');
            if (/[a-z]/.test(password)) {
                reqLower.classList.add('valid');
                reqLower.classList.remove('invalid');
                reqLower.innerHTML = '✓ At least one lowercase letter';
            } else {
                reqLower.classList.add('invalid');
                reqLower.classList.remove('valid');
                reqLower.innerHTML = '✗ At least one lowercase letter';
            }
            
            // Number check
            var reqNumber = document.getElementById('req-number');
            if (/[0-9]/.test(password)) {
                reqNumber.classList.add('valid');
                reqNumber.classList.remove('invalid');
                reqNumber.innerHTML = '✓ At least one number';
            } else {
                reqNumber.classList.add('invalid');
                reqNumber.classList.remove('valid');
                reqNumber.innerHTML = '✗ At least one number';
            }
        }
        
        // Check password match
        function checkPasswordMatch() {
            var newPass = document.getElementById('new_password').value;
            var confirmPass = document.getElementById('confirm_password').value;
            var matchText = document.getElementById('passwordMatch');
            var confirmField = document.getElementById('confirm_password');
            
            if (confirmPass.length === 0) {
                matchText.textContent = '';
                confirmField.classList.remove('is-valid', 'is-invalid');
            } else if (newPass === confirmPass) {
                matchText.textContent = '✓ Passwords match';
                matchText.style.color = '#22c55e';
                confirmField.classList.add('is-valid');
                confirmField.classList.remove('is-invalid');
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.style.color = '#ef4444';
                confirmField.classList.add('is-invalid');
                confirmField.classList.remove('is-valid');
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    </script>
</body>
</html>