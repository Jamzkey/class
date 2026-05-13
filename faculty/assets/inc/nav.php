<?php
// Check if a session is not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include('assets/inc/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Fetch user details from the `users` table
$query = "
    SELECT user_id, full_name, username, phone_number, user_pic, role, status
    FROM users
    WHERE user_id = :user_id
";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_OBJ);

if ($user) {
?>

    <div class="navbar-custom">
        <ul class="list-unstyled topnav-menu float-right mb-0">
            
            <!-- User Dropdown -->
            <li class="dropdown notification-list">
                <a class="nav-link dropdown-toggle nav-user mr-0 waves-effect waves-light" data-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                    <img src="<?php echo htmlspecialchars($user->user_pic ?: 'assets/images/default-profile.png'); ?>" alt="pic" class="rounded-circle" width="40">
                    <span class="pro-user-name ml-1">
                        <?php echo htmlspecialchars($user->full_name ?: $user->username); ?>
                        <i class="mdi mdi-chevron-down"></i>
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-right profile-dropdown">
                    <div class="dropdown-header noti-title">
                        <h6 class="text-overflow m-0">Welcome!</h6>
                    </div>
                    <a href="view_profile.php" class="dropdown-item notify-item">
                        <i class="fe-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="changepass.php" class="dropdown-item">
                        <i class="fe-lock mr-1"></i>
                        <span>Change Pass</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item notify-item text-danger">
                        <i class="fe-log-out"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </li>
        </ul>

        <!-- LOGO -->
        <div class="logo-box">
            <a href="dashboard.php" class="logo text-center">
                <span class="logo-lg">
                    <img src="assets/images/aq.png" alt="" height="45">
                </span>
                <span class="logo-sm">
                    <img src="assets/images/aq.png" alt="" height="30">
                </span>
            </a>
        </div>

        <!-- Quick Access Dropdown -->
        <ul class="list-unstyled topnav-menu topnav-menu-left m-0">
            <li>
                <button class="button-menu-mobile waves-effect waves-light">
                    <i class="fe-menu"></i>
                </button>
            </li>

            <li class="dropdown d-none d-lg-block">
                <a class="nav-link dropdown-toggle waves-effect waves-light" data-toggle="dropdown" href="#" role="button">
                    <i class="fe-grid mr-1"></i> Quick Access <i class="mdi mdi-chevron-down"></i>
                </a>
                <div class="dropdown-menu">
                    
                    <a href="dashboard.php" class="dropdown-item">
                        <i class="fe-airplay mr-1"></i>
                        <span>Dashboard</span>
                    </a>
                    
                    <div class="dropdown-divider"></div>
                    
                    <div class="dropdown-header">My Profile</div>
                    <a href="view_profile.php" class="dropdown-item">
                        <i class="fas fa-user mr-1"></i>
                        <span>View Profile</span>
                    </a>
                    <a href="changepass.php" class="dropdown-item">
                        <i class="fas fa-lock mr-1"></i>
                        <span>Change Password</span>
                    </a>
                    
                </div>
            </li>
        </ul>
    </div>

    <style>
        .navbar-custom {
            padding: 0 20px;
        }
        
        .topnav-menu {
            align-items: center;
        }
        
        .nav-user {
            padding: 0 12px !important;
        }
        
        .pro-user-name {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-radius: 12px;
        }
        
        .dropdown-item {
            padding: 12px 20px;
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background-color: #f8fafc;
            padding-left: 25px;
        }
        
        .dropdown-item i {
            width: 20px;
            text-align: center;
        }
        
        .dropdown-header {
            padding: 12px 20px;
            font-weight: 600;
            color: #1e293b;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        @media (max-width: 768px) {
            .pro-user-name {
                max-width: 100px;
            }
        }
    </style>
    
<?php
} else {
    session_destroy();
    header('Location: login.php?error=user_not_found');
    exit();
}
?>