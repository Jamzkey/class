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

// CREATE - Add New Room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $room_name = filter_input(INPUT_POST, 'room_name', FILTER_SANITIZE_STRING);
        $room_type = filter_input(INPUT_POST, 'room_type', FILTER_SANITIZE_STRING);
        $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
        
        try {
            // Check if room name already exists
            $check = $conn->prepare("SELECT COUNT(*) FROM rooms WHERE room_name = :room_name");
            $check->execute([':room_name' => $room_name]);
            if ($check->fetchColumn() > 0) {
                $message = "Error: Room name already exists!";
                $message_type = "danger";
            } else {
                $stmt = $conn->prepare("INSERT INTO rooms (room_name, room_type, capacity) VALUES (:room_name, :room_type, :capacity)");
                $stmt->execute([':room_name' => $room_name, ':room_type' => $room_type, ':capacity' => $capacity]);
                $message = "Room added successfully!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error adding room: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // UPDATE - Edit Room Details
    elseif ($_POST['action'] === 'edit') {
        $room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
        $room_name = filter_input(INPUT_POST, 'room_name', FILTER_SANITIZE_STRING);
        $room_type = filter_input(INPUT_POST, 'room_type', FILTER_SANITIZE_STRING);
        $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
        
        try {
            // Check if room name exists for other rooms
            $check = $conn->prepare("SELECT COUNT(*) FROM rooms WHERE room_name = :room_name AND room_id != :room_id");
            $check->execute([':room_name' => $room_name, ':room_id' => $room_id]);
            if ($check->fetchColumn() > 0) {
                $message = "Error: Room name already exists!";
                $message_type = "danger";
            } else {
                $stmt = $conn->prepare("UPDATE rooms SET room_name = :room_name, room_type = :room_type, capacity = :capacity WHERE room_id = :room_id");
                $stmt->execute([':room_name' => $room_name, ':room_type' => $room_type, ':capacity' => $capacity, ':room_id' => $room_id]);
                $message = "Room updated successfully!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error updating room: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // DELETE - Remove Room
    elseif ($_POST['action'] === 'delete') {
        $room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
        
        try {
            // Check if room has existing schedules
            $check = $conn->prepare("SELECT COUNT(*) FROM schedules WHERE room_id = :room_id");
            $check->execute([':room_id' => $room_id]);
            if ($check->fetchColumn() > 0) {
                $message = "Cannot delete: This room has existing schedules. Remove schedules first.";
                $message_type = "warning";
            } else {
                $stmt = $conn->prepare("DELETE FROM rooms WHERE room_id = :room_id");
                $stmt->execute([':room_id' => $room_id]);
                $message = "Room deleted successfully!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error deleting room: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // ASSIGN - Assign Room to Schedule - Check for EXACT same time only
    elseif ($_POST['action'] === 'assign') {
        $room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
        $load_id = filter_input(INPUT_POST, 'load_id', FILTER_VALIDATE_INT);
        $day = filter_input(INPUT_POST, 'day', FILTER_SANITIZE_STRING);
        $start_time = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
        $end_time = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);
        
        try {
            // Validate time range
            if ($start_time >= $end_time) {
                $message = "Error: Start time must be before end time!";
                $message_type = "danger";
            } else {
                // Check for EXACT same start_time and end_time only (not overlapping)
                $conflict = $conn->prepare("
                    SELECT COUNT(*) FROM schedules 
                    WHERE room_id = :room_id 
                    AND day = :day 
                    AND start_time = :start_time 
                    AND end_time = :end_time
                ");
                $conflict->execute([
                    ':room_id' => $room_id,
                    ':day' => $day,
                    ':start_time' => $start_time,
                    ':end_time' => $end_time
                ]);
                
                if ($conflict->fetchColumn() > 0) {
                    $message = "❌ Schedule Conflict: Room already has an EXACT schedule on {$day} from {$start_time} to {$end_time}!";
                    $message_type = "danger";
                } else {
                    $stmt = $conn->prepare("INSERT INTO schedules (room_id, load_id, day, start_time, end_time) VALUES (:room_id, :load_id, :day, :start_time, :end_time)");
                    $stmt->execute([
                        ':room_id' => $room_id,
                        ':load_id' => $load_id,
                        ':day' => $day,
                        ':start_time' => $start_time,
                        ':end_time' => $end_time
                    ]);
                    $message = "✅ Room assigned to schedule successfully!";
                    $message_type = "success";
                }
            }
        } catch (PDOException $e) {
            $message = "Error assigning room: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // UPDATE SCHEDULE - Check for EXACT same time only (excluding current)
    elseif ($_POST['action'] === 'update_schedule') {
        $schedule_id = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
        $room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
        $day = filter_input(INPUT_POST, 'day', FILTER_SANITIZE_STRING);
        $start_time = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
        $end_time = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);
        
        try {
            // Validate time range
            if ($start_time >= $end_time) {
                $message = "Error: Start time must be before end time!";
                $message_type = "danger";
            } else {
                // Check for EXACT same time on same room and day (excluding current schedule)
                $conflict = $conn->prepare("
                    SELECT COUNT(*) FROM schedules 
                    WHERE room_id = :room_id 
                    AND schedule_id != :schedule_id
                    AND day = :day 
                    AND start_time = :start_time 
                    AND end_time = :end_time
                ");
                $conflict->execute([
                    ':room_id' => $room_id,
                    ':schedule_id' => $schedule_id,
                    ':day' => $day,
                    ':start_time' => $start_time,
                    ':end_time' => $end_time
                ]);
                
                if ($conflict->fetchColumn() > 0) {
                    $message = "❌ Schedule Conflict: This room already has an EXACT schedule on {$day} from {$start_time} to {$end_time}!";
                    $message_type = "danger";
                } else {
                    $stmt = $conn->prepare("UPDATE schedules SET room_id = :room_id, day = :day, start_time = :start_time, end_time = :end_time WHERE schedule_id = :schedule_id");
                    $stmt->execute([
                        ':room_id' => $room_id,
                        ':day' => $day,
                        ':start_time' => $start_time,
                        ':end_time' => $end_time,
                        ':schedule_id' => $schedule_id
                    ]);
                    $message = "✅ Schedule updated successfully!";
                    $message_type = "success";
                }
            }
        } catch (PDOException $e) {
            $message = "Error updating schedule: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // REMOVE SCHEDULE - Remove Room Assignment
    elseif ($_POST['action'] === 'remove_schedule') {
        $schedule_id = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
        
        try {
            $stmt = $conn->prepare("DELETE FROM schedules WHERE schedule_id = :schedule_id");
            $stmt->execute([':schedule_id' => $schedule_id]);
            $message = "✅ Schedule removed successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error removing schedule: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Fetch user details
$stmt = $conn->prepare("SELECT full_name, username, role, user_pic FROM users WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$full_name = $user['full_name'] ?? $user['username'] ?? 'Administrator';

// Fetch all rooms with schedule counts
$rooms = $conn->query("
    SELECT 
        r.*,
        COUNT(DISTINCT s.schedule_id) as schedule_count
    FROM rooms r
    LEFT JOIN schedules s ON r.room_id = s.room_id
    GROUP BY r.room_id
    ORDER BY r.room_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch room schedules grouped by instructor
$room_schedules = $conn->query("
    SELECT 
        s.schedule_id,
        s.day,
        s.start_time,
        s.end_time,
        r.room_id,
        r.room_name,
        r.room_type,
        sub.subject_code,
        sub.subject_name,
        u.full_name as instructor_name,
        u.user_id as instructor_user_id
    FROM schedules s
    JOIN rooms r ON s.room_id = r.room_id
    JOIN faculty_load fl ON s.load_id = fl.load_id
    JOIN instructors i ON fl.instructor_id = i.instructor_id
    JOIN users u ON i.user_id = u.user_id
    JOIN subject_offerings so ON fl.offering_id = so.offering_id
    JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
    JOIN subjects sub ON cs.subject_id = sub.subject_id
    ORDER BY u.full_name, FIELD(s.day, 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'), s.start_time
")->fetchAll(PDO::FETCH_ASSOC);

// Group schedules by instructor
$grouped_schedules = [];
foreach ($room_schedules as $schedule) {
    $instructor_key = $schedule['instructor_user_id'] . '_' . $schedule['instructor_name'];
    if (!isset($grouped_schedules[$instructor_key])) {
        $grouped_schedules[$instructor_key] = [
            'instructor_name' => $schedule['instructor_name'],
            'instructor_id' => $schedule['instructor_user_id'],
            'schedules' => [],
            'total_assignments' => 0
        ];
    }
    $grouped_schedules[$instructor_key]['schedules'][] = $schedule;
    $grouped_schedules[$instructor_key]['total_assignments']++;
}

// Fetch approved faculty loads for assignment
$approved_loads = $conn->query("
    SELECT 
        fl.load_id,
        u.full_name as instructor_name,
        sub.subject_code,
        sub.subject_name,
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
    WHERE fl.status = 'approved'
    ORDER BY fl.load_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$total_rooms = count($rooms);
$lecture_rooms = count(array_filter($rooms, fn($r) => $r['room_type'] === 'lecture'));
$lab_rooms = count(array_filter($rooms, fn($r) => $r['room_type'] === 'laboratory'));
$total_capacity = array_sum(array_column($rooms, 'capacity'));
$scheduled_rooms = count(array_filter($rooms, fn($r) => $r['schedule_count'] > 0));

// Calculate room utilization
$utilization_percent = $total_rooms > 0 ? round(($scheduled_rooms / $total_rooms) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Room & Laboratory Management | Class Scheduling System</title>
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
        
        .badge-room {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-lecture {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-laboratory {
            background: #f3e8ff;
            color: #6b21a8;
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
        
        .btn-assign {
            background: #3b82f6;
            color: white;
        }
        
        .btn-assign:hover {
            background: #2563eb;
            color: white;
        }
        
        .btn-edit {
            background: #10b981;
            color: white;
        }
        
        .btn-edit:hover {
            background: #059669;
            color: white;
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        
        .btn-delete:hover {
            background: #dc2626;
            color: white;
        }
        
        .btn-expand {
            background: #8b5cf6;
            color: white;
        }
        
        .btn-expand:hover {
            background: #7c3aed;
            color: white;
        }
        
        .btn-schedule {
            background: #8b5cf6;
            color: white;
        }
        
        .btn-schedule:hover {
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
        
        .schedule-timeline {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .schedule-item {
            background: #f8fafc;
            border-radius: 10px;
            padding: 12px;
            border-left: 4px solid;
            flex: 1;
            min-width: 200px;
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
        
        .conflict-warning {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 12px;
            margin-top: 15px;
            display: none;
        }
        
        .conflict-warning.show {
            display: block;
        }
        
        .info-note {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 10px 15px;
            margin-top: 15px;
            font-size: 13px;
            color: #1e40af;
        }
        
        .group-row {
            background-color: #f8fafc;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .group-row:hover {
            background-color: #f1f5f9;
        }
        
        .child-row {
            background-color: #ffffff;
        }
        
        .child-row td {
            padding-left: 40px;
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
                                        <i class="fas fa-building mr-2" style="color: #8b5cf6;"></i>
                                        Classroom & Laboratory Management
                                    </h1>
                                    <p class="page-subtitle mb-0">Manage rooms, laboratories, and room assignments for schedules</p>
                                </div>
                                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                    <button class="btn btn-primary btn-lg" data-toggle="modal" data-target="#addRoomModal" style="border-radius: 30px; padding: 10px 24px;">
                                        <i class="fas fa-plus-circle mr-2"></i>Add New Room
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
                                    <i class="fas fa-door-open"></i>
                                </div>
                                <div class="stat-value"><?php echo $total_rooms; ?></div>
                                <div class="stat-label">Total Rooms</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #f0fdf4; color: #22c55e;">
                                    <i class="fas fa-chalkboard"></i>
                                </div>
                                <div class="stat-value"><?php echo $lecture_rooms; ?></div>
                                <div class="stat-label">Lecture Rooms</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
                                    <i class="fas fa-flask"></i>
                                </div>
                                <div class="stat-value"><?php echo $lab_rooms; ?></div>
                                <div class="stat-label">Laboratories</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #f1f5f9; color: #64748b;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-value"><?php echo $total_capacity; ?></div>
                                <div class="stat-label">Total Capacity</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #f3e8ff; color: #9333ea;">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="stat-value"><?php echo $scheduled_rooms; ?></div>
                                <div class="stat-label">Scheduled Rooms</div>
                            </div>
                        </div>

                        <!-- Rooms List -->
                        <div class="table-card">
                            <div class="table-header">
                                <h3 class="table-title">
                                    <i class="fas fa-list mr-2" style="color: #3b82f6;"></i>
                                    Room Directory
                                </h3>
                                <div class="filter-section">
                                    <div class="search-box">
                                        <i class="fas fa-search"></i>
                                        <input type="text" id="searchRoom" class="form-control" placeholder="Search rooms..." style="min-width: 250px;">
                                    </div>
                                    <select id="typeFilter" class="form-select" style="width: auto; border-radius: 30px;">
                                        <option value="all">All Types</option>
                                        <option value="lecture">Lecture Rooms</option>
                                        <option value="laboratory">Laboratories</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table" id="roomsTable">
                                    <thead>
                                        <tr>
                                            <th>Room Name</th>
                                            <th>Type</th>
                                            <th>Capacity</th>
                                            <th>Schedules</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($rooms)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-5 text-muted">
                                                    <i class="fas fa-building fa-3x mb-3" style="opacity: 0.5;"></i>
                                                    <p class="mb-0">No rooms available. Click "Add New Room" to get started.</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($rooms as $room): ?>
                                            <tr data-type="<?php echo $room['room_type']; ?>">
                                                <td>
                                                    <i class="fas fa-<?php echo $room['room_type'] === 'lecture' ? 'chalkboard' : 'flask'; ?> mr-2"></i>
                                                    <strong><?php echo htmlspecialchars($room['room_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge-room badge-<?php echo $room['room_type']; ?>">
                                                        <?php echo ucfirst($room['room_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <i class="fas fa-user mr-1"></i>
                                                    <?php echo $room['capacity']; ?> seats
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo $room['schedule_count']; ?> schedules
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($room['schedule_count'] > 0): ?>
                                                        <span class="badge badge-success">In Use</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Available</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button class="btn-action btn-assign" onclick="assignRoom(<?php echo $room['room_id']; ?>)" title="Assign to Schedule">
                                                            <i class="fas fa-calendar-plus"></i>
                                                        </button>
                                                        <button class="btn-action btn-edit" onclick="editRoom(<?php echo $room['room_id']; ?>, '<?php echo htmlspecialchars($room['room_name']); ?>', '<?php echo $room['room_type']; ?>', <?php echo $room['capacity']; ?>)" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn-action btn-delete" onclick="deleteRoom(<?php echo $room['room_id']; ?>)" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Room Schedules - Grouped by Instructor -->
                        <div class="table-card">
                            <div class="table-header">
                                <h3 class="table-title">
                                    <i class="fas fa-calendar-alt mr-2" style="color: #8b5cf6;"></i>
                                    Current Room Assignments
                                    <span class="badge-count"><?php echo count($room_schedules); ?> total schedules</span>
                                </h3>
                                <button class="btn btn-outline-primary" data-toggle="modal" data-target="#assignScheduleModal" style="border-radius: 30px;">
                                    <i class="fas fa-plus mr-1"></i>New Assignment
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table" id="schedulesTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;"></th>
                                            <th>Instructor</th>
                                            <th>Day</th>
                                            <th>Time</th>
                                            <th>Room</th>
                                            <th>Subject</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($grouped_schedules)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4 text-muted">
                                                    <i class="fas fa-calendar fa-3x mb-3" style="opacity: 0.5;"></i>
                                                    <p>No room assignments yet.</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $group_index = 0; ?>
                                            <?php foreach ($grouped_schedules as $group): ?>
                                                <?php 
                                                $group_id = 'group_' . $group_index;
                                                $expand_icon_id = 'expand_icon_' . $group_index;
                                                $group_index++;
                                                $first_schedule = $group['schedules'][0];
                                                ?>
                                                <!-- Group Row -->
                                                <tr class="group-row" onclick="toggleGroup('<?php echo $group_id; ?>', '<?php echo $expand_icon_id; ?>')" style="cursor: pointer;">
                                                    <td class="text-center">
                                                        <i id="<?php echo $expand_icon_id; ?>" class="fas fa-chevron-right rotate-icon"></i>
                                                    </td>
                                                    <td>
                                                        <strong>
                                                            <i class="fas fa-chalkboard-user mr-2" style="color: #8b5cf6;"></i>
                                                            <?php echo htmlspecialchars($group['instructor_name']); ?>
                                                        </strong>
                                                        <span class="badge-count"><?php echo $group['total_assignments']; ?> assignment(s)</span>
                                                    </td>
                                                    <td colspan="5">
                                                        <span class="text-muted">Click to expand/collapse schedules</span>
                                                    </td>
                                                </tr>
                                                
                                                <!-- Child Rows (Schedules) -->
                                                <?php foreach ($group['schedules'] as $schedule): ?>
                                                <tr class="child-row" id="<?php echo $group_id; ?>" style="display: none;">
                                                    <td></td>
                                                    <td></td>
                                                    <td><strong><?php echo $schedule['day']; ?></strong></td>
                                                    <td>
                                                        <?php echo date('h:i A', strtotime($schedule['start_time'])); ?> - 
                                                        <?php echo date('h:i A', strtotime($schedule['end_time'])); ?>
                                                    </td>
                                                    <td>
                                                        <i class="fas fa-<?php echo $schedule['room_type'] === 'lecture' ? 'chalkboard' : 'flask'; ?> mr-1"></i>
                                                        <?php echo htmlspecialchars($schedule['room_name']); ?>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($schedule['subject_code']); ?></strong>
                                                        <br>
                                                        <small><?php echo htmlspecialchars($schedule['subject_name']); ?></small>
                                                    </td>
                                                    <td>
                                                        <button class="btn-action btn-edit" onclick="editSchedule(<?php echo $schedule['schedule_id']; ?>, <?php echo $schedule['room_id']; ?>, '<?php echo $schedule['day']; ?>', '<?php echo $schedule['start_time']; ?>', '<?php echo $schedule['end_time']; ?>')" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn-action btn-delete" onclick="removeSchedule(<?php echo $schedule['schedule_id']; ?>)" title="Remove">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
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

    <!-- Add Room Modal -->
    <div class="modal fade" id="addRoomModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus-circle mr-2" style="color: #3b82f6;"></i>
                            Add New Room
                        </h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Room Name</label>
                            <input type="text" name="room_name" class="form-control" placeholder="e.g., Room 101, Lab A" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Room Type</label>
                            <select name="room_type" class="form-select" required>
                                <option value="">Select type...</option>
                                <option value="lecture">Lecture Room</option>
                                <option value="laboratory">Laboratory</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Capacity (Number of seats)</label>
                            <input type="number" name="capacity" class="form-control" min="1" placeholder="e.g., 40" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Room Modal -->
    <div class="modal fade" id="editRoomModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="room_id" id="edit_room_id">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit mr-2" style="color: #10b981;"></i>
                            Edit Room
                        </h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Room Name</label>
                            <input type="text" name="room_name" id="edit_room_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Room Type</label>
                            <select name="room_type" id="edit_room_type" class="form-select" required>
                                <option value="lecture">Lecture Room</option>
                                <option value="laboratory">Laboratory</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Capacity</label>
                            <input type="number" name="capacity" id="edit_capacity" class="form-control" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Room Modal with Real-time Conflict Check -->
    <div class="modal fade" id="assignScheduleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="assignScheduleForm">
                    <input type="hidden" name="action" value="assign">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-calendar-plus mr-2" style="color: #8b5cf6;"></i>
                            Assign Room to Schedule
                        </h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Select Room</label>
                                    <select name="room_id" id="assign_room_id" class="form-select" required>
                                        <option value="">Choose room...</option>
                                        <?php foreach ($rooms as $room): ?>
                                        <option value="<?php echo $room['room_id']; ?>">
                                            <?php echo htmlspecialchars($room['room_name']); ?> 
                                            (<?php echo ucfirst($room['room_type']); ?> - <?php echo $room['capacity']; ?> seats)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Select Faculty Load</label>
                                    <select name="load_id" class="form-select" required>
                                        <option value="">Choose load...</option>
                                        <?php foreach ($approved_loads as $load): ?>
                                        <option value="<?php echo $load['load_id']; ?>">
                                            <?php echo htmlspecialchars($load['subject_code']); ?> - 
                                            <?php echo htmlspecialchars($load['instructor_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Day</label>
                                    <select name="day" id="assign_day" class="form-select" required>
                                        <option value="">Select day...</option>
                                        <option value="Mon">Monday</option>
                                        <option value="Tue">Tuesday</option>
                                        <option value="Wed">Wednesday</option>
                                        <option value="Thu">Thursday</option>
                                        <option value="Fri">Friday</option>
                                        <option value="Sat">Saturday</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Start Time</label>
                                    <input type="time" name="start_time" id="assign_start_time" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">End Time</label>
                                    <input type="time" name="end_time" id="assign_end_time" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Conflict Warning Area -->
                        <div id="conflictWarning" class="conflict-warning">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: #dc2626;"></i>
                            <span id="conflictMessage"></span>
                        </div>
                        
                        <!-- Info Note -->
                        <div class="info-note">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Note:</strong> You can schedule multiple classes in the same room on the same day as long as they don't have the EXACT same start and end time. Overlapping times (e.g., 8:00-10:00 and 9:00-11:00) are allowed.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitAssignBtn">Assign Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal with Real-time Conflict Check -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="editScheduleForm">
                    <input type="hidden" name="action" value="update_schedule">
                    <input type="hidden" name="schedule_id" id="edit_schedule_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Schedule Assignment</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Select Room</label>
                                    <select name="room_id" id="edit_schedule_room_id" class="form-select" required>
                                        <?php foreach ($rooms as $room): ?>
                                        <option value="<?php echo $room['room_id']; ?>">
                                            <?php echo htmlspecialchars($room['room_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Day</label>
                                    <select name="day" id="edit_schedule_day" class="form-select" required>
                                        <option value="Mon">Monday</option>
                                        <option value="Tue">Tuesday</option>
                                        <option value="Wed">Wednesday</option>
                                        <option value="Thu">Thursday</option>
                                        <option value="Fri">Friday</option>
                                        <option value="Sat">Saturday</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Start Time</label>
                                    <input type="time" name="start_time" id="edit_schedule_start" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">End Time</label>
                                    <input type="time" name="end_time" id="edit_schedule_end" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Conflict Warning Area for Edit -->
                        <div id="editConflictWarning" class="conflict-warning">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: #dc2626;"></i>
                            <span id="editConflictMessage"></span>
                        </div>
                        
                        <!-- Info Note -->
                        <div class="info-note">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Note:</strong> You can schedule multiple classes in the same room on the same day as long as they don't have the EXACT same start and end time.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitEditBtn">Update Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Room Form -->
    <form method="POST" id="deleteRoomForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="room_id" id="delete_room_id">
    </form>

    <!-- Remove Schedule Form -->
    <form method="POST" id="removeScheduleForm" style="display: none;">
        <input type="hidden" name="action" value="remove_schedule">
        <input type="hidden" name="schedule_id" id="remove_schedule_id">
    </form>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>
    
    <script>
        // Function to toggle group expand/collapse
        function toggleGroup(groupId, iconId) {
            const groupRows = document.querySelectorAll('#' + groupId);
            const icon = document.getElementById(iconId);
            
            groupRows.forEach(row => {
                if (row.style.display === 'none' || row.style.display === '') {
                    row.style.display = '';
                    icon.classList.add('expanded');
                } else {
                    row.style.display = 'none';
                    icon.classList.remove('expanded');
                }
            });
        }
        
        // Search and Filter functionality for rooms
        document.getElementById('searchRoom').addEventListener('keyup', filterRooms);
        document.getElementById('typeFilter').addEventListener('change', filterRooms);
        
        function filterRooms() {
            const searchTerm = document.getElementById('searchRoom').value.toLowerCase();
            const typeFilter = document.getElementById('typeFilter').value;
            const rows = document.querySelectorAll('#roomsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const type = row.getAttribute('data-type');
                const matchesSearch = text.includes(searchTerm);
                const matchesType = typeFilter === 'all' || type === typeFilter;
                
                row.style.display = matchesSearch && matchesType ? '' : 'none';
            });
        }
        
        // Room CRUD Functions
        function editRoom(id, name, type, capacity) {
            document.getElementById('edit_room_id').value = id;
            document.getElementById('edit_room_name').value = name;
            document.getElementById('edit_room_type').value = type;
            document.getElementById('edit_capacity').value = capacity;
            $('#editRoomModal').modal('show');
        }
        
        function deleteRoom(id) {
            if (confirm('Are you sure you want to delete this room? This action cannot be undone.')) {
                document.getElementById('delete_room_id').value = id;
                document.getElementById('deleteRoomForm').submit();
            }
        }
        
        function assignRoom(roomId) {
            document.getElementById('assign_room_id').value = roomId;
            $('#assignScheduleModal').modal('show');
            // Clear conflict warning when opening
            document.getElementById('conflictWarning').classList.remove('show');
        }
        
        // Real-time conflict check for assignment (checking EXACT same time only)
        function checkAssignmentConflict() {
            const roomId = document.getElementById('assign_room_id').value;
            const day = document.getElementById('assign_day').value;
            const startTime = document.getElementById('assign_start_time').value;
            const endTime = document.getElementById('assign_end_time').value;
            const conflictWarning = document.getElementById('conflictWarning');
            const conflictMessage = document.getElementById('conflictMessage');
            const submitBtn = document.getElementById('submitAssignBtn');
            
            if (!roomId || !day || !startTime || !endTime) {
                conflictWarning.classList.remove('show');
                submitBtn.disabled = false;
                return;
            }
            
            if (startTime >= endTime) {
                conflictMessage.innerHTML = '⚠️ Invalid time range: Start time must be before end time!';
                conflictWarning.classList.add('show');
                submitBtn.disabled = true;
                return;
            }
            
            // AJAX call to check for EXACT same time conflict
            fetch('check_room_conflict.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `room_id=${roomId}&day=${day}&start_time=${startTime}&end_time=${endTime}&exact_match=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.hasConflict) {
                    conflictMessage.innerHTML = `❌ ${data.message}`;
                    conflictWarning.classList.add('show');
                    submitBtn.disabled = true;
                } else {
                    conflictWarning.classList.remove('show');
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                conflictWarning.classList.remove('show');
                submitBtn.disabled = false;
            });
        }
        
        // Real-time conflict check for edit (checking EXACT same time only)
        function checkEditConflict() {
            const scheduleId = document.getElementById('edit_schedule_id').value;
            const roomId = document.getElementById('edit_schedule_room_id').value;
            const day = document.getElementById('edit_schedule_day').value;
            const startTime = document.getElementById('edit_schedule_start').value;
            const endTime = document.getElementById('edit_schedule_end').value;
            const conflictWarning = document.getElementById('editConflictWarning');
            const conflictMessage = document.getElementById('editConflictMessage');
            const submitBtn = document.getElementById('submitEditBtn');
            
            if (!roomId || !day || !startTime || !endTime) {
                conflictWarning.classList.remove('show');
                submitBtn.disabled = false;
                return;
            }
            
            if (startTime >= endTime) {
                conflictMessage.innerHTML = '⚠️ Invalid time range: Start time must be before end time!';
                conflictWarning.classList.add('show');
                submitBtn.disabled = true;
                return;
            }
            
            // AJAX call to check for EXACT same time conflict (excluding current schedule)
            fetch('check_room_conflict.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `room_id=${roomId}&day=${day}&start_time=${startTime}&end_time=${endTime}&schedule_id=${scheduleId}&exact_match=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.hasConflict) {
                    conflictMessage.innerHTML = `❌ ${data.message}`;
                    conflictWarning.classList.add('show');
                    submitBtn.disabled = true;
                } else {
                    conflictWarning.classList.remove('show');
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                conflictWarning.classList.remove('show');
                submitBtn.disabled = false;
            });
        }
        
        // Add event listeners for real-time conflict checking
        document.getElementById('assign_room_id').addEventListener('change', checkAssignmentConflict);
        document.getElementById('assign_day').addEventListener('change', checkAssignmentConflict);
        document.getElementById('assign_start_time').addEventListener('change', checkAssignmentConflict);
        document.getElementById('assign_end_time').addEventListener('change', checkAssignmentConflict);
        
        document.getElementById('edit_schedule_room_id').addEventListener('change', checkEditConflict);
        document.getElementById('edit_schedule_day').addEventListener('change', checkEditConflict);
        document.getElementById('edit_schedule_start').addEventListener('change', checkEditConflict);
        document.getElementById('edit_schedule_end').addEventListener('change', checkEditConflict);
        
        // Schedule Functions
        function editSchedule(id, roomId, day, start, end) {
            document.getElementById('edit_schedule_id').value = id;
            document.getElementById('edit_schedule_room_id').value = roomId;
            document.getElementById('edit_schedule_day').value = day;
            document.getElementById('edit_schedule_start').value = start;
            document.getElementById('edit_schedule_end').value = end;
            $('#editScheduleModal').modal('show');
            // Clear conflict warning when opening
            document.getElementById('editConflictWarning').classList.remove('show');
        }
        
        function removeSchedule(id) {
            if (confirm('Are you sure you want to remove this schedule assignment?')) {
                document.getElementById('remove_schedule_id').value = id;
                document.getElementById('removeScheduleForm').submit();
            }
        }
        
        // Auto-hide alerts
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
        // Initialize tooltips
        $(function () {
            $('[title]').tooltip();
        });
    </script>
</body>
</html>