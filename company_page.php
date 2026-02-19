<?php
session_start();
require_once "config.php";

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and is a company
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
    if ($user_data['role'] !== 'company') {
        if ($user_data['role'] === 'farmer') {
            header('Location: farmer_page.php');
        } elseif ($user_data['role'] === 'contractor') {
            header('Location: contractor_page.php');
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

/* ==================== COMPANY TABLES ==================== */

// Create company tables if they don't exist
$conn->query("CREATE TABLE IF NOT EXISTS company_available_farms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT,
    farm_name VARCHAR(255) NOT NULL,
    farmer_name VARCHAR(255),
    farmer_id INT,
    crop_type VARCHAR(100),
    acres DECIMAL(10,2),
    location VARCHAR(255),
    expected_yield VARCHAR(100),
    ai_score INT,
    readiness VARCHAR(100),
    price DECIMAL(10,2),
    price_currency VARCHAR(10) DEFAULT 'KSh',
    status ENUM('ready', 'ai-verified', 'pending', 'harvested') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS company_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE,
    farm_name VARCHAR(255),
    farm_id INT,
    crop_type VARCHAR(100),
    quantity VARCHAR(100),
    amount DECIMAL(10,2),
    order_date DATE,
    status ENUM('processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'processing',
    tracking_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS company_smart_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    contract_number VARCHAR(50) UNIQUE,
    farm_name VARCHAR(255),
    farm_id INT,
    crop_type VARCHAR(100),
    amount DECIMAL(10,2),
    duration VARCHAR(100),
    terms TEXT,
    status VARCHAR(50) DEFAULT 'Active',
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS company_ai_validation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    farm_name VARCHAR(255),
    farm_id INT,
    crop_type VARCHAR(100),
    maturity_score INT,
    quality_grade VARCHAR(10),
    recommendation TEXT,
    confidence VARCHAR(50),
    analysis_date DATE,
    report_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS company_supply_chain (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    order_id INT,
    stage VARCHAR(100),
    location VARCHAR(255),
    status VARCHAR(50),
    estimated_delivery DATE,
    actual_delivery DATE,
    tracking_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS company_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    order_id INT,
    contract_id INT,
    amount DECIMAL(10,2),
    payment_date DATE,
    status VARCHAR(50) DEFAULT 'completed',
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES users(id) ON DELETE CASCADE
)");

/* ==================== PROFILE HANDLING ==================== */

// Handle Profile Update
if (isset($_POST['update_profile'])) {
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
    $registration_no = mysqli_real_escape_string($conn, $_POST['registration_no'] ?? '');
    $year_established = intval($_POST['year_established'] ?? 0);
    $business_type = mysqli_real_escape_string($conn, $_POST['business_type'] ?? '');
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    
    // Check if email exists for another user
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    if ($checkEmail) {
        $checkEmail->bind_param("si", $email, $user_id);
        $checkEmail->execute();
        $result = $checkEmail->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = 'Email already exists for another user!';
        } else {
            // Add company-specific columns if they don't exist
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) AFTER email");
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(100) AFTER phone");
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS registration_no VARCHAR(100) AFTER location");
            $conn->query("ALTER TABLE USERS ADD COLUMN IF NOT EXISTS year_established INT AFTER registration_no");
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS business_type VARCHAR(100) AFTER year_established");
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS description TEXT AFTER business_type");
            
            $update = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, location = ?, registration_no = ?, year_established = ?, business_type = ?, description = ? WHERE id = ?");
            if ($update) {
                $update->bind_param("sssssissi", $company_name, $email, $phone, $location, $registration_no, $year_established, $business_type, $description, $user_id);
                
                if ($update->execute()) {
                    $_SESSION['name'] = $company_name;
                    $_SESSION['email'] = $email;
                    $success_message = 'Company profile updated successfully!';
                    
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

/* ==================== ORDER HANDLING ==================== */

// Handle Create Order
if (isset($_POST['create_order'])) {
    $farm_id = intval($_POST['farm_id']);
    $farm_name = mysqli_real_escape_string($conn, $_POST['farm_name']);
    $crop_type = mysqli_real_escape_string($conn, $_POST['crop_type']);
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $amount = floatval($_POST['amount']);
    $order_date = date('Y-m-d');
    
    // Generate unique order number
    $order_number = 'ORD-' . date('Ymd') . '-' . rand(100, 999);
    
    $stmt = $conn->prepare("INSERT INTO company_orders (company_id, order_number, farm_name, farm_id, crop_type, quantity, amount, order_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'processing')");
    if ($stmt) {
        $stmt->bind_param("ississds", $user_id, $order_number, $farm_name, $farm_id, $crop_type, $quantity, $amount, $order_date);
        
        if ($stmt->execute()) {
            $order_id = $stmt->insert_id;
            $success_message = "Order created successfully! Order Number: $order_number";
            
            // Create smart contract automatically
            $contract_number = 'CT-' . date('Ymd') . '-' . rand(100, 999);
            $terms = "Purchase agreement for $quantity of $crop_type from $farm_name. Delivery within 30 days.";
            $end_date = date('Y-m-d', strtotime('+30 days'));
            
            $contract = $conn->prepare("INSERT INTO company_smart_contracts (company_id, contract_number, farm_name, farm_id, crop_type, amount, duration, terms, status, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, '30 days', ?, 'Active', ?, ?)");
            if ($contract) {
                $contract->bind_param("ississdsss", $user_id, $contract_number, $farm_name, $farm_id, $crop_type, $amount, $terms, $order_date, $end_date);
                $contract->execute();
                $contract->close();
            }
            
            // Update farm status
            $conn->query("UPDATE company_available_farms SET status = 'harvested' WHERE id = $farm_id");
            
        } else {
            $error_message = 'Error creating order: ' . $conn->error;
        }
        $stmt->close();
    }
}

// Handle Update Order Status
if (isset($_POST['update_order_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $tracking = mysqli_real_escape_string($conn, $_POST['tracking_info'] ?? '');
    
    $update = $conn->prepare("UPDATE company_orders SET status = ?, tracking_info = ? WHERE id = ? AND company_id = ?");
    if ($update) {
        $update->bind_param("ssii", $new_status, $tracking, $order_id, $user_id);
        
        if ($update->execute()) {
            $success_message = "Order status updated to $new_status";
        } else {
            $error_message = 'Error updating order: ' . $conn->error;
        }
        $update->close();
    }
}

/* ==================== FETCH DATA ==================== */

// Get available farms (from farms table or sample data)
$available_farms = [];
$farm_query = "SELECT f.*, u.name as farmer_name FROM farms f LEFT JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC";
$farm_result = $conn->query($farm_query);
if ($farm_result && $farm_result->num_rows > 0) {
    while ($row = $farm_result->fetch_assoc()) {
        // Add AI score and other company-specific fields
        $row['ai_score'] = rand(90, 99); // Simulated AI score
        $row['price'] = ($row['area'] ?? 0) * 30000; // Estimated price based on area
        $row['expected_yield'] = isset($row['area']) ? round($row['area'] * 0.8, 1) . ' tons' : 'N/A';
        $row['status'] = $row['ai_score'] > 95 ? 'ai-verified' : ($row['ai_score'] > 90 ? 'ready' : 'pending');
        $available_farms[] = $row;
    }
}

// If no farms in database, use sample data
if (empty($available_farms)) {
    $sample_farms = [
        ['id' => 1, 'name' => 'Green Valley Farm', 'farmer_name' => 'James Mwangi', 'crop_type' => 'Maize', 'area' => 25, 'location' => 'Kiambu', 'expected_yield' => '12 tons', 'ai_score' => 98, 'status' => 'ready', 'price' => 450000, 'created_at' => date('Y-m-d H:i:s', strtotime('-30 days'))],
        ['id' => 2, 'name' => 'Sunrise Coffee Estate', 'farmer_name' => 'Sarah Ochieng', 'crop_type' => 'Coffee', 'area' => 15, 'location' => 'Thika', 'expected_yield' => '8 tons', 'ai_score' => 95, 'status' => 'growing', 'price' => 320000, 'created_at' => date('Y-m-d H:i:s', strtotime('-45 days'))],
        ['id' => 3, 'name' => 'Golden Fields Farm', 'farmer_name' => 'Peter Kamau', 'crop_type' => 'Wheat', 'area' => 40, 'location' => 'Nakuru', 'expected_yield' => '25 tons', 'ai_score' => 92, 'status' => 'growing', 'price' => 750000, 'created_at' => date('Y-m-d H:i:s', strtotime('-20 days'))],
        ['id' => 4, 'name' => 'River Side Farm', 'farmer_name' => 'Mary Wanjiku', 'crop_type' => 'Tomatoes', 'area' => 10, 'location' => 'Murang\'a', 'expected_yield' => '5 tons', 'ai_score' => 96, 'status' => 'ready', 'price' => 350000, 'created_at' => date('Y-m-d H:i:s', strtotime('-60 days'))],
        ['id' => 5, 'name' => 'Highland Tea Plantation', 'farmer_name' => 'John Kiprop', 'crop_type' => 'Tea', 'area' => 30, 'location' => 'Kericho', 'expected_yield' => '18 tons', 'ai_score' => 97, 'status' => 'growing', 'price' => 890000, 'created_at' => date('Y-m-d H:i:s', strtotime('-100 days'))]
    ];
    
    $available_farms = $sample_farms;
}

// Get company orders
$orders = [];
$order_query = "SELECT * FROM company_orders WHERE company_id = ? ORDER BY order_date DESC";
$order_stmt = $conn->prepare($order_query);
if ($order_stmt) {
    $order_stmt->bind_param("i", $user_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    while ($row = $order_result->fetch_assoc()) {
        $orders[] = $row;
    }
    $order_stmt->close();
}

// If no orders, use sample data
if (empty($orders)) {
    $sample_orders = [
        ['order_number' => 'ORD-001', 'farm_name' => 'Green Valley Farm', 'crop_type' => 'Maize', 'quantity' => '10 tons', 'amount' => 450000, 'order_date' => '2024-06-10', 'status' => 'delivered'],
        ['order_number' => 'ORD-002', 'farm_name' => 'Sunrise Coffee Estate', 'crop_type' => 'Coffee', 'quantity' => '5 tons', 'amount' => 320000, 'order_date' => '2024-06-12', 'status' => 'shipped'],
        ['order_number' => 'ORD-003', 'farm_name' => 'Highland Tea Plantation', 'crop_type' => 'Tea', 'quantity' => '8 tons', 'amount' => 480000, 'order_date' => '2024-06-15', 'status' => 'processing'],
        ['order_number' => 'ORD-004', 'farm_name' => 'Fruitful Harvest', 'crop_type' => 'Avocado', 'quantity' => '3 tons', 'amount' => 210000, 'order_date' => '2024-06-05', 'status' => 'delivered']
    ];
    
    $orders = $sample_orders;
}

// Get smart contracts
$contracts = [];
$contract_query = "SELECT * FROM company_smart_contracts WHERE company_id = ? ORDER BY created_at DESC";
$contract_stmt = $conn->prepare($contract_query);
if ($contract_stmt) {
    $contract_stmt->bind_param("i", $user_id);
    $contract_stmt->execute();
    $contract_result = $contract_stmt->get_result();
    while ($row = $contract_result->fetch_assoc()) {
        $contracts[] = $row;
    }
    $contract_stmt->close();
}

// If no contracts, use sample data
if (empty($contracts)) {
    $sample_contracts = [
        ['contract_number' => 'CT-001', 'farm_name' => 'Green Valley Farm', 'crop_type' => 'Maize', 'amount' => 450000, 'duration' => '30 days', 'status' => 'Active', 'terms' => 'Harvest and delivery by June 30'],
        ['contract_number' => 'CT-002', 'farm_name' => 'Sunrise Coffee Estate', 'crop_type' => 'Coffee', 'amount' => 320000, 'duration' => '15 days', 'status' => 'Active', 'terms' => 'Quality grade AA, delivered to Nairobi']
    ];
    
    $contracts = $sample_contracts;
}

// Get AI validation results
$ai_results = [];
$ai_query = "SELECT * FROM company_ai_validation WHERE company_id = ? ORDER BY analysis_date DESC";
$ai_stmt = $conn->prepare($ai_query);
if ($ai_stmt) {
    $ai_stmt->bind_param("i", $user_id);
    $ai_stmt->execute();
    $ai_result = $ai_stmt->get_result();
    while ($row = $ai_result->fetch_assoc()) {
        $ai_results[] = $row;
    }
    $ai_stmt->close();
}

// If no AI results, use sample data
if (empty($ai_results)) {
    $sample_ai = [
        ['farm_name' => 'Green Valley Farm', 'crop_type' => 'Maize', 'maturity_score' => 98, 'quality_grade' => 'Grade A', 'recommendation' => 'Harvest in 3 days', 'confidence' => 'High'],
        ['farm_name' => 'River Side Farm', 'crop_type' => 'Tomatoes', 'maturity_score' => 95, 'quality_grade' => 'Grade AA', 'recommendation' => 'Harvest now', 'confidence' => 'Very High'],
        ['farm_name' => 'Sunrise Coffee Estate', 'crop_type' => 'Coffee', 'maturity_score' => 96, 'quality_grade' => 'Grade A', 'recommendation' => 'Ready for picking', 'confidence' => 'High']
    ];
    
    $ai_results = $sample_ai;
}

/* ==================== REAL STATS FROM DATABASE ==================== */

// 1. Total available farms (count ALL farms from the farms table)
$farms_query = "SELECT COUNT(*) as total FROM farms";
$farms_result = $conn->query($farms_query);
$available_farms_count = ($farms_result && $farms_result->num_rows > 0) ? $farms_result->fetch_assoc()['total'] : 0;

// 2. Active contracts (count company's contracts with status 'Active')
$active_contracts_count = 0;
if (isset($user_id)) {
    $contracts_count_query = "SELECT COUNT(*) as total FROM company_smart_contracts WHERE company_id = $user_id AND status = 'Active'";
    $contracts_count_result = $conn->query($contracts_count_query);
    $active_contracts_count = ($contracts_count_result && $contracts_count_result->num_rows > 0) ? $contracts_count_result->fetch_assoc()['total'] : 0;
}

// 3. Pending orders (count company's orders with status 'processing')
$pending_orders_count = 0;
if (isset($user_id)) {
    $pending_count_query = "SELECT COUNT(*) as total FROM company_orders WHERE company_id = $user_id AND status = 'processing'";
    $pending_count_result = $conn->query($pending_count_query);
    $pending_orders_count = ($pending_count_result && $pending_count_result->num_rows > 0) ? $pending_count_result->fetch_assoc()['total'] : 0;
}

// 4. Monthly spend (sum of THIS company's order amounts for current month)
$monthly_spend = 0;
if (isset($user_id)) {
    $current_month = date('Y-m');
    $spend_query = "SELECT SUM(amount) as total FROM company_orders WHERE company_id = $user_id AND DATE_FORMAT(order_date, '%Y-%m') = '$current_month'";
    $spend_result = $conn->query($spend_query);
    $monthly_spend = ($spend_result && $spend_result->num_rows > 0) ? $spend_result->fetch_assoc()['total'] : 0;
}

// If still zero, set a default message but keep it at 0
if ($monthly_spend == 0) {
    $monthly_spend = 0; // No default fake number
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VEDARA - Company Dashboard</title>
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
        
        .btn-sm {
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
            color: #2196f3;
            font-weight: 600;
            font-size: 14px;
            background-color: rgba(33, 150, 243, 0.1);
            padding: 4px 12px;
            border-radius: 50px;
            display: inline-block;
        }
        
        .company-info {
            font-size: 12px;
            color: var(--gray);
            margin-top: 5px;
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
            border-bottom: 1px solid var(--light-gray);
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
            align-items: center;
            flex-wrap: wrap;
        }
        
        /* Export Dropdown */
        .export-container {
            position: relative;
            display: inline-block;
        }
        
        .export-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 5px;
            background-color: white;
            min-width: 240px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            border-radius: 8px;
            z-index: 1000;
            overflow: hidden;
            border: 1px solid var(--light-gray);
        }
        
        .export-dropdown a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            text-decoration: none;
            color: #333;
            transition: background-color 0.2s;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .export-dropdown a:last-child {
            border-bottom: none;
        }
        
        .export-dropdown a:hover {
            background-color: var(--primary-light);
        }
        
        .export-dropdown i {
            margin-right: 12px;
            width: 20px;
            color: var(--primary);
            font-size: 16px;
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
        
        /* Available Farms Section */
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
        
        .farms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .farm-card {
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .farm-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .farm-header {
            padding: 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .farm-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .farm-status {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-ready {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-ai-verified {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-harvested {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .farm-details {
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
        
        .ai-score {
            display: inline-block;
            padding: 4px 8px;
            background-color: var(--primary-light);
            border-radius: 4px;
            color: var(--primary);
            font-weight: 600;
        }
        
        .farm-actions {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-top: 1px solid var(--light-gray);
            display: flex;
            gap: 10px;
        }
        
        /* Orders Table */
        .table-container {
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .table-header h2 {
            font-size: 22px;
            color: var(--dark);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background-color: #f8f9fa;
        }
        
        th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 1px solid var(--light-gray);
        }
        
        td {
            padding: 15px 20px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .order-status {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-processing {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-shipped {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-delivered {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* AI Validation Dashboard */
        .ai-dashboard {
            background: var(--white);
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .ai-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .ai-header h2 {
            font-size: 22px;
            color: var(--dark);
        }
        
        .ai-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .ai-metric {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        
        .ai-metric h3 {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .ai-metric p {
            color: var(--gray);
            font-size: 14px;
        }
        
        /* Smart Contracts */
        .contracts-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .contract-card {
            background: var(--white);
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
        }
        
        .contract-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .contract-details {
            margin-bottom: 20px;
        }
        
        .contract-detail {
            margin-bottom: 8px;
            font-size: 14px;
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
        
        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
        }
        
        .form-control, .form-select, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 16px;
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(42, 125, 46, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        /* Profile Section */
        .profile-container {
            background: var(--white);
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
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
            color: #2196f3;
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
            
            .farms-grid, .contracts-container {
                grid-template-columns: 1fr;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .export-dropdown {
                right: auto;
                left: 0;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
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
                flex-wrap: wrap;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <a href="company_page.php" class="logo">VEDARA</a>
        
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-building"></i>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($user['name'] ?? 'Company Name'); ?></div>
            <div class="user-role">AGRIBUSINESS</div>
            <div class="company-info"><?php echo htmlspecialchars($user['business_type'] ?? 'Procurement Manager'); ?></div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a class="nav-link active" data-section="dashboard">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-section="farms">
                    <i class="fas fa-tractor nav-icon"></i>
                    Available Farms
                    <span class="notification-badge"><?php echo $available_farms_count; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-section="orders">
                    <i class="fas fa-shopping-cart nav-icon"></i>
                    My Orders
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-section="contracts">
                    <i class="fas fa-file-contract nav-icon"></i>
                    Smart Contracts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-section="ai-validation">
                    <i class="fas fa-robot nav-icon"></i>
                    AI Validation
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-section="profile">
                    <i class="fas fa-user-cog nav-icon"></i>
                    Company Profile
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
                <h1 id="pageTitle">Company Dashboard</h1>
                <p id="pageSubtitle">Manage your farm procurement, contracts, and supply chain</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" id="newOrderBtn">
                    <i class="fas fa-plus"></i> New Procurement
                </button>
                
                <!-- UPDATED: Single export button with dropdown -->
                <div class="export-container">
                    <button class="btn" id="exportReportBtn">
                        <i class="fas fa-download"></i> Export Reports ▼
                    </button>
                    <div class="export-dropdown" id="exportDropdown">
                        <a href="export_company.php?type=farms" onclick="return confirmExport('farms')">
                            <i class="fas fa-tractor"></i> Farms Report (CSV)
                        </a>
                        <a href="export_company.php?type=orders" onclick="return confirmExport('orders')">
                            <i class="fas fa-shopping-cart"></i> Orders Report (CSV)
                        </a>
                        <a href="export_company.php?type=contracts" onclick="return confirmExport('contracts')">
                            <i class="fas fa-file-contract"></i> Contracts Report (CSV)
                        </a>
                        <a href="export_company.php?type=complete" onclick="return confirmExport('complete')">
                            <i class="fas fa-database"></i> Complete Data (CSV)
                        </a>
                    </div>
                </div>
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
        
        <!-- Dashboard Section (Default) -->
        <div id="dashboard" class="dashboard-section active">
            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-tractor"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $available_farms_count; ?></h3>
                        <p>Available Farms</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $active_contracts_count; ?></h3>
                        <p>Active Contracts</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pending_orders_count; ?></h3>
                        <p>Pending Orders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>KSh <?php echo number_format($monthly_spend); ?></h3>
                        <p>Monthly Spend</p>
                    </div>
                </div>
            </div>

            <!-- Available Farms Section -->
            <div class="section">
                <div class="section-header">
                    <h2>Available Farms</h2>
                    <button class="btn btn-outline" id="refreshFarmsBtn" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                
                <div class="farms-grid" id="availableFarmsGrid">
                    <?php 
                    $display_farms = array_slice($available_farms, 0, 6);
                    foreach ($display_farms as $farm): 
                    ?>
                    <div class="farm-card">
                        <div class="farm-header">
                            <div class="farm-title"><?php echo htmlspecialchars($farm['name'] ?? $farm['farm_name'] ?? 'Farm'); ?></div>
                            <div class="farm-status status-<?php echo $farm['status'] ?? 'pending'; ?>">
                                <?php echo strtoupper(str_replace('-', ' ', $farm['status'] ?? 'PENDING')); ?>
                            </div>
                        </div>
                        <div class="farm-details">
                            <div class="detail-item">
                                <div class="detail-label">Farmer:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($farm['farmer_name'] ?? 'N/A'); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Crop:</div>
                                <div class="detail-value">
                                    <?php 
                                    if (!empty($farm['crop_type']) && $farm['crop_type'] != 'N/A') {
                                        echo htmlspecialchars($farm['crop_type']);
                                    } elseif (!empty($farm['crops'])) {
                                        echo htmlspecialchars($farm['crops']);
                                    } else {
                                        echo 'Not specified';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Location:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($farm['location'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Yield:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($farm['expected_yield'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">AI Score:</div>
                                <div class="detail-value"><span class="ai-score"><?php echo $farm['ai_score'] ?? rand(90,99); ?>%</span></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Readiness:</div>
                                <div class="detail-value">
                                    <?php 
                                    if (isset($farm['status'])) {
                                        if ($farm['status'] == 'ready') {
                                            echo '<span style="color: #2d5016; font-weight: 600;">READY FOR HARVEST</span>';
                                        } elseif ($farm['status'] == 'growing') {
                                            if (isset($farm['created_at'])) {
                                                $planted = strtotime($farm['created_at']);
                                                $now = time();
                                                $days_growing = floor(($now - $planted) / (60 * 60 * 24));
                                                
                                                $maturity_days = 90;
                                                $crop_lower = strtolower($farm['crop_type'] ?? '');
                                                if (strpos($crop_lower, 'maize') !== false) $maturity_days = 120;
                                                elseif (strpos($crop_lower, 'tomato') !== false) $maturity_days = 75;
                                                elseif (strpos($crop_lower, 'coffee') !== false) $maturity_days = 180;
                                                elseif (strpos($crop_lower, 'bean') !== false) $maturity_days = 90;
                                                elseif (strpos($crop_lower, 'potato') !== false) $maturity_days = 100;
                                                elseif (strpos($crop_lower, 'wheat') !== false) $maturity_days = 110;
                                                elseif (strpos($crop_lower, 'tea') !== false) $maturity_days = 365;
                                                
                                                $percent_complete = min(100, round(($days_growing / $maturity_days) * 100));
                                                $days_left = max(0, $maturity_days - $days_growing);
                                                
                                                if ($percent_complete < 25) {
                                                    echo "Early growth ({$percent_complete}%) - ~{$days_left} days left";
                                                } elseif ($percent_complete < 50) {
                                                    echo "Developing ({$percent_complete}%) - ~{$days_left} days left";
                                                } elseif ($percent_complete < 75) {
                                                    echo "Maturing ({$percent_complete}%) - ~{$days_left} days left";
                                                } elseif ($percent_complete < 100) {
                                                    echo "Almost ready ({$percent_complete}%) - ~{$days_left} days left";
                                                } else {
                                                    echo "Ready for harvest (update status)";
                                                }
                                            } else {
                                                echo 'Growing (estimate)';
                                            }
                                        } elseif ($farm['status'] == 'harvested') {
                                            echo 'Harvested';
                                        } else {
                                            echo ucfirst($farm['status'] ?? 'Unknown');
                                        }
                                    } else {
                                        echo 'Status unknown';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Price:</div>
                                <div class="detail-value"><strong>KSh <?php echo number_format($farm['price'] ?? 0); ?></strong></div>
                            </div>
                        </div>
                        <div class="farm-actions">
                            <button class="btn" onclick="createOrder(<?php echo $farm['id'] ?? rand(1,100); ?>, '<?php echo htmlspecialchars($farm['name'] ?? $farm['farm_name'] ?? ''); ?>', '<?php echo htmlspecialchars($farm['crop_type'] ?? ''); ?>', '<?php echo htmlspecialchars($farm['expected_yield'] ?? ''); ?>', <?php echo $farm['price'] ?? 0; ?>)">
                                <i class="fas fa-cart-plus"></i> Create Order
                            </button>
                            <button class="btn btn-outline" onclick="viewFarmDetails(<?php echo $farm['id'] ?? rand(1,100); ?>)">
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Orders Table -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Recent Orders</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Farm</th>
                            <th>Crop</th>
                            <th>Quantity</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTable">
                        <?php 
                        $display_orders = array_slice($orders, 0, 5);
                        foreach ($display_orders as $order): 
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($order['order_number'] ?? 'ORD-000'); ?></strong></td>
                            <td><?php echo htmlspecialchars($order['farm_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['crop_type'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['quantity'] ?? 'N/A'); ?></td>
                            <td><strong>KSh <?php echo number_format($order['amount'] ?? 0); ?></strong></td>
                            <td><span class="order-status status-<?php echo $order['status'] ?? 'processing'; ?>"><?php echo strtoupper($order['status'] ?? 'PROCESSING'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- AI Validation Dashboard -->
            <div class="ai-dashboard">
                <div class="ai-header">
                    <h2>AI Crop Validation Dashboard</h2>
                    <button class="btn btn-outline" onclick="runAIAnalysis()">
                        <i class="fas fa-robot"></i> Run AI Analysis
                    </button>
                </div>
                
                <div class="ai-metrics">
                    <div class="ai-metric">
                        <h3>98%</h3>
                        <p>Accuracy Rate</p>
                    </div>
                    <div class="ai-metric">
                        <h3><?php echo $available_farms_count; ?></h3>
                        <p>Farms Analyzed</p>
                    </div>
                    <div class="ai-metric">
                        <h3><?php echo count(array_filter($available_farms, function($f) { return ($f['status'] ?? '') == 'ready' || ($f['status'] ?? '') == 'ai-verified'; })); ?></h3>
                        <p>Ready for Harvest</p>
                    </div>
                    <div class="ai-metric">
                        <h3>7 days</h3>
                        <p>Avg. Time Saved</p>
                    </div>
                </div>
                
                <div class="section-header">
                    <h3>Latest AI Validation Results</h3>
                </div>
                
                <div class="contracts-container" id="aiResults">
                    <?php 
                    $display_ai = array_slice($ai_results, 0, 2);
                    foreach ($display_ai as $ai): 
                    ?>
                    <div class="contract-card" style="border-left-color: #4caf50;">
                        <div class="contract-title"><?php echo htmlspecialchars($ai['farm_name'] ?? 'Farm'); ?></div>
                        <div class="contract-details">
                            <div class="contract-detail"><strong>Crop:</strong> <?php echo htmlspecialchars($ai['crop_type'] ?? 'N/A'); ?></div>
                            <div class="contract-detail"><strong>Maturity:</strong> <span style="color: #4caf50; font-weight: 600;"><?php echo $ai['maturity_score'] ?? 95; ?>%</span></div>
                            <div class="contract-detail"><strong>Quality:</strong> <?php echo htmlspecialchars($ai['quality_grade'] ?? 'Grade A'); ?></div>
                            <div class="contract-detail"><strong>Recommendation:</strong> <?php echo htmlspecialchars($ai['recommendation'] ?? 'Ready for harvest'); ?></div>
                            <div class="contract-detail"><strong>Confidence:</strong> <?php echo htmlspecialchars($ai['confidence'] ?? 'High'); ?></div>
                        </div>
                        <button class="btn btn-outline" onclick="viewAIReport('<?php echo htmlspecialchars($ai['farm_name'] ?? ''); ?>')" style="width: 100%;">
                            <i class="fas fa-chart-line"></i> View Full Report
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Active Smart Contracts -->
            <div class="section">
                <div class="section-header">
                    <h2>Active Smart Contracts</h2>
                    <button class="btn btn-outline" onclick="createNewContract()">
                        <i class="fas fa-file-contract"></i> Create New Contract
                    </button>
                </div>
                
                <div class="contracts-container" id="smartContracts">
                    <?php 
                    $display_contracts = array_slice($contracts, 0, 3);
                    foreach ($display_contracts as $contract): 
                    ?>
                    <div class="contract-card">
                        <div class="contract-title">Contract <?php echo htmlspecialchars($contract['contract_number'] ?? 'CT-000'); ?></div>
                        <div class="contract-details">
                            <div class="contract-detail"><strong>Farm:</strong> <?php echo htmlspecialchars($contract['farm_name'] ?? 'N/A'); ?></div>
                            <div class="contract-detail"><strong>Crop:</strong> <?php echo htmlspecialchars($contract['crop_type'] ?? 'N/A'); ?></div>
                            <div class="contract-detail"><strong>Amount:</strong> KSh <?php echo number_format($contract['amount'] ?? 0); ?></div>
                            <div class="contract-detail"><strong>Duration:</strong> <?php echo htmlspecialchars($contract['duration'] ?? '30 days'); ?></div>
                            <div class="contract-detail"><strong>Status:</strong> <span style="color: var(--primary); font-weight: 600;"><?php echo htmlspecialchars($contract['status'] ?? 'Active'); ?></span></div>
                            <div class="contract-detail"><strong>Terms:</strong> <?php echo htmlspecialchars(substr($contract['terms'] ?? '', 0, 50)) . '...'; ?></div>
                        </div>
                        <button class="btn btn-outline" onclick="viewContract('<?php echo htmlspecialchars($contract['contract_number'] ?? ''); ?>')" style="width: 100%;">
                            <i class="fas fa-file-contract"></i> View Contract
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Farms Section (Full) -->
        <div id="farms" class="dashboard-section">
            <div class="section-header">
                <h2>All Available Farms</h2>
                <button class="btn btn-outline" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <div class="farms-grid" id="allFarmsGrid">
                <?php foreach ($available_farms as $farm): ?>
                <div class="farm-card">
                    <div class="farm-header">
                        <div class="farm-title"><?php echo htmlspecialchars($farm['name'] ?? $farm['farm_name'] ?? 'Farm'); ?></div>
                        <div class="farm-status status-<?php echo $farm['status'] ?? 'pending'; ?>">
                            <?php echo strtoupper(str_replace('-', ' ', $farm['status'] ?? 'PENDING')); ?>
                        </div>
                    </div>
                    <div class="farm-details">
                        <div class="detail-item">
                            <div class="detail-label">Farmer:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($farm['farmer_name'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Crop:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($farm['crop_type'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Acres:</div>
                            <div class="detail-value"><?php echo $farm['area'] ?? '0'; ?> acres</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Location:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($farm['location'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Yield:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($farm['expected_yield'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">AI Score:</div>
                            <div class="detail-value"><span class="ai-score"><?php echo $farm['ai_score'] ?? rand(90,99); ?>%</span></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Readiness:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($farm['readiness'] ?? 'Ready now'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Price:</div>
                            <div class="detail-value"><strong>KSh <?php echo number_format($farm['price'] ?? 0); ?></strong></div>
                        </div>
                    </div>
                    <div class="farm-actions">
                        <button class="btn" onclick="createOrder(<?php echo $farm['id'] ?? rand(1,100); ?>, '<?php echo htmlspecialchars($farm['name'] ?? $farm['farm_name'] ?? ''); ?>', '<?php echo htmlspecialchars($farm['crop_type'] ?? ''); ?>', '<?php echo htmlspecialchars($farm['expected_yield'] ?? ''); ?>', <?php echo $farm['price'] ?? 0; ?>)">
                            <i class="fas fa-cart-plus"></i> Create Order
                        </button>
                        <button class="btn btn-outline" onclick="viewFarmDetails(<?php echo $farm['id'] ?? rand(1,100); ?>)">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Orders Section -->
        <div id="orders" class="dashboard-section">
            <div class="table-container">
                <div class="table-header">
                    <h2>All Orders</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Farm</th>
                            <th>Crop</th>
                            <th>Quantity</th>
                            <th>Amount</th>
                            <th>Order Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($order['order_number'] ?? 'ORD-000'); ?></strong></td>
                            <td><?php echo htmlspecialchars($order['farm_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['crop_type'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['quantity'] ?? 'N/A'); ?></td>
                            <td><strong>KSh <?php echo number_format($order['amount'] ?? 0); ?></strong></td>
                            <td><?php echo $order['order_date'] ?? date('Y-m-d'); ?></td>
                            <td><span class="order-status status-<?php echo $order['status'] ?? 'processing'; ?>"><?php echo strtoupper($order['status'] ?? 'PROCESSING'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Contracts Section -->
        <div id="contracts" class="dashboard-section">
            <div class="table-container">
                <div class="table-header">
                    <h2>Smart Contracts</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Contract #</th>
                            <th>Farm</th>
                            <th>Crop</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $contract): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($contract['contract_number'] ?? 'CT-000'); ?></strong></td>
                            <td><?php echo htmlspecialchars($contract['farm_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($contract['crop_type'] ?? 'N/A'); ?></td>
                            <td><strong>KSh <?php echo number_format($contract['amount'] ?? 0); ?></strong></td>
                            <td><span class="order-status" style="background-color: #d4edda; color: #155724;"><?php echo htmlspecialchars($contract['status'] ?? 'Active'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profile" class="dashboard-section">
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['name'] ?? 'Company Name'); ?></h2>
                        <div class="profile-role">Agribusiness</div>
                        <div class="profile-stats">
                            <div class="profile-stat">
                                <h4><?php echo count($orders); ?></h4>
                                <p>Total Orders</p>
                            </div>
                            <div class="profile-stat">
                                <h4><?php echo count($contracts); ?></h4>
                                <p>Contracts</p>
                            </div>
                            <div class="profile-stat">
                                <h4>KSh <?php echo number_format($monthly_spend); ?></h4>
                                <p>Monthly Spend</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Profile form and password change sections remain same -->
                <form method="POST" action="" class="profile-form">
                    <h3>Company Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Company Name</label>
                            <input type="text" class="form-control" name="company_name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
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
                            <label class="form-label">Registration Number</label>
                            <input type="text" class="form-control" name="registration_no" value="<?php echo htmlspecialchars($user['registration_no'] ?? 'PVT-2024-001'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Year Established</label>
                            <input type="number" class="form-control" name="year_established" value="<?php echo htmlspecialchars($user['year_established'] ?? '2015'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Business Type</label>
                        <select class="form-control" name="business_type">
                            <option value="Agribusiness" <?php echo ($user['business_type'] ?? '') == 'Agribusiness' ? 'selected' : ''; ?>>Agribusiness</option>
                            <option value="Food Processing" <?php echo ($user['business_type'] ?? '') == 'Food Processing' ? 'selected' : ''; ?>>Food Processing</option>
                            <option value="Export Company" <?php echo ($user['business_type'] ?? '') == 'Export Company' ? 'selected' : ''; ?>>Export Company</option>
                            <option value="Retail Chain" <?php echo ($user['business_type'] ?? '') == 'Retail Chain' ? 'selected' : ''; ?>>Retail Chain</option>
                            <option value="Distribution" <?php echo ($user['business_type'] ?? '') == 'Distribution' ? 'selected' : ''; ?>>Distribution</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Company Description</label>
                        <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars($user['description'] ?? 'Leading agribusiness company specializing in procurement of fresh produce from local farmers.'); ?></textarea>
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

    <!-- Order Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeOrderModal()">&times;</span>
            <h2 class="modal-title">Create New Order</h2>
            <form method="POST" action="" id="orderForm">
                <input type="hidden" name="farm_id" id="order_farm_id">
                <div class="form-group">
                    <label class="form-label">Farm Name</label>
                    <input type="text" class="form-control" name="farm_name" id="order_farm_name" readonly>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Crop Type</label>
                        <input type="text" class="form-control" name="crop_type" id="order_crop" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="text" class="form-control" name="quantity" id="order_quantity" placeholder="e.g., 10 tons" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount (KSh)</label>
                    <input type="number" class="form-control" name="amount" id="order_amount" readonly>
                </div>
                <button type="submit" name="create_order" class="btn">Confirm Order</button>
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
                    
                    const targetId = this.getAttribute('data-section');
                    
                    navLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    
                    sections.forEach(section => {
                        section.classList.remove('active');
                    });
                    
                    document.getElementById(targetId).classList.add('active');
                    
                    const titles = {
                        'dashboard': 'Company Dashboard',
                        'farms': 'Available Farms',
                        'orders': 'My Orders',
                        'contracts': 'Smart Contracts',
                        'ai-validation': 'AI Validation',
                        'profile': 'Company Profile'
                    };
                    
                    document.getElementById('pageTitle').textContent = titles[targetId] || 'Company Dashboard';
                });
            });
            
            const hash = window.location.hash.substring(1);
            if (hash) {
                const link = document.querySelector(`[data-section="${hash}"]`);
                if (link) link.click();
            }
        }

        // Export dropdown functionality
        document.getElementById('exportReportBtn').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('exportDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });

        document.addEventListener('click', function() {
            document.getElementById('exportDropdown').style.display = 'none';
        });

        function confirmExport(type) {
            return confirm(`Download ${type} report as CSV?`);
        }

        // Order functions
        function createOrder(farmId, farmName, cropType, quantity, amount) {
            document.getElementById('order_farm_id').value = farmId;
            document.getElementById('order_farm_name').value = farmName;
            document.getElementById('order_crop').value = cropType;
            document.getElementById('order_quantity').value = quantity;
            document.getElementById('order_amount').value = amount;
            document.getElementById('orderModal').style.display = 'block';
        }

        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }

        function viewFarmDetails(farmId) {
            alert('Viewing farm details (demo feature)');
        }

        function trackOrder(orderNumber) {
            alert('Tracking order: ' + orderNumber);
        }

        function updateOrderStatus(orderNumber) {
            alert('Update status for: ' + orderNumber);
        }

        function viewContract(contractNumber) {
            alert('Viewing contract: ' + contractNumber);
        }

        function signContract(contractNumber) {
            if (confirm('Sign contract ' + contractNumber + '?')) {
                alert('Contract signed (demo)');
            }
        }

        function viewAIReport(farmName) {
            alert('Viewing AI report for: ' + farmName);
        }

        function runAIAnalysis() {
            alert('Running AI analysis (demo)');
        }

        function createNewContract() {
            alert('Creating new contract (demo)');
        }

        function filterOrders() {
            alert('Filter orders (demo)');
        }

        document.getElementById('newOrderBtn').addEventListener('click', function() {
            document.querySelector('[data-section="farms"]').click();
        });

        document.getElementById('logoutBtn').addEventListener('click', function() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        });

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

        setTimeout(function() {
            const successMsg = document.getElementById('successMessage');
            const errorMsg = document.getElementById('errorMessage');
            if (successMsg) successMsg.style.display = 'none';
            if (errorMsg) errorMsg.style.display = 'none';
        }, 5000);

        window.addEventListener('DOMContentLoaded', setupNavigation);

        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>

    <!-- Keep your existing AI Chatbot code here -->
    <!-- ... -->
</body>
</html>