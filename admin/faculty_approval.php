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

// Handle Faculty Approval/Rejection/Activation/Deactivation
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // APPROVE - Approve pending faculty
    if ($_POST['action'] === 'approve') {
        $faculty_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        
        try {
            $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = :user_id AND role = 'faculty' AND status = 'pending'");
            $stmt->execute([':user_id' => $faculty_user_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = "✅ Faculty account approved successfully!";
                $message_type = "success";
            } else {
                $message = "❌ Faculty account not found or already processed.";
                $message_type = "warning";
            }
        } catch (PDOException $e) {
            $message = "Error approving faculty: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // REJECT - Reject pending faculty (set to inactive)
    elseif ($_POST['action'] === 'reject') {
        $faculty_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        
        try {
            $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE user_id = :user_id AND role = 'faculty' AND status = 'pending'");
            $stmt->execute([':user_id' => $faculty_user_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = "❌ Faculty account rejected and set to inactive.";
                $message_type = "warning";
            } else {
                $message = "Faculty account not found or already processed.";
                $message_type = "info";
            }
        } catch (PDOException $e) {
            $message = "Error rejecting faculty: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // ACTIVATE - Activate inactive faculty
    elseif ($_POST['action'] === 'activate') {
        $faculty_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        
        try {
            $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = :user_id AND role = 'faculty' AND status = 'inactive'");
            $stmt->execute([':user_id' => $faculty_user_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = "✅ Faculty account activated successfully!";
                $message_type = "success";
            } else {
                $message = "Faculty account not found or already active.";
                $message_type = "info";
            }
        } catch (PDOException $e) {
            $message = "Error activating faculty: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // DEACTIVATE - Deactivate active faculty
    elseif ($_POST['action'] === 'deactivate') {
        $faculty_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        
        try {
            $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE user_id = :user_id AND role = 'faculty' AND status = 'active'");
            $stmt->execute([':user_id' => $faculty_user_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = "⚠️ Faculty account deactivated successfully!";
                $message_type = "warning";
            } else {
                $message = "Faculty account not found or already inactive.";
                $message_type = "info";
            }
        } catch (PDOException $e) {
            $message = "Error deactivating faculty: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // APPROVE ALL - Approve all pending faculty
    elseif ($_POST['action'] === 'approve_all') {
        try {
            $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE role = 'faculty' AND status = 'pending'");
            $stmt->execute();
            $count = $stmt->rowCount();
            
            if ($count > 0) {
                $message = "✅ {$count} faculty account(s) approved successfully!";
                $message_type = "success";
            } else {
                $message = "No pending faculty accounts to approve.";
                $message_type = "info";
            }
        } catch (PDOException $e) {
            $message = "Error approving faculty: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Fetch user details
$stmt = $conn->prepare("SELECT full_name, username, role, user_pic FROM users WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$full_name = $user['full_name'] ?? $user['username'] ?? 'Administrator';

// Fetch PENDING faculty accounts
$pending_faculty = $conn->query("
    SELECT 
        u.user_id,
        u.full_name,
        u.username,
        u.phone_number,
        u.status,
        u.user_pic,
        i.specialization,
        i.instructor_id
    FROM users u
    LEFT JOIN instructors i ON u.user_id = i.user_id
    WHERE u.role = 'faculty' AND u.status = 'pending'
    ORDER BY u.user_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch ACTIVE faculty accounts
$active_faculty = $conn->query("
    SELECT 
        u.user_id,
        u.full_name,
        u.username,
        u.phone_number,
        u.status,
        u.user_pic,
        i.specialization,
        i.instructor_id,
        (SELECT COUNT(*) FROM faculty_load fl WHERE fl.instructor_id = i.instructor_id) as total_loads
    FROM users u
    LEFT JOIN instructors i ON u.user_id = i.user_id
    WHERE u.role = 'faculty' AND u.status = 'active'
    ORDER BY u.full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch INACTIVE faculty accounts
$inactive_faculty = $conn->query("
    SELECT 
        u.user_id,
        u.full_name,
        u.username,
        u.phone_number,
        u.status,
        u.user_pic,
        i.specialization,
        i.instructor_id
    FROM users u
    LEFT JOIN instructors i ON u.user_id = i.user_id
    WHERE u.role = 'faculty' AND u.status = 'inactive'
    ORDER BY u.full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$total_pending = count($pending_faculty);
$total_active = count($active_faculty);
$total_inactive = count($inactive_faculty);
$total_faculty = $total_pending + $total_active + $total_inactive;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Faculty Approval | Class Scheduling System</title>
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
            background: white;
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 28px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            color: #64748b;
            font-size: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.08);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 16px;
        }
        
        .stat-value { font-size: 32px; font-weight: 700; color: #0f172a; line-height: 1.2; }
        .stat-label { font-size: 14px; color: #64748b; font-weight: 500; }
        
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            margin-bottom: 28px;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .table-title {
            font-size: 20px;
            font-weight: 600;
            color: #0f172a;
        }
        
        .table th {
            font-weight: 600;
            color: #475569;
            border-bottom-width: 1px;
            padding: 14px 16px;
            background: #f8fafc;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table td {
            padding: 16px;
            vertical-align: middle;
            color: #334155;
        }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-active {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            margin: 2px 4px 2px 0;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn-approve {
            background: #22c55e;
            color: white;
        }
        
        .btn-approve:hover {
            background: #16a34a;
            color: white;
        }
        
        .btn-reject {
            background: #ef4444;
            color: white;
        }
        
        .btn-reject:hover {
            background: #dc2626;
            color: white;
        }
        
        .btn-activate {
            background: #3b82f6;
            color: white;
        }
        
        .btn-activate:hover {
            background: #2563eb;
            color: white;
        }
        
        .btn-deactivate {
            background: #f59e0b;
            color: white;
        }
        
        .btn-deactivate:hover {
            background: #d97706;
            color: white;
        }
        
        .btn-view {
            background: #8b5cf6;
            color: white;
        }
        
        .btn-view:hover {
            background: #7c3aed;
            color: white;
        }
        
        .btn-approve-all {
            background: #22c55e;
            color: white;
            border-radius: 30px;
            padding: 10px 24px;
            font-weight: 600;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-approve-all:hover {
            background: #16a34a;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(34, 197, 94, 0.3);
        }
        
        .avatar-circle {
            width: 40px;
            height: 40px;
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
        
        .faculty-info {
            display: flex;
            align-items: center;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 0;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #64748b;
            font-weight: 500;
            padding: 12px 24px;
            border-radius: 30px;
            margin-right: 8px;
        }
        
        .nav-tabs .nav-link.active {
            background: #667eea;
            color: white;
        }
        
        .nav-tabs .nav-link .badge {
            margin-left: 8px;
        }
        
        .filter-section {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .search-box input {
            padding-left: 36px;
            border-radius: 30px;
            border: 1px solid #cbd5e1;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .page-wrapper { padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .table-header { flex-direction: column; align-items: flex-start; }
            .filter-section { width: 100%; }
            .search-box { width: 100%; }
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
                                        <i class="fas fa-user-check mr-2" style="color: #22c55e;"></i>
                                        Faculty Approval Management
                                    </h1>
                                    <p class="page-subtitle mb-0">Review, approve, or manage faculty accounts</p>
                                </div>
                                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                    <?php if ($total_pending > 0): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve_all">
                                        <button type="submit" class="btn-approve-all" onclick="return confirm('Approve ALL pending faculty accounts?')">
                                            <i class="fas fa-check-double mr-2"></i>Approve All (<?php echo $total_pending; ?>)
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Alert Messages -->
                        <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-circle' : 'info-circle'); ?> mr-2"></i>
                            <?php echo $message; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php endif; ?>

                        <!-- Statistics Cards -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-value"><?php echo $total_faculty; ?></div>
                                <div class="stat-label">Total Faculty</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #fef3c7; color: #eab308;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-value"><?php echo $total_pending; ?></div>
                                <div class="stat-label">Pending Approval</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #dcfce7; color: #22c55e;">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stat-value"><?php echo $total_active; ?></div>
                                <div class="stat-label">Active Faculty</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #fee2e2; color: #ef4444;">
                                    <i class="fas fa-ban"></i>
                                </div>
                                <div class="stat-value"><?php echo $total_inactive; ?></div>
                                <div class="stat-label">Inactive Faculty</div>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <ul class="nav nav-tabs mb-4" id="facultyTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#pendingTab">
                                    <i class="fas fa-clock mr-1"></i>Pending
                                    <span class="badge badge-warning"><?php echo $total_pending; ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#activeTab">
                                    <i class="fas fa-check-circle mr-1"></i>Active
                                    <span class="badge badge-success"><?php echo $total_active; ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#inactiveTab">
                                    <i class="fas fa-ban mr-1"></i>Inactive
                                    <span class="badge badge-danger"><?php echo $total_inactive; ?></span>
                                </a>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content">
                            
                            <!-- PENDING FACULTY TAB -->
                            <div class="tab-pane fade show active" id="pendingTab">
                                <div class="table-card">
                                    <div class="table-header">
                                        <h3 class="table-title">
                                            <i class="fas fa-user-clock mr-2" style="color: #eab308;"></i>
                                            Pending Faculty Approvals
                                        </h3>
                                        <div class="filter-section">
                                            <div class="search-box">
                                                <i class="fas fa-search"></i>
                                                <input type="text" id="searchPending" class="form-control" placeholder="Search pending..." style="min-width: 250px;">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table" id="pendingTable">
                                            <thead>
                                                <tr>
                                                    <th>Faculty</th>
                                                    <th>Username</th>
                                                    <th>Phone</th>
                                                    <th>Specialization</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($pending_faculty)): ?>
                                                    <tr>
                                                        <td colspan="6">
                                                            <div class="empty-state">
                                                                <i class="fas fa-check-circle"></i>
                                                                <p class="mb-0">No pending faculty approvals.</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($pending_faculty as $faculty): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="faculty-info">
                                                                <?php 
                                                                $name_parts = explode(' ', $faculty['full_name']);
                                                                $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                                                ?>
                                                                <div class="avatar-circle"><?php echo $initials; ?></div>
                                                                <strong><?php echo htmlspecialchars($faculty['full_name']); ?></strong>
                                                            </div>
                                                        </td>
                                                        <td>@<?php echo htmlspecialchars($faculty['username']); ?></td>
                                                        <td><?php echo htmlspecialchars($faculty['phone_number'] ?: '—'); ?></td>
                                                        <td><?php echo htmlspecialchars($faculty['specialization'] ?: 'Not specified'); ?></td>
                                                        <td>
                                                            <span class="badge-status badge-pending">
                                                                <i class="fas fa-clock mr-1"></i>Pending
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="approve">
                                                                <input type="hidden" name="user_id" value="<?php echo $faculty['user_id']; ?>">
                                                                <button type="submit" class="btn-action btn-approve" onclick="return confirm('Approve this faculty account?')">
                                                                    <i class="fas fa-check mr-1"></i>Approve
                                                                </button>
                                                            </form>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="reject">
                                                                <input type="hidden" name="user_id" value="<?php echo $faculty['user_id']; ?>">
                                                                <button type="submit" class="btn-action btn-reject" onclick="return confirm('Reject this faculty account? It will be set to inactive.')">
                                                                    <i class="fas fa-times mr-1"></i>Reject
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ACTIVE FACULTY TAB -->
                            <div class="tab-pane fade" id="activeTab">
                                <div class="table-card">
                                    <div class="table-header">
                                        <h3 class="table-title">
                                            <i class="fas fa-user-check mr-2" style="color: #22c55e;"></i>
                                            Active Faculty Accounts
                                        </h3>
                                        <div class="filter-section">
                                            <div class="search-box">
                                                <i class="fas fa-search"></i>
                                                <input type="text" id="searchActive" class="form-control" placeholder="Search active..." style="min-width: 250px;">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table" id="activeTable">
                                            <thead>
                                                <tr>
                                                    <th>Faculty</th>
                                                    <th>Username</th>
                                                    <th>Phone</th>
                                                    <th>Specialization</th>
                                                    <th>Loads</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($active_faculty)): ?>
                                                    <tr>
                                                        <td colspan="7">
                                                            <div class="empty-state">
                                                                <i class="fas fa-users"></i>
                                                                <p class="mb-0">No active faculty accounts.</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($active_faculty as $faculty): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="faculty-info">
                                                                <?php 
                                                                $name_parts = explode(' ', $faculty['full_name']);
                                                                $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                                                ?>
                                                                <div class="avatar-circle"><?php echo $initials; ?></div>
                                                                <strong><?php echo htmlspecialchars($faculty['full_name']); ?></strong>
                                                            </div>
                                                        </td>
                                                        <td>@<?php echo htmlspecialchars($faculty['username']); ?></td>
                                                        <td><?php echo htmlspecialchars($faculty['phone_number'] ?: '—'); ?></td>
                                                        <td><?php echo htmlspecialchars($faculty['specialization'] ?: 'Not specified'); ?></td>
                                                        <td>
                                                            <span class="badge bg-info"><?php echo $faculty['total_loads']; ?> load(s)</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge-status badge-active">
                                                                <i class="fas fa-check-circle mr-1"></i>Active
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="deactivate">
                                                                <input type="hidden" name="user_id" value="<?php echo $faculty['user_id']; ?>">
                                                                <button type="submit" class="btn-action btn-deactivate" onclick="return confirm('Deactivate this faculty account?')">
                                                                    <i class="fas fa-ban mr-1"></i>Deactivate
                                                                </button>
                                                            </form>
                                                            <a href="view_faculty.php?id=<?php echo $faculty['user_id']; ?>" class="btn-action btn-view">
                                                                <i class="fas fa-eye mr-1"></i>View
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- INACTIVE FACULTY TAB -->
                            <div class="tab-pane fade" id="inactiveTab">
                                <div class="table-card">
                                    <div class="table-header">
                                        <h3 class="table-title">
                                            <i class="fas fa-user-slash mr-2" style="color: #ef4444;"></i>
                                            Inactive Faculty Accounts
                                        </h3>
                                        <div class="filter-section">
                                            <div class="search-box">
                                                <i class="fas fa-search"></i>
                                                <input type="text" id="searchInactive" class="form-control" placeholder="Search inactive..." style="min-width: 250px;">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table" id="inactiveTable">
                                            <thead>
                                                <tr>
                                                    <th>Faculty</th>
                                                    <th>Username</th>
                                                    <th>Phone</th>
                                                    <th>Specialization</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($inactive_faculty)): ?>
                                                    <tr>
                                                        <td colspan="6">
                                                            <div class="empty-state">
                                                                <i class="fas fa-user-slash"></i>
                                                                <p class="mb-0">No inactive faculty accounts.</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($inactive_faculty as $faculty): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="faculty-info">
                                                                <?php 
                                                                $name_parts = explode(' ', $faculty['full_name']);
                                                                $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                                                ?>
                                                                <div class="avatar-circle"><?php echo $initials; ?></div>
                                                                <strong><?php echo htmlspecialchars($faculty['full_name']); ?></strong>
                                                            </div>
                                                        </td>
                                                        <td>@<?php echo htmlspecialchars($faculty['username']); ?></td>
                                                        <td><?php echo htmlspecialchars($faculty['phone_number'] ?: '—'); ?></td>
                                                        <td><?php echo htmlspecialchars($faculty['specialization'] ?: 'Not specified'); ?></td>
                                                        <td>
                                                            <span class="badge-status badge-inactive">
                                                                <i class="fas fa-ban mr-1"></i>Inactive
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="activate">
                                                                <input type="hidden" name="user_id" value="<?php echo $faculty['user_id']; ?>">
                                                                <button type="submit" class="btn-action btn-activate" onclick="return confirm('Activate this faculty account?')">
                                                                    <i class="fas fa-check-circle mr-1"></i>Activate
                                                                </button>
                                                            </form>
                                                            <a href="view_faculty.php?id=<?php echo $faculty['user_id']; ?>" class="btn-action btn-view">
                                                                <i class="fas fa-eye mr-1"></i>View
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>
    
    <script>
        // Search functionality for all tables
        document.getElementById('searchPending').addEventListener('keyup', function() {
            filterTable('pendingTable', this.value);
        });
        
        document.getElementById('searchActive').addEventListener('keyup', function() {
            filterTable('activeTable', this.value);
        });
        
        document.getElementById('searchInactive').addEventListener('keyup', function() {
            filterTable('inactiveTable', this.value);
        });
        
        function filterTable(tableId, searchTerm) {
            const table = document.getElementById(tableId);
            const rows = table.querySelectorAll('tbody tr');
            const term = searchTerm.toLowerCase();
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        }
        
        // Auto-hide alerts
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    </script>
</body>
</html>