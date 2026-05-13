<?php
// Check if a session is not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include('assets/inc/db.php'); // Assuming db.php contains the PDO connection code

// Check if user is logged in and has admin/super_admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Redirect to login page if user is not logged in
    header('Location: ../index.php');
    exit();
}

// Check if user has admin privileges (admin or super_admin)
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    // Redirect unauthorized users
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Fetch pending faculty count for notification badge
try {
    $pending_stmt = $conn->prepare("SELECT COUNT(*) AS pending_count FROM users WHERE role = 'faculty' AND status = 'pending'");
    $pending_stmt->execute();
    $pending_count = $pending_stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];
} catch (PDOException $e) {
    $pending_count = 0;
}

// Total notifications (only pending faculty accounts)
$total_notifications = $pending_count;

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

            <!-- Notification Bell Icon -->
            <li class="dropdown notification-list">
                <a class="nav-link dropdown-toggle waves-effect waves-light" data-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                    <i class="fe-bell"></i>
                    <?php if ($total_notifications > 0) { ?>
                        <span class="badge badge-danger badge-pill" style="position: relative; top: -8px; right: -5px;"><?php echo $total_notifications; ?></span>
                    <?php } ?>
                </a>
                <div class="dropdown-menu dropdown-menu-right dropdown-menu-lg">
                    <div class="dropdown-header noti-title">
                        <h6 class="text-overflow m-0">Notifications (<?php echo $total_notifications; ?>)</h6>
                    </div>
                    
                    <?php if ($pending_count > 0): ?>
                    <a href="faculty_approval.php" class="dropdown-item notify-item">
                        <div class="notify-icon bg-warning">
                            <i class="fe-user-plus"></i>
                        </div>
                        <p class="notify-details">
                            <b><?php echo $pending_count; ?> faculty registration<?php echo $pending_count > 1 ? 's' : ''; ?></b> pending approval
                            <small class="text-muted">Click to review</small>
                        </p>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($total_notifications == 0): ?>
                    <div class="text-center p-3">
                        <i class="fe-check-circle text-success" style="font-size: 24px;"></i>
                        <p class="mt-2 mb-0 text-muted">No new notifications</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($total_notifications > 0): ?>
                    <div class="dropdown-divider"></div>
                    <a href="faculty_approval.php" class="dropdown-item text-center text-primary">
                        <small>View all pending items</small>
                    </a>
                    <?php endif; ?>
                </div>
            </li>
            
            <!-- User Dropdown -->
            <li class="dropdown notification-list">
                <a class="nav-link dropdown-toggle nav-user mr-0 waves-effect waves-light" data-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                    <!-- Display Profile Picture -->
                    <img src="<?php echo htmlspecialchars($user->user_pic ?: 'assets/images/default-profile.png'); ?>" alt="pic" class="rounded-circle" width="40">
                    <span class="pro-user-name ml-1">
                        <?php echo htmlspecialchars($user->full_name ?: $user->username); ?>
                        <i class="mdi mdi-chevron-down"></i>
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-right profile-dropdown">
                    <!-- User Info Header -->
                    <div class="dropdown-header noti-title">
                        <h6 class="text-overflow m-0">Welcome!</h6>
                    </div>
                    <!-- View Profile Link -->
                    <a href="view_profile.php" class="dropdown-item notify-item">
                        <i class="fe-user"></i>
                        <span>My Profile</span>
                    </a>
                    <!-- Change Password -->
                   <a href="changepass.php" class="dropdown-item">
                        <i class="fe-lock mr-1"></i>
                        <span>Change Password</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <!-- Logout -->
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

        <!-- Access Features Dropdown -->
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
                    
                    <!-- Dashboard -->
                    <a href="dashboard.php" class="dropdown-item">
                        <i class="fe-airplay mr-1"></i>
                        <span>Dashboard</span>
                    </a>
                    
                    <div class="dropdown-divider"></div>
                    
                    <!-- Manage Faculty -->
                    <div class="dropdown-header">Manage Faculty</div>
                    <a href="facultyload.php" class="dropdown-item">
                        <i class="fas fa-chalkboard-teacher mr-1"></i>
                        <span>Faculty Load</span>
                    </a>
                    
                    <div class="dropdown-divider"></div>
                    
                    <!-- Manage Lab -->
                    <div class="dropdown-header">Manage Lab</div>
                    <a href="laboratory.php" class="dropdown-item">
                        <i class="fas fa-desktop mr-1"></i>
                        <span>Laboratory</span>
                    </a>
                    
                    <div class="dropdown-divider"></div>
                    
                    <!-- Manage Subject -->
                    <div class="dropdown-header">Manage Subject</div>
                    <a href="subject_prospectus.php" class="dropdown-item">
                        <i class="fas fa-book mr-1"></i>
                        <span>Subject Prospectus & Offerings</span>
                    </a>
                    
                    <div class="dropdown-divider"></div>
                    
                    <!-- Manage Loading -->
                    <div class="dropdown-header">Manage Loading</div>
                    <a href="submission_loading.php" class="dropdown-item">
                        <i class="fas fa-spinner mr-1"></i>
                        <span>Loading Submission</span>
                    </a>
                    
                    <div class="dropdown-divider"></div>
                    
                    <!-- Auto Schedules -->
                    <div class="dropdown-header">Auto Schedules</div>
                    <a href="generate_sched.php" class="dropdown-item">
                        <i class="fas fa-calendar-plus mr-1"></i>
                        <span>Laboratory & Sched Course</span>
                    </a>
                    
                    <div class="dropdown-divider"></div>
                    
                    <!-- Faculty Approval -->
                    <div class="dropdown-header">Faculty Approval</div>
                    <a href="faculty_approval.php" class="dropdown-item">
                        <i class="fas fa-user-check mr-1"></i>
                        <span>Manage Approval</span>
                    </a>
                    <?php 
                    // Get pending faculty count if needed
                    $pending_query = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'faculty' AND status = 'pending'");
                    $pending_count_quick = $pending_query->fetchColumn();
                    if ($pending_count_quick > 0): 
                    ?>
                    <a href="faculty_approval.php?filter=pending" class="dropdown-item">
                        <i class="fas fa-clock mr-1"></i>
                        <span>Pending Approvals</span>
                        <span class="badge badge-warning ml-2"><?php echo $pending_count_quick; ?></span>
                    </a>
                    <?php endif; ?>
                    
                    <div class="dropdown-divider"></div>
                    
                    <!-- My Profile -->
                    <div class="dropdown-header">My Profile</div>
                    <a href="view_profile.php" class="dropdown-item">
                        <i class="fas fa-user mr-1"></i>
                        <span>View Profile</span>
                    </a>
                    <a href="changepass.php" class="dropdown-item">
                        <i class="fas fa-lock mr-1"></i>
                        <span>Change Pass</span>
                    </a>
                    
                </div>
            </li>
        </ul>
    </div>

    <!-- Add this CSS to your stylesheet for better dropdown appearance -->
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        
        .dropdown-menu-lg {
            min-width: 320px;
        }
        
        .dropdown-item {
            padding: 10px 20px;
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
            padding-left: 25px;
        }
        
        .dropdown-item i {
            width: 20px;
            text-align: center;
        }
        
        .dropdown-header {
            padding: 8px 20px;
            font-weight: 600;
            color: #6c757d;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-danger {
            position: relative;
            top: -8px;
            right: -5px;
            padding: 3px 6px;
            font-size: 10px;
        }
        
        .badge-pill {
            border-radius: 50px;
        }
        
        .notify-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: white;
        }
        
        .notify-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
        }
        
        .notify-details {
            flex: 1;
            margin-bottom: 0;
        }
        
        .notify-details b {
            display: block;
            font-size: 14px;
        }
        
        .notify-details small {
            display: block;
            font-size: 11px;
        }
        
        .bg-warning { background: #f59e0b !important; }
        .bg-info { background: #3b82f6 !important; }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .pro-user-name {
                max-width: 100px;
            }
            
            .dropdown-menu-lg {
                min-width: 280px;
            }
        }
    </style>
    
<?php
} else {
    // Handle case when user not found
    session_destroy();
    header('Location: login.php?error=user_not_found');
    exit();
}
?>