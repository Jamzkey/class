<?php
session_start();
include('assets/inc/db.php');

// Check if user is logged in and has faculty role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$success = '';
$err = '';

// Handle form submission for updating profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update user details
        if (isset($_POST['full_name']) && isset($_POST['username']) && isset($_POST['phone_number'])) {
            $full_name = trim($_POST['full_name']);
            $username = trim($_POST['username']);
            $phone_number = trim($_POST['phone_number']);

            // Validate Philippine phone number
            if (!empty($phone_number)) {
                $phone_error = validatePhilippinePhone($phone_number);
                if ($phone_error) {
                    $err = $phone_error;
                }
            }

            if (empty($err)) {
                // Check if username already exists for other users
                $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND user_id != :user_id");
                $check->execute([':username' => $username, ':user_id' => $user_id]);
                
                if ($check->fetchColumn() > 0) {
                    $err = "Username already taken. Please choose another.";
                } else {
                    $update_query = "UPDATE users 
                                     SET full_name = :full_name, username = :username, phone_number = :phone_number 
                                     WHERE user_id = :user_id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->execute([
                        ':full_name' => $full_name,
                        ':username' => $username,
                        ':phone_number' => $phone_number,
                        ':user_id' => $user_id,
                    ]);

                    $success = "Profile updated successfully!";
                    
                    // Update session with new name
                    $_SESSION['full_name'] = $full_name;
                }
            }
        }

        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
            $file_type = $_FILES['profile_picture']['type'];
            $file_size = $_FILES['profile_picture']['size'];

            if (!in_array($file_type, $allowed_types)) {
                $err = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            } elseif ($file_size > 2 * 1024 * 1024) {
                $err = "File size exceeds the 2MB limit.";
            } else {
                $target_dir = "assets/images/profile/";
                
                // Create directory if it doesn't exist
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION);
                $file_name = 'faculty_' . $user_id . '_' . uniqid() . '.' . $file_extension;
                $target_file = $target_dir . $file_name;

                if (!move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                    $err = "Failed to upload the profile picture.";
                } else {
                    $update_picture_query = "UPDATE users SET user_pic = :user_pic WHERE user_id = :user_id";
                    $update_picture_stmt = $conn->prepare($update_picture_query);
                    $update_picture_stmt->execute([
                        ':user_pic' => $target_file,
                        ':user_id' => $user_id,
                    ]);

                    $success = "Profile picture updated successfully!";
                }
            }
        }
        
        // Handle specialization update (faculty-specific)
        if (isset($_POST['specialization'])) {
            $specialization = trim($_POST['specialization']);
            
            // Check if instructor record exists
            $check_instructor = $conn->prepare("SELECT instructor_id FROM instructors WHERE user_id = :user_id");
            $check_instructor->execute([':user_id' => $user_id]);
            $instructor = $check_instructor->fetch(PDO::FETCH_ASSOC);
            
            if ($instructor) {
                // Update existing specialization
                $update_spec = $conn->prepare("UPDATE instructors SET specialization = :specialization WHERE user_id = :user_id");
                $update_spec->execute([
                    ':specialization' => $specialization,
                    ':user_id' => $user_id
                ]);
            } else {
                // Create instructor record if it doesn't exist
                $insert_spec = $conn->prepare("INSERT INTO instructors (user_id, specialization) VALUES (:user_id, :specialization)");
                $insert_spec->execute([
                    ':user_id' => $user_id,
                    ':specialization' => $specialization
                ]);
            }
            
            if (empty($success)) {
                $success = "Specialization updated successfully!";
            }
        }
    } catch (Exception $e) {
        $err = "Error: " . $e->getMessage();
    }
}

/**
 * Validate Philippine phone number format
 * Accepts: 09XXXXXXXXX (11 digits) or +63XXXXXXXXXX (12 digits with +63)
 */
