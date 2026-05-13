<?php
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Get the role of the user from session
    $role = $_SESSION['role'];
    $user_id = $_SESSION['user_id'];
    $full_name = $_SESSION['full_name'] ?? 'User';

    // Clear specific session data based on role
    switch ($role) {
        case 'super_admin':
        case 'admin':
            // Clear admin-specific session data
            unset($_SESSION['admin_id']);
            unset($_SESSION['admin_name']);
            break;

        case 'faculty':
            // Clear faculty-specific session data
            unset($_SESSION['faculty_id']);
            unset($_SESSION['instructor_id']);
            unset($_SESSION['specialization']);
            break;

        default:
            // For any undefined role, clear all
            break;
    }

    // Log the logout activity (optional - you can create a login_logs table)
    // include('assets/inc/db.php');
    // $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, action, timestamp) VALUES (?, 'logout', NOW())");
    // $log_stmt->execute([$user_id]);

    // Clear all session data
    session_unset();
    
    // Destroy the session
    session_destroy();

    // Set a logout message in a new session
    session_start();
    $_SESSION['logout_message'] = "You have been successfully logged out.";
    $_SESSION['logout_type'] = "success";

    // Redirect to login page
    header("Location: index.php");
    exit;
} else {
    // If no user is logged in, redirect to login page
    header("Location: index.php");
    exit;
}
?>