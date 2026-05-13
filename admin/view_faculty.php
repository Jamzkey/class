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

// Get faculty ID from URL
$faculty_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$faculty_id) {
    header('Location: faculty_approval.php');
    exit();
}

// Fetch faculty details
$stmt = $conn->prepare("
    SELECT 
        u.user_id,
        u.full_name,
        u.username,
        u.phone_number,
        u.status,
        u.user_pic,
        u.role,
        i.instructor_id,
        i.specialization
    FROM users u
    LEFT JOIN instructors i ON u.user_id = i.user_id
    WHERE u.user_id = :user_id AND u.role = 'faculty'
");
$stmt->execute([':user_id' => $faculty_id]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$faculty) {
    header('Location: faculty_approval.php');
    exit();
}

// Fetch faculty load history
$load_history = $conn->prepare("
    SELECT 
        fl.load_id,
        fl.status as load_status,
        s.subject_code,
        s.subject_name,
        s.units,
        so.school_year,
        so.semester,
        c.course_name,
        curr.year_level,
        a.approval_date,
        a.status as approval_status,
        approver.full_name as approved_by_name
    FROM faculty_load fl
    LEFT JOIN subject_offerings so ON fl.offering_id = so.offering_id
    LEFT JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
    LEFT JOIN subjects s ON cs.subject_id = s.subject_id
    LEFT JOIN curriculum curr ON cs.curriculum_id = curr.curriculum_id
    LEFT JOIN courses c ON curr.course_id = c.course_id
    LEFT JOIN approvals a ON fl.load_id = a.load_id
    LEFT JOIN users approver ON a.approved_by = approver.user_id
    WHERE fl.instructor_id = :instructor_id
    ORDER BY so.school_year DESC, so.semester DESC, s.subject_code ASC
");
$load_history->execute([':instructor_id' => $faculty['instructor_id']]);
$loads = $load_history->fetchAll(PDO::FETCH_ASSOC);

// Fetch schedules for this faculty
$schedules = $conn->prepare("
    SELECT 
        sch.schedule_id,
        sch.day,
        sch.start_time,
        sch.end_time,
        r.room_name,
        r.room_type,
        s.subject_code,
        s.subject_name,
        fl.status as load_status
    FROM schedules sch
    JOIN faculty_load fl ON sch.load_id = fl.load_id
    JOIN rooms r ON sch.room_id = r.room_id
    JOIN subject_offerings so ON fl.offering_id = so.offering_id
    JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.subject_id
    WHERE fl.instructor_id = :instructor_id 
    AND fl.status = 'approved'
    ORDER BY 
        FIELD(sch.day, 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'),
        sch.start_time ASC
");
$schedules->execute([':instructor_id' => $faculty['instructor_id']]);
$faculty_schedules = $schedules->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$total_loads = count($loads);
$approved_loads = count(array_filter($loads, fn($load) => $load['load_status'] === 'approved'));
$pending_loads = count(array_filter($loads, fn($load) => $load['load_status'] === 'pending'));
$rejected_loads = count(array_filter($loads, fn($load) => $load['load_status'] === 'rejected'));

// Group schedules by day
$schedules_by_day = [];
foreach ($faculty_schedules as $schedule) {
    $day = $schedule['day'];
    if (!isset($schedules_by_day[$day])) {
        $schedules_by_day[$day] = [];
    }
    $schedules_by_day[$day][] = $schedule;
}

// Day order for display
$day_order = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>View Faculty | Class Scheduling System</title>
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
        
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            margin-bottom: 28px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 36px;
        }
        
        .profile-info h2 {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }
        
        .profile-info p {
            color: #64748b;
            margin-bottom: 4px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 18px;
        }
        
        .info-content label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }
        
        .info-content p {
            font-size: 16px;
            font-weight: 500;
            color: #0f172a;
            margin: 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }
        
        .stat-value { font-size: 28px; font-weight: 700; color: #0f172a; line-height: 1.2; }
        .stat-label { font-size: 13px; color: #64748b; font-weight: 500; }
        
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
        
        .badge-approved {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .day-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #e2e8f0;
        }
        
        .day-title {
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .schedule-item {
            padding: 10px;
            background: white;
            border-radius: 8px;
            margin-bottom: 8px;
            border-left: 3px solid #667eea;
        }
        
        .schedule-time {
            font-weight: 600;
            color: #667eea;
            font-size: 13px;
        }
        
        .schedule-subject {
            font-weight: 500;
            color: #0f172a;
            margin: 4px 0;
        }
        
        .schedule-room {
            font-size: 12px;
            color: #64748b;
        }
        
        .btn-back {
            background: #f1f5f9;
            color: #0f172a;
            border-radius: 30px;
            padding: 10px 20px;
            font-weight: 500;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-back:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
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
            text-decoration: none;
            display: inline-block;
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
            .profile-header { flex-direction: column; text-align: center; }
            .info-grid { grid-template-columns: 1fr; }
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
                        
                        <!-- Back Button -->
                        <div class="mb-4">
                            <a href="faculty_approval.php" class="btn-back">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Faculty Management
                            </a>
                        </div>

                        <!-- Profile Card -->
                        <div class="profile-card">
                            <div class="profile-header">
                                <?php 
                                $name_parts = explode(' ', $faculty['full_name']);
                                $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                ?>
                                <div class="avatar-large"><?php echo $initials; ?></div>
                                <div class="profile-info">
                                    <h2><?php echo htmlspecialchars($faculty['full_name']); ?></h2>
                                    <p><i class="fas fa-user mr-2"></i>@<?php echo htmlspecialchars($faculty['username']); ?></p>
                                    <p>
                                        <span class="badge-status <?php echo $faculty['status'] === 'active' ? 'badge-active' : ($faculty['status'] === 'pending' ? 'badge-pending' : 'badge-inactive'); ?>">
                                            <i class="fas fa-<?php echo $faculty['status'] === 'active' ? 'check-circle' : ($faculty['status'] === 'pending' ? 'clock' : 'ban'); ?> mr-1"></i>
                                            <?php echo ucfirst($faculty['status']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div class="info-content">
                                        <label>Phone Number</label>
                                        <p><?php echo htmlspecialchars($faculty['phone_number'] ?: 'Not provided'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                    <div class="info-content">
                                        <label>Specialization</label>
                                        <p><?php echo htmlspecialchars($faculty['specialization'] ?: 'Not specified'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-id-card"></i>
                                    </div>
                                    <div class="info-content">
                                        <label>Instructor ID</label>
                                        <p><?php echo $faculty['instructor_id'] ? 'INS-' . str_pad($faculty['instructor_id'], 4, '0', STR_PAD_LEFT) : 'Not assigned'; ?></p>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <div class="info-content">
                                        <label>Role</label>
                                        <p><?php echo ucfirst($faculty['role']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $total_loads; ?></div>
                                <div class="stat-label">Total Loads</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-value text-success"><?php echo $approved_loads; ?></div>
                                <div class="stat-label">Approved</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-value text-warning"><?php echo $pending_loads; ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-value text-danger"><?php echo $rejected_loads; ?></div>
                                <div class="stat-label">Rejected</div>
                            </div>
                        </div>

                        <!-- Current Schedule -->
                        <?php if (!empty($faculty_schedules)): ?>
                        <div class="table-card">
                            <div class="table-header">
                                <h3 class="table-title">
                                    <i class="fas fa-calendar-alt mr-2" style="color: #667eea;"></i>
                                    Current Teaching Schedule
                                </h3>
                            </div>
                            
                            <div class="schedule-grid">
                                <?php foreach ($day_order as $day): ?>
                                    <?php if (isset($schedules_by_day[$day])): ?>
                                    <div class="day-card">
                                        <div class="day-title">
                                            <i class="fas fa-calendar-day mr-2"></i><?php echo $day; ?>
                                        </div>
                                        <?php foreach ($schedules_by_day[$day] as $schedule): ?>
                                        <div class="schedule-item">
                                            <div class="schedule-time">
                                                <i class="far fa-clock mr-1"></i>
                                                <?php echo date('h:i A', strtotime($schedule['start_time'])); ?> - 
                                                <?php echo date('h:i A', strtotime($schedule['end_time'])); ?>
                                            </div>
                                            <div class="schedule-subject">
                                                <?php echo htmlspecialchars($schedule['subject_code']); ?> - 
                                                <?php echo htmlspecialchars($schedule['subject_name']); ?>
                                            </div>
                                            <div class="schedule-room">
                                                <i class="fas fa-door-open mr-1"></i>
                                                <?php echo htmlspecialchars($schedule['room_name']); ?> 
                                                (<?php echo ucfirst($schedule['room_type']); ?>)
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Load History -->
                        <div class="table-card">
                            <div class="table-header">
                                <h3 class="table-title">
                                    <i class="fas fa-history mr-2" style="color: #8b5cf6;"></i>
                                    Teaching Load History
                                </h3>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Subject Code</th>
                                            <th>Subject Name</th>
                                            <th>Units</th>
                                            <th>Course</th>
                                            <th>Year Level</th>
                                            <th>School Year</th>
                                            <th>Semester</th>
                                            <th>Status</th>
                                            <th>Approved By</th>
                                            <th>Approval Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($loads)): ?>
                                            <tr>
                                                <td colspan="10">
                                                    <div class="empty-state">
                                                        <i class="fas fa-book-open"></i>
                                                        <p class="mb-0">No teaching load history found.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($loads as $load): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($load['subject_code'] ?: '—'); ?></strong></td>
                                                <td><?php echo htmlspecialchars($load['subject_name'] ?: '—'); ?></td>
                                                <td><?php echo $load['units'] ?: '—'; ?></td>
                                                <td><?php echo htmlspecialchars($load['course_name'] ?: '—'); ?></td>
                                                <td><?php echo $load['year_level'] ? $load['year_level'] . ' Year' : '—'; ?></td>
                                                <td><?php echo htmlspecialchars($load['school_year'] ?: '—'); ?></td>
                                                <td><?php echo htmlspecialchars($load['semester'] ?: '—'); ?></td>
                                                <td>
                                                    <span class="badge-status <?php 
                                                        echo $load['load_status'] === 'approved' ? 'badge-approved' : 
                                                        ($load['load_status'] === 'pending' ? 'badge-pending' : 'badge-rejected'); 
                                                    ?>">
                                                        <?php echo ucfirst($load['load_status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($load['approved_by_name'] ?: '—'); ?></td>
                                                <td>
                                                    <?php 
                                                    if ($load['approval_date']) {
                                                        echo date('M d, Y h:i A', strtotime($load['approval_date']));
                                                    } else {
                                                        echo '—';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="text-center mb-4">
                            <?php if ($faculty['status'] === 'pending'): ?>
                            <form method="POST" action="faculty_approval.php" style="display: inline;">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="user_id" value="<?php echo $faculty['user_id']; ?>">
                                <button type="submit" class="btn-action btn-approve" style="background: #22c55e; color: white; padding: 12px 24px;" onclick="return confirm('Approve this faculty account?')">
                                    <i class="fas fa-check mr-2"></i>Approve Faculty
                                </button>
                            </form>
                            <form method="POST" action="faculty_approval.php" style="display: inline;">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="user_id" value="<?php echo $faculty['user_id']; ?>">
                                <button type="submit" class="btn-action btn-reject" style="background: #ef4444; color: white; padding: 12px 24px;" onclick="return confirm('Reject this faculty account?')">
                                    <i class="fas fa-times mr-2"></i>Reject Faculty
                                </button>
                            </form>
                            <?php elseif ($faculty['status'] === 'active'): ?>
                            <form method="POST" action="faculty_approval.php" style="display: inline;">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="user_id" value="<?php echo $faculty['user_id']; ?>">
                                <button type="submit" class="btn-action btn-deactivate" style="background: #f59e0b; color: white; padding: 12px 24px;" onclick="return confirm('Deactivate this faculty account?')">
                                    <i class="fas fa-ban mr-2"></i>Deactivate Faculty
                                </button>
                            </form>
                            <?php elseif ($faculty['status'] === 'inactive'): ?>
                            <form method="POST" action="faculty_approval.php" style="display: inline;">
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="user_id" value="<?php echo $faculty['user_id']; ?>">
                                <button type="submit" class="btn-action btn-activate" style="background: #3b82f6; color: white; padding: 12px 24px;" onclick="return confirm('Activate this faculty account?')">
                                    <i class="fas fa-check-circle mr-2"></i>Activate Faculty
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
            <?php include('assets/inc/footer.php'); ?>
        </div>
    </div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>
</body>
</html>