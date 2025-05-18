<?php
// Include the configuration file
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please log in to access your account.";
    header('Location: signin.php');
    exit();
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: account.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Check if user is a stall owner
    $stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['user_type'] === 'stall_owner') {
        // Delete menu items associated with the user's stall(s)
        $stmt = $pdo->prepare("
            DELETE mi FROM menu_items mi
            JOIN food_stalls fs ON mi.stall_id = fs.id
            WHERE fs.owner_id = :owner_id
        ");
        $stmt->execute(['owner_id' => $user_id]);
        
        // Delete reviews for the user's stall(s)
        $stmt = $pdo->prepare("
            DELETE r FROM reviews r
            JOIN food_stalls fs ON r.stall_id = fs.id
            WHERE fs.owner_id = :owner_id
        ");
        $stmt->execute(['owner_id' => $user_id]);
        
        // Delete stalls owned by the user
        $stmt = $pdo->prepare("DELETE FROM food_stalls WHERE owner_id = :owner_id");
        $stmt->execute(['owner_id' => $user_id]);
    }
    
    // Delete votes given by the user
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'review_votes'");
    if ($tableCheck->rowCount() > 0) {
        $stmt = $pdo->prepare("DELETE FROM review_votes WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
    }
    
    // Delete reviews written by the user
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    
    // Delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    
    // Commit transaction
    $pdo->commit();
    
    // Clear session and redirect
    session_unset();
    session_destroy();
    
    // Create a new session to set a success message
    session_start();
    $_SESSION['success_message'] = "Your account has been deleted successfully.";
    header('Location: index.php');
    exit();
    
} catch (PDOException $e) {
    // Roll back transaction
    $pdo->rollBack();
    
    $_SESSION['error_message'] = "Failed to delete your account. Please try again later.";
    header('Location: account.php');
    exit();
}
?> 