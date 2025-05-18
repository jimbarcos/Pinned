<?php
// Enable error reporting for debugging (comment out in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load sensitive credentials from config.secret.php
$secrets = include __DIR__ . '/config.secret.php';

// Database connection configuration for InfinityFree hosting
$db_host = $secrets['DB_HOST'];
$db_name = $secrets['DB_NAME'];
$db_user = $secrets['DB_USER'];
$db_pass = $secrets['DB_PASS'];

// Initialize connection variables
$pdo = null;
$conn = null;

// Create PDO connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Log the error but continue to allow non-database functionality to work
    error_log("Database connection failed: " . $e->getMessage());
}

// Create MySQLi connection for compatibility
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Check connection
    if ($conn->connect_error) {
        error_log("MySQLi connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log("MySQLi connection error: " . $e->getMessage());
}

// Start the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?> 