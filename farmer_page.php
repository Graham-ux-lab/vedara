<?php
session_start();
require_once "config.php";

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Farmer';
$user_role = $_SESSION['role'] ?? 'farmer';
$success_message = '';
$error_message = '';

// Get active tab from URL (default to 'profile')
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

/* ==================== PROFILE HANDLING ==================== */

// Handle Profile Update
if (isset($_POST['update_profile'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
    $bio = mysqli_real_escape_string($conn, $_POST['bio'] ?? '');
    
    // Check if email exists for another user
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $checkEmail->bind_param("si", $email, $user_id);
    $checkEmail->execute();
    $result = $checkEmail->get_result();
    
    if ($result->num_rows > 0) {
        $error_message = 'Email already exists for another user!';
    } else {
        $update = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, location = ?, bio = ? WHERE id = ?");
        $update->bind_param("sssssi", $name, $email, $phone, $location, $bio, $user_id);
        
        if ($update->execute()) {
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            $success_message = 'Profile updated successfully!';
        } else {
            $error_message = 'Failed to update profile. Please try again.';
        }
        $update->close();
    }
    $checkEmail->close();
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
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            if (password_verify($current_password, $user_data['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->bind_param("si", $hashed_password, $user_id);
                if ($update->execute()) {
                    $success_message = 'Password changed successfully!';
                } else {
                    $error_message = 'Failed to change password.';
                }
                $update->close();
            } else {
                $error_message = 'Current password is incorrect.';
            }
        }
        $stmt->close();
    }
}

// Get user data
$user = [];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
}
$stmt->close();

/* ==================== FARMS HANDLING ==================== */

// Handle Add Farm
if (isset($_POST['add_farm'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $area = floatval($_POST['area']);
    $soil_type = mysqli_real_escape_string($conn, $_POST['soil_type']);
    $irrigation = mysqli_real_escape_string($conn, $_POST['irrigation']);
    $established_year = intval($_POST['established_year']);
    $crops = mysqli_real_escape_string($conn, $_POST['crops']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $stmt = $conn->prepare("INSERT INTO farms (user_id, name, location, area, soil_type, irrigation, established_year, crops, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdssiss", $user_id, $name, $location, $area, $soil_type, $irrigation, $established_year, $crops, $status);
    
    if ($stmt->execute()) {
        $success_message = 'Farm added successfully!';
        $active_tab = 'farms'; // Switch to farms tab
    } else {
        $error_message = 'Error adding farm: ' . $conn->error;
    }
    $stmt->close();
}

// Handle Edit Farm
if (isset($_POST['edit_farm'])) {
    $farm_id = intval($_POST['farm_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $area = floatval($_POST['area']);
    $soil_type = mysqli_real_escape_string($conn, $_POST['soil_type']);
    $irrigation = mysqli_real_escape_string($conn, $_POST['irrigation']);
    $established_year = intval($_POST['established_year']);
    $crops = mysqli_real_escape_string($conn, $_POST['crops']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $stmt = $conn->prepare("UPDATE farms SET name=?, location=?, area=?, soil_type=?, irrigation=?, established_year=?, crops=?, status=? WHERE id=? AND user_id=?");
    $stmt->bind_param("ssdssissii", $name, $location, $area, $soil_type, $irrigation, $established_year, $crops, $status, $farm_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = 'Farm updated successfully!';
        $active_tab = 'farms';
    } else {
        $error_message = 'Error updating farm: ' . $conn->error;
    }
    $stmt->close();
}

// Handle Delete Farm
if (isset($_GET['delete_farm'])) {
    $farm_id = intval($_GET['delete_farm']);
    
    $stmt = $conn->prepare("DELETE FROM farms WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $farm_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = 'Farm deleted successfully!';
    } else {
        $error_message = 'Error deleting farm: ' . $conn->error;
    }
    $stmt->close();
}

// Get all farms for this user
$farms = [];
$total_farm_area = 0;
$active_farms = 0;
$total_farm_crops = 0;

$farm_query = "SELECT * FROM farms WHERE user_id = ? ORDER BY created_at DESC";
$farm_stmt = $conn->prepare($farm_query);
$farm_stmt->bind_param("i", $user_id);
$farm_stmt->execute();
$farm_result = $farm_stmt->get_result();
while ($row = $farm_result->fetch_assoc()) {
    $farms[] = $row;
    $total_farm_area += $row['area'];
    if ($row['status'] == 'active') {
        $active_farms++;
    }
    // Count crops
    if (!empty($row['crops'])) {
        $crop_array = explode(',', $row['crops']);
        $total_farm_crops += count($crop_array);
    }
}
$farm_stmt->close();

// Get farm for editing if ID is provided
$edit_farm = null;
if (isset($_GET['edit_farm'])) {
    $farm_id = intval($_GET['edit_farm']);
    $stmt = $conn->prepare("SELECT * FROM farms WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $farm_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_farm = $result->fetch_assoc();
    $stmt->close();
}

/* ==================== CROPS HANDLING ==================== */

// Handle Add Crop
if (isset($_POST['add_crop'])) {
    $crop_name = mysqli_real_escape_string($conn, $_POST['crop_name']);
    $crop_type = mysqli_real_escape_string($conn, $_POST['crop_type'] ?? '');
    $farm_id = !empty($_POST['farm_id']) ? intval($_POST['farm_id']) : 'NULL';
    $planting_date = !empty($_POST['planting_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['planting_date']) . "'" : 'NULL';
    $expected_harvest = !empty($_POST['expected_harvest']) ? "'" . mysqli_real_escape_string($conn, $_POST['expected_harvest']) . "'" : 'NULL';
    $area = floatval($_POST['area']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $growth_progress = intval($_POST['growth_progress']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    
    if ($farm_id == 'NULL') {
        $query = "INSERT INTO crops (user_id, crop_name, crop_type, planting_date, expected_harvest, area, status, growth_progress, notes) 
                  VALUES ($user_id, '$crop_name', '$crop_type', $planting_date, $expected_harvest, $area, '$status', $growth_progress, '$notes')";
    } else {
        $query = "INSERT INTO crops (user_id, farm_id, crop_name, crop_type, planting_date, expected_harvest, area, status, growth_progress, notes) 
                  VALUES ($user_id, $farm_id, '$crop_name', '$crop_type', $planting_date, $expected_harvest, $area, '$status', $growth_progress, '$notes')";
    }
    
    if ($conn->query($query)) {
        $success_message = 'Crop added successfully!';
        $active_tab = 'crops';
    } else {
        $error_message = 'Error adding crop: ' . $conn->error;
    }
}

// Handle Edit Crop
if (isset($_POST['edit_crop'])) {
    $crop_id = intval($_POST['crop_id']);
    $crop_name = mysqli_real_escape_string($conn, $_POST['crop_name']);
    $crop_type = mysqli_real_escape_string($conn, $_POST['crop_type'] ?? '');
    $farm_id = !empty($_POST['farm_id']) ? intval($_POST['farm_id']) : 'NULL';
    $planting_date = !empty($_POST['planting_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['planting_date']) . "'" : 'NULL';
    $expected_harvest = !empty($_POST['expected_harvest']) ? "'" . mysqli_real_escape_string($conn, $_POST['expected_harvest']) . "'" : 'NULL';
    $area = floatval($_POST['area']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $growth_progress = intval($_POST['growth_progress']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    
    if ($farm_id == 'NULL') {
        $query = "UPDATE crops SET 
                  crop_name='$crop_name', 
                  crop_type='$crop_type', 
                  farm_id=NULL, 
                  planting_date=$planting_date, 
                  expected_harvest=$expected_harvest, 
                  area=$area, 
                  status='$status', 
                  growth_progress=$growth_progress, 
                  notes='$notes' 
                  WHERE id=$crop_id AND user_id=$user_id";
    } else {
        $query = "UPDATE crops SET 
                  crop_name='$crop_name', 
                  crop_type='$crop_type', 
                  farm_id=$farm_id, 
                  planting_date=$planting_date, 
                  expected_harvest=$expected_harvest, 
                  area=$area, 
                  status='$status', 
                  growth_progress=$growth_progress, 
                  notes='$notes' 
                  WHERE id=$crop_id AND user_id=$user_id";
    }
    
    if ($conn->query($query)) {
        $success_message = 'Crop updated successfully!';
        $active_tab = 'crops';
    } else {
        $error_message = 'Error updating crop: ' . $conn->error;
    }
}

// Handle Delete Crop
if (isset($_GET['delete_crop'])) {
    $crop_id = intval($_GET['delete_crop']);
    
    $stmt = $conn->prepare("DELETE FROM crops WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $crop_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = 'Crop deleted successfully!';
    } else {
        $error_message = 'Error deleting crop: ' . $conn->error;
    }
    $stmt->close();
}

// Handle Update Progress
if (isset($_POST['update_progress'])) {
    $crop_id = intval($_POST['crop_id']);
    $growth_progress = intval($_POST['growth_progress']);
    
    $stmt = $conn->prepare("UPDATE crops SET growth_progress=? WHERE id=? AND user_id=?");
    $stmt->bind_param("iii", $growth_progress, $crop_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = 'Progress updated successfully!';
    } else {
        $error_message = 'Error updating progress: ' . $conn->error;
    }
    $stmt->close();
}

// Get all crops for this user
$crops = [];
$total_crops = 0;
$growing_crops = 0;
$harvested_crops = 0;
$total_crop_area = 0;

// Filter by farm if specified
$farm_filter = isset($_GET['farm_filter']) ? intval($_GET['farm_filter']) : 0;

if ($farm_filter > 0) {
    $crop_query = "SELECT c.*, f.name as farm_name FROM crops c LEFT JOIN farms f ON c.farm_id = f.id WHERE c.user_id = $user_id AND (c.farm_id = $farm_filter OR c.farm_id IS NULL) ORDER BY c.created_at DESC";
} else {
    $crop_query = "SELECT c.*, f.name as farm_name FROM crops c LEFT JOIN farms f ON c.farm_id = f.id WHERE c.user_id = $user_id ORDER BY c.created_at DESC";
}

$crop_result = $conn->query($crop_query);
if ($crop_result) {
    while ($row = $crop_result->fetch_assoc()) {
        $crops[] = $row;
        $total_crops++;
        $total_crop_area += $row['area'];
        
        if ($row['status'] == 'growing') {
            $growing_crops++;
        } elseif ($row['status'] == 'harvested') {
            $harvested_crops++;
        }
    }
}

// Get crop for editing if ID is provided
$edit_crop = null;
if (isset($_GET['edit_crop'])) {
    $crop_id = intval($_GET['edit_crop']);
    $stmt = $conn->prepare("SELECT c.*, f.name as farm_name FROM crops c LEFT JOIN farms f ON c.farm_id = f.id WHERE c.id=? AND c.user_id=?");
    $stmt->bind_param("ii", $crop_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_crop = $result->fetch_assoc();
    $stmt->close();
}

// Helper function for status class
function getStatusClass($status) {
    switch($status) {
        case 'growing': return 'status-growing';
        case 'harvested': return 'status-harvested';
        case 'planning': return 'status-planning';
        default: return 'status-growing';
    }
}

// Helper function for crop icons
function getCropIcon($crop_name) {
    $crop_name_lower = strtolower($crop_name);
    if (strpos($crop_name_lower, 'maize') !== false || strpos($crop_name_lower, 'corn') !== false) return '🌽';
    if (strpos($crop_name_lower, 'coffee') !== false) return '☕';
    if (strpos($crop_name_lower, 'tomato') !== false) return '🍅';
    if (strpos($crop_name_lower, 'potato') !== false) return '🥔';
    if (strpos($crop_name_lower, 'bean') !== false) return '🫘';
    if (strpos($crop_name_lower, 'wheat') !== false || strpos($crop_name_lower, 'barley') !== false) return '🌾';
    if (strpos($crop_name_lower, 'avocado') !== false) return '🥑';
    if (strpos($crop_name_lower, 'kale') !== false || strpos($crop_name_lower, 'cabbage') !== false) return '🥬';
    if (strpos($crop_name_lower, 'carrot') !== false) return '🥕';
    if (strpos($crop_name_lower, 'onion') !== false) return '🧅';
    return '🌱';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VEDARA - Farmer Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .logo {
            font-size: 28px;
            font-weight: 700;
            color: #2d5016;
            text-decoration: none;
            letter-spacing: 1px;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            gap: 20px;
        }
        
        nav a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        nav a:hover {
            background-color: #2d5016;
            color: white;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-name {
            font-weight: 600;
            color: #2d5016;
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .page-title {
            font-size: 32px;
            color: #2d5016;
            font-weight: 700;
        }
        
        /* Tabs */
        .dashboard-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }
        
        .tab-btn {
            padding: 12px 30px;
            border: none;
            background: none;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            border-radius: 6px 6px 0 0;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .tab-btn:hover {
            color: #2d5016;
            background-color: #f0f7eb;
        }
        
        .tab-btn.active {
            color: #2d5016;
            border-bottom: 3px solid #2d5016;
            background-color: #f0f7eb;
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.5s;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background-color: #2d5016;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #3a6c1e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(45, 80, 22, 0.2);
        }
        
        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background-color: #e0e0e0;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid #2d5016;
            color: #2d5016;
        }
        
        .btn-outline:hover {
            background-color: #2d5016;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
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
        
        /* Profile Section */
        .profile-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #2d5016, #4a7a2c);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: 600;
        }
        
        .profile-title h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .profile-title p {
            color: #666;
            font-size: 16px;
        }
        
        .profile-badge {
            display: inline-block;
            padding: 4px 12px;
            background-color: #e8f5e8;
            color: #2d5016;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #2d5016;
            box-shadow: 0 0 0 3px rgba(45, 80, 22, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #2d5016;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Farms Grid */
        .farms-grid, .crops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .farm-card, .crop-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .farm-card:hover, .crop-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .farm-header {
            background: linear-gradient(135deg, #2d5016 0%, #3a6c1e 100%);
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .farm-name {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .farm-location {
            font-size: 14px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .farm-status {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .status-inactive {
            background-color: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
        }
        
        .farm-content, .crop-content {
            padding: 20px;
        }
        
        .farm-details, .crop-details {
            margin-bottom: 20px;
        }
        
        .farm-detail, .crop-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 15px;
        }
        
        .detail-label {
            color: #666;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            color: #2d5016;
        }
        
        .farm-crops, .crop-notes {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .crops-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        
        .crops-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .crop-tag {
            background-color: #e8f5e8;
            color: #2d5016;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .farm-actions, .crop-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Crop Card Specific */
        .crop-image {
            height: 120px;
            background: linear-gradient(135deg, #2d5016 0%, #4a7a2c 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }
        
        .crop-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .crop-name {
            font-size: 20px;
            font-weight: 600;
            color: #2d5016;
        }
        
        .crop-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-growing {
            background-color: #e8f5e8;
            color: #2d5016;
        }
        
        .status-harvested {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        
        .status-planning {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        .crop-progress {
            margin: 15px 0;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .progress-bar {
            height: 10px;
            background-color: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2d5016, #4a7a2c);
            border-radius: 5px;
            transition: width 0.3s;
        }
        
        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .filter-select {
            padding: 10px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            min-width: 200px;
        }
        
        /* Add Form */
        .add-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
        }
        
        .form-title {
            font-size: 24px;
            color: #2d5016;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        /* Password Section */
        .password-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin-top: 30px;
        }
        
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin: 30px 0;
        }
        
        .empty-icon {
            font-size: 64px;
            color: #e0e0e0;
            margin-bottom: 20px;
        }
        
        .empty-title {
            font-size: 24px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-description {
            color: #888;
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Footer */
        footer {
            text-align: center;
            margin-top: 50px;
            padding: 20px;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #eee;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 15px;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .farms-grid, .crops-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .dashboard-tabs {
                flex-wrap: wrap;
            }
            
            .farm-actions, .crop-actions {
                flex-direction: column;
            }
            
            .farm-actions .btn, .crop-actions .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <a href="dashboard.php" class="logo">VEDARA</a>
            
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <?php 
                if ($active_tab == 'profile') echo 'My Profile';
                elseif ($active_tab == 'farms') echo 'My Farms';
                else echo 'My Crops';
                ?>
            </h1>
            <span class="profile-badge">
                <?php echo ucfirst($user_role); ?> • Member since <?php echo isset($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : '2024'; ?>
            </span>
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
        
        <!-- Dashboard Tabs -->
        <div class="dashboard-tabs">
            <a href="?tab=profile" class="tab-btn <?php echo $active_tab == 'profile' ? 'active' : ''; ?>">👤 Profile</a>
            <a href="?tab=farms" class="tab-btn <?php echo $active_tab == 'farms' ? 'active' : ''; ?>">🏡 Farms</a>
            <a href="?tab=crops" class="tab-btn <?php echo $active_tab == 'crops' ? 'active' : ''; ?>">🌱 Crops</a>
        </div>
        
        <!-- ==================== PROFILE TAB ==================== -->
        <div id="profile-tab" class="tab-content <?php echo $active_tab == 'profile' ? 'active' : ''; ?>">
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['name'] ?? 'F', 0, 1)); ?>
                    </div>
                    <div class="profile-title">
                        <h2><?php echo htmlspecialchars($user['name'] ?? 'Farmer'); ?></h2>
                        <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                        <span class="profile-badge"><?php echo ucfirst($user['role'] ?? 'farmer'); ?></span>
                    </div>
                </div>
                
                <form method="POST" action="?tab=profile">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+254 XXX XXX XXX">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-input" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" placeholder="County, Kenya">
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">Bio / About</label>
                            <textarea name="bio" class="form-textarea" placeholder="Tell us about your farming experience..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 20px;">
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
            
            <!-- Password Change Section -->
            <div class="password-section">
                <h3 class="section-title">Change Password</h3>
                
                <form method="POST" action="?tab=profile" onsubmit="return validatePassword()">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-input" id="new_password" required>
                            <small style="color: #666;">Min. 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" id="confirm_password" required>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <button type="submit" name="change_password" class="btn btn-primary">Update Password</button>
                    </div>
                </form>
            </div>
            
            <!-- Stats Section -->
            <div class="stats-container" style="margin-top: 30px;">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($farms); ?></div>
                    <div class="stat-label">Total Farms</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($total_farm_area, 1); ?></div>
                    <div class="stat-label">Farm Area (acres)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_crops; ?></div>
                    <div class="stat-label">Total Crops</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $growing_crops; ?></div>
                    <div class="stat-label">Growing</div>
                </div>
            </div>
        </div>
        
        <!-- ==================== FARMS TAB ==================== -->
        <div id="farms-tab" class="tab-content <?php echo $active_tab == 'farms' ? 'active' : ''; ?>">
            <!-- Add/Edit Farm Form -->
            <?php if (isset($_GET['show_form']) || $edit_farm): ?>
            <div class="add-form">
                <h2 class="form-title"><?php echo $edit_farm ? 'Edit Farm' : 'Add New Farm'; ?></h2>
                <form action="?tab=farms" method="POST">
                    <?php if ($edit_farm): ?>
                    <input type="hidden" name="farm_id" value="<?php echo $edit_farm['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Farm Name *</label>
                            <input type="text" class="form-input" name="name" value="<?php echo $edit_farm['name'] ?? ''; ?>" placeholder="e.g., Green Valley Farm" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Location *</label>
                            <input type="text" class="form-input" name="location" value="<?php echo $edit_farm['location'] ?? ''; ?>" placeholder="e.g., Nakuru County, Kenya" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Area (acres) *</label>
                            <input type="number" step="0.1" class="form-input" name="area" value="<?php echo $edit_farm['area'] ?? ''; ?>" placeholder="e.g., 12.5" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Soil Type</label>
                            <input type="text" class="form-input" name="soil_type" value="<?php echo $edit_farm['soil_type'] ?? ''; ?>" placeholder="e.g., Volcanic Loam">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Irrigation System</label>
                            <select class="form-select" name="irrigation">
                                <option value="">Select Irrigation Type</option>
                                <option value="Drip System" <?php echo (isset($edit_farm) && $edit_farm['irrigation'] == 'Drip System') ? 'selected' : ''; ?>>Drip System</option>
                                <option value="Sprinkler" <?php echo (isset($edit_farm) && $edit_farm['irrigation'] == 'Sprinkler') ? 'selected' : ''; ?>>Sprinkler</option>
                                <option value="Rain-fed" <?php echo (isset($edit_farm) && $edit_farm['irrigation'] == 'Rain-fed') ? 'selected' : ''; ?>>Rain-fed</option>
                                <option value="Flood" <?php echo (isset($edit_farm) && $edit_farm['irrigation'] == 'Flood') ? 'selected' : ''; ?>>Flood</option>
                                <option value="None" <?php echo (isset($edit_farm) && $edit_farm['irrigation'] == 'None') ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Established Year</label>
                            <input type="number" class="form-input" name="established_year" value="<?php echo $edit_farm['established_year'] ?? ''; ?>" placeholder="e.g., 2015" min="1900" max="2025">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Crops Grown</label>
                            <input type="text" class="form-input" name="crops" value="<?php echo $edit_farm['crops'] ?? ''; ?>" placeholder="e.g., Maize,Tomatoes,Beans (comma separated)">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?php echo (!isset($edit_farm) || $edit_farm['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (isset($edit_farm) && $edit_farm['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="<?php echo $edit_farm ? 'edit_farm' : 'add_farm'; ?>" class="btn btn-primary">
                            <?php echo $edit_farm ? 'Update Farm' : 'Save Farm'; ?>
                        </button>
                        <a href="?tab=farms" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
                <a href="?tab=farms&show_form=1" class="btn btn-primary">+ Add New Farm</a>
            </div>
            <?php endif; ?>
            
            <!-- Farms Grid -->
            <?php if (empty($farms)): ?>
            <div class="empty-state">
                <div class="empty-icon">🏡</div>
                <h2 class="empty-title">No Farms Added Yet</h2>
                <p class="empty-description">Start by adding your first farm to track its location, area, and crops.</p>
                <a href="?tab=farms&show_form=1" class="btn btn-primary">Add Your First Farm</a>
            </div>
            <?php else: ?>
            <div class="farms-grid">
                <?php foreach ($farms as $farm): ?>
                <div class="farm-card">
                    <div class="farm-header">
                        <h3 class="farm-name"><?php echo htmlspecialchars($farm['name']); ?></h3>
                        <div class="farm-location">
                            📍 <?php echo htmlspecialchars($farm['location'] ?? 'Location not set'); ?>
                        </div>
                        <span class="farm-status <?php echo ($farm['status'] ?? 'active') === 'active' ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo ucfirst($farm['status'] ?? 'active'); ?>
                        </span>
                    </div>
                    
                    <div class="farm-content">
                        <div class="farm-details">
                            <div class="farm-detail">
                                <span class="detail-label">Total Area:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($farm['area'] ?? '0'); ?> acres</span>
                            </div>
                            <div class="farm-detail">
                                <span class="detail-label">Soil Type:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($farm['soil_type'] ?? 'Not specified'); ?></span>
                            </div>
                            <div class="farm-detail">
                                <span class="detail-label">Irrigation:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($farm['irrigation'] ?? 'None'); ?></span>
                            </div>
                            <div class="farm-detail">
                                <span class="detail-label">Established:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($farm['established_year'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                        
                        <?php if (!empty($farm['crops'])): ?>
                        <div class="farm-crops">
                            <div class="crops-title">Crops Grown:</div>
                            <div class="crops-list">
                                <?php 
                                $crops_list = explode(',', $farm['crops']);
                                foreach ($crops_list as $crop): 
                                ?>
                                <span class="crop-tag"><?php echo trim(htmlspecialchars($crop)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="farm-actions">
                            <a href="?tab=crops&farm_filter=<?php echo $farm['id']; ?>" class="btn btn-secondary" style="flex: 1;">View Crops</a>
                            <a href="?tab=farms&edit_farm=<?php echo $farm['id']; ?>" class="btn btn-outline">Edit</a>
                            <a href="?tab=farms&delete_farm=<?php echo $farm['id']; ?>" class="btn btn-outline" onclick="return confirm('Are you sure you want to delete this farm?')">Delete</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- ==================== CROPS TAB ==================== -->
        <div id="crops-tab" class="tab-content <?php echo $active_tab == 'crops' ? 'active' : ''; ?>">
            <!-- Filter Bar -->
            <div class="filter-bar">
                <select class="filter-select" onchange="window.location.href='?tab=crops&farm_filter='+this.value">
                    <option value="0">All Farms</option>
                    <?php foreach ($farms as $farm): ?>
                    <option value="<?php echo $farm['id']; ?>" <?php echo ($farm_filter == $farm['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($farm['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Add/Edit Crop Form -->
            <?php if (isset($_GET['show_crop_form']) || $edit_crop): ?>
            <div class="add-form">
                <h2 class="form-title"><?php echo $edit_crop ? 'Edit Crop' : 'Add New Crop'; ?></h2>
                <form action="?tab=crops<?php echo $farm_filter ? '&farm_filter='.$farm_filter : ''; ?>" method="POST">
                    <?php if ($edit_crop): ?>
                    <input type="hidden" name="crop_id" value="<?php echo $edit_crop['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Crop Name *</label>
                            <input type="text" class="form-input" name="crop_name" value="<?php echo $edit_crop['crop_name'] ?? ''; ?>" placeholder="e.g., Maize, Coffee, Tomatoes" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Crop Type</label>
                            <input type="text" class="form-input" name="crop_type" value="<?php echo $edit_crop['crop_type'] ?? ''; ?>" placeholder="e.g., Cereal, Vegetable, Fruit">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Farm Location</label>
                            <select class="form-select" name="farm_id">
                                <option value="">Select Farm (Optional)</option>
                                <?php foreach ($farms as $farm): ?>
                                <option value="<?php echo $farm['id']; ?>" 
                                    <?php 
                                    if (isset($edit_crop) && $edit_crop['farm_id'] == $farm['id']) echo 'selected';
                                    elseif ($farm_filter == $farm['id'] && !isset($edit_crop)) echo 'selected';
                                    ?>>
                                    <?php echo htmlspecialchars($farm['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Area (acres) *</label>
                            <input type="number" step="0.1" class="form-input" name="area" value="<?php echo $edit_crop['area'] ?? ''; ?>" placeholder="e.g., 2.5" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Planting Date</label>
                            <input type="date" class="form-input" name="planting_date" value="<?php echo $edit_crop['planting_date'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Expected Harvest</label>
                            <input type="date" class="form-input" name="expected_harvest" value="<?php echo $edit_crop['expected_harvest'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="planning" <?php echo (isset($edit_crop) && $edit_crop['status'] == 'planning') ? 'selected' : ''; ?>>Planning</option>
                                <option value="growing" <?php echo (!isset($edit_crop) || $edit_crop['status'] == 'growing') ? 'selected' : ''; ?>>Growing</option>
                                <option value="harvested" <?php echo (isset($edit_crop) && $edit_crop['status'] == 'harvested') ? 'selected' : ''; ?>>Harvested</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Growth Progress (%)</label>
                            <input type="number" class="form-input" name="growth_progress" value="<?php echo $edit_crop['growth_progress'] ?? 0; ?>" min="0" max="100">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea class="form-textarea" name="notes" placeholder="Any additional notes about this crop..."><?php echo $edit_crop['notes'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="<?php echo $edit_crop ? 'edit_crop' : 'add_crop'; ?>" class="btn btn-primary">
                            <?php echo $edit_crop ? 'Update Crop' : 'Save Crop'; ?>
                        </button>
                        <a href="?tab=crops<?php echo $farm_filter ? '&farm_filter='.$farm_filter : ''; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
                <a href="?tab=crops&show_crop_form=1<?php echo $farm_filter ? '&farm_filter='.$farm_filter : ''; ?>" class="btn btn-primary">+ Add New Crop</a>
            </div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_crops; ?></div>
                    <div class="stat-label">Total Crops</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $growing_crops; ?></div>
                    <div class="stat-label">Currently Growing</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $harvested_crops; ?></div>
                    <div class="stat-label">Harvested</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($total_crop_area, 1); ?></div>
                    <div class="stat-label">Total Area (acres)</div>
                </div>
            </div>
            
            <!-- Crops Grid -->
            <?php if (empty($crops)): ?>
            <div class="empty-state">
                <div class="empty-icon">🌱</div>
                <h2 class="empty-title">No Crops Added Yet</h2>
                <p class="empty-description">Start by adding your first crop to track its growth, harvest dates, and progress.</p>
                <a href="?tab=crops&show_crop_form=1" class="btn btn-primary">Add Your First Crop</a>
            </div>
            <?php else: ?>
            <div class="crops-grid">
                <?php foreach ($crops as $crop): 
                    $progress = $crop['growth_progress'] ?? 0;
                    if ($crop['status'] == 'harvested') $progress = 100;
                    $icon = getCropIcon($crop['crop_name']);
                ?>
                <div class="crop-card">
                    <div class="crop-image"><?php echo $icon; ?></div>
                    <div class="crop-content">
                        <div class="crop-header">
                            <h3 class="crop-name"><?php echo htmlspecialchars($crop['crop_name']); ?></h3>
                            <span class="crop-status <?php echo getStatusClass($crop['status']); ?>">
                                <?php echo ucfirst($crop['status']); ?>
                            </span>
                        </div>
                        
                        <div class="crop-details">
                            <div class="crop-detail">
                                <span class="detail-label">Farm:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($crop['farm_name'] ?? 'Not assigned'); ?></span>
                            </div>
                            <div class="crop-detail">
                                <span class="detail-label">Planted:</span>
                                <span class="detail-value"><?php echo !empty($crop['planting_date']) ? date('M d, Y', strtotime($crop['planting_date'])) : 'Not set'; ?></span>
                            </div>
                            <div class="crop-detail">
                                <span class="detail-label">Harvest:</span>
                                <span class="detail-value"><?php echo !empty($crop['expected_harvest']) ? date('M d, Y', strtotime($crop['expected_harvest'])) : 'Not set'; ?></span>
                            </div>
                            <div class="crop-detail">
                                <span class="detail-label">Area:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($crop['area'] ?? '0'); ?> acres</span>
                            </div>
                        </div>
                        
                        <div class="crop-progress">
                            <div class="progress-label">
                                <span>Growth Progress</span>
                                <span><?php echo $progress; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($crop['notes'])): ?>
                        <div class="crop-notes">
                            <strong>Notes:</strong> <?php echo htmlspecialchars(substr($crop['notes'], 0, 100)) . (strlen($crop['notes']) > 100 ? '...' : ''); ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Quick Progress Update Form -->
                        <form method="POST" action="?tab=crops<?php echo $farm_filter ? '&farm_filter='.$farm_filter : ''; ?>" style="margin: 15px 0;">
                            <input type="hidden" name="crop_id" value="<?php echo $crop['id']; ?>">
                            <div style="display: flex; gap: 10px;">
                                <input type="number" name="growth_progress" class="form-input" value="<?php echo $crop['growth_progress'] ?? 0; ?>" min="0" max="100" style="flex: 1;">
                                <button type="submit" name="update_progress" class="btn btn-sm btn-secondary">Update</button>
                            </div>
                        </form>
                        
                        <div class="crop-actions">
                            <a href="?tab=crops&edit_crop=<?php echo $crop['id']; ?><?php echo $farm_filter ? '&farm_filter='.$farm_filter : ''; ?>" class="btn btn-secondary" style="flex: 1;">Edit</a>
                            <a href="?tab=crops&delete_crop=<?php echo $crop['id']; ?><?php echo $farm_filter ? '&farm_filter='.$farm_filter : ''; ?>" class="btn btn-outline" onclick="return confirm('Are you sure you want to delete this crop?')">Delete</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <footer>
        <p>© 2024 VEDARA Farmer Dashboard. All rights reserved.</p>
    </footer>
    
    <script>
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
    </script>
    <!-- AI Chatbot Section -->
<style>
.chatbot-toggle {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2a7d2e, #1e5a21);
    color: white;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(42, 125, 46, 0.3);
    font-size: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    z-index: 1000;
}

.chatbot-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(42, 125, 46, 0.4);
}

.chatbot-container {
    position: fixed;
    bottom: 100px;
    right: 30px;
    width: 350px;
    height: 500px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 1000;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.chatbot-container.open {
    display: flex;
}

.chatbot-header {
    background: linear-gradient(135deg, #2a7d2e, #1e5a21);
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chatbot-header h3 {
    margin: 0;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chatbot-header h3 i {
    font-size: 20px;
}

.chatbot-close {
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
    padding: 0 5px;
}

.chatbot-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.message {
    display: flex;
    flex-direction: column;
    max-width: 80%;
}

.message.user {
    align-self: flex-end;
}

.message.bot {
    align-self: flex-start;
}

.message-content {
    padding: 12px 15px;
    border-radius: 15px;
    font-size: 14px;
    line-height: 1.5;
    white-space: pre-line;
}

.message.user .message-content {
    background: #2a7d2e;
    color: white;
    border-bottom-right-radius: 5px;
}

.message.bot .message-content {
    background: white;
    color: #333;
    border-bottom-left-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.message-time {
    font-size: 11px;
    color: #999;
    margin-top: 5px;
    align-self: flex-end;
}

.typing-indicator {
    display: flex;
    gap: 5px;
    padding: 12px 15px;
    background: white;
    border-radius: 15px;
    width: fit-content;
}

.typing-indicator span {
    width: 8px;
    height: 8px;
    background: #999;
    border-radius: 50%;
    animation: typing 1s infinite;
}

.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
    0%, 100% { transform: translateY(0); opacity: 0.5; }
    50% { transform: translateY(-5px); opacity: 1; }
}

.chatbot-input-area {
    padding: 15px;
    background: white;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
}

.chatbot-input {
    flex: 1;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    transition: all 0.3s;
}

.chatbot-input:focus {
    border-color: #2a7d2e;
    box-shadow: 0 0 0 3px rgba(42, 125, 46, 0.1);
}

.chatbot-send {
    background: #2a7d2e;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0 15px;
    cursor: pointer;
    transition: all 0.3s;
}

.chatbot-send:hover {
    background: #1e5a21;
}

.chatbot-send:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.quick-questions {
    padding: 10px 15px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.quick-btn {
    background: white;
    border: 1px solid #2a7d2e;
    color: #2a7d2e;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s;
}

.quick-btn:hover {
    background: #2a7d2e;
    color: white;
}

@media (max-width: 576px) {
    .chatbot-container {
        width: 100%;
        height: 100%;
        bottom: 0;
        right: 0;
        border-radius: 0;
    }
    
    .chatbot-toggle {
        bottom: 80px;
        right: 20px;
    }
}
</style>

<!-- Chatbot Toggle Button -->
<button class="chatbot-toggle" id="chatbotToggle">
    <i class="fas fa-comment-dots"></i>
</button>

<!-- Chatbot Container -->
<div class="chatbot-container" id="chatbotContainer">
    <div class="chatbot-header">
        <h3>
            <i class="fas fa-robot"></i>
            VEDARA AI Assistant
        </h3>
        <button class="chatbot-close" id="chatbotClose">&times;</button>
    </div>
    
    <div class="chatbot-messages" id="chatbotMessages">
        <div class="message bot">
            <div class="message-content">
                👋 **Hello!** I'm VEDARA AI, your farming assistant.
                
                You can ask me about:
                • Crop advice (maize, coffee, tomatoes)
                • Pest and disease control
                • Weather and planting timing
                • Fertilizer recommendations
                • Market prices
                
                How can I help you today?
            </div>
            <div class="message-time">Just now</div>
        </div>
    </div>
    
    <div class="quick-questions">
        <button class="quick-btn" onclick="askQuickQuestion('How to plant maize?')">🌽 Maize</button>
        <button class="quick-btn" onclick="askQuickQuestion('Tomato diseases?')">🍅 Tomatoes</button>
        <button class="quick-btn" onclick="askQuickQuestion('Coffee prices?')">☕ Coffee</button>
        <button class="quick-btn" onclick="askQuickQuestion('Fertilizer for vegetables?')">🧪 Fertilizer</button>
        <button class="quick-btn" onclick="askQuickQuestion('Current weather?')">⛅ Weather</button>
        <button class="quick-btn" onclick="askQuickQuestion('Pest control?')">🐛 Pests</button>
    </div>
    
    <div class="chatbot-input-area">
        <input type="text" class="chatbot-input" id="chatbotInput" 
               placeholder="Ask about farming..." 
               onkeypress="if(event.key === 'Enter') sendMessage()">
        <button class="chatbot-send" id="chatbotSend" onclick="sendMessage()">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<script>
// Chatbot functionality
const chatbotToggle = document.getElementById('chatbotToggle');
const chatbotContainer = document.getElementById('chatbotContainer');
const chatbotClose = document.getElementById('chatbotClose');
const chatbotMessages = document.getElementById('chatbotMessages');
const chatbotInput = document.getElementById('chatbotInput');
const chatbotSend = document.getElementById('chatbotSend');

// Toggle chat
chatbotToggle.addEventListener('click', () => {
    chatbotContainer.classList.add('open');
    chatbotToggle.style.display = 'none';
});

chatbotClose.addEventListener('click', () => {
    chatbotContainer.classList.remove('open');
    chatbotToggle.style.display = 'flex';
});

// Close when clicking outside (optional)
document.addEventListener('click', (e) => {
    if (!chatbotContainer.contains(e.target) && 
        !chatbotToggle.contains(e.target) && 
        chatbotContainer.classList.contains('open')) {
        chatbotContainer.classList.remove('open');
        chatbotToggle.style.display = 'flex';
    }
});

// Send message function
async function sendMessage() {
    const message = chatbotInput.value.trim();
    if (!message) return;
    
    // Clear input
    chatbotInput.value = '';
    
    // Disable send button
    chatbotSend.disabled = true;
    
    // Add user message
    addMessage(message, 'user');
    
    // Show typing indicator
    const typingId = showTypingIndicator();
    
    try {
        // Send to backend
        const response = await fetch('ai_chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ question: message })
        });
        
        const data = await response.json();
        
        // Remove typing indicator
        removeTypingIndicator(typingId);
        
        if (data.success) {
            addMessage(data.response, 'bot', data.timestamp);
        } else {
            addMessage('Sorry, I encountered an error. Please try again.', 'bot');
        }
    } catch (error) {
        removeTypingIndicator(typingId);
        addMessage('Network error. Please check your connection.', 'bot');
    }
    
    // Re-enable send button
    chatbotSend.disabled = false;
}

// Quick question function
function askQuickQuestion(question) {
    chatbotInput.value = question;
    sendMessage();
}

// Add message to chat
function addMessage(text, sender, time = null) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}`;
    
    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content';
    // Convert markdown-style formatting
    let formattedText = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    formattedText = formattedText.replace(/\n/g, '<br>');
    contentDiv.innerHTML = formattedText;
    
    const timeDiv = document.createElement('div');
    timeDiv.className = 'message-time';
    timeDiv.textContent = time || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    messageDiv.appendChild(contentDiv);
    messageDiv.appendChild(timeDiv);
    chatbotMessages.appendChild(messageDiv);
    
    // Scroll to bottom
    chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
}

// Show typing indicator
function showTypingIndicator() {
    const id = 'typing-' + Date.now();
    const typingDiv = document.createElement('div');
    typingDiv.className = 'message bot';
    typingDiv.id = id;
    typingDiv.innerHTML = `
        <div class="typing-indicator">
            <span></span>
            <span></span>
            <span></span>
        </div>
    `;
    chatbotMessages.appendChild(typingDiv);
    chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    return id;
}

// Remove typing indicator
function removeTypingIndicator(id) {
    const element = document.getElementById(id);
    if (element) element.remove();
}

// Load chat history (optional)
async function loadChatHistory() {
    try {
        const response = await fetch('get_chat_history.php');
        const data = await response.json();
        if (data.success && data.messages) {
            // Clear welcome message
            chatbotMessages.innerHTML = '';
            // Load last 10 messages
            data.messages.slice(-10).forEach(msg => {
                addMessage(msg.user_message, 'user', msg.timestamp);
                addMessage(msg.ai_response, 'bot', msg.timestamp);
            });
        }
    } catch (error) {
        console.log('Could not load chat history');
    }
}

// Optional: Load history when chat opens
// chatbotToggle.addEventListener('click', loadChatHistory);
</script>
</body>
</html>