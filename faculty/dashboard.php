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

// Fetch user details
$stmt = $conn->prepare("SELECT full_name, username, role, user_pic FROM users WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$full_name = $user['full_name'] ?? $user['username'] ?? 'Faculty Member';

// Get instructor ID for the logged-in faculty
$stmt = $conn->prepare("SELECT instructor_id, specialization FROM instructors WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$instructor = $stmt->fetch(PDO::FETCH_ASSOC);
$instructor_id = $instructor['instructor_id'] ?? null;
$specialization = $instructor['specialization'] ?? 'General';

// Fetch ONLY the logged-in faculty's loads with details including schedules
$faculty_loads = [];
if ($instructor_id) {
    $stmt = $conn->prepare("
        SELECT 
            fl.load_id,
            fl.status,
            sub.subject_code,
            sub.subject_name,
            sub.units,
            so.school_year,
            so.semester,
            c.course_name,
            curr.year_level,
            GROUP_CONCAT(
                CONCAT(s.day, ' ', TIME_FORMAT(s.start_time, '%h:%i %p'), '-', TIME_FORMAT(s.end_time, '%h:%i %p'), ' (', r.room_name, ')')
                ORDER BY FIELD(s.day, 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'), s.start_time
                SEPARATOR '<br>'
            ) as schedule_details,
            GROUP_CONCAT(
                CONCAT(s.day, '|', TIME_FORMAT(s.start_time, '%h:%i %p'), '|', TIME_FORMAT(s.end_time, '%h:%i %p'), '|', r.room_name)
                ORDER BY FIELD(s.day, 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'), s.start_time
                SEPARATOR '||'
            ) as schedule_data,
            COUNT(DISTINCT s.schedule_id) as schedule_count,
            app.approval_date,
            app.status as approval_status
        FROM faculty_load fl
        JOIN subject_offerings so ON fl.offering_id = so.offering_id
        JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
        JOIN subjects sub ON cs.subject_id = sub.subject_id
        JOIN curriculum curr ON cs.curriculum_id = curr.curriculum_id
        JOIN courses c ON curr.course_id = c.course_id
        LEFT JOIN schedules s ON fl.load_id = s.load_id
        LEFT JOIN rooms r ON s.room_id = r.room_id
        LEFT JOIN approvals app ON fl.load_id = app.load_id
        WHERE fl.instructor_id = :instructor_id
        GROUP BY fl.load_id
        ORDER BY so.school_year DESC, so.semester DESC, fl.load_id DESC
    ");
    $stmt->execute([':instructor_id' => $instructor_id]);
    $faculty_loads = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Statistics for the logged-in faculty only
$total_loads = count($faculty_loads);
$pending_count = count(array_filter($faculty_loads, fn($load) => $load['status'] === 'pending'));
$approved_count = count(array_filter($faculty_loads, fn($load) => $load['status'] === 'approved'));
$rejected_count = count(array_filter($faculty_loads, fn($load) => $load['status'] === 'rejected'));

// Calculate total units
$total_units = array_sum(array_column($faculty_loads, 'units'));

// Group by school year and semester for better organization
$grouped_loads = [];
foreach ($faculty_loads as $load) {
    $key = $load['school_year'] . ' - ' . $load['semester'] . ' Semester';
    if (!isset($grouped_loads[$key])) {
        $grouped_loads[$key] = [];
    }
    $grouped_loads[$key][] = $load;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>My Faculty Load & Schedule | Class Scheduling System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/aq.png">
    
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * { 
            font-family: 'Inter', sans-serif; 
        }
        
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="0.5"/></svg>') repeat;
            pointer-events: none;
            z-index: 0;
        }
        
        .content-page {
            position: relative;
            z-index: 1;
        }
        
        .page-wrapper { 
            padding: 24px 32px; 
        }
        
        .page-header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 28px 32px;
            margin-bottom: 28px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1), 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.3);
            animation: slideInDown 0.6s ease-out;
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            color: #64748b;
            font-size: 15px;
            font-weight: 500;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 28px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1), 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid rgba(255,255,255,0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out backwards;
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15), 0 8px 16px rgba(0,0,0,0.1);
        }
        
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 20px;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: rotate(5deg) scale(1.1);
        }
        
        .stat-value { 
            font-size: 36px; 
            font-weight: 800; 
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2; 
        }
        
        .stat-label { 
            font-size: 14px; 
            color: #64748b; 
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1), 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.3);
            margin-bottom: 28px;
            animation: fadeInUp 0.8s ease-out 0.2s backwards;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .table-title {
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        
        .table th {
            font-weight: 700;
            color: #475569;
            border: none;
            padding: 16px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .table td {
            padding: 18px 16px;
            vertical-align: middle;
            color: #334155;
            background: white;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover td {
            transform: scale(1.01);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.15);
        }
        
        .badge-status {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-pending {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }
        
        .badge-approved {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .badge-rejected {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        /* Schedule View Button Styles */
        .schedule-view-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .schedule-view-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }
        
        .schedule-view-btn i {
            font-size: 14px;
        }
        
        .schedule-preview {
            background: #f8fafc;
            border-radius: 8px;
            padding: 8px 12px;
            margin-top: 8px;
            font-size: 12px;
            border-left: 3px solid #8b5cf6;
        }
        
        .schedule-item {
            display: flex;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        
        .schedule-item:last-child {
            border-bottom: none;
        }
        
        .schedule-day {
            font-weight: 700;
            min-width: 45px;
            color: #4c1d95;
        }
        
        .schedule-time {
            min-width: 100px;
            color: #475569;
        }
        
        .schedule-room {
            color: #8b5cf6;
            font-weight: 600;
        }
        
        /* Modal Styles */
        .schedule-modal .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .schedule-modal .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 20px 24px;
            border: none;
        }
        
        .schedule-modal .modal-title {
            font-weight: 700;
            font-size: 1.25rem;
        }
        
        .schedule-modal .modal-body {
            padding: 24px;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .schedule-modal .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .schedule-table th {
            background: #f1f5f9;
            padding: 12px;
            font-weight: 600;
            color: #1e293b;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .schedule-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }
        
        .schedule-table tr:last-child td {
            border-bottom: none;
        }
        
        .schedule-table tr:hover td {
            background: #f8fafc;
        }
        
        .no-schedule-message {
            text-align: center;
            padding: 30px;
            color: #64748b;
        }
        
        .no-schedule-message i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .faculty-badge {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 700;
            margin-left: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }
        
        .section-header {
            background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
            font-weight: 700;
        }
        
        .section-header td {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            font-weight: 700;
            color: #1e293b;
        }
        
        .filter-section {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #8b5cf6;
            z-index: 1;
        }
        
        .search-box input {
            padding-left: 42px;
            border-radius: 40px;
            border: 2px solid #e2e8f0;
            background: white;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .search-box input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
            outline: none;
        }
        
        .form-select {
            border-radius: 40px;
            border: 2px solid #e2e8f0;
            background: white;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .form-select:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
            outline: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 20px;
            animation: fadeIn 1s ease-out;
        }
        
        .empty-state i {
            font-size: 64px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .approval-info {
            font-size: 12px;
            margin-top: 5px;
            color: #64748b;
        }
        
        .info-badge {
            background: #e0e7ff;
            color: #4c1d95;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            margin-top: 5px;
        }
        
        .schedule-summary {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .schedule-count-badge {
            background: #8b5cf6;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        /* Animations */
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .page-wrapper { padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .table-header { flex-direction: column; align-items: flex-start; }
            .filter-section { width: 100%; flex-direction: column; }
            .search-box { width: 100%; }
            .form-select { width: 100% !important; }
            .page-title { font-size: 24px; }
        }
        
        /* Print styles */
        @media print {
            body::before { display: none; }
            .stat-card, .table-card { box-shadow: none; border: 1px solid #ddd; }
            .filter-section, .btn, .schedule-view-btn { display: none !important; }
            .schedule-details-display { display: block !important; }
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
                                        <i class="fas fa-book-open mr-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                                        My Faculty Load & Schedule
                                        <span class="faculty-badge">
                                            <i class="fas fa-chalkboard-teacher mr-1"></i>Faculty Portal
                                        </span>
                                    </h1>
                                    <p class="page-subtitle mb-0">
                                        <i class="fas fa-user-check mr-2" style="color: #10b981;"></i>
                                        Welcome back, <?php echo htmlspecialchars($full_name); ?>! View your teaching assignments and schedules.
                                    </p>
                                </div>
                                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                    <div class="d-inline-block">
                                        <span class="badge p-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 40px; font-size: 13px;">
                                            <i class="fas fa-calendar-alt mr-2"></i>
                                            <?php echo date('F Y'); ?>
                                            <span class="ml-2 badge badge-light" style="border-radius: 20px;"><?php echo $total_loads; ?> Subjects</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);">
                                    <i class="fas fa-book" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                                </div>
                                <div class="stat-value"><?php echo $total_loads; ?></div>
                                <div class="stat-label">Total Subjects</div>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-layer-group mr-1"></i> <?php echo $total_units; ?> Total Units
                                </small>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #fbbf2420;">
                                    <i class="fas fa-clock" style="color: #f59e0b;"></i>
                                </div>
                                <div class="stat-value"><?php echo $pending_count; ?></div>
                                <div class="stat-label">Pending Approval</div>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-hourglass-half text-warning"></i> Awaiting review
                                </small>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #10b98120;">
                                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                                </div>
                                <div class="stat-value"><?php echo $approved_count; ?></div>
                                <div class="stat-label">Approved Loads</div>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-check text-success"></i> Confirmed assignments
                                </small>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #ef444420;">
                                    <i class="fas fa-graduation-cap" style="color: #8b5cf6;"></i>
                                </div>
                                <div class="stat-value"><?php echo htmlspecialchars($specialization); ?></div>
                                <div class="stat-label">Specialization</div>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-user-tie"></i> Teaching field
                                </small>
                            </div>
                        </div>

                        <!-- Faculty Load Table -->
                        <div class="table-card">
                            <div class="table-header">
                                <h3 class="table-title">
                                    <i class="fas fa-list-check mr-2"></i>
                                    My Teaching Load Details
                                    <span class="badge-count" style="margin-left: 15px;"><?php echo $total_loads; ?> Subjects</span>
                                </h3>
                                <div class="filter-section">
                                    <div class="search-box">
                                        <i class="fas fa-search"></i>
                                        <input type="text" id="searchInput" class="form-control" placeholder="Search subjects..." style="min-width: 280px;">
                                    </div>
                                    <select id="statusFilter" class="form-select" style="width: 180px;">
                                        <option value="all">📋 All Status</option>
                                        <option value="pending">⏳ Pending</option>
                                        <option value="approved">✅ Approved</option>
                                        <option value="rejected">❌ Rejected</option>
                                    </select>
                                    <button class="btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 30px; padding: 8px 20px;" onclick="printSchedule()">
                                        <i class="fas fa-print mr-2"></i>Print Schedule
                                    </button>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table" id="loadsTable">
                                    <thead>
                                        <tr>
                                            <th>Subject Code</th>
                                            <th>Subject Name</th>
                                            <th>Course/Year</th>
                                            <th>Units</th>
                                            <th>Schedule</th>
                                            <th>Status</th>
                                            <th>Approval Info</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($grouped_loads)): ?>
                                            <tr>
                                                <td colspan="7">
                                                    <div class="empty-state">
                                                        <i class="fas fa-inbox"></i>
                                                        <h4 class="mb-3">No Teaching Load Assigned</h4>
                                                        <p class="text-muted">You currently have no subjects assigned for teaching.</p>
                                                        <p class="text-muted mt-2">Please contact your administrator if you believe this is an error.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($grouped_loads as $semester_key => $loads): ?>
                                                <!-- Section Header for Semester Group -->
                                                <tr class="section-header">
                                                    <td colspan="7" style="padding: 15px;">
                                                        <i class="fas fa-calendar-alt mr-2" style="color: #8b5cf6;"></i>
                                                        <strong style="font-size: 16px;"><?php echo htmlspecialchars($semester_key); ?></strong>
                                                        <span class="badge ml-3" style="background: #8b5cf6; color: white;"><?php echo count($loads); ?> Subjects</span>
                                                    </td>
                                                </tr>
                                                
                                                <?php foreach ($loads as $load): 
                                                    // Parse schedule data for modal
                                                    $schedule_items = [];
                                                    if (!empty($load['schedule_data'])) {
                                                        $schedule_parts = explode('||', $load['schedule_data']);
                                                        foreach ($schedule_parts as $part) {
                                                            $details = explode('|', $part);
                                                            if (count($details) >= 4) {
                                                                $schedule_items[] = [
                                                                    'day' => $details[0],
                                                                    'start' => $details[1],
                                                                    'end' => $details[2],
                                                                    'room' => $details[3]
                                                                ];
                                                            }
                                                        }
                                                    }
                                                    $schedule_count = count($schedule_items);
                                                ?>
                                                <tr data-status="<?php echo $load['status']; ?>" data-subject="<?php echo strtolower(htmlspecialchars($load['subject_name'] . ' ' . $load['subject_code'])); ?>">
                                                    <td>
                                                        <strong style="font-size: 15px; color: #1e293b;">
                                                            <?php echo htmlspecialchars($load['subject_code']); ?>
                                                        </strong>
                                                    </td>
                                                    <td>
                                                        <div><?php echo htmlspecialchars($load['subject_name']); ?></div>
                                                        <small class="text-muted">
                                                            <i class="fas fa-school mr-1"></i>
                                                            <?php echo htmlspecialchars($load['school_year']); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($load['course_name']); ?>
                                                        <span class="badge ml-2" style="background: #e0e7ff; color: #4c1d95;">
                                                            Year <?php echo $load['year_level']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 6px 12px;">
                                                            <i class="fas fa-cube mr-1"></i>
                                                            <?php echo $load['units']; ?> Units
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="schedule-summary">
                                                            <?php if ($schedule_count > 0): ?>
                                                                <button class="schedule-view-btn" onclick="showScheduleModal(<?php echo $load['load_id']; ?>)">
                                                                    <i class="fas fa-calendar-alt"></i>
                                                                    View Schedule
                                                                </button>
                                                                <span class="schedule-count-badge">
                                                                    <?php echo $schedule_count; ?> session(s)
                                                                </span>
                                                                <!-- Hidden schedule data for modal -->
                                                                <div id="schedule-data-<?php echo $load['load_id']; ?>" style="display: none;">
                                                                    <div class="schedule-subject-info">
                                                                        <h6><?php echo htmlspecialchars($load['subject_code'] . ' - ' . $load['subject_name']); ?></h6>
                                                                        <p class="text-muted mb-3"><?php echo htmlspecialchars($load['course_name'] . ' Year ' . $load['year_level']); ?></p>
                                                                    </div>
                                                                    <?php if ($schedule_count > 0): ?>
                                                                        <table class="schedule-table">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th>Day</th>
                                                                                    <th>Time</th>
                                                                                    <th>Room</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php foreach ($schedule_items as $item): ?>
                                                                                <tr>
                                                                                    <td><strong><?php echo htmlspecialchars($item['day']); ?></strong></td>
                                                                                    <td><?php echo htmlspecialchars($item['start'] . ' - ' . $item['end']); ?></td>
                                                                                    <td><i class="fas fa-door-open mr-2" style="color: #8b5cf6;"></i><?php echo htmlspecialchars($item['room']); ?></td>
                                                                                </tr>
                                                                                <?php endforeach; ?>
                                                                            </tbody>
                                                                        </table>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-warning" style="font-size: 13px;">
                                                                    <i class="fas fa-exclamation-triangle"></i> No schedule assigned
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge-status badge-<?php echo $load['status']; ?>">
                                                            <?php if($load['status'] === 'pending'): ?>
                                                                <i class="fas fa-clock mr-1"></i>
                                                            <?php elseif($load['status'] === 'approved'): ?>
                                                                <i class="fas fa-check-circle mr-1"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-times-circle mr-1"></i>
                                                            <?php endif; ?>
                                                            <?php echo ucfirst($load['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($load['approval_date']): ?>
                                                            <div class="info-badge">
                                                                <i class="fas fa-calendar-check mr-1"></i>
                                                                <?php echo date('M d, Y', strtotime($load['approval_date'])); ?>
                                                            </div>
                                                            <div class="approval-info">
                                                                <?php if($load['approval_status'] === 'approved'): ?>
                                                                    <i class="fas fa-check-circle text-success"></i> Approved
                                                                <?php elseif($load['approval_status'] === 'rejected'): ?>
                                                                    <i class="fas fa-times-circle text-danger"></i> Rejected
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">
                                                                <i class="fas fa-hourglass-half"></i> Pending review
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
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

    <!-- Schedule Modal -->
    <div class="modal fade schedule-modal" id="scheduleModal" tabindex="-1" role="dialog" aria-labelledby="scheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleModalLabel">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        Class Schedule Details
                    </h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="scheduleModalBody">
                    <!-- Schedule content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;" onclick="printModalSchedule()">
                        <i class="fas fa-print mr-2"></i>Print Schedule
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>
    
    <script>
        // Search and Filter functionality
        document.getElementById('searchInput').addEventListener('keyup', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);
        
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#loadsTable tbody tr:not(.section-header)');
            const sectionHeaders = document.querySelectorAll('.section-header');
            
            // First, hide all rows
            rows.forEach(row => {
                row.style.display = 'none';
            });
            
            // Show rows that match filters
            rows.forEach(row => {
                const subjectData = row.getAttribute('data-subject') || '';
                const status = row.getAttribute('data-status');
                const matchesSearch = subjectData.includes(searchTerm);
                const matchesStatus = statusFilter === 'all' || status === statusFilter;
                
                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                }
            });
            
            // Hide section headers that have no visible rows
            sectionHeaders.forEach(header => {
                let hasVisibleRows = false;
                let nextRow = header.nextElementSibling;
                
                while (nextRow && !nextRow.classList.contains('section-header')) {
                    if (nextRow.style.display !== 'none') {
                        hasVisibleRows = true;
                        break;
                    }
                    nextRow = nextRow.nextElementSibling;
                }
                
                header.style.display = hasVisibleRows ? '' : 'none';
            });
        }
        
        // Show schedule modal
        function showScheduleModal(loadId) {
            const scheduleData = document.getElementById('schedule-data-' + loadId);
            if (scheduleData) {
                document.getElementById('scheduleModalBody').innerHTML = scheduleData.innerHTML;
                $('#scheduleModal').modal('show');
            }
        }
        
        // Print modal schedule
        function printModalSchedule() {
            const modalContent = document.getElementById('scheduleModalBody').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Class Schedule</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        body { padding: 20px; font-family: 'Inter', sans-serif; }
                        .schedule-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        .schedule-table th { background: #f1f5f9; padding: 12px; text-align: left; }
                        .schedule-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
                        .print-header { text-align: center; margin-bottom: 20px; }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h3>Class Schedule</h3>
                        <p>Printed on: ${new Date().toLocaleString()}</p>
                    </div>
                    ${modalContent}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        // Print schedule functionality
        function printSchedule() {
            window.print();
        }
        
        // Set default filter to show all on page load
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('statusFilter').value = 'all';
            
            // Add entrance animations to rows
            const rows = document.querySelectorAll('#loadsTable tbody tr');
            rows.forEach((row, index) => {
                if (!row.classList.contains('section-header')) {
                    row.style.animation = `fadeInUp 0.5s ease-out ${index * 0.03}s backwards`;
                }
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
            
            // Escape to clear search
            if (e.key === 'Escape') {
                document.getElementById('searchInput').value = '';
                filterTable();
            }
        });
        
        // Real-time search with debounce
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                filterTable();
            }, 300);
        });
        
        // Initialize tooltips
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });
        
        // Add refresh functionality (optional)
        function refreshData() {
            location.reload();
        }
    </script>
</body>
</html>