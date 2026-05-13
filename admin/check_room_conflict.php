<?php
session_start();
include('assets/inc/db.php');

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['hasConflict' => true, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
    $day = filter_input(INPUT_POST, 'day', FILTER_SANITIZE_STRING);
    $start_time = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
    $end_time = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);
    $schedule_id = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
    $exact_match = filter_input(INPUT_POST, 'exact_match', FILTER_VALIDATE_INT);
    
    if (!$room_id || !$day || !$start_time || !$end_time) {
        echo json_encode(['hasConflict' => false]);
        exit();
    }
    
    try {
        // Check for EXACT same time only
        $sql = "SELECT COUNT(*) as conflict_count, 
                       r.room_name,
                       s.start_time as existing_start,
                       s.end_time as existing_end
                FROM schedules s
                JOIN rooms r ON s.room_id = r.room_id
                WHERE s.room_id = :room_id 
                AND s.day = :day 
                AND s.start_time = :start_time 
                AND s.end_time = :end_time";
        
        $params = [
            ':room_id' => $room_id,
            ':day' => $day,
            ':start_time' => $start_time,
            ':end_time' => $end_time
        ];
        
        // Exclude current schedule if editing
        if ($schedule_id) {
            $sql .= " AND s.schedule_id != :schedule_id";
            $params[':schedule_id'] = $schedule_id;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['conflict_count'] > 0) {
            $message = "Room '{$result['room_name']}' already has an EXACT schedule on {$day} from " . 
                       date('h:i A', strtotime($result['existing_start'])) . " to " . 
                       date('h:i A', strtotime($result['existing_end'])) . 
                       "! Please choose a different time slot.";
            
            echo json_encode([
                'hasConflict' => true,
                'message' => $message
            ]);
        } else {
            echo json_encode(['hasConflict' => false]);
        }
    } catch (PDOException $e) {
        echo json_encode(['hasConflict' => true, 'message' => 'Database error occurred']);
    }
} else {
    echo json_encode(['hasConflict' => false]);
}
?>