<?php
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Get the role of the user from session
    $role = $_SESSION['role'];
    $user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
    $user_role_display = '';
    $role_icon = '';
    $role_color = '';

    // Perform actions based on the user's role
    switch ($role) {
        case 'super_admin':
            $user_role_display = 'Super Administrator';
            $role_icon = 'crown';
            $role_color = '#8b5cf6';
            unset($_SESSION['super_admin_permissions']);
            break;

        case 'admin':
            $user_role_display = 'Administrator';
            $role_icon = 'shield-alt';
            $role_color = '#3b82f6';
            unset($_SESSION['admin_permissions']);
            break;

        case 'faculty':
            $user_role_display = 'Faculty Member';
            $role_icon = 'chalkboard-teacher';
            $role_color = '#10b981';
            unset($_SESSION['faculty_id']);
            unset($_SESSION['instructor_id']);
            unset($_SESSION['specialization']);
            break;
    }

    // Clear all session data
    session_unset();
    
    // Destroy the session
    session_destroy();

    // Start new session for logout confirmation
    session_start();
    $_SESSION['logged_out'] = true;
    $_SESSION['logout_user_name'] = $user_name;
    $_SESSION['logout_user_role'] = $user_role_display;
    $_SESSION['logout_role_icon'] = $role_icon;
    $_SESSION['logout_role_color'] = $role_color;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Logged Out | Class Scheduling System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Class Scheduling System for Faculty and Rooms" name="description" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    
    <!-- App favicon -->
    <link rel="shortcut icon" href="assets/images/aq.png">

    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
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
            overflow-x: hidden;
        }
        
        /* Animated background particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }
        
        .particle {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: floatParticle 20s infinite ease-in-out;
        }
        
        .particle:nth-child(1) {
            width: 400px;
            height: 400px;
            top: -100px;
            left: -100px;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.15) 0%, transparent 70%);
            animation-delay: 0s;
        }
        
        .particle:nth-child(2) {
            width: 500px;
            height: 500px;
            bottom: -150px;
            right: -150px;
            background: radial-gradient(circle, rgba(118, 75, 162, 0.15) 0%, transparent 70%);
            animation-delay: -5s;
        }
        
        .particle:nth-child(3) {
            width: 300px;
            height: 300px;
            top: 40%;
            right: 5%;
            background: radial-gradient(circle, rgba(34, 197, 94, 0.1) 0%, transparent 70%);
            animation-delay: -10s;
        }
        
        .particle:nth-child(4) {
            width: 250px;
            height: 250px;
            bottom: 20%;
            left: 5%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, transparent 70%);
            animation-delay: -7s;
        }
        
        .particle:nth-child(5) {
            width: 350px;
            height: 350px;
            top: 10%;
            right: 20%;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.08) 0%, transparent 70%);
            animation-delay: -12s;
        }
        
        @keyframes floatParticle {
            0%, 100% { 
                transform: translateY(0) translateX(0) scale(1);
                opacity: 0.5;
            }
            25% {
                transform: translateY(-30px) translateX(15px) scale(1.02);
                opacity: 0.7;
            }
            50% { 
                transform: translateY(-20px) translateX(-15px) scale(0.98);
                opacity: 0.6;
            }
            75% {
                transform: translateY(-40px) translateX(10px) scale(1.03);
                opacity: 0.8;
            }
        }
        
        /* Main container */
        .logout-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 500px;
            padding: 20px;
        }
        
        /* Card styling */
        .logout-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            padding: 48px 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 
                        0 0 0 1px rgba(255, 255, 255, 0.1) inset;
            transform: scale(1);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            animation: cardEntrance 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        @keyframes cardEntrance {
            0% {
                opacity: 0;
                transform: scale(0.9) translateY(30px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .logout-card:hover {
            box-shadow: 0 35px 60px -15px rgba(0, 0, 0, 0.3),
                        0 0 0 1px rgba(255, 255, 255, 0.15) inset;
        }
        
        /* Logo section */
        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo-wrapper {
            display: inline-block;
            position: relative;
        }
        
        .logo-circle {
            width: 100px;
            height: 100px;
            border-radius: 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .logo-circle::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: logoShine 3s infinite;
        }
        
        @keyframes logoShine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        .logo-circle img {
            width: 60px;
            height: 60px;
            filter: brightness(0) invert(1);
            position: relative;
            z-index: 1;
        }
        
        /* Success icon */
        .success-section {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .success-icon-wrapper {
            position: relative;
            display: inline-block;
        }
        
        .success-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 0 15px 35px rgba(34, 197, 94, 0.3);
            animation: successPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
        }
        
        @keyframes successPop {
            0% {
                opacity: 0;
                transform: scale(0);
            }
            70% {
                transform: scale(1.1);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .success-circle i {
            font-size: 48px;
            color: white;
            animation: checkDraw 0.5s ease-out 0.5s both;
        }
        
        @keyframes checkDraw {
            0% {
                opacity: 0;
                transform: scale(0.5);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .success-pulse {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(34, 197, 94, 0.3);
            animation: successPulse 2s infinite;
        }
        
        @keyframes successPulse {
            0% {
                width: 100px;
                height: 100px;
                opacity: 0.5;
            }
            100% {
                width: 160px;
                height: 160px;
                opacity: 0;
            }
        }
        
        /* Typography */
        .text-content {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .logout-title {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
            animation: textFadeIn 0.6s ease-out 0.3s both;
        }
        
        .logout-message {
            color: #64748b;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 24px;
            animation: textFadeIn 0.6s ease-out 0.4s both;
        }
        
        @keyframes textFadeIn {
            0% {
                opacity: 0;
                transform: translateY(10px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* User info badge */
        .user-info-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 50px;
            padding: 12px 28px;
            margin-bottom: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
            animation: textFadeIn 0.6s ease-out 0.5s both;
        }
        
        .user-avatar-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
            margin-right: 12px;
        }
        
        .user-details {
            text-align: left;
        }
        
        .user-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 16px;
            line-height: 1.3;
        }
        
        .user-role {
            font-size: 13px;
            color: #64748b;
            display: flex;
            align-items: center;
            margin-top: 2px;
        }
        
        .user-role i {
            margin-right: 6px;
            font-size: 11px;
        }
        
        /* Timer section */
        .timer-section {
            text-align: center;
            margin-bottom: 32px;
            animation: textFadeIn 0.6s ease-out 0.6s both;
        }
        
        .timer-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 12px;
        }
        
        .timer-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }
        
        .timer-circle.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.3);
        }
        
        .timer-circle.danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
        }
        
        .timer-circle.cancelled {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            box-shadow: 0 10px 25px rgba(100, 116, 139, 0.3);
        }
        
        #countdown {
            font-size: 28px;
            font-weight: 800;
            color: white;
        }
        
        .timer-info {
            text-align: left;
        }
        
        .timer-label {
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }
        
        .timer-text {
            font-size: 13px;
            color: #64748b;
        }
        
        .timer-text i {
            margin-right: 6px;
            color: #667eea;
        }
        
        /* Action buttons */
        .action-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
            animation: textFadeIn 0.6s ease-out 0.7s both;
        }
        
        .btn-primary-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 16px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-primary-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .btn-primary-login:hover::before {
            left: 100%;
        }
        
        .btn-primary-login i {
            transition: transform 0.3s ease;
        }
        
        .btn-primary-login:hover i {
            transform: translateX(-5px);
        }
        
        .btn-secondary-action {
            width: 100%;
            background: transparent;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
            padding: 14px 24px;
            border-radius: 16px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: white;
        }
        
        .btn-secondary-action:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
        }
        
        /* Keyboard hint */
        .keyboard-hint {
            margin-top: 20px;
            text-align: center;
            animation: textFadeIn 0.6s ease-out 0.8s both;
        }
        
        .key-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            font-size: 12px;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .key-badge kbd {
            background: white;
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
            color: #0f172a;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        /* Footer */
        .logout-footer {
            margin-top: 24px;
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
            font-size: 13px;
            animation: textFadeIn 0.6s ease-out 0.9s both;
        }
        
        .logout-footer a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.2s;
            margin: 0 4px;
        }
        
        .logout-footer a:hover {
            color: white;
        }
        
        .logout-footer .separator {
            color: rgba(255, 255, 255, 0.3);
            margin: 0 8px;
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .logout-container {
                padding: 16px;
            }
            
            .logout-card {
                padding: 36px 24px;
            }
            
            .logout-title {
                font-size: 28px;
            }
            
            .logout-message {
                font-size: 14px;
            }
            
            .user-info-badge {
                padding: 10px 20px;
            }
            
            .timer-display {
                gap: 12px;
            }
            
            .timer-circle {
                width: 50px;
                height: 50px;
            }
            
            #countdown {
                font-size: 24px;
            }
            
            .btn-primary-login {
                padding: 14px 20px;
                font-size: 15px;
            }
            
            .btn-secondary-action {
                padding: 12px 20px;
                font-size: 13px;
            }
        }
    </style>
