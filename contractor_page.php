<?php
session_start();
require_once "config.php";

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and is a contractor
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

// Verify user role
$role_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
if ($role_check) {
    $role_check->bind_param("i", $user_id);
    $role_check->execute();
    $role_result = $role_check->get_result();

    if ($role_result->num_rows === 0) {
        session_destroy();
        header('Location: login.php');
        exit();
    }

    $user_data = $role_result->fetch_assoc();
    if ($user_data['role'] !== 'contractor') {
        if ($user_data['role'] === 'farmer') {
            header('Location: farmer_page.php');
        } elseif ($user_data['role'] === 'company') {
            header('Location: company_page.php');
        } else {
            header('Location: login.php');
        }
        exit();
    }
    $role_check->close();
} else {
    die("Database error: " . $conn->error);
}

$success_message = '';
$error_message = '';

// Get user data
$user = [];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
    }
    $stmt->close();
}

/* ==================== FIX: RECREATE CONTRACTOR JOBS TABLE WITH CORRECT STRUCTURE ==================== */

// Drop and recreate contractor_jobs table with correct structure
$conn->query("DROP TABLE IF EXISTS contractor_jobs");
$conn->query("CREATE TABLE contractor_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contractor_id INT NOT NULL,
    farm_id INT NOT NULL,
    farm_name VARCHAR(255),
    farmer_name VARCHAR(255),
    crop_type VARCHAR(100),
    acres DECIMAL(10,2),
    payment_amount DECIMAL(10,2),
    location VARCHAR(255),
    distance_km INT,
    status ENUM('accepted', 'in-progress', 'completed', 'cancelled') DEFAULT 'accepted',
    accepted_date DATETIME,
    completed_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS contractor_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contractor_id INT NOT NULL,
    sender_name VARCHAR(255),
    sender_avatar VARCHAR(10),
    message_content TEXT,
    message_time VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS contractor_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contractor_id INT NOT NULL,
    reviewer_name VARCHAR(255),
    rating INT,
    review_content TEXT,
    review_date DATE,
    job_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS contractor_earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contractor_id INT NOT NULL,
    job_id INT,
    job_title VARCHAR(255),
    amount DECIMAL(10,2),
    payment_date DATE,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

/* ==================== PROFILE HANDLING ==================== */

// Handle Profile Update
if (isset($_POST['update_profile'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
    $specialization = mysqli_real_escape_string($conn, $_POST['specialization'] ?? '');
    $experience = intval($_POST['experience'] ?? 0);
    $bio = mysqli_real_escape_string($conn, $_POST['bio'] ?? '');
    
    // Check if email exists for another user
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    if ($checkEmail) {
        $checkEmail->bind_param("si", $email, $user_id);
        $checkEmail->execute();
        $result = $checkEmail->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = 'Email already exists for another user!';
        } else {
            // Add contractor-specific columns if they don't exist
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) AFTER email");
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(100) AFTER phone");
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS specialization VARCHAR(100) AFTER location");
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS experience INT DEFAULT 0 AFTER specialization");
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT AFTER experience");
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS rating DECIMAL(3,2) DEFAULT 0.0 AFTER bio");
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS jobs_completed INT DEFAULT 0 AFTER rating");
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS success_rate INT DEFAULT 0 AFTER jobs_completed");
            
            $update = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, location = ?, specialization = ?, experience = ?, bio = ? WHERE id = ?");
            if ($update) {
                $update->bind_param("sssssisi", $name, $email, $phone, $location, $specialization, $experience, $bio, $user_id);
                
                if ($update->execute()) {
                    $_SESSION['name'] = $name;
                    $_SESSION['email'] = $email;
                    $success_message = 'Profile updated successfully!';
                    
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                        $stmt->close();
                    }
                } else {
                    $error_message = 'Failed to update profile. Please try again.';
                }
                $update->close();
            }
        }
        $checkEmail->close();
    }
}

// Handle Password Change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'All password fields are required!';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'New password must be at least 6 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'New passwords do not match.';
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user_data = $result->fetch_assoc();
                if (password_verify($current_password, $user_data['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($update) {
                        $update->bind_param("si", $hashed_password, $user_id);
                        if ($update->execute()) {
                            $success_message = 'Password changed successfully!';
                        } else {
                            $error_message = 'Failed to change password.';
                        }
                        $update->close();
                    }
                } else {
                    $error_message = 'Current password is incorrect.';
                }
            }
            $stmt->close();
        }
    }
}

/* ==================== FIXED JOB HANDLING ==================== */

