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
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validate form data
    $errors = [];
    
    // Check if email and password are provided
    if (empty($email)) {
        $errors[] = "Email is required.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    
    // If no validation errors, check credentials
    if (empty($errors)) {
        try {
            // Check if PDO connection is available
            if (!isset($pdo)) {
                throw new Exception("Database connection is not available. Please try again later.");
            }
            
            // Prepare and execute SQL query using PDO
            $sql = "SELECT id, name, email, password, user_type FROM users WHERE email = :email";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['email' => $email]);
            
            // Check if user exists
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_type'] = $user['user_type'];
                    
                    // Redirect to homepage
                    header("Location: index.php");
                    exit();
                } else {
                    $errors[] = "Invalid email or password.";
                }
            } else {
                $errors[] = "Invalid email or password.";
            }
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
            // Log the error for debugging
            error_log("Login error: " . $e->getMessage());
        }
    }
    
    // If there are errors, store them in session
    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        $_SESSION['login_email'] = $email;
        header("Location: signin.php");
        exit();
    }
} else {
    // If accessing the page directly, redirect to signin page
    header("Location: signin.php");
    exit();
}
?> 