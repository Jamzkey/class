<?php
include('assets/inc/db.php');

$err = $success = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $phone_number = trim($_POST['phone_number']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $specialization = trim($_POST['specialization']);

    // Validation
    if (!$full_name || !$username || !$password || !$confirm_password) {
        $err = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $err = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $err = "Password must be at least 6 characters long.";
    } elseif (!empty($phone_number) && !validatePhilippinePhone($phone_number)) {
        $err = "Please enter a valid Philippine phone number (e.g., 09123456789 or +639123456789).";
    } else {
        try {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            if ($stmt->fetch()) {
                $err = "Username already taken. Please choose another.";
            } else {
                // Begin transaction
                $conn->beginTransaction();
                
                // Format phone number (remove spaces and special chars, ensure PH format)
                $formatted_phone = !empty($phone_number) ? formatPhilippinePhone($phone_number) : null;
                
                // Insert into users table with pending status
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    INSERT INTO users (full_name, username, password, role, status, phone_number, user_pic) 
                    VALUES (:full_name, :username, :password, 'faculty', 'pending', :phone_number, 'assets/images/default-profile.png')
                ");
                $stmt->execute([
                    ':full_name' => $full_name,
                    ':username' => $username,
                    ':password' => $hashedPassword,
                    ':phone_number' => $formatted_phone
                ]);
                
                $user_id = $conn->lastInsertId();
                
                // Insert into instructors table
                $stmt = $conn->prepare("
                    INSERT INTO instructors (user_id, specialization) 
                    VALUES (:user_id, :specialization)
                ");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':specialization' => $specialization
                ]);
                
                $conn->commit();
                $success = "Registration successful! Your account is pending approval. You will be notified once approved.";
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $err = "Registration failed. Please try again.";
            error_log("Signup Error: " . $e->getMessage());
        }
    }
}

// Function to validate Philippine phone number
function validatePhilippinePhone($phone) {
    // Remove all non-numeric characters except +
    $phone = preg_replace('/[^\d+]/', '', $phone);
    
    // Check if it starts with +63, 63, or 0
    if (preg_match('/^(\+63|63|0)/', $phone)) {
        // Remove prefix for length check
        $clean = preg_replace('/^(\+63|63|0)/', '', $phone);
        // Should be 10 digits (9 for mobile, but typically 10)
        return strlen($clean) === 10 && is_numeric($clean);
    }
    
    return false;
}

