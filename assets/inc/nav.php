<?php
// Check if a session is not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include('assets/inc/db.php'); // Assuming db.php contains the PDO connection code

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // Redirect to login page if admin_id is not set
    header('Location: login.php');
    exit();
}

$admin_id = $_SESSION['admin_id']; // Assuming `admin_id` is stored in the session

// Fetch unread notifications count
$notif_query = $conn->query("SELECT COUNT(*) AS unread_count FROM notifications WHERE status = 'unread'");
$notif_count = $notif_query->fetch(PDO::FETCH_ASSOC)['unread_count'];

// Fetch admin details from the `admin` table
$query = "
    SELECT admin_id, full_name, email, phone_number, user_pic
    FROM admin
    WHERE admin_id = :admin_id
";
$stmt = $conn->prepare($query);
$stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_OBJ);


if ($admin) {
?>


    <div class="navbar-custom">
        <ul class="list-unstyled topnav-menu float-right mb-0">

<!-- Notification Bell Icon -->
<li class="nav-item">
                <a class="nav-link" href="notifications.php">
                    <i class="fas fa-bell"></i>
                    <?php if ($notif_count > 0) { ?>
                        <span class="badge badge-danger"><?php echo $notif_count; ?></span>
                    <?php } ?>
                </a>
            </li>
            <!-- User Dropdown -->
            <li class="dropdown notification-list">
                <a class="nav-link dropdown-toggle nav-user mr-0 waves-effect waves-light" data-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                    <!-- Display Profile Picture -->
                    <img src="<?php echo htmlspecialchars($admin->user_pic ?: 'assets/images/default-profile.png'); ?>" alt="pic" class="rounded-circle" width="40">
                    <span class="pro-user-name ml-1">
                        <?php echo htmlspecialchars($admin->email); ?>
                        <i class="mdi mdi-chevron-down"></i>
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-right profile-dropdown">
                    <!-- View Profile Link -->
                    <a href="view_profile.php" class="dropdown-item notify-item">
                        <i class="fe-user"></i>
                        <span>View Profile</span>
                    </a>
                    <!-- Logout -->
                    <a href="logout.php" class="dropdown-item notify-item">
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
                <div class="dropdown-menu">
                    <!-- Admin Tools -->
                    <a href="certification_asset.php" class="dropdown-item">
                        <i class="fe-file-text mr-1"></i>
                        <span>Certification Asset</span>
                    </a>
                    <a href="view_profile.php" class="dropdown-item">
                        <i class="fe-check-square mr-1"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="changepass.php" class="dropdown-item">
                        <i class="fe-lock mr-1"></i>
                        <span>Change Password</span>
                    </a>
                    <div class="dropdown-divider"></div>
                </div>
            </li>
        </ul>
    </div>
<?php
} else {
    echo "Admin not found or unauthorized access.";
}
?>
