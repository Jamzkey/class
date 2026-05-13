<?php
session_start();
include('assets/inc/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$load_id = filter_input(INPUT_GET, 'load_id', FILTER_VALIDATE_INT);

if (!$load_id) {
    echo '<div class="alert alert-danger">Invalid load ID</div>';
    exit();
}

try {
    // Get load details with schedule
    $stmt = $conn->prepare("
        SELECT 
            fl.load_id,
            fl.status,
            u.full_name as instructor_name,
            sub.subject_code,
            sub.subject_name,
            sub.lecture_hours,
            sub.lab_hours,
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
        WHERE fl.load_id = ?
    ");
    $stmt->execute([$load_id]);
    $load = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$load) {
        echo '<div class="alert alert-warning">Load not found</div>';
        exit();
    }
    
    // Get schedules for this load
    $stmt = $conn->prepare("
        SELECT 
            s.schedule_id,
            s.day,
            s.start_time,
            s.end_time,
            r.room_name,
            r.room_type,
            r.capacity
        FROM schedules s
        JOIN rooms r ON s.room_id = r.room_id
        WHERE s.load_id = ?
        ORDER BY FIELD(s.day, 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'), s.start_time
    ");
    $stmt->execute([$load_id]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ?>
    <div class="schedule-details">
        <div class="mb-3">
            <h6 class="font-weight-bold"><?php echo htmlspecialchars($load['subject_code'] . ' - ' . $load['subject_name']); ?></h6>
            <p class="mb-1">
                <strong>Instructor:</strong> <?php echo htmlspecialchars($load['instructor_name']); ?><br>
                <strong>Course/Year:</strong> <?php echo htmlspecialchars($load['course_name'] . ' - Year ' . $load['year_level']); ?><br>
                <strong>Units:</strong> <?php echo $load['units']; ?> | 
                <strong>Lecture Hours:</strong> <?php echo $load['lecture_hours']; ?> | 
                <strong>Lab Hours:</strong> <?php echo $load['lab_hours']; ?><br>
                <strong>School Year:</strong> <?php echo htmlspecialchars($load['school_year']); ?> | 
                <strong>Semester:</strong> <?php echo $load['semester']; ?>
            </p>
        </div>
        
        <?php if (empty($schedules)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>No schedule has been generated for this load yet.
            </div>
        <?php else: ?>
            <h6 class="font-weight-bold mb-3">Scheduled Sessions (<?php echo count($schedules); ?> sessions):</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Room</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                        <tr>
                            <td>
                                <?php 
                                $days_map = [
                                    'Mon' => 'Monday',
                                    'Tue' => 'Tuesday',
                                    'Wed' => 'Wednesday',
                                    'Thu' => 'Thursday',
                                    'Fri' => 'Friday',
                                    'Sat' => 'Saturday'
                                ];
                                echo $days_map[$schedule['day']] ?? $schedule['day'];
                                ?>
                            </td>
                            <td>
                                <?php echo date('h:i A', strtotime($schedule['start_time'])); ?> - 
                                <?php echo date('h:i A', strtotime($schedule['end_time'])); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($schedule['room_name']); ?>
                                <small class="text-muted">(Cap: <?php echo $schedule['capacity']; ?>)</small>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $schedule['room_type'] === 'laboratory' ? 'warning' : 'info'; ?>">
                                    <?php echo ucfirst($schedule['room_type']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="alert alert-success mt-3">
                <i class="fas fa-check-circle mr-2"></i>
                <strong>Schedule Summary:</strong> 
                <?php 
                $lecture_count = 0;
                $lab_count = 0;
                foreach ($schedules as $schedule) {
                    if ($schedule['room_type'] === 'laboratory') {
                        $lab_count++;
                    } else {
                        $lecture_count++;
                    }
                }
                echo $lecture_count . ' lecture session(s), ' . $lab_count . ' laboratory session(s)';
                ?>
            </div>
        <?php endif; ?>
        
        <div class="mt-3">
            <?php if ($load['status'] === 'approved'): ?>
                <?php if (!empty($schedules)): ?>
                    <button class="btn btn-primary btn-sm" onclick="window.regenerateSchedule(<?php echo $load_id; ?>)">
                        <i class="fas fa-sync-alt mr-1"></i>Regenerate Schedule
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="window.clearSchedule(<?php echo $load_id; ?>)">
                        <i class="fas fa-trash mr-1"></i>Clear Schedule
                    </button>
                <?php else: ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="regenerate_schedule">
                        <input type="hidden" name="load_id" value="<?php echo $load_id; ?>">
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fas fa-magic mr-1"></i>Generate Schedule
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                <i class="fas fa-times mr-1"></i>Close
            </button>
        </div>
    </div>
    
    <style>
        .schedule-details { font-size: 14px; }
        .badge-warning { background: #f59e0b; color: white; }
        .badge-info { background: #3b82f6; color: white; }
    </style>
    <?php
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error loading schedule: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>