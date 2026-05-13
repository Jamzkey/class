<?php
// Continue from previous code - this is the complete file

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ==================== SUBJECTS CRUD ====================
    if ($_POST['action'] === 'create_subject') {
        $subject_code = strtoupper(filter_input(INPUT_POST, 'subject_code', FILTER_SANITIZE_STRING));
        $subject_name = filter_input(INPUT_POST, 'subject_name', FILTER_SANITIZE_STRING);
        $lecture_hours = filter_input(INPUT_POST, 'lecture_hours', FILTER_VALIDATE_INT);
        $lab_hours = filter_input(INPUT_POST, 'lab_hours', FILTER_VALIDATE_INT);
        $units = filter_input(INPUT_POST, 'units', FILTER_VALIDATE_INT);
        
        try {
            $check = $conn->prepare("SELECT COUNT(*) FROM subjects WHERE subject_code = :subject_code");
            $check->execute([':subject_code' => $subject_code]);
            if ($check->fetchColumn() > 0) {
                $message = "Error: Subject code already exists!";
                $message_type = "danger";
            } else {
                $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, lecture_hours, lab_hours, units) VALUES (:subject_code, :subject_name, :lecture_hours, :lab_hours, :units)");
                $stmt->execute([':subject_code' => $subject_code, ':subject_name' => $subject_name, ':lecture_hours' => $lecture_hours, ':lab_hours' => $lab_hours, ':units' => $units]);
                $message = "Subject added successfully!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    elseif ($_POST['action'] === 'edit_subject') {
        $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
        $subject_code = strtoupper(filter_input(INPUT_POST, 'subject_code', FILTER_SANITIZE_STRING));
        $subject_name = filter_input(INPUT_POST, 'subject_name', FILTER_SANITIZE_STRING);
        $lecture_hours = filter_input(INPUT_POST, 'lecture_hours', FILTER_VALIDATE_INT);
        $lab_hours = filter_input(INPUT_POST, 'lab_hours', FILTER_VALIDATE_INT);
        $units = filter_input(INPUT_POST, 'units', FILTER_VALIDATE_INT);
        
        try {
            $check = $conn->prepare("SELECT COUNT(*) FROM subjects WHERE subject_code = :subject_code AND subject_id != :subject_id");
            $check->execute([':subject_code' => $subject_code, ':subject_id' => $subject_id]);
            if ($check->fetchColumn() > 0) {
                $message = "Error: Subject code already exists!";
                $message_type = "danger";
            } else {
                $stmt = $conn->prepare("UPDATE subjects SET subject_code=:subject_code, subject_name=:subject_name, lecture_hours=:lecture_hours, lab_hours=:lab_hours, units=:units WHERE subject_id=:subject_id");
                $stmt->execute([':subject_code' => $subject_code, ':subject_name' => $subject_name, ':lecture_hours' => $lecture_hours, ':lab_hours' => $lab_hours, ':units' => $units, ':subject_id' => $subject_id]);
                $message = "Subject updated!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    elseif ($_POST['action'] === 'delete_subject') {
        $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
        try {
            // Check if subject is used in any curriculum
            $check = $conn->prepare("
                SELECT c.curriculum_id, co.course_name, c.year_level, c.semester 
                FROM curriculum_subjects cs 
                JOIN curriculum c ON cs.curriculum_id = c.curriculum_id 
                JOIN courses co ON c.course_id = co.course_id 
                WHERE cs.subject_id = :subject_id
            ");
            $check->execute([':subject_id' => $subject_id]);
            $linkedCurriculums = $check->fetchAll();
            
            if (count($linkedCurriculums) > 0) {
                $message = "Cannot delete: Subject is linked to " . count($linkedCurriculums) . " curriculum(s)!<br><small>";
                foreach ($linkedCurriculums as $lc) {
                    $message .= "• " . htmlspecialchars($lc['course_name']) . " - Year " . $lc['year_level'] . " (" . $lc['semester'] . ")<br>";
                }
                $message .= "</small>";
                $message_type = "warning";
            } else {
                $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = :subject_id");
                $stmt->execute([':subject_id' => $subject_id]);
                $message = "Subject deleted successfully!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // ==================== COURSES CRUD ====================
    elseif ($_POST['action'] === 'create_course') {
        $course_name = filter_input(INPUT_POST, 'course_name', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        try {
            $stmt = $conn->prepare("INSERT INTO courses (course_name, description) VALUES (:course_name, :description)");
            $stmt->execute([':course_name' => $course_name, ':description' => $description]);
            $message = "Course added!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    elseif ($_POST['action'] === 'edit_course') {
        $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
        $course_name = filter_input(INPUT_POST, 'course_name', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        try {
            $stmt = $conn->prepare("UPDATE courses SET course_name = :course_name, description = :description WHERE course_id = :course_id");
            $stmt->execute([':course_name' => $course_name, ':description' => $description, ':course_id' => $course_id]);
            $message = "Course updated successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    elseif ($_POST['action'] === 'delete_course') {
        $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
        try {
            // Check if course has curriculum
            $check = $conn->prepare("
                SELECT c.curriculum_id, c.year_level, c.semester,
                       (SELECT COUNT(*) FROM curriculum_subjects WHERE curriculum_id = c.curriculum_id) as subject_count
                FROM curriculum c 
                WHERE c.course_id = :course_id
            ");
            $check->execute([':course_id' => $course_id]);
            $linkedCurriculums = $check->fetchAll();
            
            if (count($linkedCurriculums) > 0) {
                $message = "Cannot delete: Course has " . count($linkedCurriculums) . " curriculum(s) linked!<br><small>";
                foreach ($linkedCurriculums as $lc) {
                    $message .= "• Year " . $lc['year_level'] . " (" . $lc['semester'] . ") - " . $lc['subject_count'] . " subjects<br>";
                }
                $message .= "</small>";
                $message_type = "warning";
            } else {
                $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = :course_id");
                $stmt->execute([':course_id' => $course_id]);
                $message = "Course deleted!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // ==================== CURRICULUM CRUD ====================
    elseif ($_POST['action'] === 'create_curriculum') {
        $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
        $year_level = filter_input(INPUT_POST, 'year_level', FILTER_VALIDATE_INT);
        $semester = filter_input(INPUT_POST, 'semester', FILTER_SANITIZE_STRING);
        try {
            // Check if curriculum already exists
            $check = $conn->prepare("SELECT COUNT(*) FROM curriculum WHERE course_id = :course_id AND year_level = :year_level AND semester = :semester");
            $check->execute([':course_id' => $course_id, ':year_level' => $year_level, ':semester' => $semester]);
            if ($check->fetchColumn() > 0) {
                $message = "Curriculum already exists for this course, year, and semester!";
                $message_type = "danger";
            } else {
                $stmt = $conn->prepare("INSERT INTO curriculum (course_id, year_level, semester) VALUES (:course_id, :year_level, :semester)");
                $stmt->execute([':course_id' => $course_id, ':year_level' => $year_level, ':semester' => $semester]);
                $message = "Curriculum created!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    elseif ($_POST['action'] === 'delete_curriculum') {
        $curriculum_id = filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);
        try {
            // Start transaction for cascade delete
            $conn->beginTransaction();
            
            // Get all curriculum_subject IDs for this curriculum
            $getCsIds = $conn->prepare("SELECT id FROM curriculum_subjects WHERE curriculum_id = :curriculum_id");
            $getCsIds->execute([':curriculum_id' => $curriculum_id]);
            $csIds = $getCsIds->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($csIds)) {
                // Delete offerings linked to these curriculum_subjects
                $placeholders = implode(',', array_fill(0, count($csIds), '?'));
                $delOfferings = $conn->prepare("DELETE FROM subject_offerings WHERE curriculum_subject_id IN ($placeholders)");
                $delOfferings->execute($csIds);
                
                // Delete curriculum_subjects
                $delCs = $conn->prepare("DELETE FROM curriculum_subjects WHERE curriculum_id = :curriculum_id");
                $delCs->execute([':curriculum_id' => $curriculum_id]);
            }
            
            // Delete curriculum
            $stmt = $conn->prepare("DELETE FROM curriculum WHERE curriculum_id = :curriculum_id");
            $stmt->execute([':curriculum_id' => $curriculum_id]);
            
            $conn->commit();
            $message = "Curriculum and all associated subjects & offerings deleted!";
            $message_type = "success";
        } catch (PDOException $e) {
            $conn->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    elseif ($_POST['action'] === 'add_to_curriculum') {
        $curriculum_id = filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);
        $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
        try {
            $check = $conn->prepare("SELECT COUNT(*) FROM curriculum_subjects WHERE curriculum_id=:cid AND subject_id=:sid");
            $check->execute([':cid' => $curriculum_id, ':sid' => $subject_id]);
            if ($check->fetchColumn() > 0) {
                $message = "Subject already in curriculum!";
                $message_type = "danger";
            } else {
                $stmt = $conn->prepare("INSERT INTO curriculum_subjects (curriculum_id, subject_id) VALUES (:cid, :sid)");
                $stmt->execute([':cid' => $curriculum_id, ':sid' => $subject_id]);
                $message = "Subject added to curriculum!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    elseif ($_POST['action'] === 'remove_from_curriculum') {
        $curriculum_subject_id = filter_input(INPUT_POST, 'curriculum_subject_id', FILTER_VALIDATE_INT);
        try {
            // Check if this curriculum subject has offerings
            $check = $conn->prepare("
                SELECT so.offering_id, so.school_year, so.semester 
                FROM subject_offerings so 
                WHERE so.curriculum_subject_id = :id
            ");
            $check->execute([':id' => $curriculum_subject_id]);
            $offerings = $check->fetchAll();
            
            if (count($offerings) > 0) {
                $message = "Cannot remove: Subject has " . count($offerings) . " active offering(s)!<br><small>";
                foreach ($offerings as $off) {
                    $message .= "• " . $off['school_year'] . " (" . $off['semester'] . ")<br>";
                }
                $message .= "</small>";
                $message_type = "warning";
            } else {
                $stmt = $conn->prepare("DELETE FROM curriculum_subjects WHERE id=:id");
                $stmt->execute([':id' => $curriculum_subject_id]);
                $message = "Removed from curriculum!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    // ==================== SUBJECT OFFERINGS CRUD ====================
    elseif ($_POST['action'] === 'create_offering') {
        $curriculum_subject_id = filter_input(INPUT_POST, 'curriculum_subject_id', FILTER_VALIDATE_INT);
        $school_year = filter_input(INPUT_POST, 'school_year', FILTER_SANITIZE_STRING);
        $semester = filter_input(INPUT_POST, 'semester', FILTER_SANITIZE_STRING);
        try {
            $check = $conn->prepare("SELECT COUNT(*) FROM subject_offerings WHERE curriculum_subject_id=:csid AND school_year=:sy AND semester=:sem");
            $check->execute([':csid' => $curriculum_subject_id, ':sy' => $school_year, ':sem' => $semester]);
            if ($check->fetchColumn() > 0) {
                $message = "Offering already exists!";
                $message_type = "danger";
            } else {
                $stmt = $conn->prepare("INSERT INTO subject_offerings (curriculum_subject_id, school_year, semester) VALUES (:csid, :sy, :sem)");
                $stmt->execute([':csid' => $curriculum_subject_id, ':sy' => $school_year, ':sem' => $semester]);
                $message = "Offering created!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    elseif ($_POST['action'] === 'edit_offering') {
        $offering_id = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
        $curriculum_subject_id = filter_input(INPUT_POST, 'curriculum_subject_id', FILTER_VALIDATE_INT);
        $school_year = filter_input(INPUT_POST, 'school_year', FILTER_SANITIZE_STRING);
        $semester = filter_input(INPUT_POST, 'semester', FILTER_SANITIZE_STRING);
        try {
            $check = $conn->prepare("SELECT COUNT(*) FROM subject_offerings WHERE curriculum_subject_id=:csid AND school_year=:sy AND semester=:sem AND offering_id!=:oid");
            $check->execute([':csid' => $curriculum_subject_id, ':sy' => $school_year, ':sem' => $semester, ':oid' => $offering_id]);
            if ($check->fetchColumn() > 0) {
                $message = "Offering already exists!";
                $message_type = "danger";
            } else {
                $stmt = $conn->prepare("UPDATE subject_offerings SET curriculum_subject_id=:csid, school_year=:sy, semester=:sem WHERE offering_id=:oid");
                $stmt->execute([':csid' => $curriculum_subject_id, ':sy' => $school_year, ':sem' => $semester, ':oid' => $offering_id]);
                $message = "Offering updated!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    elseif ($_POST['action'] === 'delete_offering') {
        $offering_id = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
        try {
            // Check if offering has faculty loads
            $check = $conn->prepare("
                SELECT fl.load_id, u.full_name 
                FROM faculty_load fl 
                JOIN instructors i ON fl.instructor_id = i.instructor_id 
                JOIN users u ON i.user_id = u.user_id 
                WHERE fl.offering_id = :oid
            ");
            $check->execute([':oid' => $offering_id]);
            $facultyLoads = $check->fetchAll();
            
            if (count($facultyLoads) > 0) {
                $message = "Cannot delete: Has " . count($facultyLoads) . " faculty load(s) assigned!<br><small>";
                foreach ($facultyLoads as $fl) {
                    $message .= "• Assigned to: " . htmlspecialchars($fl['full_name']) . "<br>";
                }
                $message .= "</small>";
                $message_type = "warning";
            } else {
                $stmt = $conn->prepare("DELETE FROM subject_offerings WHERE offering_id=:oid");
                $stmt->execute([':oid' => $offering_id]);
                $message = "Offering deleted!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    elseif ($_POST['action'] === 'bulk_create_offerings') {
        $curriculum_id = filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);
        $school_year = filter_input(INPUT_POST, 'school_year', FILTER_SANITIZE_STRING);
        $semester = filter_input(INPUT_POST, 'semester', FILTER_SANITIZE_STRING);
        try {
            $stmt = $conn->prepare("SELECT id FROM curriculum_subjects WHERE curriculum_id=:cid");
            $stmt->execute([':cid' => $curriculum_id]);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $success = 0; $skip = 0;
            foreach ($subjects as $cs) {
                $check = $conn->prepare("SELECT COUNT(*) FROM subject_offerings WHERE curriculum_subject_id=:id AND school_year=:sy AND semester=:sem");
                $check->execute([':id' => $cs['id'], ':sy' => $school_year, ':sem' => $semester]);
                if ($check->fetchColumn() == 0) {
                    $ins = $conn->prepare("INSERT INTO subject_offerings (curriculum_subject_id, school_year, semester) VALUES (:id, :sy, :sem)");
                    $ins->execute([':id' => $cs['id'], ':sy' => $school_year, ':sem' => $semester]);
                    $success++;
                } else { $skip++; }
            }
            $message = "Created: $success, Skipped: $skip";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Fetch data with counts for dashboard stats
$stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$full_name = $user['full_name'] ?? 'Administrator';

$courses = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM curriculum WHERE course_id = c.course_id) as curriculum_count FROM courses c ORDER BY c.course_name")->fetchAll();
$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_code")->fetchAll();
$curriculums = $conn->query("SELECT c.*, co.course_name, (SELECT COUNT(*) FROM curriculum_subjects WHERE curriculum_id = c.curriculum_id) as subject_count FROM curriculum c JOIN courses co ON c.course_id=co.course_id ORDER BY co.course_name, c.year_level")->fetchAll();

$selected_curriculum_id = isset($_GET['curriculum_id']) ? $_GET['curriculum_id'] : ($curriculums[0]['curriculum_id'] ?? 0);
$curriculum_subjects = [];
if ($selected_curriculum_id) {
    $stmt = $conn->prepare("SELECT cs.id as curriculum_subject_id, s.* FROM curriculum_subjects cs JOIN subjects s ON cs.subject_id=s.subject_id WHERE cs.curriculum_id=:cid ORDER BY s.subject_code");
    $stmt->execute([':cid' => $selected_curriculum_id]);
    $curriculum_subjects = $stmt->fetchAll();
}

$offerings = $conn->query("
    SELECT so.*, s.subject_code, s.subject_name, s.units, c.course_name, curr.year_level,
           (SELECT COUNT(*) FROM faculty_load WHERE offering_id=so.offering_id) as faculty_count
    FROM subject_offerings so
    JOIN curriculum_subjects cs ON so.curriculum_subject_id=cs.id
    JOIN subjects s ON cs.subject_id=s.subject_id
    JOIN curriculum curr ON cs.curriculum_id=curr.curriculum_id
    JOIN courses c ON curr.course_id=c.course_id
    ORDER BY so.school_year DESC, so.semester DESC
")->fetchAll();

$curriculum_subjects_list = $conn->query("
    SELECT cs.id, s.subject_code, s.subject_name, c.course_name, curr.year_level
    FROM curriculum_subjects cs
    JOIN subjects s ON cs.subject_id=s.subject_id
    JOIN curriculum curr ON cs.curriculum_id=curr.curriculum_id
    JOIN courses c ON curr.course_id=c.course_id
    ORDER BY c.course_name, curr.year_level, s.subject_code
")->fetchAll();

// Get statistics
$totalSubjects = count($subjects);
$totalCurriculums = count($curriculums);
$totalOfferings = count($offerings);
$totalCourses = count($courses);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Subjects & Offerings Management | Class Scheduling System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/aq.png">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f4f9; }
        .page-wrapper { padding: 24px 32px; }
        
        /* Modern Header */
        .page-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 24px; padding: 28px 32px; margin-bottom: 28px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .page-title { font-size: 28px; font-weight: 800; color: white; margin-bottom: 8px; letter-spacing: -0.5px; }
        .page-subtitle { color: rgba(255,255,255,0.9); font-size: 14px; }
        
        /* Stats Cards */
        .stat-card { background: white; border-radius: 20px; padding: 20px; text-align: center; transition: all 0.3s; border: 1px solid #e2e8f0; margin-bottom: 24px; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .stat-number { font-size: 32px; font-weight: 800; color: #1e293b; }
        .stat-label { font-size: 13px; color: #64748b; margin-top: 8px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-icon { font-size: 32px; opacity: 0.2; position: absolute; right: 20px; top: 20px; }
        
        .section-title { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 3px solid #667eea; display: inline-block; }
        .table-card { background: white; border-radius: 24px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 32px; transition: all 0.3s ease; }
        .table-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .table th { font-weight: 700; color: #475569; background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; position: sticky; top: 0; z-index: 10; }
        .table td { padding: 14px 16px; vertical-align: middle; font-size: 14px; }
        
        /* Scrollable table body */
        .table-scroll { max-height: 350px; overflow-y: auto; border-radius: 12px; }
        .table-scroll::-webkit-scrollbar { width: 8px; }
        .table-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .table-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-1st { background: #dbeafe; color: #1e40af; }
        .badge-2nd { background: #f3e8ff; color: #6b21a8; }
        .btn-action { padding: 6px 12px; border-radius: 10px; font-size: 12px; margin: 2px; border: none; cursor: pointer; transition: all 0.2s; font-weight: 500; }
        .btn-edit { background: #10b981; color: white; }
        .btn-edit:hover { background: #059669; transform: translateY(-1px); }
        .btn-delete { background: #ef4444; color: white; }
        .btn-delete:hover { background: #dc2626; transform: translateY(-1px); }
        .btn-view { background: #3b82f6; color: white; }
        .btn-view:hover { background: #2563eb; transform: translateY(-1px); }
        .btn-primary { background: linear-gradient(135deg, #3b82f6, #2563eb); border: none; padding: 8px 20px; border-radius: 12px; font-weight: 600; }
        .btn-success { background: linear-gradient(135deg, #22c55e, #16a34a); border: none; padding: 8px 20px; border-radius: 12px; font-weight: 600; }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #d97706); border: none; color: white; padding: 8px 20px; border-radius: 12px; font-weight: 600; }
        .btn-info { background: linear-gradient(135deg, #06b6d4, #0891b2); border: none; color: white; padding: 8px 20px; border-radius: 12px; font-weight: 600; }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); border: none; padding: 8px 20px; border-radius: 12px; font-weight: 600; }
        .modal-content { border-radius: 24px; border: none; }
        .modal-header { border-bottom: 1px solid #e2e8f0; padding: 20px 24px; background: #f8fafc; border-radius: 24px 24px 0 0; }
        .modal-title { font-weight: 700; color: #1e293b; }
        .form-control, .form-select { border-radius: 12px; border: 1px solid #cbd5e1; padding: 10px 14px; font-size: 14px; transition: all 0.2s; }
        .form-control:focus, .form-select:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .curriculum-selector { background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 20px; padding: 20px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .alert { border-radius: 16px; border: none; padding: 15px 20px; margin-bottom: 20px; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 20px; }
        .empty-state h4 { color: #64748b; font-weight: 600; }
        
        /* Course description preview */
        .course-description { max-width: 300px; }
        .description-preview { color: #64748b; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        /* Table footer with count */
        .table-footer { padding: 12px 16px; background: #f8fafc; border-radius: 0 0 12px 12px; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .table-card { animation: fadeInUp 0.4s ease-out; }
        
        @media (max-width: 768px) { 
            .page-wrapper { padding: 16px; }
            .stat-number { font-size: 24px; }
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
                    
                    <div class="page-header">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <h1 class="page-title"><i class="fas fa-book-open mr-3"></i>Subjects & Offerings Management</h1>
                                <p class="page-subtitle mb-0">Manage subjects, curriculum prospectus,and subject offerings</p>
                            </div>
                            <div class="col-md-5 text-md-right mt-3 mt-md-0">
                                <div class="d-flex justify-content-md-end" style="gap: 12px;">
                                    <button class="btn btn-primary" data-toggle="modal" data-target="#addCourseModal"><i class="fas fa-graduation-cap mr-2"></i>Add Course</button>
                                    <button class="btn btn-success" data-toggle="modal" data-target="#addSubjectModal"><i class="fas fa-book mr-2"></i>Add Subject</button>
                                    <button class="btn btn-warning" data-toggle="modal" data-target="#createCurriculumModal"><i class="fas fa-sitemap mr-2"></i>New Curriculum</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card position-relative">
                                <div class="stat-number"><?php echo $totalCourses; ?></div>
                                <div class="stat-label">Total Courses</div>
                                <i class="fas fa-graduation-cap stat-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card position-relative">
                                <div class="stat-number"><?php echo $totalSubjects; ?></div>
                                <div class="stat-label">Total Subjects</div>
                                <i class="fas fa-book stat-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card position-relative">
                                <div class="stat-number"><?php echo $totalCurriculums; ?></div>
                                <div class="stat-label">Curriculums</div>
                                <i class="fas fa-sitemap stat-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card position-relative">
                                <div class="stat-number"><?php echo $totalOfferings; ?></div>
                                <div class="stat-label">Active Offerings</div>
                                <i class="fas fa-calendar-alt stat-icon"></i>
                            </div>
                        </div>
                    </div>

                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm" role="alert">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-circle' : 'info-circle'); ?> mr-2"></i>
                        <?php echo $message; ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                    <?php endif; ?>

                    <!-- ==================== SECTION 0: COURSES ==================== -->
                    <div class="table-card">
                        <div class="table-header">
                            <h3 class="section-title"><i class="fas fa-graduation-cap mr-2" style="color: #8b5cf6;"></i>Course Management</h3>
                            <div class="search-box">
                                <input type="text" id="searchCourse" class="form-control" placeholder="🔍 Search courses..." style="min-width:280px; border-radius: 40px; padding-left: 40px; background-image: url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%239ca3af\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Ccircle cx=\'11\' cy=\'11\' r=\'8\'%3E%3C/circle%3E%3Cline x1=\'21\' y1=\'21\' x2=\'16.65\' y2=\'16.65\'%3E%3C/line%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: 12px center;">
                            </div>
                        </div>
                        <?php if (empty($courses)): ?>
                        <div class="empty-state">
                            <i class="fas fa-graduation-cap"></i>
                            <h4>No Courses Yet</h4>
                            <p class="text-muted">Click "Add Course" to create your first course.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-scroll">
                            <table class="table table-hover mb-0" id="coursesTable">
                                <thead>
                                    <tr>
                                        <th>Course Name</th>
                                        <th>Description</th>
                                        <th>Curriculums</th>
                                        <th style="width: 150px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $c): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($c['course_name']); ?></strong></td>
                                        <td class="course-description">
                                            <?php if (!empty($c['description'])): ?>
                                                <span class="description-preview" title="<?php echo htmlspecialchars($c['description']); ?>">
                                                    <?php echo htmlspecialchars(substr($c['description'], 0, 50)) . (strlen($c['description']) > 50 ? '...' : ''); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted font-italic">No description</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info text-white"><?php echo $c['curriculum_count']; ?> curriculum(s)</span>
                                        </td>
                                        <td>
                                            <button class="btn-action btn-view" onclick="viewCourse(<?php echo $c['course_id']; ?>, '<?php echo htmlspecialchars(addslashes($c['course_name'])); ?>', '<?php echo htmlspecialchars(addslashes($c['description'] ?? '')); ?>')" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action btn-edit" onclick="editCourse(<?php echo $c['course_id']; ?>, '<?php echo htmlspecialchars(addslashes($c['course_name'])); ?>', '<?php echo htmlspecialchars(addslashes($c['description'] ?? '')); ?>')" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirmDeleteCourse(event, <?php echo $c['course_id']; ?>, '<?php echo htmlspecialchars(addslashes($c['course_name'])); ?>', <?php echo $c['curriculum_count']; ?>)">
                                                <input type="hidden" name="action" value="delete_course">
                                                <input type="hidden" name="course_id" value="<?php echo $c['course_id']; ?>">
                                                <button type="submit" class="btn-action btn-delete" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-footer">
                            <i class="fas fa-list mr-1"></i> Showing <?php echo min(5, count($courses)); ?> of <?php echo count($courses); ?> courses
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- ==================== SECTION 1: SUBJECT DIRECTORY ==================== -->
                    <div class="table-card">
                        <div class="table-header">
                            <h3 class="section-title"><i class="fas fa-list mr-2" style="color: #3b82f6;"></i>Subject Directory</h3>
                            <div class="search-box">
                                <input type="text" id="searchSubject" class="form-control" placeholder="🔍 Search by code or name..." style="min-width:280px; border-radius: 40px; padding-left: 40px; background-image: url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%239ca3af\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Ccircle cx=\'11\' cy=\'11\' r=\'8\'%3E%3C/circle%3E%3Cline x1=\'21\' y1=\'21\' x2=\'16.65\' y2=\'16.65\'%3E%3C/line%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: 12px center;">
                            </div>
                        </div>
                        <?php if (empty($subjects)): ?>
                        <div class="empty-state">
                            <i class="fas fa-book-open"></i>
                            <h4>No Subjects Yet</h4>
                            <p class="text-muted">Click "Add Subject" to create your first subject.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-scroll">
                            <table class="table table-hover mb-0" id="subjectsTable">
                                <thead><tr><th>Code</th><th>Subject Name</th><th>Lec Hrs</th><th>Lab Hrs</th><th>Units</th><th style="width: 100px;">Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($subjects as $s): ?>
                                    <tr>
                                        <td><span class="font-weight-bold"><?php echo htmlspecialchars($s['subject_code']); ?></span></td>
                                        <td><?php echo htmlspecialchars($s['subject_name']); ?></td>
                                        <td><?php echo $s['lecture_hours']; ?>h</td>
                                        <td><?php echo $s['lab_hours']; ?>h</td>
                                        <td><span class="badge bg-light text-dark border"><?php echo $s['units']; ?> units</span></td>
                                        <td>
                                            <button class="btn-action btn-edit" onclick="editSubject(<?php echo $s['subject_id']; ?>, '<?php echo htmlspecialchars($s['subject_code']); ?>', '<?php echo htmlspecialchars(addslashes($s['subject_name'])); ?>', <?php echo $s['lecture_hours']; ?>, <?php echo $s['lab_hours']; ?>, <?php echo $s['units']; ?>)"><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ Delete this subject?');">
                                                <input type="hidden" name="action" value="delete_subject">
                                                <input type="hidden" name="subject_id" value="<?php echo $s['subject_id']; ?>">
                                                <button type="submit" class="btn-action btn-delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-footer">
                            <i class="fas fa-list mr-1"></i> Showing <?php echo min(5, count($subjects)); ?> of <?php echo count($subjects); ?> subjects
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- ==================== SECTION 2: CURRICULUM PROSPECTUS ==================== -->
                    <div class="table-card">
                        <div class="table-header">
                            <h3 class="section-title"><i class="fas fa-sitemap mr-2" style="color: #8b5cf6;"></i>Curriculum Prospectus</h3>
                            <div>
                                <button class="btn btn-danger btn-sm" data-toggle="modal" data-target="#deleteCurriculumModal" <?php echo empty($curriculums) ? 'disabled' : ''; ?>><i class="fas fa-trash-alt mr-1"></i>Delete Curriculum</button>
                            </div>
                        </div>
                        <div class="curriculum-selector">
                            <form method="GET" class="d-flex align-items-center flex-wrap" style="gap:15px;">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-graduation-cap mr-2 text-muted"></i>
                                    <select name="curriculum_id" class="form-select" style="width:380px;" onchange="this.form.submit()">
                                        <?php foreach ($curriculums as $cur): ?>
                                        <option value="<?php echo $cur['curriculum_id']; ?>" <?php echo $selected_curriculum_id == $cur['curriculum_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cur['course_name']); ?> - Year <?php echo $cur['year_level']; ?> (<?php echo $cur['semester']; ?> Semester) 
                                            (<?php echo $cur['subject_count']; ?> subjects)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="btn btn-info" data-toggle="modal" data-target="#addToCurriculumModal"><i class="fas fa-plus mr-1"></i>Add Subject to Curriculum</button>
                            </form>
                        </div>
                        <?php if (empty($curriculum_subjects)): ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <h4>No Subjects in Curriculum</h4>
                            <p class="text-muted">Click "Add Subject to Curriculum" to populate this curriculum.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-scroll">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Code</th><th>Subject Name</th><th>Lec</th><th>Lab</th><th>Units</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php foreach ($curriculum_subjects as $cs): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($cs['subject_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($cs['subject_name']); ?></td>
                                        <td><?php echo $cs['lecture_hours']; ?>h</td>
                                        <td><?php echo $cs['lab_hours']; ?>h</td>
                                        <td><?php echo $cs['units']; ?> units</td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('⚠️ Remove this subject from curriculum?');">
                                                <input type="hidden" name="action" value="remove_from_curriculum">
                                                <input type="hidden" name="curriculum_subject_id" value="<?php echo $cs['curriculum_subject_id']; ?>">
                                                <input type="hidden" name="curriculum_id" value="<?php echo $selected_curriculum_id; ?>">
                                                <button type="submit" class="btn-action btn-delete"><i class="fas fa-trash"></i> Remove</button>
                                            </form>
                                         </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-footer">
                            <i class="fas fa-list mr-1"></i> Showing <?php echo min(5, count($curriculum_subjects)); ?> of <?php echo count($curriculum_subjects); ?> subjects
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- ==================== SECTION 3: SUBJECT OFFERINGS ==================== -->
                    <div class="table-card">
                        <div class="table-header">
                            <h3 class="section-title"><i class="fas fa-calendar-alt mr-2" style="color: #06b6d4;"></i>Subject Offerings</h3>
                            <div>
                                <button class="btn btn-primary" data-toggle="modal" data-target="#addOfferingModal"><i class="fas fa-plus mr-1"></i>Add Offering</button>
                                <button class="btn btn-info" data-toggle="modal" data-target="#bulkCreateModal"><i class="fas fa-layer-group mr-1"></i>Bulk Create</button>
                            </div>
                        </div>
                        <?php if (empty($offerings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h4>No Offerings Yet</h4>
                            <p class="text-muted">Create subject offerings to start scheduling classes.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-scroll">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>School Year</th><th>Sem</th><th>Code</th><th>Subject</th><th>Course</th><th>Year</th><th>Faculty Count</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($offerings as $o): ?>
                                    <tr>
                                        <td><strong><?php echo $o['school_year']; ?></strong></td>
                                        <td><span class="badge badge-<?php echo $o['semester'] == '1st' ? '1st' : '2nd'; ?>"><?php echo $o['semester']; ?> Sem</span></td>
                                        <td><strong><?php echo htmlspecialchars($o['subject_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($o['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($o['course_name']); ?></td>
                                        <td>Year <?php echo $o['year_level']; ?></td>
                                        <td>
                                            <?php if ($o['faculty_count'] > 0): ?>
                                                <span class="badge bg-warning text-dark"><?php echo $o['faculty_count']; ?> assigned</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">0 assigned</span>
                                            <?php endif; ?>
                                         </td>
                                        <td>
                                            <button class="btn-action btn-edit" onclick="editOffering(<?php echo $o['offering_id']; ?>, <?php echo $o['curriculum_subject_id']; ?>, '<?php echo $o['school_year']; ?>', '<?php echo $o['semester']; ?>')"><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirmDeleteOffering(event, <?php echo $o['offering_id']; ?>, '<?php echo htmlspecialchars($o['subject_code']); ?>', <?php echo $o['faculty_count']; ?>)">
                                                <input type="hidden" name="action" value="delete_offering">
                                                <input type="hidden" name="offering_id" value="<?php echo $o['offering_id']; ?>">
                                                <button type="submit" class="btn-action btn-delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                         </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-footer">
                            <i class="fas fa-list mr-1"></i> Showing <?php echo min(5, count($offerings)); ?> of <?php echo count($offerings); ?> offerings
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
      
    </div>
</div>

<!-- ==================== MODALS ==================== -->

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="create_course">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-graduation-cap mr-2"></i>Add New Course</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Course Name *</label><input type="text" name="course_name" class="form-control" placeholder="e.g., Bachelor of Science in Computer Science" required></div>
                <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3" placeholder="Optional description"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Add Course</button></div>
        </form>
    </div></div>
</div>

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="edit_course">
            <input type="hidden" name="course_id" id="edit_course_id">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Course</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Course Name *</label><input type="text" name="course_name" id="edit_course_name" class="form-control" required></div>
                <div class="form-group"><label>Description</label><textarea name="description" id="edit_description" class="form-control" rows="3"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Update Course</button></div>
        </form>
    </div></div>
</div>

<!-- View Course Modal -->
<div class="modal fade" id="viewCourseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="fas fa-eye mr-2"></i>Course Details</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body">
            <div class="text-center mb-4">
                <i class="fas fa-graduation-cap" style="font-size: 48px; color: #8b5cf6;"></i>
            </div>
            <table class="table table-bordered">
                <tr><th style="width: 120px;">Course Name</th><td id="view_course_name"></td></tr>
                <tr><th>Description</th><td id="view_description"></td></tr>
            </table>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></div>
    </div></div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="create_subject">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-book mr-2"></i>Add New Subject</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Subject Code *</label><input type="text" name="subject_code" class="form-control" placeholder="e.g., CS101" required></div>
                <div class="form-group"><label>Subject Name *</label><input type="text" name="subject_name" class="form-control" placeholder="e.g., Introduction to Computing" required></div>
                <div class="row">
                    <div class="col-4"><label>Lecture Hours</label><input type="number" name="lecture_hours" class="form-control" value="2" required></div>
                    <div class="col-4"><label>Lab Hours</label><input type="number" name="lab_hours" class="form-control" value="1" required></div>
                    <div class="col-4"><label>Units</label><input type="number" name="units" class="form-control" value="3" required></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Subject</button></div>
        </form>
    </div></div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="edit_subject">
            <input type="hidden" name="subject_id" id="edit_subject_id">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Subject</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Subject Code</label><input type="text" name="subject_code" id="edit_subject_code" class="form-control" required></div>
                <div class="form-group"><label>Subject Name</label><input type="text" name="subject_name" id="edit_subject_name" class="form-control" required></div>
                <div class="row">
                    <div class="col-4"><label>Lecture Hours</label><input type="number" name="lecture_hours" id="edit_lecture_hours" class="form-control" required></div>
                    <div class="col-4"><label>Lab Hours</label><input type="number" name="lab_hours" id="edit_lab_hours" class="form-control" required></div>
                    <div class="col-4"><label>Units</label><input type="number" name="units" id="edit_units" class="form-control" required></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Update Subject</button></div>
        </form>
    </div></div>
</div>

<!-- Create Curriculum Modal -->
<div class="modal fade" id="createCurriculumModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="create_curriculum">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-sitemap mr-2"></i>Create New Curriculum</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Course *</label><select name="course_id" class="form-select" required><option value="">Select Course</option><?php foreach ($courses as $c): ?><option value="<?php echo $c['course_id']; ?>"><?php echo htmlspecialchars($c['course_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Year Level *</label><select name="year_level" class="form-select" required><option value="1">1st Year</option><option value="2">2nd Year</option><option value="3">3rd Year</option><option value="4">4th Year</option></select></div>
                <div class="form-group"><label>Semester *</label><select name="semester" class="form-select" required><option value="1st">1st Semester</option><option value="2nd">2nd Semester</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning">Create Curriculum</button></div>
        </form>
    </div></div>
</div>

<!-- Delete Curriculum Modal -->
<div class="modal fade" id="deleteCurriculumModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="delete_curriculum">
            <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i>Delete Curriculum</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <p class="font-weight-bold">Are you sure you want to delete this curriculum?</p>
                <div class="form-group"><label>Select Curriculum to Delete</label><select name="curriculum_id" class="form-select" required>
                    <option value="">Select Curriculum</option>
                    <?php foreach ($curriculums as $cur): ?>
                    <option value="<?php echo $cur['curriculum_id']; ?>"><?php echo htmlspecialchars($cur['course_name']); ?> - Year <?php echo $cur['year_level']; ?> (<?php echo $cur['semester']; ?> Sem) - <?php echo $cur['subject_count']; ?> subjects</option>
                    <?php endforeach; ?>
                </select></div>
                <div class="alert alert-danger mt-3">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <strong>Warning:</strong> This will delete:
                    <ul class="mt-2 mb-0">
                        <li>All subjects in this curriculum</li>
                        <li>All offerings linked to those subjects</li>
                        <li>This action cannot be undone!</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Permanently Delete</button></div>
        </form>
    </div></div>
</div>

<!-- Add to Curriculum Modal -->
<div class="modal fade" id="addToCurriculumModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="add_to_curriculum">
            <input type="hidden" name="curriculum_id" value="<?php echo $selected_curriculum_id; ?>">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Add Subject to Curriculum</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Select Subject</label><select name="subject_id" class="form-select" required>
                    <option value="">Choose Subject</option>
                    <?php 
                    // Get subjects not already in curriculum
                    $availSubjects = $conn->prepare("SELECT s.* FROM subjects s WHERE s.subject_id NOT IN (SELECT subject_id FROM curriculum_subjects WHERE curriculum_id = :cid)");
                    $availSubjects->execute([':cid' => $selected_curriculum_id]);
                    $available = $availSubjects->fetchAll();
                    foreach ($available as $s): ?>
                        <option value="<?php echo $s['subject_id']; ?>"><?php echo htmlspecialchars($s['subject_code']); ?> - <?php echo htmlspecialchars($s['subject_name']); ?></option>
                    <?php endforeach; ?>
                    <?php if(empty($available)): ?>
                        <option disabled>No available subjects to add</option>
                    <?php endif; ?>
                </select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-info">Add to Curriculum</button></div>
        </form>
    </div></div>
</div>

<!-- Add Offering Modal -->
<div class="modal fade" id="addOfferingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="create_offering">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-calendar-plus mr-2"></i>Add Subject Offering</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Curriculum Subject</label><select name="curriculum_subject_id" class="form-select" required><option value="">Select Subject from Curriculum</option><?php foreach ($curriculum_subjects_list as $cs): ?><option value="<?php echo $cs['id']; ?>"><?php echo htmlspecialchars($cs['subject_code']); ?> - <?php echo htmlspecialchars($cs['subject_name']); ?> (<?php echo htmlspecialchars($cs['course_name']); ?> Y<?php echo $cs['year_level']; ?>)</option><?php endforeach; ?></select></div>
                <div class="row">
                    <div class="col-6"><label>School Year</label><select name="school_year" class="form-select" required><?php $y=date('Y'); for($i=-1;$i<=2;$i++): $sy=($y+$i).'-'.($y+$i+1); ?><option value="<?php echo $sy; ?>" <?php echo $i==0?'selected':''; ?>><?php echo $sy; ?></option><?php endfor; ?></select></div>
                    <div class="col-6"><label>Semester</label><select name="semester" class="form-select" required><option value="1st">1st Semester</option><option value="2nd">2nd Semester</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Create Offering</button></div>
        </form>
    </div></div>
</div>

<!-- Edit Offering Modal -->
<div class="modal fade" id="editOfferingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="edit_offering">
            <input type="hidden" name="offering_id" id="edit_offering_id">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Offering</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Curriculum Subject</label><select name="curriculum_subject_id" id="edit_curriculum_subject_id" class="form-select" required><?php foreach ($curriculum_subjects_list as $cs): ?><option value="<?php echo $cs['id']; ?>"><?php echo htmlspecialchars($cs['subject_code']); ?> - <?php echo htmlspecialchars($cs['subject_name']); ?></option><?php endforeach; ?></select></div>
                <div class="row">
                    <div class="col-6"><label>School Year</label><select name="school_year" id="edit_school_year" class="form-select" required><?php $y=date('Y'); for($i=-1;$i<=2;$i++): $sy=($y+$i).'-'.($y+$i+1); ?><option value="<?php echo $sy; ?>"><?php echo $sy; ?></option><?php endfor; ?></select></div>
                    <div class="col-6"><label>Semester</label><select name="semester" id="edit_semester" class="form-select" required><option value="1st">1st Semester</option><option value="2nd">2nd Semester</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Update Offering</button></div>
        </form>
    </div></div>
</div>

<!-- Bulk Create Modal -->
<div class="modal fade" id="bulkCreateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="bulk_create_offerings">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-layer-group mr-2"></i>Bulk Create Offerings</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <p>Create offerings for all subjects in a curriculum at once.</p>
                <div class="form-group"><label>Select Curriculum</label><select name="curriculum_id" class="form-select" required><option value="">Choose Curriculum</option><?php foreach ($curriculums as $cur): ?><option value="<?php echo $cur['curriculum_id']; ?>"><?php echo htmlspecialchars($cur['course_name']); ?> Y<?php echo $cur['year_level']; ?> (<?php echo $cur['semester']; ?> Sem) - <?php echo $cur['subject_count']; ?> subjects</option><?php endforeach; ?></select></div>
                <div class="row">
                    <div class="col-6"><label>School Year</label><select name="school_year" class="form-select" required><?php $y=date('Y'); for($i=-1;$i<=2;$i++): $sy=($y+$i).'-'.($y+$i+1); ?><option value="<?php echo $sy; ?>"><?php echo $sy; ?></option><?php endfor; ?></select></div>
                    <div class="col-6"><label>Semester</label><select name="semester" class="form-select" required><option value="1st">1st Semester</option><option value="2nd">2nd Semester</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Bulk Create</button></div>
        </form>
    </div></div>
</div>

<script src="assets/js/vendor.min.js"></script>
<script src="assets/js/app.min.js"></script>
<script>
    // Search functionality for Courses
    document.getElementById('searchCourse')?.addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('#coursesTable tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
    
    // Search functionality for Subjects
    document.getElementById('searchSubject')?.addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('#subjectsTable tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
    
    // Delete confirmation with constraint message for Course
    function confirmDeleteCourse(event, courseId, courseName, curriculumCount) {
        event.preventDefault();
        const form = event.target;
        
        if (curriculumCount > 0) {
            alert('⚠️ Cannot delete course "' + courseName + '"!\n\nThis course has ' + curriculumCount + ' curriculum(s) linked to it.\nYou must delete all linked curriculums first.');
            return false;
        }
        
        if (confirm('⚠️ Delete course "' + courseName + '"?\n\nThis action cannot be undone.')) {
            form.submit();
        }
        return false;
    }
    
    // Delete confirmation with constraint message for Offering
    function confirmDeleteOffering(event, offeringId, subjectCode, facultyCount) {
        event.preventDefault();
        const form = event.target;
        
        if (facultyCount > 0) {
            alert('⚠️ Cannot delete offering for "' + subjectCode + '"!\n\nThis offering has ' + facultyCount + ' faculty load(s) assigned to it.\nYou must remove all faculty loads first.');
            return false;
        }
        
        if (confirm('⚠️ Delete offering for "' + subjectCode + '"?\n\nThis action cannot be undone.')) {
            form.submit();
        }
        return false;
    }
    
    // Course functions
    function viewCourse(id, name, description) {
        document.getElementById('view_course_name').textContent = name;
        document.getElementById('view_description').textContent = description || 'No description available';
        $('#viewCourseModal').modal('show');
    }
    
    function editCourse(id, name, description) {
        document.getElementById('edit_course_id').value = id;
        document.getElementById('edit_course_name').value = name;
        document.getElementById('edit_description').value = description || '';
        $('#editCourseModal').modal('show');
    }
    
    // Edit subject function
    function editSubject(id, code, name, lec, lab, units) {
        document.getElementById('edit_subject_id').value = id;
        document.getElementById('edit_subject_code').value = code;
        document.getElementById('edit_subject_name').value = name;
        document.getElementById('edit_lecture_hours').value = lec;
        document.getElementById('edit_lab_hours').value = lab;
        document.getElementById('edit_units').value = units;
        $('#editSubjectModal').modal('show');
    }
    
    // Edit offering function
    function editOffering(id, csid, sy, sem) {
        document.getElementById('edit_offering_id').value = id;
        document.getElementById('edit_curriculum_subject_id').value = csid;
        document.getElementById('edit_school_year').value = sy;
        document.getElementById('edit_semester').value = sem;
        $('#editOfferingModal').modal('show');
    }
    
    // Auto-hide alerts after 4 seconds
    setTimeout(() => {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 4000);
</script>
</body>
</html>