</head>

<body>
    <!-- Animated background particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <div class="logout-container">
        <div class="logout-card">
            
            <!-- Logo Section -->
            <div class="logo-section">
                <div class="logo-wrapper">
                    <div class="logo-circle">
                        <img src="assets/images/aq.png" alt="Logo">
                    </div>
                </div>
            </div>

            <!-- Success Section -->
            <div class="success-section">
                <div class="success-icon-wrapper">
                    <div class="success-pulse"></div>
                    <div class="success-circle">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
            </div>

            <!-- Text Content -->
            <div class="text-content">
                <h1 class="logout-title">See You Again!</h1>
                <p class="logout-message">
                    You have been successfully logged out of your account.
                </p>
                
                <?php if (isset($_SESSION['logout_user_name'])): ?>
                <?php 
                    $name_parts = explode(' ', $_SESSION['logout_user_name']);
                    $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                    $role_color = $_SESSION['logout_role_color'] ?? '#667eea';
                    $role_icon = $_SESSION['logout_role_icon'] ?? 'user';
                ?>
                <div style="text-align: center;">
                    <div class="user-info-badge">
                        <div class="user-avatar-icon" style="background: <?php echo $role_color; ?>;">
                            <?php echo $initials; ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($_SESSION['logout_user_name']); ?></div>
                            <div class="user-role">
                                <i class="fas fa-<?php echo $role_icon; ?>" style="color: <?php echo $role_color; ?>;"></i>
                                <?php echo htmlspecialchars($_SESSION['logout_user_role']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Timer Section -->
            <div class="timer-section">
                <div class="timer-display">
                    <div class="timer-circle" id="timerCircle">
                        <span id="countdown">5</span>
                    </div>
                    <div class="timer-info">
                        <div class="timer-label">Auto-redirect</div>
                        <div class="timer-text" id="timerMessage">
                            <i class="fas fa-clock"></i>
                            <span id="timerStatusText">Redirecting to login page...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-section">
                <a href="../index.php" class="btn-primary-login">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Back to Log In</span>
                    <i class="fas fa-arrow-right" style="opacity: 0.7; font-size: 14px;"></i>
                </a>
                
                <a href="javascript:void(0)" onclick="toggleRedirect()" class="btn-secondary-action" id="cancelRedirectBtn">
                    <i class="fas fa-pause-circle"></i>
                    <span>Pause Auto-redirect</span>
                </a>
            </div>

            <!-- Keyboard Hint -->
            <div class="keyboard-hint">
                <div class="key-badge">
                    <i class="fas fa-keyboard" style="opacity: 0.5;"></i>
                    <span>Press</span>
                    <kbd>ESC</kbd>
                    <span>to pause redirect</span>
                </div>
            </div>

        </div>
        
        <!-- Footer -->
        <div class="logout-footer">
            <span>&copy; <?php echo date('Y'); ?> Class Scheduling System</span>
            <span class="separator">•</span>
            <a href="#">Privacy</a>
            <span class="separator">•</span>
            <a href="#">Terms</a>
            <span class="separator">•</span>
            <a href="#">Support</a>
        </div>
    </div>

    <?php include('assets/inc/footer1.php');?>

    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>
    
    <script>
        let seconds = 5;
        let countdownInterval;
        let redirectCancelled = false;
        
        const countdownElement = document.getElementById('countdown');
        const timerCircle = document.getElementById('timerCircle');
        const timerStatusText = document.getElementById('timerStatusText');
        const cancelRedirectBtn = document.getElementById('cancelRedirectBtn');
        
        function startCountdown() {
            countdownInterval = setInterval(function() {
                seconds--;
                countdownElement.textContent = seconds;
                
                // Update timer circle class based on time left
                timerCircle.classList.remove('warning', 'danger', 'cancelled');
                if (seconds <= 2) {
                    timerCircle.classList.add('danger');
                } else if (seconds <= 3) {
                    timerCircle.classList.add('warning');
                }
                
                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                    if (!redirectCancelled) {
                        // Smooth exit animation
                        const card = document.querySelector('.logout-card');
                        card.style.transition = 'all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.95) translateY(-20px)';
                        
                        setTimeout(function() {
                            window.location.href = '../index.php';
                        }, 400);
                    } else {
                        updateCancelledState();
                    }
                }
            }, 1000);
        }
        
        function updateCancelledState() {
            timerCircle.classList.add('cancelled');
            countdownElement.innerHTML = '<i class="fas fa-pause" style="font-size: 20px;"></i>';
            timerStatusText.innerHTML = 'Auto-redirect paused';
            document.querySelector('.timer-label').textContent = 'Paused';
            
            // Update button
            cancelRedirectBtn.innerHTML = `
                <i class="fas fa-play-circle"></i>
                <span>Resume Auto-redirect</span>
            `;
        }
        
        function updateActiveState() {
            timerCircle.classList.remove('cancelled');
            countdownElement.textContent = seconds;
            timerStatusText.innerHTML = 'Redirecting to login page...';
            document.querySelector('.timer-label').textContent = 'Auto-redirect';
            
            // Update button
            cancelRedirectBtn.innerHTML = `
                <i class="fas fa-pause-circle"></i>
                <span>Pause Auto-redirect</span>
            `;
            
            // Update timer circle class
            timerCircle.classList.remove('warning', 'danger');
            if (seconds <= 2) {
                timerCircle.classList.add('danger');
            } else if (seconds <= 3) {
                timerCircle.classList.add('warning');
            }
        }
        
        function toggleRedirect() {
            if (!redirectCancelled) {
                redirectCancelled = true;
                clearInterval(countdownInterval);
                updateCancelledState();
            } else {
                redirectCancelled = false;
                seconds = 5;
                updateActiveState();
                clearInterval(countdownInterval);
                startCountdown();
            }
        }
        
        // Start countdown when page loads
        window.addEventListener('DOMContentLoaded', function() {
            startCountdown();
        });
        
        // Clear any stored session data on the client side
        localStorage.clear();
        sessionStorage.clear();
        
        // Keyboard shortcut to pause redirect (Esc key)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !redirectCancelled) {
                toggleRedirect();
                
                // Visual feedback
                const keyBadge = document.querySelector('.key-badge');
                keyBadge.style.transition = 'all 0.2s ease';
                keyBadge.style.background = 'rgba(102, 126, 234, 0.1)';
                keyBadge.style.borderColor = '#667eea';
                
                setTimeout(() => {
                    keyBadge.style.background = '';
                    keyBadge.style.borderColor = '';
                }, 200);
            }
        });
        
        // Hover effect for card
        const card = document.querySelector('.logout-card');
        card.addEventListener('mousemove', function(e) {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 20;
            const rotateY = (centerX - x) / 20;
            
            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(10px)`;
        });
        
        card.addEventListener('mouseleave', function() {
            card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateZ(0)';
        });
        
        // Console message
        console.log('%c👋 Goodbye! Thank you for using Class Scheduling System.', 'color: #667eea; font-size: 14px; font-weight: bold;');
        console.log('%cSession cleared successfully.', 'color: #22c55e; font-size: 12px;');
    </script>
    
</body>
</html>