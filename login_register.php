<?php
session_start();
require_once "config.php";

// Registration
if (isset($_POST['register'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($role) || empty($_POST['password'])) {
        $_SESSION['register_error'] = 'All fields are required!';
        $_SESSION['active_form'] = 'register';
        header("Location: login.php");
        exit();
    }
    
    if (strlen($_POST['password']) < 6) {
        $_SESSION['register_error'] = 'Password must be at least 6 characters!';
        $_SESSION['active_form'] = 'register';
        header("Location: login.php");
        exit();
    }
    
    // Check if email exists using prepared statement
    $checkEmail = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $result = $checkEmail->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['register_error'] = 'Email is already registered!';
        $_SESSION['active_form'] = 'register';
    } else {
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $password, $role);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Registration successful! Please login.';
            $_SESSION['active_form'] = 'login';
        } else {
            $_SESSION['register_error'] = 'Registration failed: ' . $conn->error;
            $_SESSION['active_form'] = 'register';
        }
        $stmt->close();
    }
    $checkEmail->close();
    
    header("Location: login.php");
    exit();
}

// Login
if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = 'Email and password are required!';
        $_SESSION['active_form'] = 'login';
        header("Location: login.php");
        exit();
    }
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect based on role
            if ($user['role'] === 'farmer') {
                header('Location: farmer_page.php');
            } elseif ($user['role'] === 'contractor') {
                header('Location: contractor_page.php');
            } else {
                header('Location: company_page.php');
            }
            exit();
        } else {
            $_SESSION['login_error'] = 'Incorrect password!';
        }
    } else {
        $_SESSION['login_error'] = 'Email not found!';
    }
    
    $_SESSION['active_form'] = 'login';
    header('Location: login.php');
    exit();
}

// Password Reset
if (isset($_POST['reset_password'])) {
    $email = mysqli_real_escape_string($conn, $_POST['reset_email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate
    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['login_error'] = 'All fields are required!';
        $_SESSION['active_form'] = 'reset';
        header("Location: login.php");
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        $_SESSION['login_error'] = 'Passwords do not match!';
        $_SESSION['active_form'] = 'reset';
        header("Location: login.php");
        exit();
    }
    
    if (strlen($new_password) < 6) {
        $_SESSION['login_error'] = 'Password must be at least 6 characters!';
        $_SESSION['active_form'] = 'reset';
        header("Location: login.php");
        exit();
    }
    
    // Check if email exists
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $result = $checkEmail->get_result();
    
    if ($result->num_rows > 0) {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $update->bind_param("ss", $hashed_password, $email);
        
        if ($update->execute()) {
            $_SESSION['success'] = 'Password reset successful! Please login with your new password.';
            $_SESSION['active_form'] = 'login';
        } else {
            $_SESSION['login_error'] = 'Password reset failed. Please try again.';
            $_SESSION['active_form'] = 'reset';
        }
        $update->close();
    } else {
        $_SESSION['login_error'] = 'Email not found in our system.';
        $_SESSION['active_form'] = 'reset';
    }
    $checkEmail->close();
    
    header("Location: login.php");
    exit();
}
?>