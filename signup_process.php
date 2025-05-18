<?php
// Include database configuration
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = isset($_POST['user_type']) ? trim($_POST['user_type']) : 'food_enthusiast';
    
    // Validate form data
    $errors = [];
    
    // Check if name is empty
    if (empty($name)) {
        $errors[] = "Name is required.";
    } elseif (strlen($name) > 16) {
        $errors[] = "Name must be 16 characters or less.";
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    try {
        // Check if PDO connection is available
        if (!isset($pdo)) {
            throw new Exception("Database connection is not available. Please try again later.");
        }
        
        // Check if email already exists
        $check_email = "SELECT id FROM users WHERE email = :email";
        $stmt = $pdo->prepare($check_email);
        $stmt->execute(['email' => $email]);
        
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email already exists. Please use a different email.";
        }
        
        // Check if username already exists
        $check_name = "SELECT id FROM users WHERE name = :name";
        $stmt = $pdo->prepare($check_name);
        $stmt->execute(['name' => $name]);
        
        if ($stmt->rowCount() > 0) {
            $errors[] = "Username already exists. Please choose a different username.";
        }
        
        // Validate password
        if (empty($password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
        
        // Check if passwords match
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
        
        // If no errors, insert user into database
        if (empty($errors)) {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Prepare and execute SQL query with PDO
            $sql = "INSERT INTO users (name, email, password, user_type) VALUES (:name, :email, :password, :user_type)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':user_type', $user_type);
            
            if ($stmt->execute()) {
                // Set session variables
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_type'] = $user_type;
                
                // Redirect to appropriate page based on user type
                if ($user_type == 'stall_owner') {
                    header("Location: register-stall.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $errors[] = "Error: Unable to create account. Please try again.";
            }
        }
    } catch (Exception $e) {
        $errors[] = "Error: " . $e->getMessage();
        // Log the error for debugging
        error_log("Signup error: " . $e->getMessage());
    }
    
    // If there are errors, store them in session
    if (!empty($errors)) {
        $_SESSION['signup_errors'] = $errors;
        $_SESSION['signup_form_data'] = [
            'name' => $name,
            'email' => $email,
            'user_type' => $user_type
        ];
        header("Location: signup.php");
        exit();
    }
} else {
    // If accessing the page directly, redirect to signup page
    header("Location: signup.php");
    exit();
}
?> 