function validatePhilippinePhone($phone) {
    // Remove all spaces, dashes, and parentheses
    $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
    
    // Check for Philippine mobile format
    // Format 1: 09XXXXXXXXX (11 digits starting with 09)
    if (preg_match('/^09[0-9]{9}$/', $phone)) {
        return false; // Valid
    }
    
    // Format 2: +63XXXXXXXXXX (12 digits starting with +63 followed by 9)
    if (preg_match('/^\+639[0-9]{9}$/', $phone)) {
        return false; // Valid
    }
    
    // Format 3: 639XXXXXXXXX (12 digits starting with 639)
    if (preg_match('/^639[0-9]{9}$/', $phone)) {
        return false; // Valid
    }
    
    return "Invalid Philippine mobile number. Use format: 09XXXXXXXXX or +639XXXXXXXXX";
}

// Fetch user profile data including instructor details
try {
    $query = "SELECT u.full_name, u.username, u.phone_number, u.user_pic, u.role, u.status,
                     i.specialization
              FROM users u
              LEFT JOIN instructors i ON u.user_id = i.user_id
              WHERE u.user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        header('Location: index.php');
        exit();
    }
    
    // Get teaching load statistics
    $load_query = "SELECT 
                    COUNT(DISTINCT fl.load_id) as total_subjects,
                    SUM(CASE WHEN fl.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN fl.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(s.units) as total_units
                   FROM instructors i
                   LEFT JOIN faculty_load fl ON i.instructor_id = fl.instructor_id
                   LEFT JOIN subject_offerings so ON fl.offering_id = so.offering_id
                   LEFT JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
                   LEFT JOIN subjects s ON cs.subject_id = s.subject_id
                   WHERE i.user_id = :user_id";
    $load_stmt = $conn->prepare($load_query);
    $load_stmt->execute([':user_id' => $user_id]);
    $load_stats = $load_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $err = "Error fetching profile: " . $e->getMessage();
    $load_stats = ['total_subjects' => 0, 'pending_count' => 0, 'approved_count' => 0, 'total_units' => 0];
}

// Get current time for greeting
$hour = date('H');
if ($hour < 12) {
    $greeting = 'Good Morning';
    $greeting_icon = 'sun';
    $greeting_color = '#f59e0b';
} elseif ($hour < 17) {
    $greeting = 'Good Afternoon';
    $greeting_icon = 'sun';
    $greeting_color = '#f97316';
} else {
    $greeting = 'Good Evening';
    $greeting_icon = 'moon';
    $greeting_color = '#6366f1';
}