// Handle Accept Job - FIXED
if (isset($_POST['accept_job'])) {
    $farm_id = intval($_POST['farm_id']);
    $farm_name = mysqli_real_escape_string($conn, $_POST['farm_name']);
    $farmer_name = mysqli_real_escape_string($conn, $_POST['farmer_name'] ?? 'Farmer');
    $crop_type = mysqli_real_escape_string($conn, $_POST['crop_type']);
    $acres = floatval($_POST['acres']);
    $payment_amount = floatval($_POST['payment_amount']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $distance_km = intval($_POST['distance_km']);
    
    // Check if job already accepted
    $check = $conn->prepare("SELECT id FROM contractor_jobs WHERE farm_id = ?");
    $check->bind_param("i", $farm_id);
    $check->execute();
    $check_result = $check->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = 'This job has already been accepted by another contractor.';
    } else {
        // Insert into contractor_jobs
        $insert = $conn->prepare("INSERT INTO contractor_jobs 
            (contractor_id, farm_id, farm_name, farmer_name, crop_type, acres, payment_amount, location, distance_km, status, accepted_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'accepted', NOW())");
        $insert->bind_param("iissssdss", $user_id, $farm_id, $farm_name, $farmer_name, $crop_type, $acres, $payment_amount, $location, $distance_km);
        
        if ($insert->execute()) {
            $success_message = 'Job accepted successfully! It has been removed from available jobs.';
        } else {
            $error_message = 'Error accepting job: ' . $conn->error;
        }
        $insert->close();
    }
    $check->close();
}

// Handle Update Job Status - FIXED
if (isset($_POST['update_job_status'])) {
    $job_id = intval($_POST['job_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    
    $update = $conn->prepare("UPDATE contractor_jobs SET status = ? WHERE id = ? AND contractor_id = ?");
    $update->bind_param("sii", $new_status, $job_id, $user_id);
    
    if ($update->execute()) {
        $success_message = "Job status updated to $new_status";
        
        // If job is completed, add to earnings
        if ($new_status == 'completed') {
            // Update completed date
            $date_update = $conn->prepare("UPDATE contractor_jobs SET completed_date = NOW() WHERE id = ?");
            $date_update->bind_param("i", $job_id);
            $date_update->execute();
            $date_update->close();
            
            // Get job details for earnings
            $job_query = $conn->prepare("SELECT farm_name, crop_type, payment_amount FROM contractor_jobs WHERE id = ?");
            $job_query->bind_param("i", $job_id);
            $job_query->execute();
            $job_result = $job_query->get_result();
            
            if ($job_row = $job_result->fetch_assoc()) {
                // Add to earnings with 'pending' status
                $earnings_insert = $conn->prepare("INSERT INTO contractor_earnings 
                    (contractor_id, job_id, job_title, amount, payment_date, status) 
                    VALUES (?, ?, ?, ?, CURDATE(), 'pending')");
                $title = $job_row['farm_name'] . ' - ' . $job_row['crop_type'] . ' Harvest';
                $earnings_insert->bind_param("iisd", $user_id, $job_id, $title, $job_row['payment_amount']);
                $earnings_insert->execute();
                $earnings_insert->close();
                
                // Update jobs_completed count
                $conn->query("UPDATE users SET jobs_completed = jobs_completed + 1 WHERE id = $user_id");
                
                $success_message = 'Job marked as completed! Payment will be processed within 7 days.';
            }
            $job_query->close();
        }
    } else {
        $error_message = 'Error updating job status: ' . $conn->error;
    }
    $update->close();
}

// Handle New Message
if (isset($_POST['send_message'])) {
    $sender = mysqli_real_escape_string($conn, $_POST['sender']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $avatar = mysqli_real_escape_string($conn, $_POST['avatar'] ?? substr($sender, 0, 2));
    $time = date('h:i A');
    
    $insert = $conn->prepare("INSERT INTO contractor_messages (contractor_id, sender_name, sender_avatar, message_content, message_time, is_read) VALUES (?, ?, ?, ?, ?, FALSE)");
    if ($insert) {
        $insert->bind_param("issss", $user_id, $sender, $avatar, $content, $time);
        
        if ($insert->execute()) {
            $success_message = 'Message sent successfully!';
        } else {
            $error_message = 'Error sending message: ' . $conn->error;
        }
        $insert->close();
    }
}

// Handle Mark Message as Read
if (isset($_GET['mark_read'])) {
    $message_id = intval($_GET['mark_read']);
    
    $update = $conn->prepare("UPDATE contractor_messages SET is_read = TRUE WHERE id = ? AND contractor_id = ?");
    if ($update) {
        $update->bind_param("ii", $message_id, $user_id);
        $update->execute();
        $update->close();
    }
}

/* ==================== FIXED DATA FETCHING ==================== */

// Get contractor jobs (accepted and completed jobs) - FIXED
$my_jobs = [];
$accepted_jobs = 0;
$in_progress_jobs = 0;
$completed_jobs = 0;

$jobs_query = "SELECT * FROM contractor_jobs 
               WHERE contractor_id = ? 
               ORDER BY 
               CASE status 
                   WHEN 'accepted' THEN 1
                   WHEN 'in-progress' THEN 2
                   WHEN 'completed' THEN 3
                   ELSE 4
               END, accepted_date DESC";
$jobs_stmt = $conn->prepare($jobs_query);
if ($jobs_stmt) {
    $jobs_stmt->bind_param("i", $user_id);
    $jobs_stmt->execute();
    $jobs_result = $jobs_stmt->get_result();
    while ($row = $jobs_result->fetch_assoc()) {
        $my_jobs[] = $row;
        if ($row['status'] == 'accepted') {
            $accepted_jobs++;
        } elseif ($row['status'] == 'in-progress') {
            $in_progress_jobs++;
        } elseif ($row['status'] == 'completed') {
            $completed_jobs++;
        }
    }
    $jobs_stmt->close();
}

// FIXED: Get available jobs - ONLY farms with status 'ready' and NOT already accepted
$available_jobs = [];
$avail_query = "SELECT 
    f.id as farm_id,
    f.name as farm_name,
    u.name as farmer_name,
    f.crop_type,
    f.area as acres,
    f.location,
    f.area * 30000 as payment_amount,
    FLOOR(RAND() * 50) + 5 as distance_km
FROM farms f 
LEFT JOIN users u ON f.user_id = u.id
WHERE LOWER(f.status) = 'ready' 
AND f.id NOT IN (SELECT farm_id FROM contractor_jobs WHERE farm_id IS NOT NULL)
ORDER BY f.created_at DESC";
$avail_result = $conn->query($avail_query);
if ($avail_result && $avail_result->num_rows > 0) {
    while ($row = $avail_result->fetch_assoc()) {
        $available_jobs[] = $row;
    }
}

// If no farms in database, use sample data for demonstration
if (empty($available_jobs)) {
    $sample_jobs = [
        ['farm_id' => 1, 'farm_name' => 'Green Valley Farm', 'farmer_name' => 'James Mwangi', 'crop_type' => 'Maize', 'acres' => 15, 'payment_amount' => 45000, 'location' => 'Kiambu County', 'distance_km' => 12],
        ['farm_id' => 2, 'farm_name' => 'Sunrise Coffee Estate', 'farmer_name' => 'Sarah Ochieng', 'crop_type' => 'Coffee', 'acres' => 8, 'payment_amount' => 32000, 'location' => 'Thika', 'distance_km' => 25],
        ['farm_id' => 3, 'farm_name' => 'Golden Fields Farm', 'farmer_name' => 'Peter Kamau', 'crop_type' => 'Wheat', 'acres' => 25, 'payment_amount' => 75000, 'location' => 'Nakuru', 'distance_km' => 45],
        ['farm_id' => 4, 'farm_name' => 'River Side Farm', 'farmer_name' => 'Mary Wanjiku', 'crop_type' => 'Tomatoes', 'acres' => 10, 'payment_amount' => 35000, 'location' => 'Murang\'a', 'distance_km' => 30]
    ];
    $available_jobs = $sample_jobs;
}

// Get messages
$messages = [];
$unread_count = 0;
$msg_query = "SELECT * FROM contractor_messages WHERE contractor_id = ? ORDER BY is_read ASC, created_at DESC LIMIT 20";
$msg_stmt = $conn->prepare($msg_query);
if ($msg_stmt) {
    $msg_stmt->bind_param("i", $user_id);
    $msg_stmt->execute();
    $msg_result = $msg_stmt->get_result();
    while ($row = $msg_result->fetch_assoc()) {
        $messages[] = $row;
        if (!$row['is_read']) {
            $unread_count++;
        }
    }
    $msg_stmt->close();
}

// If no messages, add sample messages
if (empty($messages)) {
    $sample_msgs = [
        ['sender_name' => 'Green Valley Farm', 'sender_avatar' => 'GV', 'message_content' => 'Hello, we\'d like to confirm the harvest schedule for next week', 'message_time' => '10:30 AM', 'is_read' => 0],
        ['sender_name' => 'VEDARA Support', 'sender_avatar' => 'VS', 'message_content' => 'Welcome to VEDARA Contractor platform!', 'message_time' => '2 days ago', 'is_read' => 1]
    ];
    
    foreach ($sample_msgs as $msg) {
        $messages[] = $msg;
        if (!$msg['is_read']) {
            $unread_count++;
        }
    }
}

// Get reviews
$reviews = [];
$avg_rating = 0;
$total_reviews = 0;

$rev_query = "SELECT * FROM contractor_reviews WHERE contractor_id = ? ORDER BY review_date DESC";
$rev_stmt = $conn->prepare($rev_query);
if ($rev_stmt) {
    $rev_stmt->bind_param("i", $user_id);
    $rev_stmt->execute();
    $rev_result = $rev_stmt->get_result();
    while ($row = $rev_result->fetch_assoc()) {
        $reviews[] = $row;
        $total_reviews++;
        $avg_rating += $row['rating'];
    }
    $rev_stmt->close();
}

if ($total_reviews > 0) {
    $avg_rating = round($avg_rating / $total_reviews, 1);
}

// Get earnings
$total_earnings = 0;
$this_month = 0;
$pending_payments = 0;
$earnings_history = [];

$earn_query = "SELECT * FROM contractor_earnings WHERE contractor_id = ? ORDER BY payment_date DESC";
$earn_stmt = $conn->prepare($earn_query);
if ($earn_stmt) {
    $earn_stmt->bind_param("i", $user_id);
    $earn_stmt->execute();
    $earn_result = $earn_stmt->get_result();
    while ($row = $earn_result->fetch_assoc()) {
        $earnings_history[] = $row;
        $total_earnings += $row['amount'];
        
        if (substr($row['payment_date'], 0, 7) == date('Y-m')) {
            $this_month += $row['amount'];
        }
        
        if ($row['status'] == 'pending') {
            $pending_payments += $row['amount'];
        }
    }
    $earn_stmt->close();
}

// Calculate average per job
$avg_per_job = ($completed_jobs > 0) ? round($total_earnings / $completed_jobs) : 0;

// Get user's jobs_completed from database or calculate
$jobs_completed = $user['jobs_completed'] ?? $completed_jobs;

// Get rating from database or use calculated
$user_rating = $user['rating'] ?? ($avg_rating > 0 ? $avg_rating : 4.8);

/* ==================== STATS ==================== */

$active_jobs_count = $accepted_jobs + $in_progress_jobs;
$completed_jobs_count = $completed_jobs;
$total_earnings_count = $total_earnings;
$avg_rating_count = $user_rating;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VEDARA - Contractor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2a7d2e;
            --primary-dark: #1e5a21;
            --primary-light: #e8f5e9;
            --secondary: #f9a825;
            --dark: #1a331b;
            --light: #f8f9fa;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --white: #ffffff;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Open Sans', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }
        
        h1, h2, h3, h4, h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            line-height: 1.3;
        }
        
        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 24px;
            background-color: var(--primary);
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .btn-secondary {
            background-color: var(--secondary);
            color: var(--dark);
        }
        
        .btn-secondary:hover {
            background-color: #e69500;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        /* Messages */
        .success-message, .error-message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideDown 0.3s;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
            padding: 0 5px;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--white);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }
        
        .logo {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .user-info {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: var(--primary);
            font-size: 32px;
        }
        
        .user-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .user-role {
            color: var(--secondary);
            font-weight: 600;
            font-size: 14px;
            background-color: rgba(249, 168, 37, 0.1);
            padding: 4px 12px;
            border-radius: 50px;
            display: inline-block;
        }
        
        .nav-menu {
            list-style: none;
            padding: 20px 0;
            flex-grow: 1;
        }
        
        .nav-item {
            margin-bottom: 5px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            cursor: pointer;
        }
        
        .nav-link:hover, .nav-link.active {
            background-color: var(--primary-light);
            color: var(--primary);
            border-right: 4px solid var(--primary);
        }
        
        .nav-icon {
            width: 24px;
            margin-right: 12px;
            font-size: 18px;
            text-align: center;
        }
        
        .notification-badge {
            position: absolute;
            right: 20px;
            background-color: #ff4757;
            color: white;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--light-gray);
        }
        
        .logout-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            color: #dc3545;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .logout-btn:hover {
            background-color: #dc3545;
            color: white;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        
        .page-title h1 {
            font-size: 28px;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .page-title p {
            color: var(--gray);
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
        }
        
        /* Dashboard Content */
        .dashboard-section {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .dashboard-section.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--white);
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 24px;
        }
        
        .stat-icon.blue {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196f3;
        }
        
        .stat-icon.green {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4caf50;
        }
        
        .stat-icon.orange {
            background-color: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }
        
        .stat-icon.purple {
            background-color: rgba(156, 39, 176, 0.1);
            color: #9c27b0;
        }
        
        .stat-info h3 {
            font-size: 32px;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: var(--gray);
            font-size: 14px;
        }
        
        /* Available Jobs Section */
        .section {
            margin-bottom: 40px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            font-size: 22px;
            color: var(--dark);
        }
        
        .jobs-grid, .available-jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .job-card {
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .job-header {
            padding: 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .job-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .job-status {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-in-progress {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-accepted {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-high {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-medium {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-low {
            background-color: #d4edda;
            color: #155724;
        }
        
        .job-details {
            padding: 20px;
        }
        
        .detail-item {
            display: flex;
            margin-bottom: 12px;
        }
        
        .detail-label {
            width: 120px;
            font-weight: 600;
            color: var(--gray);
        }
        
        .detail-value {
            flex: 1;
            color: var(--dark);
        }
        
        .job-actions {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-top: 1px solid var(--light-gray);
            display: flex;
            gap: 10px;
        }
        
        /* Earnings Section */
        .earnings-container {
            background: var(--white);
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
        }
        
        .earnings-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .earnings-card {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        
        .earnings-card h3 {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .earnings-card p {
            color: var(--gray);
            font-size: 14px;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
            margin: 30px 0;
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            padding: 0 10px;
        }
        
        .chart-bar {
            width: 40px;
            background: linear-gradient(to top, var(--primary), var(--primary-dark));
            border-radius: 4px 4px 0 0;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
        }
        
        .chart-bar:hover {
            transform: scaleY(1.05);
        }
        
        .chart-bar:hover::after {
            content: attr(data-amount);
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--dark);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
        }
        
        .chart-labels {
            display: flex;
            justify-content: space-around;
            margin-top: 10px;
        }
        
        .chart-label {
            font-size: 12px;
            color: var(--gray);
            width: 40px;
            text-align: center;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            text-align: left;
            padding: 12px;
            background-color: #f8f9fa;
            color: var(--dark);
            font-weight: 600;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        /* Schedule Section */
        .schedule-container {
            background: var(--white);
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
        }
        
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        
        .calendar-header {
            text-align: center;
            font-weight: 600;
            color: var(--dark);
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .calendar-day {
            height: 100px;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 5px;
            position: relative;
            transition: var(--transition);
            overflow-y: auto;
        }
        
        .calendar-day:hover {
            background-color: #f8f9fa;
        }
        
        .calendar-day.today {
            background-color: var(--primary-light);
            border-color: var(--primary);
        }
        
        .calendar-day.has-job {
            background-color: rgba(249, 168, 37, 0.1);
            border-color: var(--secondary);
        }
        
        .day-number {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .day-job {
            font-size: 10px;
            color: white;
            background-color: var(--secondary);
            padding: 2px 4px;
            border-radius: 3px;
            margin-top: 3px;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Messages Section */
        .messages-container {
            background: var(--white);
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
        }
        
        .messages-list {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .message-item {
            display: flex;
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            transition: var(--transition);
            cursor: pointer;
        }
        
        .message-item:hover {
            background-color: #f8f9fa;
        }
        
        .message-item.unread {
            background-color: rgba(33, 150, 243, 0.05);
        }
        
        .message-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--primary);
            font-size: 20px;
            font-weight: 600;
        }
        
        .message-content {
            flex: 1;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .message-sender {
            font-weight: 600;
            color: var(--dark);
        }
        
        .message-time {
            font-size: 12px;
            color: var(--gray);
        }
        
        .message-preview {
            color: var(--gray);
            font-size: 14px;
        }
        
        /* Reviews Section */
        .reviews-container {
            background: var(--white);
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
        }
        
        .review-item {
            padding: 20px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .reviewer {
            font-weight: 600;
            color: var(--dark);
        }
        
        .review-rating {
            color: var(--secondary);
        }
        
        .review-date {
            font-size: 12px;
            color: var(--gray);
        }
        
        .review-content {
            color: var(--dark);
            line-height: 1.5;
        }
        
        /* Profile Section */
        .profile-container {
            background: var(--white);
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            color: var(--primary);
            font-size: 40px;
        }
        
        .profile-info h2 {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .profile-role {
            color: var(--secondary);
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .profile-stats {
            display: flex;
            gap: 20px;
        }
        
        .profile-stat {
            text-align: center;
        }
        
        .profile-stat h4 {
            font-size: 20px;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .profile-stat p {
            color: var(--gray);
            font-size: 12px;
        }
        
        .profile-form {
            margin-top: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 16px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(42, 125, 46, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        
        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: var(--shadow-lg);
            position: relative;
            animation: slideUp 0.3s;
        }
        
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
        }
        
        .modal-close:hover {
            color: var(--dark);
        }
        
        .modal-title {
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
        }
        
        /* Map Section */
        .map-container {
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .map-header {
            padding: 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .map-header h2 {
            font-size: 22px;
            color: var(--dark);
        }
        
        .map-placeholder {
            height: 400px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            position: relative;
            overflow: hidden;
        }
        
        .map-placeholder:before {
            content: "";
            position: absolute;
            width: 200%;
            height: 200%;
            background-image: 
                linear-gradient(to right, rgba(255,255,255,0.1) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 40px 40px;
            transform: rotate(45deg);
        }
        
        .map-point {
            position: absolute;
            width: 20px;
            height: 20px;
            background-color: var(--secondary);
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
            cursor: pointer;
            z-index: 10;
        }
        
        .map-point:hover::after {
            content: attr(data-job);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--dark);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            margin-bottom: 5px;
        }
        
        /* Responsive Styles */
        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
            }
            
            .main-content {
                margin-left: 220px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .jobs-grid, .available-jobs-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .calendar {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <a href="contractor_page.php" class="logo">VEDARA</a>
        
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user-hard-hat"></i>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($user['name'] ?? 'Contractor'); ?></div>
            <div class="user-role">CONTRACTOR</div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a class="nav-link active" data-section="dashboard">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-section="my-jobs">
                    <i class="fas fa-briefcase nav-icon"></i>
                    My Jobs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-section="available-jobs">
                    <i class="fas fa-tasks nav-icon"></i>
                    Available Jobs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-section="earnings">
                    <i class="fas fa-chart-line nav-icon"></i>
                    Earnings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-section="schedule">
                    <i class="fas fa-calendar-alt nav-icon"></i>
                    Schedule
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-section="messages">
                    <i class="fas fa-comments nav-icon"></i>
                    Messages
                    <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-section="reviews">
                    <i class="fas fa-star nav-icon"></i>
                    Reviews
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-section="profile">
                    <i class="fas fa-user-cog nav-icon"></i>
                    Profile
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <button class="logout-btn" id="logoutBtn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </button>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="page-title">
                <h1 id="pageTitle">Contractor Dashboard</h1>
                <p id="pageSubtitle">Manage your jobs, earnings, and schedule</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" id="newJobAlertBtn">
                    <i class="fas fa-plus"></i> New Job Alert
                </button>
                <button class="btn" id="exportReportBtn">
                    <i class="fas fa-download"></i> Export Report
                </button>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
        <div class="success-message" id="successMessage">
            <span>✅ <?php echo htmlspecialchars($success_message); ?></span>
            <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="error-message" id="errorMessage">
            <span>❌ <?php echo htmlspecialchars($error_message); ?></span>
            <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
        <?php endif; ?>

        <!-- Dashboard Content Sections -->
        
        <!-- Dashboard Section -->
        <div id="dashboard" class="dashboard-section active">
            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $active_jobs_count; ?></h3>
                        <p>Active Jobs</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $completed_jobs_count; ?></h3>
                        <p>Completed Jobs</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>KSh <?php echo number_format($total_earnings_count); ?></h3>
                        <p>Total Earnings</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $avg_rating_count; ?></h3>
                        <p>Average Rating</p>
                    </div>
                </div>
            </div>

            <!-- Available Jobs Section -->
            <div class="section">
                <div class="section-header">
                    <h2>Available Jobs Near You</h2>
                    <button class="btn btn-outline" id="refreshJobsBtn" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh Jobs
                    </button>
                </div>
                
                <div class="available-jobs-grid" id="availableJobsGrid">
                    <?php 
                    $display_jobs = array_slice($available_jobs, 0, 4);
                    foreach ($display_jobs as $job): 
                    ?>
                    <div class="job-card">
                        <div class="job-header">
                            <div class="job-title"><?php echo htmlspecialchars($job['farm_name'] . ' - ' . $job['crop_type']); ?></div>
                            <div class="job-status status-ready">READY</div>
                        </div>
                        <div class="job-details">
                            <div class="detail-item">
                                <div class="detail-label">Farm:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($job['farm_name']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Farmer:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($job['farmer_name'] ?? 'Farmer'); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Crop:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($job['crop_type']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Acres:</div>
                                <div class="detail-value"><?php echo $job['acres']; ?> acres</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Payment:</div>
                                <div class="detail-value"><strong>KSh <?php echo number_format($job['payment_amount']); ?></strong></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Distance:</div>
                                <div class="detail-value"><?php echo $job['distance_km']; ?> km</div>
                            </div>
                        </div>
                        <div class="job-actions">
                            <button class="btn" onclick="acceptJob(
                                <?php echo $job['farm_id']; ?>,
                                '<?php echo htmlspecialchars(addslashes($job['farm_name'])); ?>',
                                '<?php echo htmlspecialchars(addslashes($job['farmer_name'] ?? 'Farmer')); ?>',
                                '<?php echo htmlspecialchars(addslashes($job['crop_type'])); ?>',
                                <?php echo $job['acres']; ?>,
                                <?php echo $job['payment_amount']; ?>,
                                '<?php echo htmlspecialchars(addslashes($job['location'])); ?>',
                                <?php echo $job['distance_km']; ?>
                            )">
                                <i class="fas fa-check"></i> Accept Job
                            </button>
                            <button class="btn btn-outline" onclick="viewJobDetails(<?php echo $job['farm_id']; ?>)">
                                <i class="fas fa-info-circle"></i> Details
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Map Section -->
            <div class="map-container">
                <div class="map-header">
                    <h2>Job Locations</h2>
                    <button class="btn btn-outline" onclick="alert('Opening full map view...')">
                        <i class="fas fa-map-marked-alt"></i> View Full Map
                    </button>
                </div>
                <div class="map-placeholder">
                    <?php 
                    $positions = ['top:30%;left:40%', 'top:50%;left:60%', 'top:70%;left:30%', 'top:40%;left:70%'];
                    $index = 0;
                    $display_jobs = array_slice($available_jobs, 0, 4);
                    foreach ($display_jobs as $job): 
                    ?>
                    <div class="map-point" data-job="<?php echo htmlspecialchars($job['farm_name'] . ' - ' . $job['crop_type']); ?>" style="<?php echo $positions[$index]; ?>" onclick="alert('Selected: <?php echo htmlspecialchars(addslashes($job['farm_name'])); ?>')"></div>
                    <?php $index++; endforeach; ?>
                    <span>Interactive GPS Map - Job Locations</span>
                </div>
            </div>
        </div>

        <!-- My Jobs Section -->
        <div id="my-jobs" class="dashboard-section">
            <div class="section-header">
                <h2>My Jobs</h2>
                <button class="btn btn-secondary" onclick="window.location.href='#available-jobs'; document.querySelector(\'[data-section=\"available-jobs\"]\').click()">
                    <i class="fas fa-plus"></i> Find New Jobs
                </button>
            </div>
            
            <div class="jobs-grid" id="myJobsGrid">
                <?php if (empty($my_jobs)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 10px;">
                    <i class="fas fa-briefcase" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                    <h3>No jobs yet</h3>
                    <p style="color: #666; margin: 15px 0;">Start by accepting available jobs near you.</p>
                    <button class="btn" onclick="window.location.href='#available-jobs'; document.querySelector(\'[data-section=\"available-jobs\"]\').click()">Browse Available Jobs</button>
                </div>
                <?php else: ?>
                <?php foreach ($my_jobs as $job): ?>
                <div class="job-card">
                    <div class="job-header">
                        <div class="job-title"><?php echo htmlspecialchars($job['farm_name'] . ' - ' . $job['crop_type']); ?></div>
                        <div class="job-status status-<?php echo $job['status']; ?>"><?php echo strtoupper($job['status']); ?></div>
                    </div>
                    <div class="job-details">
                        <div class="detail-item">
                            <div class="detail-label">Farm:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($job['farm_name']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Farmer:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($job['farmer_name'] ?? 'Farmer'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Crop:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($job['crop_type']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Acres:</div>
                            <div class="detail-value"><?php echo $job['acres']; ?> acres</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Payment:</div>
                            <div class="detail-value"><strong>KSh <?php echo number_format($job['payment_amount']); ?></strong></div>
                        </div>
                        <?php if (!empty($job['accepted_date'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">Accepted:</div>
                            <div class="detail-value"><?php echo date('M d, Y', strtotime($job['accepted_date'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="job-actions">
                        <?php if ($job['status'] == 'accepted'): ?>
                        <button class="btn" onclick="updateJobStatus(<?php echo $job['id']; ?>, 'in-progress')">
                            <i class="fas fa-play"></i> Start Job
                        </button>
                        <?php elseif ($job['status'] == 'in-progress'): ?>
                        <button class="btn" onclick="updateJobStatus(<?php echo $job['id']; ?>, 'completed')">
                            <i class="fas fa-check"></i> Mark Completed
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-outline" onclick="viewJobDetails(<?php echo $job['id']; ?>)">
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Available Jobs Section (Full) -->
        <div id="available-jobs" class="dashboard-section">
            <div class="section-header">
                <h2>All Available Jobs</h2>
                <div>
                    <button class="btn btn-outline" onclick="alert('Filter dialog would open here')">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </div>
            
            <div class="available-jobs-grid" id="allJobsGrid">
                <?php if (empty($available_jobs)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 60px;">
                    <p>No available jobs at the moment.</p>
                </div>
                <?php else: ?>
                <?php foreach ($available_jobs as $job): ?>
                <div class="job-card">
                    <div class="job-header">
                        <div class="job-title"><?php echo htmlspecialchars($job['farm_name'] . ' - ' . $job['crop_type']); ?></div>
                        <div class="job-status status-ready">READY</div>
                    </div>
                    <div class="job-details">
                        <div class="detail-item">
                            <div class="detail-label">Farm:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($job['farm_name']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Farmer:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($job['farmer_name'] ?? 'Farmer'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Crop:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($job['crop_type']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Acres:</div>
                            <div class="detail-value"><?php echo $job['acres']; ?> acres</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Payment:</div>
                            <div class="detail-value"><strong>KSh <?php echo number_format($job['payment_amount']); ?></strong></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Distance:</div>
                            <div class="detail-value"><?php echo $job['distance_km']; ?> km</div>
                        </div>
                    </div>
                    <div class="job-actions">
                        <button class="btn" onclick="acceptJob(
                            <?php echo $job['farm_id']; ?>,
                            '<?php echo htmlspecialchars(addslashes($job['farm_name'])); ?>',
                            '<?php echo htmlspecialchars(addslashes($job['farmer_name'] ?? 'Farmer')); ?>',
                            '<?php echo htmlspecialchars(addslashes($job['crop_type'])); ?>',
                            <?php echo $job['acres']; ?>,
                            <?php echo $job['payment_amount']; ?>,
                            '<?php echo htmlspecialchars(addslashes($job['location'])); ?>',
                            <?php echo $job['distance_km']; ?>
                        )">
                            <i class="fas fa-check"></i> Accept Job
                        </button>
                        <button class="btn btn-outline" onclick="viewJobDetails(<?php echo $job['farm_id']; ?>)">
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Earnings Section -->
        <div id="earnings" class="dashboard-section">
            <div class="earnings-container">
                <div class="section-header">
                    <h2>Earnings Overview</h2>
                    <button class="btn btn-outline" onclick="alert('Select period dialog would open here')">
                        <i class="fas fa-calendar-alt"></i> Select Period
                    </button>
                </div>
                
                <div class="earnings-summary">
                    <div class="earnings-card">
                        <h3>KSh <?php echo number_format($total_earnings); ?></h3>
                        <p>Total Earnings</p>
                    </div>
                    <div class="earnings-card">
                        <h3>KSh <?php echo number_format($this_month); ?></h3>
                        <p>This Month</p>
                    </div>
                    <div class="earnings-card">
                        <h3>KSh <?php echo number_format($pending_payments); ?></h3>
                        <p>Pending Payments</p>
                    </div>
                    <div class="earnings-card">
                        <h3>KSh <?php echo number_format($avg_per_job); ?></h3>
                        <p>Average per Job</p>
                    </div>
                </div>
                
                <h3>Weekly Earnings</h3>
                <div class="chart-container">
                    <?php
                    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    $values = [45000, 67500, 90000, 112500, 78750, 101250, 56250];
                    foreach ($values as $i => $value):
                    ?>
                    <div class="chart-bar" style="height: <?php echo ($value / 120000) * 100; ?>%" data-amount="KSh <?php echo number_format($value); ?>"></div>
                    <?php endforeach; ?>
                </div>
                <div class="chart-labels">
                    <?php foreach ($days as $day): ?>
                    <span class="chart-label"><?php echo $day; ?></span>
                    <?php endforeach; ?>
                </div>
                
                <div class="section-header" style="margin-top: 40px;">
                    <h3>Payment History</h3>
                    <button class="btn btn-outline" onclick="alert('Downloading statement...')">
                        <i class="fas fa-download"></i> Download Statement
                    </button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Job</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="paymentHistory">
                        <?php if (!empty($earnings_history)): ?>
                        <?php foreach ($earnings_history as $earning): ?>
                        <tr>
                            <td><?php echo $earning['payment_date']; ?></td>
                            <td><?php echo htmlspecialchars($earning['job_title']); ?></td>
                            <td><strong>KSh <?php echo number_format($earning['amount']); ?></strong></td>
                            <td><span style="color: <?php echo $earning['status'] == 'paid' ? '#4caf50' : '#ffa502'; ?>;"><?php echo ucfirst($earning['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Schedule Section -->
        <div id="schedule" class="dashboard-section">
            <div class="schedule-container">
                <div class="section-header">
                    <h2>My Schedule</h2>
                    <button class="btn btn-secondary" onclick="alert('Add event form would open here')">
                        <i class="fas fa-plus"></i> Add Event
                    </button>
                </div>
                
                <div class="calendar" id="calendar">
                    <?php
                    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    foreach ($days as $day):
                    ?>
                    <div class="calendar-header"><?php echo $day; ?></div>
                    <?php endforeach; ?>
                    
                    <?php
                    $today = date('j');
                    for ($i = 1; $i <= 31; $i++):
                        $has_job = false;
                        $job_title = '';
                        foreach ($my_jobs as $job) {
                            if ($job['status'] == 'accepted' || $job['status'] == 'in-progress') {
                                $job_date = date('j', strtotime($job['accepted_date'] ?? 'now'));
                                if ($job_date == $i) {
                                    $has_job = true;
                                    $job_title = $job['farm_name'];
                                    break;
                                }
                            }
                        }
                    ?>
                    <div class="calendar-day <?php echo ($i == $today) ? 'today' : ''; ?> <?php echo $has_job ? 'has-job' : ''; ?>">
                        <div class="day-number"><?php echo $i; ?></div>
                        <?php if ($has_job): ?>
                        <span class="day-job" title="<?php echo $job_title; ?>"><?php echo $job_title; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
                
                <div style="margin-top: 40px;">
                    <h3>Upcoming Jobs</h3>
                    <div class="jobs-grid" id="upcomingJobsGrid">
                        <?php 
                        $upcoming = array_filter($my_jobs, function($job) {
                            return $job['status'] == 'accepted' || $job['status'] == 'in-progress';
                        });
                        if (empty($upcoming)):
                        ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 40px;">
                            <p>No upcoming jobs scheduled.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($upcoming as $job): ?>
                        <div class="job-card">
                            <div class="job-header">
                                <div class="job-title"><?php echo htmlspecialchars($job['farm_name'] . ' - ' . $job['crop_type']); ?></div>
                                <div class="job-status status-<?php echo $job['status']; ?>"><?php echo strtoupper($job['status']); ?></div>
                            </div>
                            <div class="job-details">
                                <div class="detail-item">
                                    <div class="detail-label">Farm:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($job['farm_name']); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Date:</div>
                                    <div class="detail-value"><?php echo $job['accepted_date'] ? date('M d, Y', strtotime($job['accepted_date'])) : 'TBD'; ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages Section -->
        <div id="messages" class="dashboard-section">
            <div class="messages-container">
                <div class="section-header">
                    <h2>Messages</h2>
                    <button class="btn btn-secondary" onclick="showNewMessageModal()">
                        <i class="fas fa-plus"></i> New Message
                    </button>
                </div>
                
                <div class="messages-list" id="messagesList">
                    <?php if (empty($messages)): ?>
                    <div style="text-align: center; padding: 40px;">
                        <p>No messages yet.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                    <div class="message-item <?php echo empty($msg['is_read']) ? 'unread' : ''; ?>" onclick="markMessageRead(<?php echo $msg['id'] ?? 0; ?>)">
                        <div class="message-avatar"><?php echo htmlspecialchars($msg['sender_avatar'] ?? 'U'); ?></div>
                        <div class="message-content">
                            <div class="message-header">
                                <div class="message-sender"><?php echo htmlspecialchars($msg['sender_name']); ?></div>
                                <div class="message-time"><?php echo htmlspecialchars($msg['message_time']); ?></div>
                            </div>
                            <div class="message-preview"><?php echo htmlspecialchars($msg['message_content']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reviews Section -->
        <div id="reviews" class="dashboard-section">
            <div class="reviews-container">
                <div class="section-header">
                    <h2>Customer Reviews</h2>
                    <div>
                        <span style="color: var(--secondary); font-size: 24px;">
                            <i class="fas fa-star"></i> <?php echo $avg_rating_count; ?>/5.0
                        </span>
                        <span style="color: var(--gray); margin-left: 10px;">(<?php echo count($reviews); ?> reviews)</span>
                    </div>
                </div>
                
                <div id="reviewsList">
                    <?php if (empty($reviews)): ?>
                    <div style="text-align: center; padding: 40px;">
                        <p>No reviews yet.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div>
                                <div class="reviewer"><?php echo htmlspecialchars($review['reviewer_name']); ?></div>
                                <div class="review-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star" style="color: <?php echo $i <= ($review['rating'] ?? 0) ? 'var(--secondary)' : '#ddd'; ?>;"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="review-date"><?php echo $review['review_date'] ?? date('Y-m-d'); ?></div>
                        </div>
                        <div class="review-content"><?php echo htmlspecialchars($review['review_content']); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profile" class="dashboard-section">
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-user-hard-hat"></i>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['name'] ?? 'Contractor'); ?></h2>
                        <div class="profile-role">Verified Contractor</div>
                        <div class="profile-stats">
                            <div class="profile-stat">
                                <h4><?php echo $jobs_completed; ?></h4>
                                <p>Jobs Completed</p>
                            </div>
                            <div class="profile-stat">
                                <h4><?php echo $avg_rating_count; ?></h4>
                                <p>Rating</p>
                            </div>
                            <div class="profile-stat">
                                <h4><?php echo $user['success_rate'] ?? '98'; ?>%</h4>
                                <p>Success Rate</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="" class="profile-form">
                    <h3>Profile Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name'] ?? 'Contractor'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? '+254 712 345 678'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($user['location'] ?? 'Nairobi, Kenya'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Specialization</label>
                            <select class="form-control" name="specialization">
                                <option value="Harvesting" <?php echo ($user['specialization'] ?? '') == 'Harvesting' ? 'selected' : ''; ?>>Harvesting</option>
                                <option value="Pruning & Trimming" <?php echo ($user['specialization'] ?? '') == 'Pruning & Trimming' ? 'selected' : ''; ?>>Pruning & Trimming</option>
                                <option value="Planting" <?php echo ($user['specialization'] ?? '') == 'Planting' ? 'selected' : ''; ?>>Planting</option>
                                <option value="Irrigation" <?php echo ($user['specialization'] ?? '') == 'Irrigation' ? 'selected' : ''; ?>>Irrigation</option>
                                <option value="Pest Control" <?php echo ($user['specialization'] ?? '') == 'Pest Control' ? 'selected' : ''; ?>>Pest Control</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Experience (Years)</label>
                            <input type="number" class="form-control" name="experience" value="<?php echo htmlspecialchars($user['experience'] ?? '8'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Bio/Description</label>
                        <textarea class="form-control" name="bio" rows="4"><?php echo htmlspecialchars($user['bio'] ?? 'Experienced agricultural contractor with 8+ years in the field. Specialized in crop harvesting and farm management.'); ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn" style="margin-top: 20px;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
            
            <!-- Password Change Section -->
            <div class="profile-container" style="margin-top: 30px;">
                <h3>Change Password</h3>
                <form method="POST" action="" onsubmit="return validatePassword()">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" id="new_password" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" id="confirm_password" required>
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-outline">Update Password</button>
                </form>
            </div>
        </div>
    </main>

    <!-- Modal for New Message -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h2 class="modal-title">New Message</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">To</label>
                    <select class="form-control" name="sender" required>
                        <option value="">Select recipient</option>
                        <option value="Green Valley Farm">Green Valley Farm</option>
                        <option value="Sunrise Coffee Estate">Sunrise Coffee Estate</option>
                        <option value="VEDARA Support">VEDARA Support</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea class="form-textarea" name="content" rows="5" placeholder="Type your message here..." required></textarea>
                </div>
                <input type="hidden" name="avatar" value="U">
                <button type="submit" name="send_message" class="btn">Send Message</button>
            </form>
        </div>
    </div>

    <!-- Modal for Job Alert -->
    <div id="jobAlertModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeJobAlertModal()">&times;</span>
            <h2 class="modal-title">New Job Alert Preferences</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Alert Title</label>
                    <input type="text" class="form-control" name="alert_title" value="New Job Alert" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Crop Type</label>
                        <select class="form-control" name="alert_crop">
                            <option value="any">Any Crop</option>
                            <option value="maize">Maize</option>
                            <option value="coffee">Coffee</option>
                            <option value="wheat">Wheat</option>
                            <option value="tea">Tea</option>
                            <option value="vegetables">Vegetables</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min Payment (KSh)</label>
                        <input type="number" class="form-control" name="min_payment" value="30000">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Max Distance (km)</label>
                    <input type="number" class="form-control" name="max_distance" value="50">
                </div>
                <button type="submit" name="add_job_alert" class="btn btn-secondary">Save Preferences</button>
            </form>
        </div>
    </div>

    <script>
        // Navigation between sections
        function setupNavigation() {
            const navLinks = document.querySelectorAll('.nav-link');
            const sections = document.querySelectorAll('.dashboard-section');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Get target section
                    const targetId = this.getAttribute('data-section');
                    
                    // Update active nav link
                    navLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update page title
                    const sectionTitles = {
                        'dashboard': { title: 'Contractor Dashboard', subtitle: 'Manage your jobs, earnings, and schedule' },
                        'my-jobs': { title: 'My Jobs', subtitle: 'View and manage your jobs' },
                        'available-jobs': { title: 'Available Jobs', subtitle: 'Browse and accept new job opportunities' },
                        'earnings': { title: 'Earnings', subtitle: 'Track your income and payment history' },
                        'schedule': { title: 'Schedule', subtitle: 'Manage your work calendar and upcoming jobs' },
                        'messages': { title: 'Messages', subtitle: 'Communicate with farms and VEDARA support' },
                        'reviews': { title: 'Reviews', subtitle: 'View customer feedback and ratings' },
                        'profile': { title: 'Profile', subtitle: 'Manage your contractor profile and settings' }
                    };
                    
                    if (sectionTitles[targetId]) {
                        document.getElementById('pageTitle').textContent = sectionTitles[targetId].title;
                        document.getElementById('pageSubtitle').textContent = sectionTitles[targetId].subtitle;
                    }
                    
                    // Show target section
                    sections.forEach(section => {
                        section.classList.remove('active');
                    });
                    
                    document.getElementById(targetId).classList.add('active');
                    
                    // Update URL hash
                    window.location.hash = targetId;
                });
            });
            
            // Check hash on load
            const hash = window.location.hash.substring(1);
            if (hash) {
                const link = document.querySelector(`[data-section="${hash}"]`);
                if (link) link.click();
            }
        }

        // Job actions - FIXED
        function acceptJob(farmId, farmName, farmerName, cropType, acres, payment, location, distance) {
            if (confirm(`Accept this job?\n\nFarm: ${farmName}\nFarmer: ${farmerName}\nCrop: ${cropType}\nAcres: ${acres}\nPayment: KSh ${payment.toLocaleString()}\nDistance: ${distance} km`)) {
                
                // Disable button to prevent double submission
                const btn = event.target;
                btn.disabled = true;
                btn.innerHTML = 'Accepting...';
                
                // Create and submit form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const fields = {
                    'farm_id': farmId,
                    'farm_name': farmName,
                    'farmer_name': farmerName,
                    'crop_type': cropType,
                    'acres': acres,
                    'payment_amount': payment,
                    'location': location,
                    'distance_km': distance,
                    'accept_job': '1'
                };
                
                for (const [key, value] of Object.entries(fields)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        function updateJobStatus(jobId, newStatus) {
            if (confirm(`Mark job as ${newStatus}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const input1 = document.createElement('input');
                input1.type = 'hidden';
                input1.name = 'job_id';
                input1.value = jobId;
                form.appendChild(input1);
                
                const input2 = document.createElement('input');
                input2.type = 'hidden';
                input2.name = 'new_status';
                input2.value = newStatus;
                form.appendChild(input2);
                
                const input3 = document.createElement('input');
                input3.type = 'hidden';
                input3.name = 'update_job_status';
                input3.value = '1';
                form.appendChild(input3);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewJobDetails(jobId) {
            alert(`Viewing details for job #${jobId}\n\nThis would open a detailed view with full job information.`);
        }

        function markMessageRead(messageId) {
            window.location.href = `?mark_read=${messageId}#messages`;
        }

        // Modal functions
        function showNewMessageModal() {
            document.getElementById('messageModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('messageModal').style.display = 'none';
        }

        function showJobAlertModal() {
            document.getElementById('jobAlertModal').style.display = 'block';
        }

        function closeJobAlertModal() {
            document.getElementById('jobAlertModal').style.display = 'none';
        }

        // Button handlers
        document.getElementById('newJobAlertBtn').addEventListener('click', function() {
            showJobAlertModal();
        });

        document.getElementById('exportReportBtn').addEventListener('click', function() {
            alert('Exporting earnings report...');
        });

        // Logout
        document.getElementById('logoutBtn').addEventListener('click', function() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        });

        // Password validation
        function validatePassword() {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass.length < 6) {
                alert('New password must be at least 6 characters long!');
                return false;
            }
            
            if (newPass !== confirmPass) {
                alert('New passwords do not match!');
                return false;
            }
            
            return true;
        }

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const successMsg = document.getElementById('successMessage');
            const errorMsg = document.getElementById('errorMessage');
            
            if (successMsg) successMsg.style.display = 'none';
            if (errorMsg) errorMsg.style.display = 'none';
        }, 5000);

        // Initialize navigation
        window.addEventListener('DOMContentLoaded', setupNavigation);

        // Close modals when clicking outside
        window.onclick = function(event) {
            const msgModal = document.getElementById('messageModal');
            const alertModal = document.getElementById('jobAlertModal');
            if (event.target == msgModal) msgModal.style.display = 'none';
            if (event.target == alertModal) alertModal.style.display = 'none';
        }
    </script>
</body>
</html>