// Function to format Philippine phone number to standard format
function formatPhilippinePhone($phone) {
    // Remove all non-numeric characters except +
    $phone = preg_replace('/[^\d+]/', '', $phone);
    
    // Convert to standard format: +63XXXXXXXXXX
    if (preg_match('/^0/', $phone)) {
        // Convert 09XXXXXXXXX to +639XXXXXXXXX
        $phone = '+63' . substr($phone, 1);
    } elseif (preg_match('/^63/', $phone) && !preg_match('/^\+/', $phone)) {
        // Convert 63XXXXXXXXXX to +63XXXXXXXXXX
        $phone = '+' . $phone;
    }
    
    return $phone;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Faculty Sign Up | Class Scheduling System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/aq.png">
    
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
            padding: 30px 0;
        }
        
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
        
        .signup-container {
            max-width: 480px;
            width: 100%;
            padding: 20px;
            position: relative;
            z-index: 10;
            margin: 20px auto;
        }
        
        .signup-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: visible;
            animation: fadeInUp 0.6s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .signup-header {
            padding: 30px 32px 20px;
            text-align: center;
        }
        
        .signup-logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 18px;
            padding: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            margin: 0 auto 20px;
        }
        
        .signup-title {
            color: #0f172a;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        
        .signup-subtitle {
            color: #64748b;
            font-size: 14px;
        }
        
        .signup-body {
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
            z-index: 1;
            font-size: 16px;
            pointer-events: none;
        }
        
        .form-control {
            width: 100%;
            height: 48px;
            padding: 0 16px 0 48px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            color: #0f172a;
            background: #ffffff;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .form-control.error {
            border-color: #ef4444;
        }
        
        .form-control.valid {
            border-color: #22c55e;
        }
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            z-index: 2;
            padding: 8px;
            background: transparent;
            border: none;
        }
        
        .password-toggle:hover {
            color: #3b82f6;
        }
        
        .phone-hint {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
            padding-left: 4px;
        }
        
        .phone-hint i {
            margin-right: 4px;
        }
        
        .btn-signup {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.3);
        }
        
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.4);
        }
        
        .btn-signup.loading {
            pointer-events: none;
            opacity: 0.9;
        }
        
        .signup-footer {
            text-align: center;
            padding-top: 20px;
        }
        
        .back-login {
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-login:hover {
            color: #0f172a;
        }
        
        .system-info {
            text-align: center;
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
        }
        
        .note-box {
            background: #eff6ff;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        
        .required-label::after {
            content: ' *';
            color: #ef4444;
            font-weight: 600;
        }
        
        @media (max-width: 480px) {
            .signup-header { padding: 25px 20px 15px; }
            .signup-body { padding: 5px 20px 30px; }
            .signup-title { font-size: 22px; }
        }
    </style>
</head>

<body>
    <div class="signup-container">
        <div class="signup-card">
            <div class="signup-header">
                <div class="signup-logo">
                    <img src="assets/images/aq.png" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <h2 class="signup-title">Faculty Registration</h2>
                <p class="signup-subtitle">Create your faculty account</p>
            </div>
            
            <div class="signup-body">
                <div class="note-box">
                    <i class="fas fa-info-circle mr-2"></i>
                    Your account will require admin approval before you can log in.
                </div>
                
                <form method="post" id="signupForm">
                    <div class="input-group-custom">
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" class="form-control" name="full_name" id="full_name" placeholder="Full Name" required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="input-group-custom">
                        <div class="input-wrapper">
                            <i class="fas fa-at input-icon"></i>
                            <input type="text" class="form-control" name="username" id="username" placeholder="Username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="input-group-custom">
                        <div class="input-wrapper">
                            <i class="fas fa-phone input-icon"></i>
                            <input type="tel" class="form-control" name="phone_number" id="phone_number" placeholder="Phone Number (Optional)" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                        </div>
                        <div class="phone-hint">
                            <i class="fas fa-info-circle"></i>
                            Format: 09123456789 or +639123456789
                        </div>
                    </div>
                    
                    <div class="input-group-custom">
                        <div class="input-wrapper">
                            <i class="fas fa-graduation-cap input-icon"></i>
                            <input type="text" class="form-control" name="specialization" id="specialization" placeholder="Specialization (Optional)" value="<?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="input-group-custom">
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="input-group-custom">
                        <div class="input-wrapper">
                            <i class="fas fa-check-circle input-icon"></i>
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-signup" id="signupBtn">
                        <span class="btn-text">Register</span>
                    </button>
                </form>

                <div class="signup-footer">
                    <a href="index.php" class="back-login">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Login</span>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="system-info">
            <i class="fas fa-shield-alt"></i>
            Class Scheduling System v2.0
            <span style="margin: 0 8px;">•</span>
            <i class="far fa-copyright"></i>
            <?php echo date('Y'); ?>
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Philippine phone number validation and formatting
        const phoneInput = document.getElementById('phone_number');
        
        phoneInput.addEventListener('input', function(e) {
            let value = this.value.replace(/[^\d+]/g, '');
            
            // Auto-format as user types
            if (value.startsWith('0')) {
                // Don't auto-convert, just validate
                this.classList.remove('error', 'valid');
                if (value.length >= 11) {
                    validatePhoneNumber(value);
                }
            } else if (value.startsWith('63')) {
                this.classList.remove('error', 'valid');
                if (value.length >= 12) {
                    validatePhoneNumber(value);
                }
            } else if (value.startsWith('+63')) {
                this.classList.remove('error', 'valid');
                if (value.length >= 13) {
                    validatePhoneNumber(value);
                }
            } else if (value.length > 0 && !value.startsWith('0') && !value.startsWith('63') && !value.startsWith('+')) {
                // Auto-prefix with 0 if user starts typing numbers
                if (/^\d+$/.test(value) && value.length <= 10) {
                    // Let them type freely
                }
            }
            
            this.value = value;
        });
        
        phoneInput.addEventListener('blur', function() {
            if (this.value.trim() !== '') {
                validatePhoneNumber(this.value);
            } else {
                this.classList.remove('error', 'valid');
            }
        });
        
        function validatePhoneNumber(phone) {
            const clean = phone.replace(/[^\d+]/g, '');
            let isValid = false;
            
            if (clean.startsWith('+63')) {
                const digits = clean.substring(3);
                isValid = digits.length === 10 && /^\d+$/.test(digits);
            } else if (clean.startsWith('63')) {
                const digits = clean.substring(2);
                isValid = digits.length === 10 && /^\d+$/.test(digits);
            } else if (clean.startsWith('0')) {
                const digits = clean.substring(1);
                isValid = digits.length === 10 && /^\d+$/.test(digits);
            }
            
            if (isValid) {
                phoneInput.classList.add('valid');
                phoneInput.classList.remove('error');
            } else {
                phoneInput.classList.add('error');
                phoneInput.classList.remove('valid');
            }
            
            return isValid;
        }
        
        // Password match validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        confirmPassword.addEventListener('input', function() {
            if (this.value && this.value === password.value) {
                this.classList.add('valid');
                this.classList.remove('error');
            } else if (this.value) {
                this.classList.add('error');
                this.classList.remove('valid');
            } else {
                this.classList.remove('error', 'valid');
            }
        });
        
        password.addEventListener('input', function() {
            if (confirmPassword.value) {
                if (confirmPassword.value === this.value) {
                    confirmPassword.classList.add('valid');
                    confirmPassword.classList.remove('error');
                } else {
                    confirmPassword.classList.add('error');
                    confirmPassword.classList.remove('valid');
                }
            }
        });
        
        // Form submission
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const phone = phoneInput.value.trim();
            
            if (phone !== '') {
                const clean = phone.replace(/[^\d+]/g, '');
                let isValid = false;
                
                if (clean.startsWith('+63')) {
                    isValid = clean.substring(3).length === 10;
                } else if (clean.startsWith('63')) {
                    isValid = clean.substring(2).length === 10;
                } else if (clean.startsWith('0')) {
                    isValid = clean.substring(1).length === 10;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Invalid Phone Number!',
                        text: 'Please enter a valid Philippine phone number (e.g., 09123456789 or +639123456789)',
                        icon: 'error',
                        confirmButtonColor: '#3b82f6'
                    });
                    phoneInput.classList.add('error');
                    return;
                }
            }
            
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                Swal.fire({
                    title: 'Password Mismatch!',
                    text: 'Passwords do not match.',
                    icon: 'error',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            
            if (password.value.length < 6) {
                e.preventDefault();
                Swal.fire({
                    title: 'Weak Password!',
                    text: 'Password must be at least 6 characters long.',
                    icon: 'error',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            
            // Show loading state
            const btn = document.getElementById('signupBtn');
            btn.classList.add('loading');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>Registering...';
        });
        
        // Focus effect
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

    <?php if (isset($success)): ?>
    <script>
        Swal.fire({ title: 'Success!', text: '<?php echo $success; ?>', icon: 'success', confirmButtonColor: '#3b82f6' })
        .then(() => { window.location.href = 'index.php?registered=1'; });
    </script>
    <?php endif; ?>

    <?php if (isset($err)): ?>
    <script>
        Swal.fire({ title: 'Error!', text: '<?php echo $err; ?>', icon: 'error', confirmButtonColor: '#3b82f6' });
    </script>
    <?php endif; ?>
</body>
</html>