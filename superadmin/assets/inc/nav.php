<?php
// Check if a session is not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include('assets/inc/db.php');

// Check if user is logged in and has admin/super_admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

// Check if user has admin privileges (admin or super_admin)
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Fetch ONLY pending faculty load approvals count with details for notifications
try {
    $load_stmt = $conn->prepare("
        SELECT 
            fl.load_id,
            u.full_name as instructor_name,
            sub.subject_code,
            sub.subject_name,
            fl.status
        FROM faculty_load fl
        JOIN instructors i ON fl.instructor_id = i.instructor_id
        JOIN users u ON i.user_id = u.user_id
        JOIN subject_offerings so ON fl.offering_id = so.offering_id
        JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
        JOIN subjects sub ON cs.subject_id = sub.subject_id
        WHERE fl.status = 'pending'
        ORDER BY fl.load_id DESC
        LIMIT 5
    ");
    $load_stmt->execute();
    $pending_loads_details = $load_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count of pending faculty loads ONLY
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS pending_loads FROM faculty_load WHERE status = 'pending'");
    $count_stmt->execute();
    $pending_loads = $count_stmt->fetch(PDO::FETCH_ASSOC)['pending_loads'];
} catch (PDOException $e) {
    $pending_loads = 0;
    $pending_loads_details = [];
}

// Total notifications = pending faculty loads ONLY
$total_notifications = $pending_loads;

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

            <!-- Notification Bell Icon - FACULTY LOADING ONLY -->
            <li class="dropdown notification-list">
                <a class="nav-link dropdown-toggle waves-effect waves-light" data-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                    <i class="fe-bell noti-icon"></i>
                    <?php if ($pending_loads > 0) { ?>
                        <span class="badge badge-danger badge-pill notification-badge"><?php echo $pending_loads; ?></span>
                    <?php } ?>
                </a>
                <div class="dropdown-menu dropdown-menu-right dropdown-menu-lg">
                    <div class="dropdown-header noti-title">
                        <h6 class="text-overflow m-0">
                            <i class="fas fa-book-open mr-2"></i>Pending Faculty Loading
                            <?php if ($pending_loads > 0): ?>
                                <span class="badge badge-warning ml-2"><?php echo $pending_loads; ?></span>
                            <?php endif; ?>
                        </h6>
                    </div>
                    
                    <?php if (!empty($pending_loads_details)): ?>
                        <?php foreach ($pending_loads_details as $load): ?>
                        <a href="dashboard.php?load_id=<?php echo $load['load_id']; ?>" class="dropdown-item notify-item">
                            <div class="notify-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="notify-content">
                                <p class="notify-details">
                                    <span class="notify-title"><?php echo htmlspecialchars($load['subject_code']); ?></span>
                                    <span class="notify-subject"><?php echo htmlspecialchars($load['subject_name']); ?></span>
                                </p>
                                <p class="notify-instructor">
                                    <i class="fas fa-chalkboard-teacher mr-1"></i>
                                    <?php echo htmlspecialchars($load['instructor_name']); ?>
                                </p>
                                <small class="text-muted">
                                    <i class="far fa-clock mr-1"></i>Pending approval
                                </small>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        
                        <?php if ($pending_loads > 5): ?>
                        <div class="dropdown-divider"></div>
                        <a href="dashboard.php" class="dropdown-item text-center text-primary">
                            <small><i class="fas fa-list mr-1"></i>View all <?php echo $pending_loads; ?> pending loads</small>
                        </a>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="text-center p-4">
                            <div class="empty-notification-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <p class="mt-3 mb-0 text-muted">No pending faculty loads</p>
                            <small class="text-muted">All faculty loading has been processed</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="dropdown-divider"></div>
                    <a href="dashboard.php" class="dropdown-item text-center notify-footer">
                        <i class="fas fa-external-link-alt mr-1"></i>Go to Faculty Loading Approval
                    </a>
                </div>
            </li>
            
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
                        <span>Change Pass</span>
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
        
        .dropdown-menu-lg {
            min-width: 360px;
            max-height: 400px;
            overflow-y: auto;
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
        
        .noti-icon {
            font-size: 20px;
            color: #475569;
        }
        
        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            padding: 3px 6px;
            font-size: 10px;
            font-weight: 600;
            background: #ef4444;
            box-shadow: 0 2px 5px rgba(239, 68, 68, 0.3);
        }
        
        .badge-pill {
            border-radius: 50px;
        }
        
        .notify-item {
            display: flex;
            align-items: flex-start;
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .notify-item:hover {
            background: #f8fafc;
        }
        
        .notify-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .notify-content {
            flex: 1;
        }
        
        .notify-details {
            margin-bottom: 4px;
        }
        
        .notify-title {
            font-weight: 700;
            color: #0f172a;
            font-size: 14px;
        }
        
        .notify-subject {
            color: #64748b;
            font-size: 13px;
            margin-left: 8px;
        }
        
        .notify-instructor {
            font-size: 12px;
            color: #475569;
            margin-bottom: 3px;
        }
        
        .empty-notification-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #dcfce7;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            color: #22c55e;
            font-size: 28px;
        }
        
        .notify-footer {
            color: #667eea;
            font-weight: 500;
            padding: 12px 20px;
        }
        
        .notify-footer:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            color: #667eea;
        }
        
        .badge-warning {
            background: #f59e0b;
            color: white;
            padding: 3px 8px;
            font-size: 11px;
        }
        
        @media (max-width: 768px) {
            .pro-user-name {
                max-width: 100px;
            }
            
            .dropdown-menu-lg {
                min-width: 300px;
                max-height: 350px;
            }
            
            .notify-item {
                padding: 12px 15px;
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