<?php
session_start();
include('assets/inc/db.php');

// Check if user is logged in and has proper role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$message = '';
$message_type = '';

// ========== SUBMISSION OPERATIONS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Admin creates a draft load (NOT submitted yet)
    if ($_POST['action'] === 'create_draft' && $user_role === 'admin') {
        $instructor_id = filter_input(INPUT_POST, 'instructor_id', FILTER_VALIDATE_INT);
        $offering_id = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
        
        try {
            $stmt = $conn->prepare("INSERT INTO faculty_load (instructor_id, offering_id, status) VALUES (:instructor_id, :offering_id, 'draft')");
            $stmt->execute([':instructor_id' => $instructor_id, ':offering_id' => $offering_id]);
            $message = "✅ Draft saved! Select it and click 'Submit for Approval' when ready.";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // Admin submits draft loads for approval (DRAFT → PENDING)
    elseif ($_POST['action'] === 'submit_for_approval' && $user_role === 'admin') {
        $load_ids = $_POST['load_ids'] ?? [];
        
        if (!empty($load_ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($load_ids), '?'));
                $stmt = $conn->prepare("UPDATE faculty_load SET status = 'pending' WHERE load_id IN ($placeholders) AND status = 'draft'");
                $stmt->execute($load_ids);
                $count = $stmt->rowCount();
                $message = "✅ " . $count . " draft(s) submitted for Super Admin approval!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    }
    
    // Super Admin approves a pending load
    elseif ($_POST['action'] === 'approve' && $user_role === 'super_admin') {
        $load_id = filter_input(INPUT_POST, 'load_id', FILTER_VALIDATE_INT);
        
        try {
            $stmt = $conn->prepare("UPDATE faculty_load SET status = 'approved' WHERE load_id = :load_id");
            $stmt->execute([':load_id' => $load_id]);
            
            $stmt = $conn->prepare("INSERT INTO approvals (load_id, approved_by, approval_date, status) VALUES (:load_id, :approved_by, NOW(), 'approved')");
            $stmt->execute([':load_id' => $load_id, ':approved_by' => $user_id]);
            
            $message = "✅ Faculty load approved!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // Super Admin rejects a pending load
    elseif ($_POST['action'] === 'reject' && $user_role === 'super_admin') {
        $load_id = filter_input(INPUT_POST, 'load_id', FILTER_VALIDATE_INT);
        
        try {
            $stmt = $conn->prepare("UPDATE faculty_load SET status = 'rejected' WHERE load_id = :load_id");
            $stmt->execute([':load_id' => $load_id]);
            
            $stmt = $conn->prepare("INSERT INTO approvals (load_id, approved_by, approval_date, status) VALUES (:load_id, :approved_by, NOW(), 'rejected')");
            $stmt->execute([':load_id' => $load_id, ':approved_by' => $user_id]);
            
            $message = "❌ Faculty load rejected!";
            $message_type = "warning";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // Admin deletes a draft load
    elseif ($_POST['action'] === 'delete' && $user_role === 'admin') {
        $load_id = filter_input(INPUT_POST, 'load_id', FILTER_VALIDATE_INT);
        
        try {
            $stmt = $conn->prepare("DELETE FROM faculty_load WHERE load_id = :load_id AND status = 'draft'");
            $stmt->execute([':load_id' => $load_id]);
            if ($stmt->rowCount() > 0) {
                $message = "✅ Draft deleted!";
                $message_type = "success";
            } else {
                $message = "Only draft loads can be deleted.";
                $message_type = "warning";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Add draft status to enum if not exists
try {
    $conn->query("ALTER TABLE faculty_load MODIFY status ENUM('draft','pending','approved','rejected') DEFAULT 'draft'");
} catch (PDOException $e) {
    // Already modified or ignore
}

// Fetch user details
$stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$full_name = $user['full_name'] ?? 'User';

// Fetch all faculty loads grouped by instructor
$faculty_loads = $conn->query("
    SELECT 
        fl.load_id,
        fl.status,
        i.instructor_id,
        u.full_name as instructor_name,
        sub.subject_code,
        sub.subject_name,
        sub.units,
        c.course_name,
        curr.year_level,
        so.school_year,
        so.semester
    FROM faculty_load fl
    JOIN instructors i ON fl.instructor_id = i.instructor_id
    JOIN users u ON i.user_id = u.user_id
    JOIN subject_offerings so ON fl.offering_id = so.offering_id
    JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
    JOIN subjects sub ON cs.subject_id = sub.subject_id
    JOIN curriculum curr ON cs.curriculum_id = curr.curriculum_id
    JOIN courses c ON curr.course_id = c.course_id
    ORDER BY u.full_name ASC, so.school_year DESC, sub.subject_code ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Group loads by instructor
$grouped_loads = [];
foreach ($faculty_loads as $load) {
    $instructor_name = $load['instructor_name'];
    if (!isset($grouped_loads[$instructor_name])) {
        $grouped_loads[$instructor_name] = [
            'instructor_id' => $load['instructor_id'],
            'instructor_name' => $instructor_name,
            'loads' => [],
            'total_units' => 0,
            'draft_count' => 0,
            'pending_count' => 0,
            'approved_count' => 0,
            'rejected_count' => 0
        ];
    }
    $grouped_loads[$instructor_name]['loads'][] = $load;
    $grouped_loads[$instructor_name]['total_units'] += $load['units'];
    
    // Count by status
    if ($load['status'] === 'draft') $grouped_loads[$instructor_name]['draft_count']++;
    elseif ($load['status'] === 'pending') $grouped_loads[$instructor_name]['pending_count']++;
    elseif ($load['status'] === 'approved') $grouped_loads[$instructor_name]['approved_count']++;
    elseif ($load['status'] === 'rejected') $grouped_loads[$instructor_name]['rejected_count']++;
}

// Statistics
$total_loads = count($faculty_loads);
$total_instructors = count($grouped_loads);
$draft_count = 0;
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

foreach ($faculty_loads as $load) {
    if ($load['status'] === 'draft') $draft_count++;
    elseif ($load['status'] === 'pending') $pending_count++;
    elseif ($load['status'] === 'approved') $approved_count++;
    elseif ($load['status'] === 'rejected') $rejected_count++;
}

// Fetch dropdown data (Admin only)
if ($user_role === 'admin') {
    $instructors = $conn->query("
        SELECT i.instructor_id, u.full_name 
        FROM instructors i 
        JOIN users u ON i.user_id = u.user_id 
        WHERE u.status = 'active'
        ORDER BY u.full_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $offerings = $conn->query("
        SELECT 
            so.offering_id,
            sub.subject_code,
            sub.subject_name,
            sub.units,
            c.course_name,
            curr.year_level,
            so.school_year,
            so.semester
        FROM subject_offerings so
        JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
        JOIN subjects sub ON cs.subject_id = sub.subject_id
        JOIN curriculum curr ON cs.curriculum_id = curr.curriculum_id
        JOIN courses c ON curr.course_id = c.course_id
        ORDER BY so.school_year DESC, so.semester, sub.subject_code
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Faculty Load Submission | Class Scheduling System</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
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
        
        .stat-value { font-size: 32px; font-weight: 700; color: #0f172a; }
        .stat-label { font-size: 14px; color: #64748b; font-weight: 500; }
        
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        /* Instructor Accordion Styles */
        .instructor-group {
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            background: white;
        }
        
        .instructor-header {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            background: #f8fafc;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid transparent;
        }
        
        .instructor-header:hover {
            background: #f1f5f9;
        }
        
        .instructor-group.expanded .instructor-header {
            border-bottom-color: #e2e8f0;
        }
        
        .instructor-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
            margin-right: 16px;
        }
        
        .instructor-info {
            flex: 1;
        }
        
        .instructor-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .instructor-stats {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }
        
        .stat-badge {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
        }
        
        .expand-icon {
            font-size: 20px;
            color: #64748b;
            transition: transform 0.3s;
            margin-left: 16px;
        }
        
        .instructor-group.expanded .expand-icon {
            transform: rotate(90deg);
        }
        
        .loads-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .loads-table th {
            background: #f8fafc;
            padding: 12px 16px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .loads-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        
        .loads-table tr:last-child td {
            border-bottom: none;
        }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-draft { background: #e2e8f0; color: #475569; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            margin: 2px;
        }
        
        .btn-approve { background: #22c55e; color: white; }
        .btn-approve:hover { background: #16a34a; }
        
        .btn-reject { background: #ef4444; color: white; }
        .btn-reject:hover { background: #dc2626; }
        
        .btn-submit { background: #8b5cf6; color: white; }
        .btn-submit:hover { background: #7c3aed; }
        
        .btn-delete { background: #64748b; color: white; }
        .btn-delete:hover { background: #475569; }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 30px;
            font-weight: 500;
        }
        
        .role-badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-admin { background: #3b82f6; color: white; }
        .badge-superadmin { background: #8b5cf6; color: white; }
        
        .modal-content { border-radius: 20px; border: none; }
        .modal-header { border-bottom: 1px solid #e2e8f0; padding: 20px 24px; }
        .modal-body { padding: 24px; }
        
        .form-label {
            font-weight: 500;
            color: #475569;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 10px 14px;
        }
        
        .checkbox-select {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .bulk-bar {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
        }
        
        .bulk-bar.show {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .workflow-steps {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .workflow-step {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .workflow-arrow {
            color: rgba(255,255,255,0.7);
            font-size: 20px;
        }
        
        /* Search Box */
        .search-box {
            position: relative;
            min-width: 300px;
        }
        
        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .search-box input {
            padding-left: 42px;
            border-radius: 30px;
            border: 1px solid #cbd5e1;
            width: 100%;
            height: 42px;
        }
        
        .search-box input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .units-badge {
            background: #3b82f6;
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .page-wrapper { padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .table-header { flex-direction: column; align-items: flex-start; }
            .filter-group { width: 100%; flex-wrap: wrap; }
            .search-box { min-width: 100%; }
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
                                        <i class="fas fa-paper-plane mr-2" style="color: #3b82f6;"></i>
                                        Faculty Load Submission
                                        <span class="role-badge <?php echo $user_role === 'super_admin' ? 'badge-superadmin' : 'badge-admin'; ?>">
                                            <?php echo $user_role === 'super_admin' ? 'Super Admin' : 'Admin'; ?>
                                        </span>
                                    </h1>
                                    <p class="mb-0" style="color: #64748b;">
                                        <?php if($user_role === 'admin'): ?>
                                            <i class="fas fa-info-circle mr-1"></i> Create draft → Submit for approval → Super Admin reviews
                                        <?php else: ?>
                                            <i class="fas fa-check-circle mr-1"></i> Review and approve pending faculty loads
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                    <?php if($user_role === 'admin'): ?>
                                    <button class="btn btn-primary" data-toggle="modal" data-target="#createDraftModal">
                                        <i class="fas fa-plus-circle mr-2"></i>Create New Draft
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Alert Messages -->
                        <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-circle' : 'info-circle'); ?> mr-2"></i>
                            <?php echo $message; ?>
                            <button type="button" class="close" onclick="this.parentElement.style.display='none'">
                                <span>&times;</span>
                            </button>
                        </div>
                        <?php endif; ?>

                        <!-- Workflow Steps (Admin Only) -->
                        <?php if($user_role === 'admin'): ?>
                        <div class="workflow-steps">
                            <div class="workflow-step">
                                <i class="fas fa-pencil-alt"></i>
                                <span><strong>1.</strong> Create Draft</span>
                            </div>
                            <i class="fas fa-arrow-right workflow-arrow"></i>
                            <div class="workflow-step">
                                <i class="fas fa-check-square"></i>
                                <span><strong>2.</strong> Select Draft(s)</span>
                            </div>
                            <i class="fas fa-arrow-right workflow-arrow"></i>
                            <div class="workflow-step">
                                <i class="fas fa-paper-plane"></i>
                                <span><strong>3.</strong> Submit for Approval</span>
                            </div>
                            <i class="fas fa-arrow-right workflow-arrow"></i>
                            <div class="workflow-step">
                                <i class="fas fa-check-circle"></i>
                                <span><strong>4.</strong> Super Admin Approves</span>
                            </div>
                        </div>
                        <?php endif; ?>

                      

                        <!-- Bulk Submit Bar (Admin Only) -->
                        <?php if($user_role === 'admin'): ?>
                        <div class="bulk-bar" id="bulkSubmitBar">
                            <i class="fas fa-check-square" style="color: #8b5cf6;"></i>
                            <span id="selectedCount">0</span> draft(s) selected
                            <button class="btn-submit btn-action" onclick="submitSelectedDrafts()">
                                <i class="fas fa-paper-plane mr-1"></i>Submit Selected for Approval
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="clearSelection()">
                                <i class="fas fa-times mr-1"></i>Clear
                            </button>
                        </div>
                        <?php endif; ?>

                        <!-- Faculty Load Table -->
                        <div class="table-card">
                            <div class="table-header">
                                <h3 style="font-size: 20px; font-weight: 600; margin: 0;">
                                    <i class="fas fa-tasks mr-2" style="color: #8b5cf6;"></i>
                                    Faculty Loads by Instructor
                                </h3>
                                <div class="filter-group">
                                    <div class="search-box">
                                        <i class="fas fa-search"></i>
                                        <input type="text" id="searchInstructor" class="form-control" placeholder="Search instructor or subject...">
                                    </div>
                                    <select id="statusFilter" class="form-select" style="width: 150px; border-radius: 30px;">
                                        <option value="all">All Status</option>
                                        <option value="draft">Draft</option>
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                    <button class="btn btn-outline-secondary" onclick="expandAll()" style="border-radius: 30px;">
                                        <i class="fas fa-chevron-down mr-1"></i>Expand All
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="collapseAll()" style="border-radius: 30px;">
                                        <i class="fas fa-chevron-up mr-1"></i>Collapse All
                                    </button>
                                </div>
                            </div>
                            
                            <div id="instructorGroups">
                                <?php if (empty($grouped_loads)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h4>No Faculty Loads Found</h4>
                                        <p class="text-muted">Create a draft to get started.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($grouped_loads as $instructor_name => $group): ?>
                                    <?php 
                                        $initials = '';
                                        $name_parts = explode(' ', $instructor_name);
                                        $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                    ?>
                                    <div class="instructor-group" data-instructor="<?php echo htmlspecialchars(strtolower($instructor_name)); ?>" data-status-counts='<?php echo json_encode(['draft' => $group['draft_count'], 'pending' => $group['pending_count'], 'approved' => $group['approved_count'], 'rejected' => $group['rejected_count']]); ?>'>
                                        <div class="instructor-header" onclick="toggleGroup(this)">
                                            <div class="instructor-avatar"><?php echo $initials; ?></div>
                                            <div class="instructor-info">
                                                <div class="instructor-name">
                                                    <?php echo htmlspecialchars($instructor_name); ?>
                                                    <span class="units-badge"><?php echo $group['total_units']; ?> units</span>
                                                </div>
                                                <div class="instructor-stats">
                                                    <span class="stat-badge" style="color: #475569;">
                                                        <i class="fas fa-list"></i> <?php echo count($group['loads']); ?> loads
                                                    </span>
                                                    <?php if($group['draft_count'] > 0): ?>
                                                    <span class="stat-badge" style="color: #64748b;">
                                                        <i class="fas fa-pencil-alt"></i> <?php echo $group['draft_count']; ?> drafts
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if($group['pending_count'] > 0): ?>
                                                    <span class="stat-badge" style="color: #d97706;">
                                                        <i class="fas fa-clock"></i> <?php echo $group['pending_count']; ?> pending
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if($group['approved_count'] > 0): ?>
                                                    <span class="stat-badge" style="color: #22c55e;">
                                                        <i class="fas fa-check-circle"></i> <?php echo $group['approved_count']; ?> approved
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <i class="fas fa-chevron-right expand-icon"></i>
                                        </div>
                                        <div class="loads-container" style="display: none;">
                                            <table class="loads-table">
                                                <thead>
                                                    <tr>
                                                        <?php if($user_role === 'admin'): ?>
                                                        <th style="width: 40px;">
                                                            <input type="checkbox" class="checkbox-select group-select-all" onchange="toggleGroupSelect(this, '<?php echo htmlspecialchars($instructor_name); ?>')">
                                                        </th>
                                                        <?php endif; ?>
                                                        <th>Subject</th>
                                                        <th>Course/Year</th>
                                                        <th>School Year</th>
                                                        <th>Units</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($group['loads'] as $load): ?>
                                                    <tr data-load-status="<?php echo $load['status']; ?>">
                                                        <?php if($user_role === 'admin'): ?>
                                                        <td>
                                                            <?php if($load['status'] === 'draft'): ?>
                                                            <input type="checkbox" class="checkbox-select load-checkbox" value="<?php echo $load['load_id']; ?>" data-instructor="<?php echo htmlspecialchars($instructor_name); ?>" onchange="updateBulkBar()">
                                                            <?php endif; ?>
                                                        </td>
                                                        <?php endif; ?>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($load['subject_code']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($load['subject_name']); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($load['course_name']); ?>
                                                            <span class="badge bg-light text-dark ml-1">Y<?php echo $load['year_level']; ?></span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($load['school_year'] . ' ' . $load['semester']); ?></td>
                                                        <td><span class="badge bg-light text-dark"><?php echo $load['units']; ?> units</span></td>
                                                        <td>
                                                            <span class="badge-status badge-<?php echo $load['status']; ?>">
                                                                <?php echo ucfirst($load['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($user_role === 'super_admin' && $load['status'] === 'pending'): ?>
                                                            <button class="btn-action btn-approve" onclick="approveLoad(<?php echo $load['load_id']; ?>)">
                                                                <i class="fas fa-check mr-1"></i>Approve
                                                            </button>
                                                            <button class="btn-action btn-reject" onclick="rejectLoad(<?php echo $load['load_id']; ?>)">
                                                                <i class="fas fa-times mr-1"></i>Reject
                                                            </button>
                                                            
                                                            <?php elseif ($user_role === 'admin' && $load['status'] === 'draft'): ?>
                                                            <button class="btn-action btn-submit" onclick="submitSingleDraft(<?php echo $load['load_id']; ?>)">
                                                                <i class="fas fa-paper-plane mr-1"></i>Submit
                                                            </button>
                                                            <button class="btn-action btn-delete" onclick="deleteDraft(<?php echo $load['load_id']; ?>)">
                                                                <i class="fas fa-trash mr-1"></i>Delete
                                                            </button>
                                                            
                                                            <?php elseif ($user_role === 'admin' && $load['status'] === 'pending'): ?>
                                                            <span class="badge bg-warning text-dark">
                                                                <i class="fas fa-clock mr-1"></i>Waiting
                                                            </span>
                                                            
                                                            <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Create Draft Modal (Admin Only) -->
                        <?php if($user_role === 'admin'): ?>
                        <div class="modal fade" id="createDraftModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="create_draft">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-pencil-alt mr-2" style="color: #3b82f6;"></i>
                                                Create Draft Faculty Load
                                            </h5>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                This will create a <strong>DRAFT</strong> load. You can submit it for approval later.
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label">Select Instructor</label>
                                                <select name="instructor_id" class="form-select" required>
                                                    <option value="">Choose instructor...</option>
                                                    <?php foreach ($instructors as $instructor): ?>
                                                    <option value="<?php echo $instructor['instructor_id']; ?>">
                                                        <?php echo htmlspecialchars($instructor['full_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group mt-3">
                                                <label class="form-label">Select Subject Offering</label>
                                                <select name="offering_id" class="form-select" required size="8" style="height: auto;">
                                                    <option value="">-- Select Subject --</option>
                                                    <?php foreach ($offerings as $offering): ?>
                                                    <option value="<?php echo $offering['offering_id']; ?>">
                                                        <?php echo htmlspecialchars($offering['subject_code'] . ' - ' . $offering['subject_name']); ?> 
                                                        (<?php echo htmlspecialchars($offering['course_name'] . ' Y' . $offering['year_level']); ?>)
                                                        [<?php echo $offering['school_year'] . ' ' . $offering['semester']; ?>]
                                                        - <?php echo $offering['units']; ?> units
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save mr-1"></i>Save as Draft
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Hidden Forms -->
                        <form method="POST" id="approveForm" style="display: none;">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="load_id" id="approve_load_id">
                        </form>
                        
                        <form method="POST" id="rejectForm" style="display: none;">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="load_id" id="reject_load_id">
                        </form>
                        
                        <form method="POST" id="submitForm" style="display: none;">
                            <input type="hidden" name="action" value="submit_for_approval">
                            <div id="submitLoadIds"></div>
                        </form>
                        
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
        // Real-time search filter
        document.getElementById('searchInstructor').addEventListener('keyup', function() {
            filterGroups();
        });
        
        document.getElementById('statusFilter').addEventListener('change', function() {
            filterGroups();
        });
        
        function filterGroups() {
            const searchTerm = document.getElementById('searchInstructor').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const groups = document.querySelectorAll('.instructor-group');
            
            groups.forEach(group => {
                const instructorName = group.getAttribute('data-instructor');
                const statusCounts = JSON.parse(group.getAttribute('data-status-counts'));
                
                // Check instructor name match
                const nameMatch = instructorName.includes(searchTerm);
                
                // Check subject match in the loads
                let subjectMatch = false;
                if (!nameMatch) {
                    const loads = group.querySelectorAll('.loads-table tbody tr');
                    loads.forEach(load => {
                        const loadText = load.textContent.toLowerCase();
                        if (loadText.includes(searchTerm)) {
                            subjectMatch = true;
                        }
                    });
                }
                
                // Check status filter
                let statusMatch = true;
                if (statusFilter !== 'all') {
                    statusMatch = statusCounts[statusFilter] > 0;
                }
                
                const shouldShow = (nameMatch || subjectMatch) && statusMatch;
                group.style.display = shouldShow ? '' : 'none';
            });
        }
        
        // Toggle group expand/collapse
        function toggleGroup(header) {
            const group = header.closest('.instructor-group');
            const container = group.querySelector('.loads-container');
            const icon = header.querySelector('.expand-icon');
            
            if (container.style.display === 'none') {
                container.style.display = 'block';
                group.classList.add('expanded');
            } else {
                container.style.display = 'none';
                group.classList.remove('expanded');
            }
        }
        
        function expandAll() {
            document.querySelectorAll('.loads-container').forEach(container => {
                container.style.display = 'block';
                container.closest('.instructor-group').classList.add('expanded');
            });
        }
        
        function collapseAll() {
            document.querySelectorAll('.loads-container').forEach(container => {
                container.style.display = 'none';
                container.closest('.instructor-group').classList.remove('expanded');
            });
        }
        
        // Toggle group select all
        function toggleGroupSelect(checkbox, instructorName) {
            const group = checkbox.closest('.instructor-group');
            const checkboxes = group.querySelectorAll('.load-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateBulkBar();
        }
        
        // Filter by status
        function filterByStatus() {
            const filter = document.getElementById('statusFilter').value;
            document.querySelectorAll('.loads-table tbody tr').forEach(row => {
                if (filter === 'all') {
                    row.style.display = '';
                } else {
                    row.style.display = row.getAttribute('data-load-status') === filter ? '' : 'none';
                }
            });
            
            // Hide empty groups
            document.querySelectorAll('.instructor-group').forEach(group => {
                const visibleRows = group.querySelectorAll('.loads-table tbody tr:not([style*="display: none"])');
                group.style.display = visibleRows.length > 0 ? '' : 'none';
            });
        }
        
        // Super Admin - Approve
        function approveLoad(loadId) {
            if (confirm('Approve this faculty load?')) {
                document.getElementById('approve_load_id').value = loadId;
                document.getElementById('approveForm').submit();
            }
        }
        
        // Super Admin - Reject
        function rejectLoad(loadId) {
            if (confirm('Reject this faculty load?')) {
                document.getElementById('reject_load_id').value = loadId;
                document.getElementById('rejectForm').submit();
            }
        }
        
        // Admin - Delete draft
        function deleteDraft(loadId) {
            if (confirm('Delete this draft?')) {
                document.getElementById('delete_load_id').value = loadId;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Admin - Submit single draft
        function submitSingleDraft(loadId) {
            if (confirm('Submit this draft for Super Admin approval?')) {
                const container = document.getElementById('submitLoadIds');
                container.innerHTML = '<input type="hidden" name="load_ids[]" value="' + loadId + '">';
                document.getElementById('submitForm').submit();
            }
        }
        
        // Bulk selection
        function updateBulkBar() {
            const selected = document.querySelectorAll('.load-checkbox:checked');
            const count = selected.length;
            document.getElementById('selectedCount').textContent = count;
            
            const bulkBar = document.getElementById('bulkSubmitBar');
            if (count > 0) {
                bulkBar.classList.add('show');
            } else {
                bulkBar.classList.remove('show');
            }
        }
        
        function clearSelection() {
            document.querySelectorAll('.load-checkbox, .group-select-all').forEach(cb => cb.checked = false);
            updateBulkBar();
        }
        
        function submitSelectedDrafts() {
            const selected = [];
            document.querySelectorAll('.load-checkbox:checked').forEach(cb => selected.push(cb.value));
            
            if (selected.length === 0) {
                alert('Please select at least one draft to submit.');
                return;
            }
            
            if (confirm('Submit ' + selected.length + ' draft(s) for Super Admin approval?')) {
                const container = document.getElementById('submitLoadIds');
                container.innerHTML = '';
                selected.forEach(id => {
                    container.innerHTML += '<input type="hidden" name="load_ids[]" value="' + id + '">';
                });
                document.getElementById('submitForm').submit();
            }
        }
        
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 500);
            });
        }, 5000);
    </script>
</body>
</html>