// Default specialization if not set
$specialization = $profile['specialization'] ?? 'General';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Faculty Account Settings | Class Scheduling System</title>
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 28px;
            color: white;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
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
        
        .profile-card {
            background: white;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        
        .stats-card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
        }
        
        .profile-sidebar {
            text-align: center;
            padding-right: 30px;
            border-right: 1px solid #e2e8f0;
        }
        
        .profile-avatar-container {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .profile-avatar:hover {
            transform: scale(1.02);
        }
        
        .avatar-edit-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        
        .avatar-edit-btn:hover {
            transform: scale(1.1);
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        }
        
        .role-badge {
            padding: 6px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            margin-top: 10px;
        }
        
        .role-faculty {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .profile-content {
            padding-left: 10px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: #10b981;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            color: #475569;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control {
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: #fafbfc;
        }
        
        .form-control:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            background: white;
        }
        
        .form-control.is-invalid {
            border-color: #ef4444;
        }
        
        .form-control.is-valid {
            border-color: #22c55e;
        }
        
        .invalid-feedback {
            display: block;
            color: #ef4444;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .valid-feedback {
            display: block;
            color: #22c55e;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .input-group-text {
            border-radius: 12px 0 0 12px;
            border: 1.5px solid #e2e8f0;
            border-right: none;
            background: #f8fafc;
            color: #64748b;
        }
        
        .input-group .form-control {
            border-radius: 0 12px 12px 0;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
            color: white;
        }
        
        .btn-outline-secondary {
            border-radius: 30px;
            padding: 12px 24px;
            border: 1.5px solid #e2e8f0;
            color: #475569;
            font-weight: 500;
        }
        
        .btn-outline-secondary:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #10b981;
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 2px;
        }
        
        .info-value {
            font-weight: 600;
            color: #0f172a;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
        }
        
        .stat-label {
            font-size: 13px;
            color: #64748b;
            margin-top: 5px;
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
        
        .phone-prefix-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .phone-prefix-btn {
            padding: 8px 16px;
            border-radius: 20px;
            border: 1.5px solid #e2e8f0;
            background: white;
            color: #475569;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .phone-prefix-btn.active {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }
        
        .phone-prefix-btn:hover {
            border-color: #10b981;
        }
        
        @media (max-width: 768px) {
            .page-wrapper { padding: 16px; }
            .profile-sidebar { 
                border-right: none; 
                border-bottom: 1px solid #e2e8f0;
                padding-right: 0;
                padding-bottom: 30px;
                margin-bottom: 20px;
            }
            .page-header { padding: 20px; }
            .page-title { font-size: 22px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
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
                                        <i class="fas fa-chalkboard-teacher mr-2"></i>
                                        Faculty Account Settings
                                    </h1>
                                    <p class="page-subtitle mb-2">Manage your profile information and specialization</p>
                                </div>
                                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                    <span class="greeting-badge">
                                        <i class="fas fa-<?php echo $greeting_icon; ?> mr-2" style="color: <?php echo $greeting_color; ?>;"></i>
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

                        <?php if ($profile): ?>
                        <!-- Teaching Load Statistics -->
                        <div class="stats-card">
                            <div class="section-title" style="margin-top: 0;">
                                <i class="fas fa-chart-bar"></i>
                                My Teaching Load Summary
                            </div>
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $load_stats['total_subjects'] ?? 0; ?></div>
                                    <div class="stat-label">Total Subjects</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $load_stats['total_units'] ?? 0; ?></div>
                                    <div class="stat-label">Total Units</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value" style="color: #f59e0b;"><?php echo $load_stats['pending_count'] ?? 0; ?></div>
                                    <div class="stat-label">Pending Approval</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value" style="color: #10b981;"><?php echo $load_stats['approved_count'] ?? 0; ?></div>
                                    <div class="stat-label">Approved Loads</div>
                                </div>
                            </div>
                        </div>

                        <div class="profile-card">
                            <div class="row">
                                <!-- Profile Sidebar -->
                                <div class="col-md-4">
                                    <div class="profile-sidebar">
                                        <div class="profile-avatar-container">
                                            <img id="profile_image" src="<?php echo htmlspecialchars($profile['user_pic'] ?: 'assets/images/default-user.png'); ?>" alt="Profile Picture" class="profile-avatar">
                                            <div class="avatar-edit-btn" onclick="document.getElementById('profile_picture').click()">
                                                <i class="fas fa-camera"></i>
                                            </div>
                                        </div>
                                        
                                        <h4 style="font-weight: 700; color: #0f172a; margin-bottom: 5px;">
                                            <?php echo htmlspecialchars($profile['full_name']); ?>
                                        </h4>
                                        <p style="color: #64748b; margin-bottom: 10px;">
                                            <i class="fas fa-at mr-1"></i><?php echo htmlspecialchars($profile['username']); ?>
                                        </p>
                                        
                                        <span class="role-badge role-faculty">
                                            <i class="fas fa-chalkboard-teacher mr-1"></i>
                                            Faculty Member
                                        </span>
                                        
                                        <?php
                                        $status_class = '';
                                        if ($profile['status'] === 'active') $status_class = 'status-active';
                                        elseif ($profile['status'] === 'pending') $status_class = 'status-pending';
                                        else $status_class = 'status-inactive';
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <i class="fas fa-circle mr-1" style="font-size: 8px;"></i>
                                            <?php echo ucfirst($profile['status']); ?>
                                        </span>
                                        
                                        <div style="margin-top: 30px; text-align: left;">
                                            <div class="info-item">
                                                <div class="info-icon">
                                                    <i class="fas fa-phone"></i>
                                                </div>
                                                <div class="info-content">
                                                    <div class="info-label">Phone Number</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($profile['phone_number'] ?: 'Not set'); ?></div>
                                                </div>
                                            </div>
                                            <div class="info-item">
                                                <div class="info-icon">
                                                    <i class="fas fa-graduation-cap"></i>
                                                </div>
                                                <div class="info-content">
                                                    <div class="info-label">Specialization</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($specialization); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Profile Content -->
                                <div class="col-md-8">
                                    <div class="profile-content">
                                        <form method="POST" enctype="multipart/form-data" id="profileForm">
                                            <input type="file" name="profile_picture" id="profile_picture" class="d-none" accept="image/*" onchange="previewImage();">
                                            
                                            <!-- Profile Information Section -->
                                            <div class="section-title">
                                                <i class="fas fa-user"></i>
                                                Profile Information
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="full_name" class="form-label">
                                                    <i class="fas fa-user-circle mr-1"></i>Full Name
                                                </label>
                                                <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo htmlspecialchars($profile['full_name']); ?>" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="username" class="form-label">
                                                    <i class="fas fa-at mr-1"></i>Username
                                                </label>
                                                <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($profile['username']); ?>" required>
                                            </div>
                                            
                                            <!-- Specialization Field (Faculty-specific) -->
                                            <div class="form-group">
                                                <label for="specialization" class="form-label">
                                                    <i class="fas fa-graduation-cap mr-1"></i>Specialization
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                                    <input type="text" name="specialization" id="specialization" class="form-control" 
                                                           value="<?php echo htmlspecialchars($specialization); ?>" 
                                                           placeholder="e.g., Computer Science, Mathematics, English">
                                                </div>
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Your area of expertise or field of specialization
                                                </small>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="phone_number" class="form-label">
                                                    <i class="fas fa-phone mr-1"></i>Phone Number <small class="text-muted">(Philippine format)</small>
                                                </label>
                                                
                                                <!-- Phone Prefix Selector -->
                                                <div class="phone-prefix-selector">
                                                    <button type="button" class="phone-prefix-btn active" data-prefix="09" onclick="setPhonePrefix('09')">
                                                        09XX XXX XXXX
                                                    </button>
                                                    <button type="button" class="phone-prefix-btn" data-prefix="+63" onclick="setPhonePrefix('+63')">
                                                        +63 9XX XXX XXXX
                                                    </button>
                                                </div>
                                                
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                                                    <input type="tel" name="phone_number" id="phone_number" class="form-control" 
                                                           value="<?php echo htmlspecialchars($profile['phone_number']); ?>" 
                                                           placeholder="e.g., 09123456789 or +639123456789"
                                                           onkeyup="validatePhoneNumber()"
                                                           oninput="formatPhoneNumber(this)">
                                                </div>
                                                <div id="phoneFeedback" class="invalid-feedback"></div>
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Format: 11 digits starting with 09 OR +63 followed by 9 digits
                                                </small>
                                            </div>
                                            
                                            <!-- Action Buttons -->
                                            <div style="display: flex; gap: 15px; margin-top: 30px;">
                                                <button type="submit" class="btn-save" id="submitBtn">
                                                    <i class="fas fa-save mr-2"></i>Save Changes
                                                </button>
                                                <button type="reset" class="btn-outline-secondary">
                                                    <i class="fas fa-undo mr-2"></i>Reset
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                            <div class="profile-card text-center py-5">
                                <i class="fas fa-user-slash fa-4x mb-3" style="color: #cbd5e1;"></i>
                                <h4>Profile Not Found</h4>
                                <p class="text-muted">Unable to load profile information.</p>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <div class="rightbar-overlay"></div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>

    <script>
        // Preview uploaded image
        function previewImage() {
            var file = document.getElementById("profile_picture").files[0];
            var reader = new FileReader();
            reader.onloadend = function () {
                document.getElementById("profile_image").src = reader.result;
            };
            if (file) {
                reader.readAsDataURL(file);
            }
        }
        
        // Set phone prefix
        function setPhonePrefix(prefix) {
            var phoneInput = document.getElementById('phone_number');
            var currentValue = phoneInput.value.replace(/^09|\+63/, '');
            
            // Update active button
            document.querySelectorAll('.phone-prefix-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Set new prefix
            if (currentValue) {
                phoneInput.value = prefix + currentValue;
            } else {
                phoneInput.value = prefix;
            }
            
            validatePhoneNumber();
        }
        
        // Format phone number as user types
        function formatPhoneNumber(input) {
            var value = input.value.replace(/\D/g, '');
            
            if (value.startsWith('09')) {
                if (value.length > 11) value = value.substr(0, 11);
                var formatted = value;
                if (value.length >= 4) formatted = value.substr(0, 4) + ' ' + value.substr(4, 3);
                if (value.length >= 7) formatted = formatted + ' ' + value.substr(7, 4);
                input.value = formatted;
                
                document.querySelectorAll('.phone-prefix-btn').forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.dataset.prefix === '09') btn.classList.add('active');
                });
            } else if (value.startsWith('639')) {
                if (value.length > 12) value = value.substr(0, 12);
                var formatted = '+63' + value.substr(2);
                if (value.length >= 5) formatted = '+63 ' + value.substr(2, 3) + ' ' + value.substr(5, 3);
                if (value.length >= 8) formatted = formatted + ' ' + value.substr(8, 4);
                input.value = formatted;
                
                document.querySelectorAll('.phone-prefix-btn').forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.dataset.prefix === '+63') btn.classList.add('active');
                });
            }
            
            validatePhoneNumber();
        }
        
        // Validate Philippine phone number
        function validatePhoneNumber() {
            var phoneInput = document.getElementById('phone_number');
            var feedback = document.getElementById('phoneFeedback');
            var phone = phoneInput.value.replace(/[\s\-\(\)]/g, '');
            
            var isValid09 = /^09[0-9]{9}$/.test(phone);
            var isValid63 = /^\+639[0-9]{9}$/.test(phone);
            var isValid639 = /^639[0-9]{9}$/.test(phone);
            
            if (phone === '') {
                phoneInput.classList.remove('is-invalid', 'is-valid');
                feedback.textContent = '';
                return true;
            } else if (isValid09 || isValid63 || isValid639) {
                phoneInput.classList.remove('is-invalid');
                phoneInput.classList.add('is-valid');
                feedback.textContent = '✓ Valid Philippine mobile number';
                feedback.className = 'valid-feedback';
                return true;
            } else {
                phoneInput.classList.remove('is-valid');
                phoneInput.classList.add('is-invalid');
                feedback.textContent = '✗ Invalid format. Use 09XXXXXXXXX or +639XXXXXXXXX';
                feedback.className = 'invalid-feedback';
                return false;
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
        // Reset form to original values
        document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('full_name').value = "<?php echo htmlspecialchars($profile['full_name']); ?>";
            document.getElementById('username').value = "<?php echo htmlspecialchars($profile['username']); ?>";
            document.getElementById('specialization').value = "<?php echo htmlspecialchars($specialization); ?>";
            document.getElementById('phone_number').value = "<?php echo htmlspecialchars($profile['phone_number']); ?>";
            
            validatePhoneNumber();
        });
        
        // Initial phone validation
        document.addEventListener('DOMContentLoaded', function() {
            validatePhoneNumber();
        });
    </script>
</body>
</html>