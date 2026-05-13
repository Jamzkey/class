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

// Fetch user details
$stmt = $conn->prepare("SELECT full_name, username, role, user_pic FROM users WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$full_name = $user['full_name'] ?? $user['username'] ?? 'Administrator';

// ========== DESCRIPTIVE ANALYTICS DATA ==========

// 1. User Role Distribution (Pie Chart)
$role_distribution = $conn->query("
    SELECT role, COUNT(*) as count 
    FROM users 
    WHERE status = 'active'
    GROUP BY role
")->fetchAll(PDO::FETCH_ASSOC);

// 2. Faculty by Specialization (Bar Chart)
$specialization_stats = $conn->query("
    SELECT 
        CASE 
            WHEN i.specialization IS NULL OR i.specialization = '' THEN 'Unspecified'
            ELSE i.specialization 
        END as specialization,
        COUNT(*) as count 
    FROM instructors i 
    JOIN users u ON i.user_id = u.user_id 
    WHERE u.status = 'active'
    GROUP BY specialization
    ORDER BY count DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 3. Room Type Distribution (Pie Chart)
$room_type_distribution = $conn->query("
    SELECT 
        room_type,
        COUNT(*) as count,
        SUM(capacity) as total_capacity
    FROM rooms 
    GROUP BY room_type
")->fetchAll(PDO::FETCH_ASSOC);

// 4. Faculty Load Status Distribution (Pie Chart)
$load_status_distribution = $conn->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM faculty_load 
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

// 5. Subjects by Units (Bar Chart)
$subjects_by_units = $conn->query("
    SELECT 
        units,
        COUNT(*) as count
    FROM subjects 
    WHERE units IS NOT NULL AND units > 0
    GROUP BY units
    ORDER BY units
")->fetchAll(PDO::FETCH_ASSOC);

// 6. Weekly Schedule Distribution (Bar Chart)
$weekly_schedule_distribution = $conn->query("
    SELECT 
        day,
        COUNT(*) as count
    FROM schedules 
    GROUP BY day
    ORDER BY FIELD(day, 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat')
")->fetchAll(PDO::FETCH_ASSOC);

// 7. Instructor Workload Distribution (Bar Chart - Top 10)
$instructor_workload = $conn->query("
    SELECT 
        u.full_name,
        COUNT(DISTINCT fl.load_id) as total_loads,
        COALESCE(SUM(sub.units), 0) as total_units
    FROM faculty_load fl
    JOIN instructors i ON fl.instructor_id = i.instructor_id
    JOIN users u ON i.user_id = u.user_id
    LEFT JOIN subject_offerings so ON fl.offering_id = so.offering_id
    LEFT JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
    LEFT JOIN subjects sub ON cs.subject_id = sub.subject_id
    WHERE fl.status = 'approved'
    GROUP BY u.user_id, u.full_name
    ORDER BY total_units DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 8. Room Utilization by Time Slot (Bar Chart)
$room_time_utilization = $conn->query("
    SELECT 
        HOUR(start_time) as hour,
        COUNT(*) as count
    FROM schedules 
    WHERE start_time IS NOT NULL
    GROUP BY HOUR(start_time)
    ORDER BY hour
")->fetchAll(PDO::FETCH_ASSOC);

// 9. Approval Rate Statistics
$approval_stats = $conn->query("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) as approved,
        COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending
    FROM faculty_load
")->fetch(PDO::FETCH_ASSOC);

// 10. Course Distribution by Year Level
$course_year_distribution = $conn->query("
    SELECT 
        c.course_name,
        curr.year_level,
        COUNT(*) as count
    FROM curriculum curr
    JOIN courses c ON curr.course_id = c.course_id
    GROUP BY c.course_name, curr.year_level
    ORDER BY c.course_name, curr.year_level
")->fetchAll(PDO::FETCH_ASSOC);

// Summary Statistics
$total_users = $conn->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$total_faculty = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'faculty' AND status = 'active'")->fetchColumn();
$total_courses = $conn->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$total_subjects = $conn->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
$total_rooms = $conn->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$total_schedules = $conn->query("SELECT COUNT(*) FROM schedules")->fetchColumn();
$total_units = $conn->query("SELECT COALESCE(SUM(units), 0) FROM subjects")->fetchColumn();

// Room Utilization Percentage
$utilized_rooms = $conn->query("SELECT COUNT(DISTINCT room_id) FROM schedules")->fetchColumn();
$room_utilization_percent = $total_rooms > 0 ? round(($utilized_rooms / $total_rooms) * 100) : 0;

// Check if we have any schedules
$has_schedules = $total_schedules > 0;

// Get current date and time
$current_date = date('l, F j, Y');
$current_time = date('h:i A');
$greeting = getGreeting();
function getGreeting() {
    $hour = date('H');
    if ($hour < 12) return 'Good Morning';
    if ($hour < 17) return 'Good Afternoon';
    return 'Good Evening';
}

// Debug function to check data
function debugData($data, $name) {
    error_log("$name: " . print_r($data, true));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Analytics Dashboard | Class Scheduling System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/aq.png">
    
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        body { background: #f1f5f9; }
        
        .page-wrapper { padding: 24px 32px; }
        
        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 28px;
            color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .welcome-title { font-size: 28px; font-weight: 700; margin-bottom: 6px; }
        .welcome-subtitle { font-size: 15px; opacity: 0.9; }
        .date-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            display: inline-block;
        }
        
        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        
        .summary-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        
        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin: 0 auto 12px;
        }
        
        .summary-value { font-size: 28px; font-weight: 700; color: #0f172a; }
        .summary-label { font-size: 13px; color: #64748b; font-weight: 500; }
        
        /* Chart Cards */
        .chart-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        
        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-title { 
            font-size: 18px; 
            font-weight: 600; 
            color: #0f172a;
            display: flex;
            align-items: center;
        }
        
        .chart-container {
            position: relative;
            height: 280px;
        }
        
        .full-width {
            grid-column: span 2;
        }
        
        .no-data-message {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }
        
        .no-data-message i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .chart-row { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
            .page-wrapper { padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .welcome-section { padding: 20px; }
            .welcome-title { font-size: 22px; }
        }
        
        /* Analytics Badge */
        .analytics-badge {
            background: rgba(255,255,255,0.15);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
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
                        
                        <!-- Welcome Section -->
                        <div class="welcome-section">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h1 class="welcome-title">
                                        <i class="fas fa-chart-pie mr-2"></i>
                                        Descriptive Analytics Dashboard
                                    </h1>
                                    <p class="welcome-subtitle mb-2"><?php echo $greeting; ?>, <?php echo htmlspecialchars($full_name); ?>! View system analytics and statistics.</p>
                                    <span class="date-badge"><i class="far fa-calendar-alt mr-2"></i><?php echo $current_date; ?> • <?php echo $current_time; ?></span>
                                </div>
                                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                    <span class="analytics-badge">
                                        <i class="fas fa-database mr-1"></i>Real-time Analytics
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div class="summary-grid">
                            <div class="summary-card">
                                <div class="summary-icon" style="background: #eff6ff; color: #3b82f6;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="summary-value"><?php echo number_format($total_users); ?></div>
                                <div class="summary-label">Active Users</div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="summary-icon" style="background: #f0fdf4; color: #22c55e;">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <div class="summary-value"><?php echo number_format($total_faculty); ?></div>
                                <div class="summary-label">Faculty</div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="summary-icon" style="background: #fff7ed; color: #f97316;">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="summary-value"><?php echo number_format($total_courses); ?></div>
                                <div class="summary-label">Courses</div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="summary-icon" style="background: #f5f3ff; color: #8b5cf6;">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="summary-value"><?php echo number_format($total_subjects); ?></div>
                                <div class="summary-label">Subjects</div>
                            </div>
                            
                            
                            
                            <div class="summary-card">
                                <div class="summary-icon" style="background: #fefce8; color: #eab308;">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="summary-value"><?php echo number_format($total_schedules); ?></div>
                                <div class="summary-label">Schedules</div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="summary-icon" style="background: #ecfdf5; color: #14b8a6;">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div class="summary-value"><?php echo number_format($total_units); ?></div>
                                <div class="summary-label">Total Units</div>
                            </div>
                            
                            
                        </div>

                        <!-- Chart Row 1: User Roles & Room Types (Pie Charts) -->
                        <div class="chart-row">
                            <!-- User Role Distribution -->
                            <div class="chart-card">
                                <div class="chart-header">
                                    <h3 class="chart-title">
                                        <i class="fas fa-users-cog mr-2" style="color: #3b82f6;"></i>
                                        User Role Distribution
                                    </h3>
                                </div>
                                <div class="chart-container">
                                    <?php if (!empty($role_distribution)): ?>
                                        <canvas id="userRoleChart"></canvas>
                                    <?php else: ?>
                                        <div class="no-data-message">
                                            <i class="fas fa-users-slash"></i>
                                            <p>No user data available</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <?php foreach ($role_distribution as $role): ?>
                                    <span class="badge bg-light text-dark mx-1 p-2">
                                        <?php echo ucfirst(str_replace('_', ' ', $role['role'])); ?>: <?php echo $role['count']; ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Room Type Distribution -->
                            <div class="chart-card">
                                <div class="chart-header">
                                    <h3 class="chart-title">
                                        <i class="fas fa-building mr-2" style="color: #ef4444;"></i>
                                        Room Type Distribution
                                    </h3>
                                </div>
                                <div class="chart-container">
                                    <?php if (!empty($room_type_distribution)): ?>
                                        <canvas id="roomTypeChart"></canvas>
                                    <?php else: ?>
                                        <div class="no-data-message">
                                            <i class="fas fa-door-closed"></i>
                                            <p>No room data available</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <?php foreach ($room_type_distribution as $room): ?>
                                    <span class="badge bg-light text-dark mx-1 p-2">
                                        <?php echo ucfirst($room['room_type']); ?>: <?php echo $room['count']; ?> (<?php echo $room['total_capacity']; ?> cap)
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Chart Row 2: Faculty Specialization & Faculty Load Status -->
                        <div class="chart-row">
                            <!-- Faculty by Specialization (Bar Chart) -->
                            <div class="chart-card">
                                <div class="chart-header">
                                    <h3 class="chart-title">
                                        <i class="fas fa-chart-bar mr-2" style="color: #22c55e;"></i>
                                        Faculty by Specialization
                                    </h3>
                                </div>
                                <div class="chart-container">
                                    <?php if (!empty($specialization_stats)): ?>
                                        <canvas id="specializationChart"></canvas>
                                    <?php else: ?>
                                        <div class="no-data-message">
                                            <i class="fas fa-user-graduate"></i>
                                            <p>No faculty specialization data available</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Faculty Load Status (Pie Chart) -->
                            <div class="chart-card">
                                <div class="chart-header">
                                    <h3 class="chart-title">
                                        <i class="fas fa-tasks mr-2" style="color: #f59e0b;"></i>
                                        Faculty Load Status
                                    </h3>
                                </div>
                                <div class="chart-container">
                                    <?php if (!empty($load_status_distribution)): ?>
                                        <canvas id="loadStatusChart"></canvas>
                                    <?php else: ?>
                                        <div class="no-data-message">
                                            <i class="fas fa-clipboard-list"></i>
                                            <p>No faculty load data available</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <span class="badge bg-warning mx-1 p-2">Pending: <?php echo $approval_stats['pending'] ?? 0; ?></span>
                                    <span class="badge bg-success mx-1 p-2">Approved: <?php echo $approval_stats['approved'] ?? 0; ?></span>
                                    <span class="badge bg-danger mx-1 p-2">Rejected: <?php echo $approval_stats['rejected'] ?? 0; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Chart Row 3: Weekly Schedule & Subjects by Units -->
                        <div class="chart-row">
                            <!-- Weekly Schedule Distribution (Bar Chart) -->
                            <div class="chart-card">
                                <div class="chart-header">
                                    <h3 class="chart-title">
                                        <i class="fas fa-calendar-week mr-2" style="color: #8b5cf6;"></i>
                                        Weekly Schedule Distribution
                                    </h3>
                                </div>
                                <div class="chart-container">
                                    <?php if (!empty($weekly_schedule_distribution)): ?>
                                        <canvas id="weeklyScheduleChart"></canvas>
                                    <?php else: ?>
                                        <div class="no-data-message">
                                            <i class="fas fa-calendar-xmark"></i>
                                            <p>No schedule data available</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Subjects by Units (Bar Chart) -->
                            <div class="chart-card">
                                <div class="chart-header">
                                    <h3 class="chart-title">
                                        <i class="fas fa-layer-group mr-2" style="color: #14b8a6;"></i>
                                        Subjects by Units
                                    </h3>
                                </div>
                                <div class="chart-container">
                                    <?php if (!empty($subjects_by_units)): ?>
                                        <canvas id="subjectsByUnitsChart"></canvas>
                                    <?php else: ?>
                                        <div class="no-data-message">
                                            <i class="fas fa-book-open"></i>
                                            <p>No subject data available</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Chart Row 4: Instructor Workload & Room Time Utilization -->
                        <div class="chart-row">
                            <!-- Instructor Workload (Horizontal Bar) -->
                            <div class="chart-card">
                                <div class="chart-header">
                                    <h3 class="chart-title">
                                        <i class="fas fa-user-clock mr-2" style="color: #ec4899;"></i>
                                        Top 10 Instructor Workload
                                    </h3>
                                </div>
                                <div class="chart-container" style="height: 350px;">
                                    <?php if (!empty($instructor_workload)): ?>
                                        <canvas id="instructorWorkloadChart"></canvas>
                                    <?php else: ?>
                                        <div class="no-data-message">
                                            <i class="fas fa-user-tie"></i>
                                            <p>No instructor workload data available</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Room Time Utilization (Bar Chart) -->
                            <div class="chart-card">
                                <div class="chart-header">
                                    <h3 class="chart-title">
                                        <i class="fas fa-clock mr-2" style="color: #06b6d4;"></i>
                                        Room Utilization by Hour
                                    </h3>
                                </div>
                                <div class="chart-container">
                                    <?php if (!empty($room_time_utilization)): ?>
                                        <canvas id="roomTimeChart"></canvas>
                                    <?php else: ?>
                                        <div class="no-data-message">
                                            <i class="fas fa-hourglass-end"></i>
                                            <p>No time utilization data available</p>
                                        </div>
                                    <?php endif; ?>
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
        // Chart Colors
        const chartColors = ['#3b82f6', '#22c55e', '#f97316', '#8b5cf6', '#ef4444', '#eab308', '#14b8a6', '#ec4899', '#06b6d4', '#f59e0b'];
        
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            
            // 1. User Role Distribution Chart (Pie)
            <?php if (!empty($role_distribution)): ?>
            (function() {
                const canvas = document.getElementById('userRoleChart');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    const roles = <?php echo json_encode(array_values($role_distribution)); ?>;
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: roles.map(r => r.role.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())),
                            datasets: [{
                                data: roles.map(r => r.count),
                                backgroundColor: chartColors,
                                borderWidth: 0,
                                borderRadius: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } }
                            },
                            cutout: '60%'
                        }
                    });
                }
            })();
            <?php endif; ?>
            
            // 2. Room Type Distribution Chart (Pie)
            <?php if (!empty($room_type_distribution)): ?>
            (function() {
                const canvas = document.getElementById('roomTypeChart');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    const roomTypes = <?php echo json_encode(array_values($room_type_distribution)); ?>;
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: roomTypes.map(r => r.room_type.charAt(0).toUpperCase() + r.room_type.slice(1)),
                            datasets: [{
                                data: roomTypes.map(r => r.count),
                                backgroundColor: ['#ef4444', '#3b82f6'],
                                borderWidth: 0,
                                borderRadius: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } }
                            },
                            cutout: '60%'
                        }
                    });
                }
            })();
            <?php endif; ?>
            
            // 3. Faculty Specialization Chart (Bar)
            <?php if (!empty($specialization_stats)): ?>
            (function() {
                const canvas = document.getElementById('specializationChart');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    const specs = <?php echo json_encode(array_values($specialization_stats)); ?>;
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: specs.map(s => s.specialization),
                            datasets: [{
                                label: 'Number of Faculty',
                                data: specs.map(s => s.count),
                                backgroundColor: chartColors,
                                borderRadius: 8,
                                borderSkipped: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: '#e2e8f0' },
                                    ticks: { stepSize: 1 }
                                },
                                x: {
                                    grid: { display: false }
                                }
                            }
                        }
                    });
                }
            })();
            <?php endif; ?>
            
            // 4. Faculty Load Status Chart (Pie)
            <?php if (!empty($load_status_distribution)): ?>
            (function() {
                const canvas = document.getElementById('loadStatusChart');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    const statuses = <?php echo json_encode(array_values($load_status_distribution)); ?>;
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: statuses.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1)),
                            datasets: [{
                                data: statuses.map(s => s.count),
                                backgroundColor: ['#eab308', '#22c55e', '#ef4444'],
                                borderWidth: 0,
                                borderRadius: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } }
                            },
                            cutout: '60%'
                        }
                    });
                }
            })();
            <?php endif; ?>
            
            // 5. Weekly Schedule Distribution (Bar)
            <?php if (!empty($weekly_schedule_distribution)): ?>
            (function() {
                const canvas = document.getElementById('weeklyScheduleChart');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    const schedules = <?php echo json_encode(array_values($weekly_schedule_distribution)); ?>;
                    const dayNames = { 'Mon': 'Monday', 'Tue': 'Tuesday', 'Wed': 'Wednesday', 'Thu': 'Thursday', 'Fri': 'Friday', 'Sat': 'Saturday' };
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: schedules.map(s => dayNames[s.day] || s.day),
                            datasets: [{
                                label: 'Number of Classes',
                                data: schedules.map(s => s.count),
                                backgroundColor: '#8b5cf6',
                                borderRadius: 8,
                                borderSkipped: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: '#e2e8f0' },
                                    ticks: { stepSize: 1 }
                                },
                                x: {
                                    grid: { display: false }
                                }
                            }
                        }
                    });
                }
            })();
            <?php endif; ?>
            
            // 6. Subjects by Units Chart (Bar)
            <?php if (!empty($subjects_by_units)): ?>
            (function() {
                const canvas = document.getElementById('subjectsByUnitsChart');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    const subjects = <?php echo json_encode(array_values($subjects_by_units)); ?>;
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: subjects.map(s => s.units + ' Units'),
                            datasets: [{
                                label: 'Number of Subjects',
                                data: subjects.map(s => s.count),
                                backgroundColor: '#14b8a6',
                                borderRadius: 8,
                                borderSkipped: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: '#e2e8f0' },
                                    ticks: { stepSize: 1 }
                                },
                                x: {
                                    grid: { display: false }
                                }
                            }
                        }
                    });
                }
            })();
            <?php endif; ?>
            
            // 7. Instructor Workload Chart (Horizontal Bar)
            <?php if (!empty($instructor_workload)): ?>
            (function() {
                const canvas = document.getElementById('instructorWorkloadChart');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    const workload = <?php echo json_encode(array_values($instructor_workload)); ?>;
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: workload.map(w => w.full_name),
                            datasets: [{
                                label: 'Total Units',
                                data: workload.map(w => w.total_units),
                                backgroundColor: '#ec4899',
                                borderRadius: 8,
                                borderSkipped: false
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    grid: { color: '#e2e8f0' },
                                    title: { display: true, text: 'Total Units' }
                                },
                                y: {
                                    grid: { display: false }
                                }
                            }
                        }
                    });
                }
            })();
            <?php endif; ?>
            
            // 8. Room Time Utilization Chart (Line)
            <?php if (!empty($room_time_utilization)): ?>
            (function() {
                const canvas = document.getElementById('roomTimeChart');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    const timeData = <?php echo json_encode(array_values($room_time_utilization)); ?>;
                    
                    // Fill in missing hours with 0
                    const allHours = [];
                    for (let i = 8; i <= 17; i++) {
                        allHours.push({ hour: i, count: 0 });
                    }
                    timeData.forEach(t => {
                        const index = allHours.findIndex(h => h.hour == t.hour);
                        if (index >= 0) allHours[index].count = t.count;
                    });
                    
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: allHours.map(h => {
                                const hour = h.hour;
                                const ampm = hour >= 12 ? 'PM' : 'AM';
                                const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
                                return displayHour + ':00 ' + ampm;
                            }),
                            datasets: [{
                                label: 'Classes Scheduled',
                                data: allHours.map(h => h.count),
                                backgroundColor: '#06b6d4',
                                borderColor: '#06b6d4',
                                borderWidth: 3,
                                tension: 0.3,
                                fill: true,
                                pointBackgroundColor: '#06b6d4',
                                pointBorderColor: 'white',
                                pointBorderWidth: 2,
                                pointRadius: 5,
                                pointHoverRadius: 7
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: '#e2e8f0' },
                                    ticks: { stepSize: 1 }
                                },
                                x: {
                                    grid: { display: false },
                                    title: { display: true, text: 'Time of Day' }
                                }
                            }
                        }
                    });
                }
            })();
            <?php endif; ?>
            
        });
    </script>
</body>
</html>