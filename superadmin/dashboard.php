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

// Handle status updates only (Approve/Reject)
$message = '';
$message_type = '';

// UPDATE - Update Faculty Load Status (Super Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if ($_SESSION['role'] === 'super_admin') {
        $load_id = filter_input(INPUT_POST, 'load_id', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
        
        try {
            $stmt = $conn->prepare("UPDATE faculty_load SET status = :status WHERE load_id = :load_id");
            $stmt->execute([':status' => $status, ':load_id' => $load_id]);
            
            // If approved, create approval record
            if ($status === 'approved') {
                $stmt = $conn->prepare("INSERT INTO approvals (load_id, approved_by, approval_date, status) VALUES (:load_id, :approved_by, NOW(), 'approved')");
                $stmt->execute([':load_id' => $load_id, ':approved_by' => $user_id]);
            } elseif ($status === 'rejected') {
                $stmt = $conn->prepare("INSERT INTO approvals (load_id, approved_by, approval_date, status) VALUES (:load_id, :approved_by, NOW(), 'rejected')");
                $stmt->execute([':load_id' => $load_id, ':approved_by' => $user_id]);
            }
            
            $message = "Faculty load status updated to " . ucfirst($status) . "!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error updating status: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Fetch user details
$stmt = $conn->prepare("SELECT full_name, username, role, user_pic FROM users WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$full_name = $user['full_name'] ?? $user['username'] ?? 'Administrator';

// Fetch all faculty loads with details - Get schedules as separate data
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
        COUNT(DISTINCT s.schedule_id) as schedule_count
    FROM faculty_load fl
    JOIN instructors i ON fl.instructor_id = i.instructor_id
    JOIN users u ON i.user_id = u.user_id
    JOIN subject_offerings so ON fl.offering_id = so.offering_id
    JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
    JOIN subjects sub ON cs.subject_id = sub.subject_id
    JOIN curriculum curr ON cs.curriculum_id = curr.curriculum_id
    JOIN courses c ON curr.course_id = c.course_id
    LEFT JOIN schedules s ON fl.load_id = s.load_id
    GROUP BY fl.load_id
    ORDER BY u.full_name, fl.load_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch schedules separately for each load
foreach ($faculty_loads as &$load) {
    $stmt = $conn->prepare("
        SELECT 
            s.schedule_id,
            s.day,
            s.start_time,
            s.end_time,
            r.room_name,
            r.room_type
        FROM schedules s
        LEFT JOIN rooms r ON s.room_id = r.room_id
        WHERE s.load_id = :load_id
        ORDER BY FIELD(s.day, 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'), s.start_time
    ");
    $stmt->execute([':load_id' => $load['load_id']]);
    $load['schedules'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($load);

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
            'total_units' => 0,
            'pending_count' => 0,
            'approved_count' => 0,
            'rejected_count' => 0
        ];
    }
    $grouped_loads[$instructor_key]['loads'][] = $load;
    $grouped_loads[$instructor_key]['total_loads']++;
    $grouped_loads[$instructor_key]['total_units'] += $load['units'];
    
    // Count statuses
    if ($load['status'] === 'pending') $grouped_loads[$instructor_key]['pending_count']++;
    elseif ($load['status'] === 'approved') $grouped_loads[$instructor_key]['approved_count']++;
    elseif ($load['status'] === 'rejected') $grouped_loads[$instructor_key]['rejected_count']++;
}

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
    <title>Review & Approve Faculty Load | Class Scheduling System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/aq.png">
    
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    
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
            cursor: pointer;
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
            position: sticky;
            top: 0;
            z-index: 10;
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
        
        .btn-action {
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            margin: 3px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-action::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-action:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-approve {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.5);
            color: white;
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        
        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.5);
            color: white;
        }
        
        /* Schedule Button */
        .btn-view-schedule {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .btn-view-schedule:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.5);
        }
        
        /* Group Row Styling */
        .group-row {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid #8b5cf6;
            position: relative;
        }
        
        .group-row:hover {
            background: linear-gradient(135deg, #e0e7ff 0%, #ddd6fe 100%);
            transform: scale(1.01);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.2);
        }
        
        .group-row td:first-child {
            border-radius: 12px 0 0 12px;
        }
        
        .group-row td:last-child {
            border-radius: 0 12px 12px 0;
        }
        
        .child-row {
            background-color: #ffffff;
            animation: slideDown 0.3s ease-out;
        }
        
        .child-row.hidden {
            display: none;
        }
        
        .child-row td {
            padding-left: 50px;
            border-left: 2px solid #e2e8f0;
            position: relative;
        }
        
        .child-row td:first-child::before {
            content: '└─';
            position: absolute;
            left: 20px;
            color: #8b5cf6;
            font-weight: bold;
        }
        
        .rotate-icon {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            color: #8b5cf6;
        }
        
        .rotate-icon.expanded {
            transform: rotate(90deg);
        }
        
        .badge-count {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border-radius: 30px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            margin-left: 10px;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
        }
        
        .status-badge-group {
            display: inline-flex;
            gap: 8px;
        }
        
        .status-badge-sm {
            padding: 3px 8px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-badge-sm.pending {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
        }
        
        .status-badge-sm.approved {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .status-badge-sm.rejected {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
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
        
        .superadmin-badge {
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
        
        /* Schedule Modal Styles */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 20px 24px;
            border: none;
        }
        
        .modal-header .close {
            color: white;
            opacity: 1;
            text-shadow: none;
        }
        
        .modal-title {
            font-weight: 700;
            font-size: 18px;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .schedule-grid-modal {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }
        
        .day-card-modal {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            padding: 16px;
            border: 1px solid #e2e8f0;
        }
        
        .day-title-modal {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #8b5cf6;
            font-size: 16px;
        }
        
        .schedule-item-modal {
            background: white;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
            border-left: 3px solid #667eea;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .schedule-time-modal {
            font-weight: 700;
            color: #667eea;
            font-size: 13px;
            margin-bottom: 4px;
        }
        
        .schedule-room-modal {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .room-badge {
            background: #e0e7ff;
            color: #4c1d95;
            padding: 2px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .schedule-indicator {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .schedule-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e0e7ff;
            color: #4c1d95;
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .schedule-badge:hover {
            background: #c7d2fe;
            transform: scale(1.05);
        }
        
        .instructor-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            margin-right: 12px;
        }
        
        .alert {
            border-radius: 16px;
            border: none;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            animation: slideInDown 0.5s ease-out;
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
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
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
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .page-wrapper { padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .table-header { flex-direction: column; align-items: flex-start; }
            .filter-section { width: 100%; flex-direction: column; }
            .search-box { width: 100%; }
            .form-select { width: 100% !important; }
            .child-row td { padding-left: 30px; }
            .page-title { font-size: 24px; }
            .schedule-grid-modal { grid-template-columns: 1fr; }
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
                                        <i class="fas fa-clipboard-check mr-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                                        Review & Approve Faculty Loading
                                        <?php if($user_role === 'super_admin'): ?>
                                            <span class="superadmin-badge pulse-animation">
                                                <i class="fas fa-crown mr-1"></i>Super Admin
                                            </span>
                                        <?php endif; ?>
                                    </h1>
                                    <p class="page-subtitle mb-0">
                                        <i class="fas fa-check-circle mr-2" style="color: #10b981;"></i>
                                        Review and approve faculty teaching loads and schedules with ease
                                    </p>
                                </div>
                                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                    <div class="d-inline-block">
                                        <span class="badge p-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 40px; font-size: 13px;">
                                            <i class="fas fa-eye mr-2"></i>Review Mode
                                            <span class="ml-2 badge badge-light" style="border-radius: 20px;"><?php echo $pending_count; ?> Pending</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alert Messages -->
                        <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-circle' : 'info-circle'); ?> fa-2x mr-3"></i>
                                <div>
                                    <strong><?php echo ucfirst($message_type); ?>!</strong><br>
                                    <?php echo $message; ?>
                                </div>
                            </div>
                            <button type="button" class="close text-white" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php endif; ?>

                        <!-- Statistics Cards -->
                        <div class="stats-grid">
                            <div class="stat-card" onclick="filterByStatus('all')">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);">
                                    <i class="fas fa-book-open" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                                </div>
                                <div class="stat-value"><?php echo $total_loads; ?></div>
                                <div class="stat-label">Total Loads</div>
                            </div>
                            
                            <div class="stat-card" onclick="filterByStatus('pending')">
                                <div class="stat-icon" style="background: #fbbf2420;">
                                    <i class="fas fa-clock" style="color: #f59e0b;"></i>
                                </div>
                                <div class="stat-value"><?php echo $pending_count; ?></div>
                                <div class="stat-label">Pending Review</div>
                            </div>
                            
                            <div class="stat-card" onclick="filterByStatus('approved')">
                                <div class="stat-icon" style="background: #10b98120;">
                                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                                </div>
                                <div class="stat-value"><?php echo $approved_count; ?></div>
                                <div class="stat-label">Approved</div>
                            </div>
                            
                            <div class="stat-card" onclick="filterByStatus('rejected')">
                                <div class="stat-icon" style="background: #ef444420;">
                                    <i class="fas fa-times-circle" style="color: #ef4444;"></i>
                                </div>
                                <div class="stat-value"><?php echo $rejected_count; ?></div>
                                <div class="stat-label">Rejected</div>
                            </div>
                        </div>

                        <!-- Faculty Load Table - Grouped by Instructor -->
                        <div class="table-card">
                            <div class="table-header">
                                <h3 class="table-title">
                                    <i class="fas fa-list-check mr-2"></i>
                                    Faculty Loads for Review
                                    <span class="badge-count" style="margin-left: 15px;"><?php echo $total_loads; ?> Total</span>
                                </h3>
                                <div class="filter-section">
                                    <div class="search-box">
                                        <i class="fas fa-search"></i>
                                        <input type="text" id="searchInput" class="form-control" placeholder="Search instructors..." style="min-width: 280px;">
                                    </div>
                                    <select id="statusFilter" class="form-select" style="width: 180px;">
                                        <option value="all">📋 All Status</option>
                                        <option value="pending" selected>⏳ Pending Review</option>
                                        <option value="approved">✅ Approved</option>
                                        <option value="rejected">❌ Rejected</option>
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
                                            <th>Schedules</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($grouped_loads)): ?>
                                            <tr>
                                                <td colspan="9">
                                                    <div class="empty-state">
                                                        <i class="fas fa-inbox"></i>
                                                        <h4 class="mb-3">No Faculty Loads Found</h4>
                                                        <p class="text-muted">There are no faculty loads available for review at this time.</p>
                                                    </div>
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
                                                
                                                // Get instructor initials for avatar
                                                $name_parts = explode(' ', $group['instructor_name']);
                                                $initials = '';
                                                foreach ($name_parts as $part) {
                                                    $initials .= strtoupper(substr($part, 0, 1));
                                                }
                                                ?>
                                                <!-- Group Row (Instructor Summary) -->
                                                <tr class="group-row" onclick="toggleGroup('<?php echo $group_id; ?>', '<?php echo $expand_icon_id; ?>')" data-instructor="<?php echo strtolower(htmlspecialchars($group['instructor_name'])); ?>">
                                                    <td class="text-center">
                                                        <i id="<?php echo $expand_icon_id; ?>" class="fas fa-chevron-right rotate-icon"></i>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="instructor-avatar">
                                                                <?php echo $initials; ?>
                                                            </div>
                                                            <div>
                                                                <strong style="font-size: 15px;">
                                                                    <?php echo htmlspecialchars($group['instructor_name']); ?>
                                                                </strong>
                                                                <span class="badge-count"><?php echo $group['total_loads']; ?> loads</span>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-graduation-cap mr-1"></i>
                                                                    <?php echo htmlspecialchars($group['specialization'] ?: 'General'); ?>
                                                                    <span class="mx-2">•</span>
                                                                    <i class="fas fa-layer-group mr-1"></i>
                                                                    <?php echo $group['total_units']; ?> units
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td colspan="8">
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
                                                        <div><strong><?php echo htmlspecialchars($load['subject_code']); ?></strong></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($load['subject_name']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($load['course_name']); ?>
                                                        <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-left: 8px;">Year <?php echo $load['year_level']; ?></span>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($load['school_year']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar-alt mr-1"></i>
                                                            <?php echo $load['semester']; ?> Semester
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <span class="badge" style="background: #e0e7ff; color: #4c1d95; padding: 6px 12px;">
                                                            <i class="fas fa-cube mr-1"></i>
                                                            <?php echo $load['units']; ?> units
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($load['schedule_count'] > 0 && !empty($load['schedules'])): ?>
                                                            <button class="schedule-badge" onclick="event.stopPropagation(); showScheduleModal(<?php echo htmlspecialchars(json_encode($load['schedules'])); ?>, '<?php echo htmlspecialchars($load['subject_code'] . ' - ' . $load['subject_name']); ?>')">
                                                                <i class="fas fa-calendar-alt"></i>
                                                                <span><?php echo $load['schedule_count']; ?> Schedule<?php echo $load['schedule_count'] > 1 ? 's' : ''; ?></span>
                                                                <i class="fas fa-external-link-alt" style="font-size: 9px;"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted" style="font-size: 12px;">
                                                                <i class="fas fa-exclamation-triangle text-warning"></i> No schedules
                                                            </span>
                                                        <?php endif; ?>
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
                                                        <?php if ($user_role === 'super_admin'): ?>
                                                            <?php if ($load['status'] === 'pending'): ?>
                                                            <button class="btn-action btn-approve" onclick="event.stopPropagation(); updateStatus(<?php echo $load['load_id']; ?>, 'approved', this)" title="Approve this load">
                                                                <i class="fas fa-check"></i> Approve
                                                            </button>
                                                            <button class="btn-action btn-reject" onclick="event.stopPropagation(); updateStatus(<?php echo $load['load_id']; ?>, 'rejected', this)" title="Reject this load">
                                                                <i class="fas fa-times"></i> Reject
                                                            </button>
                                                            <?php else: ?>
                                                            <span class="text-muted">
                                                                <i class="fas fa-<?php echo $load['status'] === 'approved' ? 'check-circle text-success' : 'times-circle text-danger'; ?> fa-lg"></i>
                                                                <small class="d-block mt-1"><?php echo ucfirst($load['status']); ?></small>
                                                            </span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="badge" style="background: #e2e8f0; color: #64748b;">
                                                                <i class="fas fa-eye mr-1"></i>View Only
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

                        <!-- Status Update Form (Hidden) - Only for Super Admin -->
                        <?php if ($user_role === 'super_admin'): ?>
                        <form method="POST" id="statusForm" style="display: none;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="load_id" id="status_load_id">
                            <input type="hidden" name="status" id="status_value">
                        </form>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" role="dialog" aria-labelledby="scheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleModalLabel">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        <span id="modalSubjectTitle">Schedule Details</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="schedule-grid-modal" id="scheduleGridModal">
                        <!-- Schedule will be populated here dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 30px; padding: 8px 24px;">
                        <i class="fas fa-times mr-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Day order mapping
        const dayOrder = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const dayNames = {
            'Mon': 'Monday',
            'Tue': 'Tuesday',
            'Wed': 'Wednesday',
            'Thu': 'Thursday',
            'Fri': 'Friday',
            'Sat': 'Saturday'
        };
        
        // Show schedule modal
        function showScheduleModal(schedules, subjectTitle) {
            const modal = $('#scheduleModal');
            const gridContainer = $('#scheduleGridModal');
            const titleSpan = $('#modalSubjectTitle');
            
            // Set title
            titleSpan.text(subjectTitle);
            
            // Group schedules by day
            const schedulesByDay = {};
            schedules.forEach(schedule => {
                if (!schedulesByDay[schedule.day]) {
                    schedulesByDay[schedule.day] = [];
                }
                schedulesByDay[schedule.day].push(schedule);
            });
            
            // Build HTML
            let html = '';
            dayOrder.forEach(day => {
                if (schedulesByDay[day] && schedulesByDay[day].length > 0) {
                    html += `
                        <div class="day-card-modal">
                            <div class="day-title-modal">
                                <i class="fas fa-calendar-day mr-2"></i>${dayNames[day]}
                            </div>
                    `;
                    
                    schedulesByDay[day].forEach(schedule => {
                        const startTime = formatTime(schedule.start_time);
                        const endTime = formatTime(schedule.end_time);
                        const roomType = schedule.room_type === 'lecture' ? 'Lecture Room' : 
                                       (schedule.room_type === 'laboratory' ? 'Laboratory' : 'Room');
                        
                        html += `
                            <div class="schedule-item-modal">
                                <div class="schedule-time-modal">
                                    <i class="far fa-clock mr-1"></i>
                                    ${startTime} - ${endTime}
                                </div>
                                <div class="schedule-room-modal">
                                    <i class="fas fa-door-open"></i>
                                    <span>${schedule.room_name || 'TBA'}</span>
                                    <span class="room-badge">${roomType}</span>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                }
            });
            
            // Check if no schedules
            if (html === '') {
                html = `
                    <div class="text-center p-5" style="grid-column: 1/-1;">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No schedules assigned for this subject.</p>
                    </div>
                `;
            }
            
            gridContainer.html(html);
            modal.modal('show');
        }
        
        // Format time helper
        function formatTime(timeString) {
            if (!timeString) return 'TBA';
            const date = new Date('1970-01-01T' + timeString);
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }
        
        // Toggle group expand/collapse function with animation
        function toggleGroup(groupId, iconId) {
            const groupRows = document.querySelectorAll('#' + groupId);
            const icon = document.getElementById(iconId);
            
            groupRows.forEach(row => {
                row.classList.toggle('hidden');
            });
            
            if (icon) {
                icon.classList.toggle('expanded');
            }
        }
        
        // Filter by status when clicking stat cards
        function filterByStatus(status) {
            document.getElementById('statusFilter').value = status;
            filterTable();
            
            // Add visual feedback
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => card.style.transform = 'scale(1)');
        }
        
        // Search and Filter functionality
        document.getElementById('searchInput').addEventListener('keyup', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);
        
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const groupRows = document.querySelectorAll('.group-row');
            
            let visibleCount = 0;
            
            groupRows.forEach(groupRow => {
                const instructorName = groupRow.getAttribute('data-instructor') || '';
                const matchesSearch = instructorName.includes(searchTerm);
                
                if (statusFilter === 'all') {
                    groupRow.style.display = matchesSearch ? '' : 'none';
                    if (matchesSearch) visibleCount++;
                } else {
                    // For status filter, check child rows
                    const childRows = [];
                    let currentRow = groupRow.nextElementSibling;
                    while (currentRow && currentRow.classList.contains('child-row')) {
                        childRows.push(currentRow);
                        currentRow = currentRow.nextElementSibling;
                    }
                    
                    let hasMatchingStatus = false;
                    childRows.forEach(row => {
                        const status = row.getAttribute('data-status');
                        if (status === statusFilter) {
                            hasMatchingStatus = true;
                        }
                    });
                    
                    groupRow.style.display = (matchesSearch && hasMatchingStatus) ? '' : 'none';
                    if (matchesSearch && hasMatchingStatus) visibleCount++;
                }
            });
            
            updateResultsCount(visibleCount);
        }
        
        function updateResultsCount(count) {
            console.log(`Showing ${count} instructors`);
        }
        
        // Enhanced Status Update Function with loading animation
        <?php if ($user_role === 'super_admin'): ?>
        function updateStatus(loadId, status, buttonElement) {
            const action = status === 'approved' ? 'approve' : 'reject';
            const actionColor = status === 'approved' ? '#10b981' : '#ef4444';
            
            Swal.fire({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} Faculty Load?`,
                text: `Are you sure you want to ${action} this faculty load?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: actionColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Yes, ${action} it!`,
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    // Add loading state to button
                    if (buttonElement) {
                        const originalText = buttonElement.innerHTML;
                        buttonElement.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
                        buttonElement.disabled = true;
                    }
                    
                    document.getElementById('status_load_id').value = loadId;
                    document.getElementById('status_value').value = status;
                    document.getElementById('statusForm').submit();
                    
                    return new Promise((resolve) => {
                        setTimeout(resolve, 500);
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            });
        }
        <?php endif; ?>
        
        // Initialize tooltips
        $(function () {
            $('[title]').tooltip();
        });
        
        // Auto-hide alerts with fade animation
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
        // Set default filter to pending on page load
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('statusFilter').value = 'pending';
            filterTable();
            
            // Add entrance animations
            const rows = document.querySelectorAll('.group-row');
            rows.forEach((row, index) => {
                row.style.animation = `fadeInUp 0.5s ease-out ${index * 0.05}s backwards`;
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
    </script>
</body>
</html>