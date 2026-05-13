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

// ========== AUTOMATIC SCHEDULE GENERATION ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Generate schedule automatically
    if ($_POST['action'] === 'generate_schedule') {
        try {
            // Get all approved loads without schedules
            $stmt = $conn->prepare("
                SELECT fl.*, 
                       sub.lecture_hours, sub.lab_hours, sub.subject_code, sub.subject_name,
                       so.school_year, so.semester,
                       curr.year_level, c.course_id
                FROM faculty_load fl
                JOIN subject_offerings so ON fl.offering_id = so.offering_id
                JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
                JOIN subjects sub ON cs.subject_id = sub.subject_id
                JOIN curriculum curr ON cs.curriculum_id = curr.curriculum_id
                JOIN courses c ON curr.course_id = c.course_id
                WHERE fl.status = 'approved' 
                AND fl.load_id NOT IN (SELECT DISTINCT load_id FROM schedules)
            ");
            $stmt->execute();
            $pending_loads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($pending_loads)) {
                $message = "No approved loads pending for scheduling.";
                $message_type = "info";
            } else {
                $generated_count = 0;
                $errors = [];
                
                foreach ($pending_loads as $load) {
                    $result = generateScheduleForLoad($conn, $load);
                    if ($result['success']) {
                        $generated_count++;
                    } else {
                        $errors[] = $result['message'];
                    }
                }
                
                if ($generated_count > 0) {
                    $message = "✅ Successfully generated schedules for " . $generated_count . " load(s).";
                    $message_type = "success";
                }
                
                if (!empty($errors)) {
                    $message .= " ⚠️ " . implode(" ", array_slice($errors, 0, 3));
                    if (count($errors) > 3) $message .= " and " . (count($errors) - 3) . " more...";
                }
            }
        } catch (PDOException $e) {
            $message = "Error generating schedule: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // Regenerate schedule for specific load
    elseif ($_POST['action'] === 'regenerate_schedule') {
        $load_id = filter_input(INPUT_POST, 'load_id', FILTER_VALIDATE_INT);
        
        try {
            // Get the current schedule before deleting
            $stmt = $conn->prepare("
                SELECT room_id, day, start_time, end_time 
                FROM schedules 
                WHERE load_id = ?
                ORDER BY day, start_time
            ");
            $stmt->execute([$load_id]);
            $old_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Delete existing schedules
            $stmt = $conn->prepare("DELETE FROM schedules WHERE load_id = ?");
            $stmt->execute([$load_id]);
            
            // Get load details
            $stmt = $conn->prepare("
                SELECT fl.*, 
                       sub.lecture_hours, sub.lab_hours, sub.subject_code, sub.subject_name,
                       so.school_year, so.semester,
                       curr.year_level, c.course_id
                FROM faculty_load fl
                JOIN subject_offerings so ON fl.offering_id = so.offering_id
                JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
                JOIN subjects sub ON cs.subject_id = sub.subject_id
                JOIN curriculum curr ON cs.curriculum_id = curr.curriculum_id
                JOIN courses c ON curr.course_id = c.course_id
                WHERE fl.load_id = ?
            ");
            $stmt->execute([$load_id]);
            $load = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($load) {
                $result = generateScheduleForLoad($conn, $load);
                if ($result['success']) {
                    // Check if schedule actually changed
                    $stmt = $conn->prepare("
                        SELECT room_id, day, start_time, end_time 
                        FROM schedules 
                        WHERE load_id = ?
                        ORDER BY day, start_time
                    ");
                    $stmt->execute([$load_id]);
                    $new_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $schedule_changed = false;
                    if (count($old_schedule) != count($new_schedule)) {
                        $schedule_changed = true;
                    } else {
                        foreach ($old_schedule as $index => $old) {
                            if ($old['room_id'] != $new_schedule[$index]['room_id'] ||
                                $old['day'] != $new_schedule[$index]['day'] ||
                                $old['start_time'] != $new_schedule[$index]['start_time'] ||
                                $old['end_time'] != $new_schedule[$index]['end_time']) {
                                $schedule_changed = true;
                                break;
                            }
                        }
                    }
                    
                    if ($schedule_changed) {
                        $message = "✅ Schedule regenerated with new time slots/rooms!";
                        $message_type = "success";
                    } else {
                        $message = "ℹ️ Schedule remained the same (no better slots available).";
                        $message_type = "info";
                    }
                } else {
                    // If generation fails, restore old schedule
                    foreach ($old_schedule as $schedule) {
                        $stmt = $conn->prepare("
                            INSERT INTO schedules (load_id, room_id, day, start_time, end_time) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $load_id,
                            $schedule['room_id'],
                            $schedule['day'],
                            $schedule['start_time'],
                            $schedule['end_time']
                        ]);
                    }
                    $message = "❌ Could not regenerate: " . $result['message'];
                    $message_type = "danger";
                }
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // Clear schedule for specific load
    elseif ($_POST['action'] === 'clear_schedule') {
        $load_id = filter_input(INPUT_POST, 'load_id', FILTER_VALIDATE_INT);
        
        try {
            $stmt = $conn->prepare("DELETE FROM schedules WHERE load_id = ?");
            $stmt->execute([$load_id]);
            $message = "✅ Schedule cleared!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // Admin creates a draft load
    elseif ($_POST['action'] === 'create_draft' && $user_role === 'admin') {
        $instructor_id = filter_input(INPUT_POST, 'instructor_id', FILTER_VALIDATE_INT);
        $offering_id = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
        
        try {
            $stmt = $conn->prepare("INSERT INTO faculty_load (instructor_id, offering_id, status) VALUES (:instructor_id, :offering_id, 'draft')");
            $stmt->execute([':instructor_id' => $instructor_id, ':offering_id' => $offering_id]);
            $message = "✅ Draft saved!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // Admin submits draft loads for approval
    elseif ($_POST['action'] === 'submit_for_approval' && $user_role === 'admin') {
        $load_ids = $_POST['load_ids'] ?? [];
        
        if (!empty($load_ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($load_ids), '?'));
                $stmt = $conn->prepare("UPDATE faculty_load SET status = 'pending' WHERE load_id IN ($placeholders) AND status = 'draft'");
                $stmt->execute($load_ids);
                $count = $stmt->rowCount();
                $message = "✅ " . $count . " draft(s) submitted for approval!";
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
            $message = "✅ Draft deleted!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

/**
 * Generate schedule for a single faculty load
 */
function generateScheduleForLoad($conn, $load) {
    try {
        $lecture_hours = $load['lecture_hours'] ?? 0;
        $lab_hours = $load['lab_hours'] ?? 0;
        
        // Get available rooms
        $lecture_rooms = getAvailableRooms($conn, 'lecture');
        $lab_rooms = getAvailableRooms($conn, 'laboratory');
        
        // Shuffle rooms for variety when regenerating
        shuffle($lecture_rooms);
        shuffle($lab_rooms);
        
        // Get ALL existing schedules for conflict checking
        $all_existing_schedules = getAllExistingSchedules($conn);
        
        // Get instructor's existing schedules
        $instructor_schedules = getInstructorSchedules($conn, $load['instructor_id']);
        
        // Define available time slots (8 AM - 5 PM, 1-hour sessions)
        $time_slots = [
            ['08:00:00', '09:00:00'], ['09:00:00', '10:00:00'], 
            ['10:00:00', '11:00:00'], ['11:00:00', '12:00:00'],
            ['13:00:00', '14:00:00'], ['14:00:00', '15:00:00'],
            ['15:00:00', '16:00:00'], ['16:00:00', '17:00:00']
        ];
        
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
        $generated_schedules = [];
        
        // Generate 3 lecture sessions if lecture hours exist
        if ($lecture_hours > 0 && !empty($lecture_rooms)) {
            // Shuffle time slots for variety
            $shuffled_time_slots = $time_slots;
            shuffle($shuffled_time_slots);
            
            $lecture_result = generateFixedSessions(
                $conn, $load['load_id'], 3,
                $lecture_rooms, $days, $shuffled_time_slots, 
                $instructor_schedules, $all_existing_schedules, 'lecture'
            );
            
            if (!$lecture_result['success']) {
                return $lecture_result;
            }
            $generated_schedules = array_merge($generated_schedules, $lecture_result['schedules']);
            
            // Update instructor schedules and all schedules for conflict checking
            foreach ($lecture_result['schedules'] as $schedule) {
                $instructor_schedules[] = [
                    'day' => $schedule['day'],
                    'start_time' => $schedule['start_time'],
                    'end_time' => $schedule['end_time']
                ];
                $all_existing_schedules[] = [
                    'room_id' => $schedule['room_id'],
                    'day' => $schedule['day'],
                    'start_time' => $schedule['start_time'],
                    'end_time' => $schedule['end_time']
                ];
            }
        }
        
        // Generate 3 laboratory sessions if lab hours exist
        if ($lab_hours > 0 && !empty($lab_rooms)) {
            // Shuffle time slots differently for lab
            $shuffled_time_slots = $time_slots;
            shuffle($shuffled_time_slots);
            
            $lab_result = generateFixedSessions(
                $conn, $load['load_id'], 3,
                $lab_rooms, $days, $shuffled_time_slots, 
                $instructor_schedules, $all_existing_schedules, 'laboratory'
            );
            
            if (!$lab_result['success']) {
                return $lab_result;
            }
            $generated_schedules = array_merge($generated_schedules, $lab_result['schedules']);
        }
        
        // Insert generated schedules
        foreach ($generated_schedules as $schedule) {
            $stmt = $conn->prepare("
                INSERT INTO schedules (load_id, room_id, day, start_time, end_time) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $load['load_id'],
                $schedule['room_id'],
                $schedule['day'],
                $schedule['start_time'],
                $schedule['end_time']
            ]);
        }
        
        return ['success' => true, 'message' => count($generated_schedules) . ' sessions scheduled'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Generate exactly 3 sessions per week for a subject type
 */
function generateFixedSessions($conn, $load_id, $sessions_needed, $rooms, $days, $time_slots, $instructor_schedules, $all_schedules, $type) {
    $schedules = [];
    $sessions_scheduled = 0;
    
    // Collect all available slots
    $available_slots_by_day = [];
    foreach ($days as $day) {
        $available_slots_by_day[$day] = [];
    }
    
    // For each room, find all available slots
    foreach ($rooms as $room) {
        foreach ($days as $day) {
            foreach ($time_slots as $slot_index => $slot) {
                $start_time = $slot[0];
                $end_time = $slot[1];
                
                // Check if this slot is available
                if (isRoomAvailable($all_schedules, $room['room_id'], $day, $start_time, $end_time) &&
                    isInstructorAvailable($instructor_schedules, $day, $start_time, $end_time)) {
                    
                    $available_slots_by_day[$day][] = [
                        'room_id' => $room['room_id'],
                        'room_name' => $room['room_name'],
                        'day' => $day,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                        'slot_index' => $slot_index
                    ];
                }
            }
        }
    }
    
    // Filter out days that have no available slots
    $available_days = array_filter($available_slots_by_day, function($slots) {
        return !empty($slots);
    });
    
    // Get available days
    $selected_days = array_keys($available_days);
    
    // If we have more than 3 available days, select the best 3
    if (count($selected_days) > 3) {
        $day_priority = ['Mon', 'Wed', 'Fri', 'Tue', 'Thu'];
        $selected_days = [];
        foreach ($day_priority as $priority_day) {
            if (in_array($priority_day, array_keys($available_days)) && count($selected_days) < 3) {
                $selected_days[] = $priority_day;
            }
        }
    }
    
    // If we have less than 3 available days, we can't schedule all sessions
    if (count($selected_days) < $sessions_needed) {
        return [
            'success' => false,
            'message' => "Not enough available days for {$type}. Only " . count($selected_days) . " days available."
        ];
    }
    
    // Select the first 3 available days
    $selected_days = array_slice($selected_days, 0, 3);
    
    // For each selected day, pick a time slot
    foreach ($selected_days as $day) {
        if ($sessions_scheduled >= $sessions_needed) break;
        
        // Sort available slots for this day by preference
        $day_slots = $available_slots_by_day[$day];
        
        // Randomize selection for variety when regenerating
        shuffle($day_slots);
        
        // Pick the first available slot
        if (!empty($day_slots)) {
            $selected_slot = $day_slots[0];
            
            $schedules[] = [
                'room_id' => $selected_slot['room_id'],
                'day' => $selected_slot['day'],
                'start_time' => $selected_slot['start_time'],
                'end_time' => $selected_slot['end_time']
            ];
            
            // Add to tracking arrays to prevent future conflicts
            $instructor_schedules[] = [
                'day' => $selected_slot['day'],
                'start_time' => $selected_slot['start_time'],
                'end_time' => $selected_slot['end_time']
            ];
            
            $all_schedules[] = [
                'room_id' => $selected_slot['room_id'],
                'day' => $selected_slot['day'],
                'start_time' => $selected_slot['start_time'],
                'end_time' => $selected_slot['end_time']
            ];
            
            $sessions_scheduled++;
        }
    }
    
    if ($sessions_scheduled < $sessions_needed) {
        return [
            'success' => false,
            'message' => "Could not schedule all {$type} sessions. Only {$sessions_scheduled}/{$sessions_needed} sessions scheduled."
        ];
    }
    
    return ['success' => true, 'schedules' => $schedules];
}

/**
 * Get all existing schedules from database for conflict checking
 */
function getAllExistingSchedules($conn) {
    $stmt = $conn->query("
        SELECT room_id, day, start_time, end_time 
        FROM schedules 
        ORDER BY day, start_time
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get available rooms by type
 */
function getAvailableRooms($conn, $type) {
    $stmt = $conn->prepare("SELECT * FROM rooms WHERE room_type = ? ORDER BY capacity DESC");
    $stmt->execute([$type]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get instructor's existing schedules
 */
function getInstructorSchedules($conn, $instructor_id) {
    $stmt = $conn->prepare("
        SELECT s.day, s.start_time, s.end_time 
        FROM schedules s
        JOIN faculty_load fl ON s.load_id = fl.load_id
        WHERE fl.instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if room is available (NO CONFLICTS - strictly one class per room per time slot)
 */
function isRoomAvailable($all_schedules, $room_id, $day, $start_time, $end_time) {
    foreach ($all_schedules as $schedule) {
        if ($schedule['room_id'] == $room_id && $schedule['day'] === $day) {
            // Check for time overlap
            if (!($end_time <= $schedule['start_time'] || $start_time >= $schedule['end_time'])) {
                return false; // Room is occupied
            }
        }
    }
    return true;
}

/**
 * Check if instructor is available
 */
function isInstructorAvailable($schedules, $day, $start_time, $end_time) {
    foreach ($schedules as $schedule) {
        if ($schedule['day'] === $day) {
            // Check for time overlap
            if (!($end_time <= $schedule['start_time'] || $start_time >= $schedule['end_time'])) {
                return false; // Instructor is teaching another class
            }
        }
    }
    return true;
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

// Fetch all faculty loads with schedule info
$faculty_loads = $conn->query("
    SELECT 
        fl.load_id,
        fl.status,
        i.instructor_id,
        u.full_name as instructor_name,
        sub.subject_code,
        sub.subject_name,
        sub.lecture_hours,
        sub.lab_hours,
        sub.units,
        c.course_name,
        curr.year_level,
        so.school_year,
        so.semester,
        (SELECT COUNT(*) FROM schedules s WHERE s.load_id = fl.load_id) as schedule_count
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

// Fetch rooms for display
$rooms = $conn->query("SELECT * FROM rooms ORDER BY room_type, room_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all schedules with details
$all_schedules = $conn->query("
    SELECT 
        s.schedule_id,
        s.day,
        s.start_time,
        s.end_time,
        r.room_name,
        r.room_type,
        fl.load_id,
        u.full_name as instructor_name,
        sub.subject_code,
        sub.subject_name
    FROM schedules s
    JOIN rooms r ON s.room_id = r.room_id
    JOIN faculty_load fl ON s.load_id = fl.load_id
    JOIN instructors i ON fl.instructor_id = i.instructor_id
    JOIN users u ON i.user_id = u.user_id
    JOIN subject_offerings so ON fl.offering_id = so.offering_id
    JOIN curriculum_subjects cs ON so.curriculum_subject_id = cs.id
    JOIN subjects sub ON cs.subject_id = sub.subject_id
    ORDER BY 
        FIELD(s.day, 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'),
        s.start_time
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
            'total_lecture_hours' => 0,
            'total_lab_hours' => 0,
            'scheduled_count' => 0
        ];
    }
    $grouped_loads[$instructor_name]['loads'][] = $load;
    $grouped_loads[$instructor_name]['total_units'] += $load['units'];
    $grouped_loads[$instructor_name]['total_lecture_hours'] += $load['lecture_hours'];
    $grouped_loads[$instructor_name]['total_lab_hours'] += $load['lab_hours'];
    if ($load['schedule_count'] > 0) {
        $grouped_loads[$instructor_name]['scheduled_count']++;
    }
}

// Statistics
$total_loads = count($faculty_loads);
$approved_loads = 0;
$scheduled_loads = 0;
$pending_schedules = 0;

foreach ($faculty_loads as $load) {
    if ($load['status'] === 'approved') {
        $approved_loads++;
        if ($load['schedule_count'] > 0) {
            $scheduled_loads++;
        } else {
            $pending_schedules++;
        }
    }
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
    <title>Schedule Generator | Class Scheduling System</title>
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
            grid-template-columns: repeat(4, 1fr);
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
        
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 2px;
            background: #e2e8f0;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .schedule-header {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: 600;
        }
        
        .schedule-time {
            background: #f8fafc;
            padding: 12px;
            text-align: center;
            font-weight: 500;
            color: #475569;
        }
        
        .schedule-cell {
            background: white;
            padding: 12px;
            min-height: 80px;
        }
        
        .schedule-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 4px;
        }
        
        .schedule-item.lab {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        .btn-generate {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 30px;
            font-weight: 600;
            transition: transform 0.2s;
            cursor: pointer;
        }
        
        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-draft { background: #e2e8f0; color: #475569; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        .badge-scheduled { background: #dcfce7; color: #166534; }
        .badge-pending-schedule { background: #fef3c7; color: #92400e; }
        
        .role-badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-admin { background: #3b82f6; color: white; }
        .badge-superadmin { background: #8b5cf6; color: white; }
        
        .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
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
        
        .loads-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .loads-table th {
            background: #f8fafc;
            padding: 12px 16px;
            font-size: 12px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
        }
        
        .loads-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            margin: 2px;
            transition: all 0.2s;
        }
        
        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-regenerate { background: #3b82f6; color: white; }
        .btn-clear { background: #ef4444; color: white; }
        .btn-view { background: #10b981; color: white; }
        .btn-generate-small { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        
        .modal-content { border-radius: 20px; border: none; }
        .modal-header { border-bottom: 1px solid #e2e8f0; padding: 20px 24px; }
        
        .algorithm-info {
            background: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        .modal-lg { max-width: 800px; }
        
        #scheduleModalBody .table { margin-bottom: 0; }
        
        #scheduleModalBody .badge {
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
        }
        
        #scheduleModalBody .btn-sm {
            padding: 6px 16px;
            font-size: 13px;
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .page-wrapper { padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .schedule-grid { font-size: 12px; }
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
                                        <i class="fas fa-calendar-alt mr-2" style="color: #667eea;"></i>
                                        Schedule Generator
                                        <span class="role-badge <?php echo $user_role === 'super_admin' ? 'badge-superadmin' : 'badge-admin'; ?>">
                                            <?php echo $user_role === 'super_admin' ? 'Super Admin' : 'Admin'; ?>
                                        </span>
                                    </h1>
                                    <p class="mb-0" style="color: #64748b;">
                                        <i class="fas fa-magic mr-1"></i> Automatic generation of lecture and laboratory schedules
                                    </p>
                                </div>
                                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="generate_schedule">
                                        <button type="submit" class="btn-generate">
                                            <i class="fas fa-magic mr-2"></i>Generate All Schedules
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Algorithm Info -->
                        <div class="algorithm-info">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle fa-2x mr-3" style="color: #3b82f6;"></i>
                                <div>
                                    <h5 style="margin-bottom: 5px; font-weight: 600;">Scheduling Algorithm Rules:</h5>
                                    <p class="mb-0" style="color: #475569;">
                                        <i class="fas fa-check-circle text-success mr-1"></i> 3 sessions per week per subject (1 hour each) <br>
                                        <i class="fas fa-check-circle text-success mr-1"></i> Schedules between 8:00 AM - 5:00 PM <br>
                                        <i class="fas fa-check-circle text-success mr-1"></i> No room conflicts (one class per room at a time) <br>
                                        <i class="fas fa-check-circle text-success mr-1"></i> No instructor conflicts (instructor can't teach two classes simultaneously) <br>
                                        <i class="fas fa-check-circle text-success mr-1"></i> Different days for each session of the same subject
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Alert Messages -->
                        <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="close" onclick="this.parentElement.style.display='none'">&times;</button>
                        </div>
                        <?php endif; ?>

                        <!-- Statistics -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #dbeafe; color: #3b82f6;">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stat-value"><?php echo $approved_loads; ?></div>
                                <div class="stat-label">Approved Loads</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #dcfce7; color: #22c55e;">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-value"><?php echo $scheduled_loads; ?></div>
                                <div class="stat-label">Scheduled Loads</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #fef3c7; color: #eab308;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-value"><?php echo $pending_schedules; ?></div>
                                <div class="stat-label">Pending Schedule</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon" style="background: #f3e8ff; color: #a855f7;">
                                    <i class="fas fa-door-open"></i>
                                </div>
                                <div class="stat-value"><?php echo count($rooms); ?></div>
                                <div class="stat-label">Available Rooms</div>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <ul class="nav nav-tabs" id="scheduleTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#facultyLoads">
                                    <i class="fas fa-list mr-1"></i>Faculty Loads
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#scheduleView">
                                    <i class="fas fa-calendar-week mr-1"></i>Weekly Schedule
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#roomsView">
                                    <i class="fas fa-building mr-1"></i>Room Utilization
                                </a>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content">
                            
                            <!-- Faculty Loads Tab -->
                            <div class="tab-pane fade show active" id="facultyLoads">
                                <div class="table-card">
                                    <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">
                                        <i class="fas fa-users mr-2" style="color: #667eea;"></i>
                                        Loads by Instructor
                                    </h3>
                                    
                                    <?php if (empty($grouped_loads)): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x mb-3" style="color: #cbd5e1;"></i>
                                            <h4>No Faculty Loads Found</h4>
                                            <p class="text-muted">Approved loads will appear here for scheduling.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($grouped_loads as $instructor_name => $group): ?>
                                        <?php 
                                            $name_parts = explode(' ', $instructor_name);
                                            $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                        ?>
                                        <div class="instructor-group">
                                            <div class="instructor-header" onclick="toggleGroup(this)">
                                                <div class="instructor-avatar"><?php echo $initials; ?></div>
                                                <div class="flex-grow-1">
                                                    <div style="font-weight: 700; color: #0f172a;">
                                                        <?php echo htmlspecialchars($instructor_name); ?>
                                                        <span class="badge bg-primary ml-2"><?php echo $group['total_units']; ?> units</span>
                                                    </div>
                                                    <div style="font-size: 13px; color: #64748b; margin-top: 4px;">
                                                        <i class="fas fa-chalkboard mr-1"></i><?php echo $group['total_lecture_hours']; ?> lecture hrs
                                                        <i class="fas fa-flask ml-3 mr-1"></i><?php echo $group['total_lab_hours']; ?> lab hrs
                                                        <i class="fas fa-calendar ml-3 mr-1"></i><?php echo $group['scheduled_count']; ?>/<?php echo count($group['loads']); ?> scheduled
                                                    </div>
                                                </div>
                                                <i class="fas fa-chevron-right expand-icon"></i>
                                            </div>
                                            <div class="loads-container" style="display: none;">
                                                <table class="loads-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Subject</th>
                                                            <th>Course/Year</th>
                                                            <th>Hours (Lec/Lab)</th>
                                                            <th>Units</th>
                                                            <th>Status</th>
                                                            <th>Schedule</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($group['loads'] as $load): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($load['subject_code']); ?></strong>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($load['subject_name']); ?></small>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($load['course_name']); ?>
                                                                <span class="badge bg-light text-dark ml-1">Y<?php echo $load['year_level']; ?></span>
                                                            </td>
                                                            <td>
                                                                <?php echo $load['lecture_hours']; ?>L / <?php echo $load['lab_hours']; ?>B
                                                            </td>
                                                            <td><?php echo $load['units']; ?></td>
                                                            <td>
                                                                <span class="badge-status badge-<?php echo $load['status']; ?>">
                                                                    <?php echo ucfirst($load['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($load['schedule_count'] > 0): ?>
                                                                    <span class="badge-status badge-scheduled">
                                                                        <i class="fas fa-check-circle mr-1"></i><?php echo $load['schedule_count']; ?> session(s)
                                                                    </span>
                                                                <?php elseif ($load['status'] === 'approved'): ?>
                                                                    <span class="badge-status badge-pending-schedule">
                                                                        <i class="fas fa-clock mr-1"></i>Not scheduled
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="text-muted">—</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($load['status'] === 'approved'): ?>
                                                                    <?php if ($load['schedule_count'] > 0): ?>
                                                                        <button class="btn-action btn-regenerate" onclick="regenerateSchedule(<?php echo $load['load_id']; ?>)">
                                                                            <i class="fas fa-sync-alt mr-1"></i>Regenerate
                                                                        </button>
                                                                        <button class="btn-action btn-clear" onclick="clearSchedule(<?php echo $load['load_id']; ?>)">
                                                                            <i class="fas fa-trash mr-1"></i>Clear
                                                                        </button>
                                                                        <button class="btn-action btn-view" onclick="viewSchedule(<?php echo $load['load_id']; ?>)">
                                                                            <i class="fas fa-eye mr-1"></i>View
                                                                        </button>
                                                                    <?php else: ?>
                                                                        <form method="POST" style="display: inline;">
                                                                            <input type="hidden" name="action" value="regenerate_schedule">
                                                                            <input type="hidden" name="load_id" value="<?php echo $load['load_id']; ?>">
                                                                            <button type="submit" class="btn-action btn-generate-small">
                                                                                <i class="fas fa-magic mr-1"></i>Generate Schedule
                                                                            </button>
                                                                        </form>
                                                                    <?php endif; ?>
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
                            
                            <!-- Weekly Schedule Tab -->
                            <div class="tab-pane fade" id="scheduleView">
                                <div class="table-card">
                                    <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">
                                        <i class="fas fa-calendar-alt mr-2" style="color: #667eea;"></i>
                                        Weekly Schedule Overview
                                    </h3>
                                    
                                    <div class="schedule-grid">
                                        <div class="schedule-header">Time</div>
                                        <div class="schedule-header">Monday</div>
                                        <div class="schedule-header">Tuesday</div>
                                        <div class="schedule-header">Wednesday</div>
                                        <div class="schedule-header">Thursday</div>
                                        <div class="schedule-header">Friday</div>
                                        
                                        <?php
                                        $times = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];
                                        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
                                        
                                        foreach ($times as $time):
                                            $time_start = $time . ':00';
                                            $time_end = sprintf('%02d:00:00', intval($time) + 1);
                                        ?>
                                        <div class="schedule-time"><?php echo $time; ?> - <?php echo sprintf('%02d:00', intval($time) + 1); ?></div>
                                        <?php foreach ($days as $day): ?>
                                        <div class="schedule-cell">
                                            <?php
                                            foreach ($all_schedules as $schedule) {
                                                if ($schedule['day'] === $day && 
                                                    substr($schedule['start_time'], 0, 5) === $time) {
                                                    $class = $schedule['room_type'] === 'laboratory' ? 'lab' : '';
                                                    echo '<div class="schedule-item ' . $class . '">';
                                                    echo '<strong>' . htmlspecialchars($schedule['subject_code']) . '</strong><br>';
                                                    echo '<small>' . htmlspecialchars($schedule['instructor_name']) . '</small><br>';
                                                    echo '<small><i class="fas fa-door-open mr-1"></i>' . htmlspecialchars($schedule['room_name']) . '</small>';
                                                    echo '</div>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Rooms View Tab -->
                            <div class="tab-pane fade" id="roomsView">
                                <div class="table-card">
                                    <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">
                                        <i class="fas fa-building mr-2" style="color: #667eea;"></i>
                                        Room Utilization
                                    </h3>
                                    
                                    <div class="row">
                                        <?php foreach ($rooms as $room): ?>
                                        <div class="col-md-6 mb-4">
                                            <div class="card">
                                                <div class="card-header" style="background: <?php echo $room['room_type'] === 'laboratory' ? '#fef3c7' : '#dbeafe'; ?>;">
                                                    <h5 class="mb-0">
                                                        <i class="fas fa-<?php echo $room['room_type'] === 'laboratory' ? 'flask' : 'chalkboard'; ?> mr-2"></i>
                                                        <?php echo htmlspecialchars($room['room_name']); ?>
                                                        <span class="badge bg-secondary ml-2">Cap: <?php echo $room['capacity']; ?></span>
                                                        <span class="badge bg-info ml-1"><?php echo ucfirst($room['room_type']); ?></span>
                                                    </h5>
                                                </div>
                                                <div class="card-body p-0">
                                                    <table class="table table-sm mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Day</th>
                                                                <th>Time</th>
                                                                <th>Subject</th>
                                                                <th>Instructor</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                            $room_schedules = array_filter($all_schedules, function($s) use ($room) {
                                                                return $s['room_name'] === $room['room_name'];
                                                            });
                                                            
                                                            if (empty($room_schedules)): ?>
                                                            <tr><td colspan="4" class="text-center text-muted py-3">No schedules</td></tr>
                                                            <?php else:
                                                                foreach ($room_schedules as $rs): ?>
                                                            <tr>
                                                                <td><?php echo $rs['day']; ?></td>
                                                                <td><?php echo substr($rs['start_time'], 0, 5) . '-' . substr($rs['end_time'], 0, 5); ?></td>
                                                                <td><?php echo htmlspecialchars($rs['subject_code']); ?></td>
                                                                <td><?php echo htmlspecialchars($rs['instructor_name']); ?></td>
                                                            </tr>
                                                            <?php endforeach;
                                                            endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
           
        </div>
    </div>
    
    <!-- View Schedule Modal -->
    <div class="modal fade" id="viewScheduleModal" tabindex="-1" role="dialog" aria-labelledby="viewScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewScheduleModalLabel">
                        <i class="fas fa-calendar-alt mr-2"></i>Schedule Details
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="scheduleModalBody">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">Loading schedule...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>
    
    <script>
    function toggleGroup(header) {
        const group = header.closest('.instructor-group');
        const container = group.querySelector('.loads-container');
        const icon = header.querySelector('.expand-icon');
        
        if (container.style.display === 'none') {
            container.style.display = 'block';
            icon.style.transform = 'rotate(90deg)';
        } else {
            container.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
        }
    }
    
    function regenerateSchedule(loadId) {
        if (confirm('Regenerate schedule? This will try to find better time slots or rooms.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'regenerate_schedule';
            
            const loadIdInput = document.createElement('input');
            loadIdInput.type = 'hidden';
            loadIdInput.name = 'load_id';
            loadIdInput.value = loadId;
            
            form.appendChild(actionInput);
            form.appendChild(loadIdInput);
            document.body.appendChild(form);
            
            form.submit();
        }
    }
    
    function clearSchedule(loadId) {
        if (confirm('Are you sure you want to clear the schedule for this load?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'clear_schedule';
            
            const loadIdInput = document.createElement('input');
            loadIdInput.type = 'hidden';
            loadIdInput.name = 'load_id';
            loadIdInput.value = loadId;
            
            form.appendChild(actionInput);
            form.appendChild(loadIdInput);
            document.body.appendChild(form);
            
            form.submit();
        }
    }
    
    function viewSchedule(loadId) {
        document.getElementById('scheduleModalBody').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading schedule...</p></div>';
        
        $('#viewScheduleModal').modal('show');
        
        fetch('get_schedule.php?load_id=' + loadId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                document.getElementById('scheduleModalBody').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('scheduleModalBody').innerHTML = 
                    '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Error loading schedule details. Please try again.</div>';
                console.error('Error:', error);
            });
    }
    
    window.regenerateSchedule = regenerateSchedule;
    window.clearSchedule = clearSchedule;
    
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