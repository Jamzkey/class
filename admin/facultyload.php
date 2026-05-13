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

// Handle CRUD Operations
$message = '';
$message_type = '';

// CREATE - Assign Faculty Load
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $instructor_id = filter_input(INPUT_POST, 'instructor_id', FILTER_VALIDATE_INT);
        $offering_id = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
        
        try {
            $stmt = $conn->prepare("INSERT INTO faculty_load (instructor_id, offering_id, status) VALUES (:instructor_id, :offering_id, 'draft')");
            $stmt->execute([':instructor_id' => $instructor_id, ':offering_id' => $offering_id]);
            $message = "Faculty load assigned successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error assigning faculty load: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // UPDATE - Update Faculty Load Status (Super Admin only)
    elseif ($_POST['action'] === 'update_status' && $_SESSION['role'] === 'super_admin') {
        $load_id = filter_input(INPUT_POST, 'load_id', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
        
        try {
            $stmt = $conn->prepare("UPDATE faculty_load SET status = :status WHERE load_id = :load_id");
            $stmt->execute([':status' => $status, ':load_id' => $load_id]);
            
            // If approved, create approval record
            if ($status === 'approved') {
                $stmt = $conn->prepare("INSERT INTO approvals (load_id, approved_by, approval_date, status) VALUES (:load_id, :approved_by, NOW(), 'approved')");
                $stmt->execute([':load_id' => $load_id, ':approved_by' => $user_id]);
            }
            
            $message = "Faculty load status updated to " . ucfirst($status) . "!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error updating status: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // DELETE - Remove Faculty Load
    elseif ($_POST['action'] === 'delete') {
        $load_id = filter_input(INPUT_POST, 'load_id', FILTER_VALIDATE_INT);
        
        try {
            // Check for existing schedules
            $check = $conn->prepare("SELECT COUNT(*) FROM schedules WHERE load_id = :load_id");
            $check->execute([':load_id' => $load_id]);
            if ($check->fetchColumn() > 0) {
                $message = "Cannot delete: This load has associated schedules. Remove schedules first.";
                $message_type = "warning";
            } else {
                $stmt = $conn->prepare("DELETE FROM faculty_load WHERE load_id = :load_id");
                $stmt->execute([':load_id' => $load_id]);
                $message = "Faculty load deleted successfully!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error deleting faculty load: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // UPDATE - Edit Faculty Load (Change instructor or offering)
    elseif ($_POST['action'] === 'edit') {
        $load_id = filter_input(INPUT_POST, 'load_id', FILTER_VALIDATE_INT);
        $instructor_id = filter_input(INPUT_POST, 'instructor_id', FILTER_VALIDATE_INT);
        $offering_id = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
        
        try {
            $stmt = $conn->prepare("UPDATE faculty_load SET instructor_id = :instructor_id, offering_id = :offering_id WHERE load_id = :load_id");
            $stmt->execute([':instructor_id' => $instructor_id, ':offering_id' => $offering_id, ':load_id' => $load_id]);
            $message = "Faculty load updated successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error updating faculty load: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Fetch user details
$stmt = $conn->prepare("SELECT full_name, username, role, user_pic FROM users WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$full_name = $user['full_name'] ?? $user['username'] ?? 'Administrator';

// Fetch all faculty loads with details
$faculty_loads = $conn->query("
    SELECT 
        fl.load_id,
        fl.status,
        u.full_name as instructor_name,
        u.user_id as instructor_user_id,
        i.specialization,
        sub.subject_code,
        sub.subject_name,
        sub.units,
        so.school_year,
        so.semester,
        c.course_name,
        curr.year_level,
        (SELECT COUNT(*) FROM schedules s WHERE s.load_id = fl.load_id) as schedule_count
    FROM faculty_load fl
    JOIN instructors i ON fl.instructor_id = i.instructor_id
    JOIN users u ON i.user_id = u.user_id
    JOIN subject_offerings so ON fl.offering_id = so.offering_id
    JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
    JOIN subjects sub ON cs.subject_id = sub.subject_id
    JOIN curriculum curr ON cs.curriculum_id = curr.curriculum_id
    JOIN courses c ON curr.course_id = c.course_id
    ORDER BY u.full_name, fl.load_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Group faculty loads by instructor
$grouped_loads = [];
foreach ($faculty_loads as $load) {
    $instructor_key = $load['instructor_user_id'] . '_' . $load['instructor_name'];
    if (!isset($grouped_loads[$instructor_key])) {
        $grouped_loads[$instructor_key] = [
            'instructor_name' => $load['instructor_name'],
            'instructor_id' => $load['instructor_user_id'],
            'specialization' => $load['specialization'],
            'loads' => [],
            'total_loads' => 0,
            'pending_count' => 0,
            'approved_count' => 0,
            'rejected_count' => 0
        ];
    }
    $grouped_loads[$instructor_key]['loads'][] = $load;
    $grouped_loads[$instructor_key]['total_loads']++;
    
    // Count statuses
    if ($load['status'] === 'pending') $grouped_loads[$instructor_key]['pending_count']++;
    elseif ($load['status'] === 'approved') $grouped_loads[$instructor_key]['approved_count']++;
    elseif ($load['status'] === 'rejected') $grouped_loads[$instructor_key]['rejected_count']++;
}

// Fetch instructors for dropdown
$instructors = $conn->query("
    SELECT i.instructor_id, u.full_name, i.specialization 
    FROM instructors i 
    JOIN users u ON i.user_id = u.user_id 
    WHERE u.status = 'active'
    ORDER BY u.full_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch subject offerings for dropdown
$offerings = $conn->query("
    SELECT 
        so.offering_id,
        sub.subject_code,
        sub.subject_name,
        c.course_name,
        curr.year_level,
        so.school_year,
        so.semester,
        CONCAT(sub.subject_code, ' - ', sub.subject_name) as display_text
    FROM subject_offerings so
    JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
    JOIN subjects sub ON cs.subject_id = sub.subject_id
    JOIN curriculum curr ON cs.curriculum_id = curr.curriculum_id
    JOIN courses c ON curr.course_id = c.course_id
    ORDER BY so.school_year DESC, so.semester, c.course_name
")->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$total_loads = count($faculty_loads);
$pending_count = count(array_filter($faculty_loads, fn($load) => $load['status'] === 'pending'));
$approved_count = count(array_filter($faculty_loads, fn($load) => $load['status'] === 'approved'));
$rejected_count = count(array_filter($faculty_loads, fn($load) => $load['status'] === 'rejected'));

// Get unique instructors count
$unique_instructors = count($grouped_loads);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Faculty Load Management | Class Scheduling System</title>
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        
        .table {
            margin-bottom: 0;
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
        
        .badge-approved {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            margin: 2px;
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
        
        .btn-edit {
            background: #3b82f6;
            color: white;
        }
        
        .btn-edit:hover {
            background: #2563eb;
            color: white;
        }
        
        .btn-delete {
            background: #64748b;
            color: white;
        }
        
        .btn-delete:hover {
            background: #475569;
            color: white;
        }
        
        .btn-expand {
            background: #8b5cf6;
            color: white;
            padding: 4px 10px;
            font-size: 11px;
        }
        
        .btn-expand:hover {
            background: #7c3aed;
            color: white;
        }
        
        .modal-content {
            border-radius: 20px;
            border: none;
        }
        
        .modal-header {
            border-bottom: 1px solid #e2e8f0;
            padding: 20px 24px;
        }
        
        .modal-title {
            font-weight: 700;
            color: #0f172a;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            border-top: 1px solid #e2e8f0;
            padding: 20px 24px;
        }
        
        .form-label {
            font-weight: 500;
            color: #475569;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 10px 14px;
            font-size: 14px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Group Row Styling */
        .group-row {
            background-color: #f8fafc;
            cursor: pointer;
            transition: background-color 0.2s;
            border-left: 4px solid #8b5cf6;
        }
        
        .group-row:hover {
            background-color: #f1f5f9;
        }
        
        .child-row {
            background-color: #ffffff;
        }
        
        .child-row.hidden {
            display: none;
        }
        
        .child-row td {
            padding-left: 50px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .rotate-icon {
            transition: transform 0.2s;
        }
        
        .rotate-icon.expanded {
            transform: rotate(90deg);
        }
        
        .badge-count {
            background: #8b5cf6;
            color: white;
            border-radius: 20px;
            padding: 3px 8px;
            font-size: 11px;
            margin-left: 8px;
        }
        
        .status-badge-group {
            display: inline-flex;
            gap: 5px;
        }
        
        .status-badge-sm {
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .status-badge-sm.pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge-sm.approved {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-badge-sm.rejected {
            background: #fee2e2;
            color: #991b1b;
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
        
        .superadmin-badge {
            background: #8b5cf6;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 8px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .page-wrapper { padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .table-header { flex-direction: column; align-items: flex-start; }
            .filter-section { width: 100%; }
            .search-box { width: 100%; }
            .child-row td { padding-left: 30px; }
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
                                        <i class="fas fa-tasks mr-2" style="color: #3b82f6;"></i>
                                        Faculty Load Management
                                        <?php if($user_role === 'super_admin'): ?>
                                            <span class="superadmin-badge">Super Admin</span>
                                        <?php endif; ?>
                                    </h1>
                                    <p class="page-subtitle mb-0">Assign, update, and manage faculty teaching loads</p>
                                </div>
                                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                    <button class="btn btn-primary btn-lg" data-toggle="modal" data-target="#assignLoadModal" style="border-radius: 30px; padding: 10px 24px;">
                                        <i class="fas fa-plus-circle mr-2"></i>Assign New Load
                                    </button>
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
                                    <i class="fas fa-book-open"></i>
                                </div>
                                <div class="stat-value"><?php echo $total_loads; ?></div>
                                <div class="stat-label">Total Loads</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #dcfce7; color: #22c55e;">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stat-value"><?php echo $approved_count; ?></div>
                                <div class="stat-label">Approved</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #fee2e2; color: #ef4444;">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="stat-value"><?php echo $rejected_count; ?></div>
                                <div class="stat-label">Rejected</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #f1f5f9; color: #64748b;">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <div class="stat-value"><?php echo $unique_instructors; ?></div>
                                <div class="stat-label">Instructors</div>
                            </div>
                        </div>

                        <!-- Faculty Load Table - Grouped by Instructor -->
                        <div class="table-card">
                            <div class="table-header">
                                <h3 class="table-title">
                                    <i class="fas fa-list mr-2" style="color: #8b5cf6;"></i>
                                    Current Faculty Loads
                                </h3>
                                <div class="filter-section">
                                    <div class="search-box">
                                        <i class="fas fa-search"></i>
                                        <input type="text" id="searchInput" class="form-control" placeholder="Search instructors..." style="min-width: 250px;">
                                    </div>
                                    <select id="statusFilter" class="form-select" style="width: auto; border-radius: 30px;">
                                        <option value="all">All Status</option>
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table" id="loadsTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;"></th>
                                            <th>Instructor</th>
                                            <th>Subject</th>
                                            <th>Course/Year</th>
                                            <th>School Year</th>
                                            <th>Units</th>
                                            <th>Status</th>
                                            <th>Schedules</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($grouped_loads)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-5 text-muted">
                                                    <i class="fas fa-inbox fa-3x mb-3" style="opacity: 0.5;"></i>
                                                    <p class="mb-0">No faculty loads found. Click "Assign New Load" to get started.</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $group_index = 0; ?>
                                            <?php foreach ($grouped_loads as $group): ?>
                                                <?php 
                                                $group_id = 'group_' . $group_index;
                                                $expand_icon_id = 'expand_icon_' . $group_index;
                                                $group_index++;
                                                $first_load = $group['loads'][0];
                                                $status_summary = [];
                                                if ($group['pending_count'] > 0) $status_summary[] = "<span class='status-badge-sm pending'>{$group['pending_count']} Pending</span>";
                                                if ($group['approved_count'] > 0) $status_summary[] = "<span class='status-badge-sm approved'>{$group['approved_count']} Approved</span>";
                                                if ($group['rejected_count'] > 0) $status_summary[] = "<span class='status-badge-sm rejected'>{$group['rejected_count']} Rejected</span>";
                                                ?>
                                                <!-- Group Row (Instructor Summary) -->
                                                <tr class="group-row" onclick="toggleGroup('<?php echo $group_id; ?>', '<?php echo $expand_icon_id; ?>')" data-instructor="<?php echo strtolower(htmlspecialchars($group['instructor_name'])); ?>">
                                                    <td class="text-center">
                                                        <i id="<?php echo $expand_icon_id; ?>" class="fas fa-chevron-right rotate-icon"></i>
                                                    </td>
                                                    <td>
                                                        <strong>
                                                            <i class="fas fa-chalkboard-user mr-2" style="color: #8b5cf6;"></i>
                                                            <?php echo htmlspecialchars($group['instructor_name']); ?>
                                                        </strong>
                                                        <span class="badge-count"><?php echo $group['total_loads']; ?> load(s)</span>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="fas fa-graduation-cap mr-1"></i>
                                                            <?php echo htmlspecialchars($group['specialization'] ?: 'General'); ?>
                                                        </small>
                                                    </td>
                                                    <td colspan="7">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="text-muted">
                                                                <i class="fas fa-folder-open mr-1"></i>
                                                                Click to expand/collapse loads
                                                            </span>
                                                            <div class="status-badge-group">
                                                                <?php echo implode(' ', $status_summary); ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                
                                                <!-- Child Rows (Individual Loads) -->
                                                <?php foreach ($group['loads'] as $load): ?>
                                                <tr class="child-row hidden" id="<?php echo $group_id; ?>" data-status="<?php echo $load['status']; ?>">
                                                    <td></td>
                                                    <td></td>
                                                    <td>
                                                        <div><?php echo htmlspecialchars($load['subject_code']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($load['subject_name']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($load['course_name']); ?>
                                                        <span class="badge bg-light text-dark ml-1">Year <?php echo $load['year_level']; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($load['school_year']); ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo $load['semester']; ?> Semester</small>
                                                    </td>
                                                    <td><span class="badge bg-light text-dark"><?php echo $load['units']; ?> units</span></td>
                                                    <td>
                                                        <span class="badge-status badge-<?php echo $load['status']; ?>">
                                                            <?php echo ucfirst($load['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark">
                                                            <i class="fas fa-calendar-alt mr-1"></i>
                                                            <?php echo $load['schedule_count']; ?> schedule(s)
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <?php if ($user_role === 'super_admin' && $load['status'] === 'pending'): ?>
                                                            <button class="btn-action btn-approve" onclick="event.stopPropagation(); updateStatus(<?php echo $load['load_id']; ?>, 'approved')" title="Approve">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button class="btn-action btn-reject" onclick="event.stopPropagation(); updateStatus(<?php echo $load['load_id']; ?>, 'rejected')" title="Reject">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                            <button class="btn-action btn-edit" onclick="event.stopPropagation(); editLoad(<?php echo $load['load_id']; ?>)" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn-action btn-delete" onclick="event.stopPropagation(); deleteLoad(<?php echo $load['load_id']; ?>)" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Assign Load Modal -->
                        <div class="modal fade" id="assignLoadModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <form method="POST" id="assignLoadForm">
                                        <input type="hidden" name="action" value="create">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-plus-circle mr-2" style="color: #3b82f6;"></i>
                                                Assign New Faculty Load
                                            </h5>
                                            <button type="button" class="close" data-dismiss="modal">
                                                <span>&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label class="form-label">
                                                            <i class="fas fa-user mr-1"></i>Select Instructor
                                                        </label>
                                                        <select name="instructor_id" class="form-select" required>
                                                            <option value="">Choose instructor...</option>
                                                            <?php foreach ($instructors as $instructor): ?>
                                                            <option value="<?php echo $instructor['instructor_id']; ?>">
                                                                <?php echo htmlspecialchars($instructor['full_name']); ?> 
                                                                (<?php echo htmlspecialchars($instructor['specialization'] ?: 'General'); ?>)
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label class="form-label">
                                                            <i class="fas fa-book mr-1"></i>Select Subject Offering
                                                        </label>
                                                        <select name="offering_id" class="form-select" id="subjectOfferingSelect" required size="8" style="height: auto; max-height: 250px;">
                                                            <option value="">-- Select Subject Offering --</option>
                                                            <?php 
                                                            $current_year = '';
                                                            $current_course = '';
                                                            foreach ($offerings as $offering):
                                                                $display_year = $offering['school_year'] . ' - ' . ucfirst($offering['semester']) . ' Semester';
                                                                $course_info = $offering['course_name'] . ' - Year ' . $offering['year_level'];
                                                                $subject_info = $offering['subject_code'] . ' - ' . $offering['subject_name'];
                                                            ?>
                                                                <?php if ($current_year != $display_year): ?>
                                                                    <?php if ($current_year != ''): ?>
                                                                        </optgroup>
                                                                    <?php endif; ?>
                                                                    <optgroup label="📅 <?php echo htmlspecialchars($display_year); ?>">
                                                                    <?php $current_year = $display_year; ?>
                                                                    <?php $current_course = ''; ?>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($current_course != $course_info): ?>
                                                                    <?php if ($current_course != ''): ?>
                                                                        </optgroup>
                                                                    <?php endif; ?>
                                                                    <optgroup label="  📚 <?php echo htmlspecialchars($course_info); ?>" style="padding-left: 20px;">
                                                                    <?php $current_course = $course_info; ?>
                                                                <?php endif; ?>
                                                                
                                                                <option value="<?php echo $offering['offering_id']; ?>" 
                                                                        title="<?php echo htmlspecialchars($subject_info); ?>">
                                                                    &nbsp;&nbsp;• <?php echo htmlspecialchars($subject_info); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                            <?php if ($current_year != ''): ?>
                                                                    </optgroup>
                                                                </optgroup>
                                                            <?php endif; ?>
                                                        </select>
                                                        <small class="form-text text-muted mt-2">
                                                            <i class="fas fa-info-circle"></i> 
                                                            Click to select a subject. Use scroll to see more options.
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="alert alert-info mt-3">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Faculty loads are created with <strong>"Pending"</strong> status by default and require super admin approval.
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save mr-1"></i>Assign Load
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Load Modal -->
                        <div class="modal fade" id="editLoadModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <form method="POST" id="editLoadForm">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="load_id" id="edit_load_id">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-edit mr-2" style="color: #3b82f6;"></i>
                                                Edit Faculty Load
                                            </h5>
                                            <button type="button" class="close" data-dismiss="modal">
                                                <span>&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label class="form-label">Select Instructor</label>
                                                        <select name="instructor_id" id="edit_instructor_id" class="form-select" required>
                                                            <option value="">Choose instructor...</option>
                                                            <?php foreach ($instructors as $instructor): ?>
                                                            <option value="<?php echo $instructor['instructor_id']; ?>">
                                                                <?php echo htmlspecialchars($instructor['full_name']); ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label class="form-label">Select Subject Offering</label>
                                                        <select name="offering_id" id="edit_offering_id" class="form-select" required size="8" style="height: auto; max-height: 250px;">
                                                            <option value="">-- Select Subject Offering --</option>
                                                            <?php 
                                                            $current_year = '';
                                                            $current_course = '';
                                                            foreach ($offerings as $offering):
                                                                $display_year = $offering['school_year'] . ' - ' . ucfirst($offering['semester']) . ' Semester';
                                                                $course_info = $offering['course_name'] . ' - Year ' . $offering['year_level'];
                                                                $subject_info = $offering['subject_code'] . ' - ' . $offering['subject_name'];
                                                            ?>
                                                                <?php if ($current_year != $display_year): ?>
                                                                    <?php if ($current_year != ''): ?>
                                                                        </optgroup>
                                                                    <?php endif; ?>
                                                                    <optgroup label="📅 <?php echo htmlspecialchars($display_year); ?>">
                                                                    <?php $current_year = $display_year; ?>
                                                                    <?php $current_course = ''; ?>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($current_course != $course_info): ?>
                                                                    <?php if ($current_course != ''): ?>
                                                                        </optgroup>
                                                                    <?php endif; ?>
                                                                    <optgroup label="  📚 <?php echo htmlspecialchars($course_info); ?>" style="padding-left: 20px;">
                                                                    <?php $current_course = $course_info; ?>
                                                                <?php endif; ?>
                                                                
                                                                <option value="<?php echo $offering['offering_id']; ?>"
                                                                        title="<?php echo htmlspecialchars($subject_info); ?>">
                                                                    &nbsp;&nbsp;• <?php echo htmlspecialchars($subject_info); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                            <?php if ($current_year != ''): ?>
                                                                    </optgroup>
                                                                </optgroup>
                                                            <?php endif; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Update Load</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Status Update Forms (Hidden) - Only for Super Admin -->
                        <?php if ($user_role === 'super_admin'): ?>
                        <form method="POST" id="statusForm" style="display: none;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="load_id" id="status_load_id">
                            <input type="hidden" name="status" id="status_value">
                        </form>
                        <?php endif; ?>

                        <!-- Delete Form (Hidden) -->
                        <form method="POST" id="deleteForm" style="display: none;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="load_id" id="delete_load_id">
                        </form>

                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>
    
    <script>
        // Toggle group expand/collapse function - FIXED VERSION
        function toggleGroup(groupId, iconId) {
            const groupRows = document.querySelectorAll('#' + groupId);
            const icon = document.getElementById(iconId);
            
            groupRows.forEach(row => {
                // Toggle the 'hidden' class instead of manipulating style directly
                row.classList.toggle('hidden');
            });
            
            if (icon) {
                icon.classList.toggle('expanded');
            }
        }
        
        // Search and Filter functionality
        document.getElementById('searchInput').addEventListener('keyup', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);
        
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const groupRows = document.querySelectorAll('.group-row');
            
            groupRows.forEach(groupRow => {
                const instructorName = groupRow.getAttribute('data-instructor') || '';
                const matchesSearch = instructorName.includes(searchTerm);
                
                if (statusFilter === 'all') {
                    groupRow.style.display = matchesSearch ? '' : 'none';
                } else {
                    // For status filter, check child rows
                    const childRows = groupRow.nextElementSibling;
                    if (childRows && childRows.classList.contains('child-row')) {
                        let hasMatchingStatus = false;
                        let currentRow = childRows;
                        while (currentRow && currentRow.classList.contains('child-row')) {
                            const status = currentRow.getAttribute('data-status');
                            if (status === statusFilter) {
                                hasMatchingStatus = true;
                                break;
                            }
                            currentRow = currentRow.nextElementSibling;
                        }
                        groupRow.style.display = (matchesSearch && hasMatchingStatus) ? '' : 'none';
                    } else {
                        groupRow.style.display = matchesSearch ? '' : 'none';
                    }
                }
            });
        }
        
        // Status Update Function (Only available for Super Admin)
        <?php if ($user_role === 'super_admin'): ?>
        function updateStatus(loadId, status) {
            if (confirm('Are you sure you want to ' + status + ' this faculty load?')) {
                document.getElementById('status_load_id').value = loadId;
                document.getElementById('status_value').value = status;
                document.getElementById('statusForm').submit();
            }
        }
        <?php endif; ?>
        
        // Delete Function
        function deleteLoad(loadId) {
            if (confirm('Are you sure you want to delete this faculty load? This action cannot be undone.')) {
                document.getElementById('delete_load_id').value = loadId;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Edit Function
        function editLoad(loadId) {
            $('#edit_load_id').val(loadId);
            $('#editLoadModal').modal('show');
        }
        
        // Initialize tooltips
        $(function () {
            $('[title]').tooltip();
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
        // Improve select dropdown on modal open
        $('#assignLoadModal, #editLoadModal').on('shown.bs.modal', function() {
            $('.form-select[size]').each(function() {
                var options = $(this).find('option').length;
                var height = Math.min(options * 32, 250);
                $(this).css('height', height + 'px');
            });
        });
    </script>
</body>
</html>