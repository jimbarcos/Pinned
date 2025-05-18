<?php
// This file handles AJAX requests for voting on reviews
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug information
$debug = [
    'post' => $_POST,
    'session' => isset($_SESSION) ? array_keys($_SESSION) : 'No session',
    'user_id' => $_SESSION['user_id'] ?? 'Not set'
];

// Include database connection
if (file_exists('includes/db.php')) {
    require_once 'includes/db.php';
} else {
    // If db.php doesn't exist in includes, try alternate paths
    if (file_exists('config.php')) {
        require_once 'config.php';
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database connection file not found', 'debug' => $debug]);
        exit;
    }
}

// Check if database connection exists
if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed', 'debug' => $debug]);
    exit;
}

// Accept both GET and POST requests
$request = array_merge($_GET, $_POST);

// Check if user is logged in and this is a valid request
if (!isset($_SESSION['user_id']) || 
    (empty($request['review_id']) || empty($request['vote_type']))) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid request or not logged in', 
        'debug' => array_merge($debug, ['request' => $request])
    ]);
    exit;
}

// Get user info and vote info
$user_id = $_SESSION['user_id'];
$review_id = isset($request['review_id']) ? intval($request['review_id']) : 0;
$vote_type = isset($request['vote_type']) ? intval($request['vote_type']) : 0;

// Validate vote type (-1 for downvote, 1 for upvote)
if ($review_id <= 0 || ($vote_type !== 1 && $vote_type !== -1)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid vote data', 'debug' => [
        'review_id' => $review_id,
        'vote_type' => $vote_type
    ]]);
    exit;
}

try {
    // Check if review_votes table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'review_votes'");
    $votesTableExists = $tableCheck->rowCount() > 0;
    
    if (!$votesTableExists) {
        // Create the review_votes table if it doesn't exist
        $createTable = "CREATE TABLE IF NOT EXISTS review_votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            user_id INT NOT NULL,
            vote_type TINYINT NOT NULL COMMENT '1 for upvote, -1 for downvote',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_vote (review_id, user_id)
        )";
        $pdo->exec($createTable);
    }
    
    // Check if review exists
    $reviewCheck = $pdo->prepare("SELECT id FROM reviews WHERE id = :review_id");
    $reviewCheck->execute(['review_id' => $review_id]);
    if ($reviewCheck->rowCount() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Review not found', 'debug' => ['review_id' => $review_id]]);
        exit;
    }
    
    // Check if user already voted on this review
    $checkStmt = $pdo->prepare("SELECT id, vote_type FROM review_votes WHERE review_id = :review_id AND user_id = :user_id");
    $checkStmt->execute([
        'review_id' => $review_id,
        'user_id' => $user_id
    ]);
    $existingVote = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingVote) {
        // User already voted
        if ($existingVote['vote_type'] == $vote_type) {
            // Remove vote if clicking the same button again
            $deleteStmt = $pdo->prepare("DELETE FROM review_votes WHERE id = :id");
            $deleteStmt->execute(['id' => $existingVote['id']]);
            $message = "Your vote has been removed.";
            $newUserVote = 0;
        } else {
            // Update vote if changing from upvote to downvote or vice versa
            $updateStmt = $pdo->prepare("UPDATE review_votes SET vote_type = :vote_type WHERE id = :id");
            $updateStmt->execute([
                'vote_type' => $vote_type,
                'id' => $existingVote['id']
            ]);
            $message = "Your vote has been updated.";
            $newUserVote = $vote_type;
        }
    } else {
        // Insert new vote
        $insertStmt = $pdo->prepare("INSERT INTO review_votes (review_id, user_id, vote_type) VALUES (:review_id, :user_id, :vote_type)");
        $insertStmt->execute([
            'review_id' => $review_id,
            'user_id' => $user_id,
            'vote_type' => $vote_type
        ]);
        $message = "Your vote has been recorded.";
        $newUserVote = $vote_type;
    }
    
    // Get updated vote counts
    $countStmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM review_votes WHERE review_id = :review_id AND vote_type = 1) as upvotes,
            (SELECT COUNT(*) FROM review_votes WHERE review_id = :review_id AND vote_type = -1) as downvotes
    ");
    $countStmt->execute(['review_id' => $review_id]);
    $voteCounts = $countStmt->fetch(PDO::FETCH_ASSOC);
    
    // Return success response with updated counts
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'upvotes' => $voteCounts['upvotes'],
        'downvotes' => $voteCounts['downvotes'],
        'userVote' => $newUserVote
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'line' => $e->getLine()
        ]
    ]);
    error_log('Vote processing error: ' . $e->getMessage());
}
?> 