<?php
// Set timezone to Philippine Standard Time
date_default_timezone_set('Asia/Manila');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include configuration file to connect to the database
require_once 'config.php';

// Check if stall ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: stalls.php');
    exit();
}

$stall_id = intval($_GET['id']);
$stall = null;
$error_message = '';
$review_message = '';
$review_message_type = '';

// Check if is_anonymous column exists in reviews table and add it if it doesn't
try {
    if (isset($pdo)) {
        // First check if the reviews table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'reviews'");
        $reviewsTableExists = $tableCheck->rowCount() > 0;
        
        if ($reviewsTableExists) {
            // Check if is_anonymous column exists
            $columnCheck = $pdo->query("SHOW COLUMNS FROM reviews LIKE 'is_anonymous'");
            $columnExists = $columnCheck->rowCount() > 0;
            
            if (!$columnExists) {
                // Add is_anonymous column
                $pdo->exec("ALTER TABLE reviews ADD COLUMN is_anonymous TINYINT(1) NOT NULL DEFAULT 0");
            }
        }
    }
} catch (PDOException $e) {
    error_log('Database error checking columns: ' . $e->getMessage());
}

// Check if review_votes table exists and create it if it doesn't
try {
    if (isset($pdo)) {
        // First check if the review_votes table exists
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
    }
} catch (PDOException $e) {
    error_log('Database error creating review_votes table: ' . $e->getMessage());
}

// Fetch stall information
try {
    if (isset($pdo)) {
        // Get stall details with explicit column selection to avoid confusion
        $stmt = $pdo->prepare("SELECT 
                               s.id, s.owner_id, s.name, s.description, s.location, 
                               s.food_type, s.logo_path, s.hours, s.created_at, s.updated_at,
                               u.name as owner_name
                               FROM food_stalls s 
                               LEFT JOIN users u ON s.owner_id = u.id 
                               WHERE s.id = :id");
        $stmt->execute(['id' => $stall_id]);
        $stall = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: Check what's being fetched for owner
        if ($stall && isset($stall['owner_id'])) {
            $debugStmt = $pdo->prepare("SELECT * FROM users WHERE id = :owner_id");
            $debugStmt->execute(['owner_id' => $stall['owner_id']]);
            $ownerData = $debugStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ownerData) {
                $stall['owner_name'] = $ownerData['name'] ?? 'Unknown';
                
                // Log for debugging
                error_log('Owner data: ' . print_r($ownerData, true));
            }
        }
        
        if (!$stall) {
            $error_message = 'Stall not found';
        }
    } else {
        $error_message = 'Database connection error';
    }
} catch (PDOException $e) {
    $error_message = 'Error retrieving stall information';
}

// Process review submission or modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && $stall) {
    $user_id = $_SESSION['user_id'];
    
    // Handle menu item operations (only for stall owner)
    if ($stall['owner_id'] == $user_id) {
        // Add new menu item
        if (isset($_POST['add_menu_item'])) {
            $itemName = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
            $itemDescription = isset($_POST['item_description']) ? trim($_POST['item_description']) : '';
            $itemPrice = isset($_POST['item_price']) ? trim($_POST['item_price']) : '';
            
            // Validate required fields
            $errors = [];
            
            if (empty($itemName)) $errors[] = "Item name is required";
            if (empty($itemPrice)) $errors[] = "Item price is required";
            if (!is_numeric($itemPrice)) $errors[] = "Price must be a valid number";
            
            if (empty($errors)) {
                try {
                    // Handle item image upload
                    $imagePath = '';
                    
                    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                        $maxSize = 5 * 1024 * 1024; // 5MB
                        
                        if (!in_array($_FILES['item_image']['type'], $allowedTypes)) {
                            $errors[] = "Invalid file type. Only PNG and JPEG files are allowed.";
                        } elseif ($_FILES['item_image']['size'] > $maxSize) {
                            $errors[] = "File size exceeds the maximum allowed size (5MB).";
                        } else {
                            $uploadDir = 'public/images/menu/';
                            
                            // Create directory if it doesn't exist
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0755, true);
                            }
                            
                            // Generate unique filename
                            $fileName = uniqid() . '_' . basename($_FILES['item_image']['name']);
                            $uploadPath = $uploadDir . $fileName;
                            
                            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $uploadPath)) {
                                $imagePath = $uploadPath;
                            } else {
                                $errors[] = "Failed to upload image. Please try again.";
                            }
                        }
                    }
                    
                    if (empty($errors)) {
                        // Insert menu item
                        $stmt = $pdo->prepare("
                            INSERT INTO menu_items (stall_id, name, description, price, image_path, created_at)
                            VALUES (:stall_id, :name, :description, :price, :image_path, NOW())
                        ");
                        
                        $stmt->execute([
                            'stall_id' => $stall_id,
                            'name' => $itemName,
                            'description' => $itemDescription,
                            'price' => $itemPrice,
                            'image_path' => $imagePath
                        ]);
                        
                        $review_message = "Menu item added successfully.";
                        $review_message_type = "success";
                        
                        // Refresh menu items
                        $menuStmt = $pdo->prepare("SELECT * FROM menu_items WHERE stall_id = :stall_id ORDER BY name ASC");
                        $menuStmt->execute(['stall_id' => $stall_id]);
                        $menu_items = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                } catch (PDOException $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
            
            if (!empty($errors)) {
                $review_message = implode("<br>", $errors);
                $review_message_type = "danger";
            }
        }
        // Update existing menu item
        else if (isset($_POST['update_menu_item'])) {
            $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
            $itemName = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
            $itemDescription = isset($_POST['item_description']) ? trim($_POST['item_description']) : '';
            $itemPrice = isset($_POST['item_price']) ? trim($_POST['item_price']) : '';
            
            // Validate required fields
            $errors = [];
            
            if (empty($itemName)) $errors[] = "Item name is required";
            if (empty($itemPrice)) $errors[] = "Item price is required";
            if (!is_numeric($itemPrice)) $errors[] = "Price must be a valid number";
            
            if (empty($errors) && $item_id > 0) {
                try {
                    // Update menu item
                    $stmt = $pdo->prepare("
                        UPDATE menu_items 
                        SET name = :name, description = :description, price = :price, updated_at = NOW()
                        WHERE id = :item_id AND stall_id = :stall_id
                    ");
                    
                    $stmt->execute([
                        'name' => $itemName,
                        'description' => $itemDescription,
                        'price' => $itemPrice,
                        'item_id' => $item_id,
                        'stall_id' => $stall_id
                    ]);
                    
                    $review_message = "Menu item updated successfully.";
                    $review_message_type = "success";
                    
                    // Refresh menu items
                    $menuStmt = $pdo->prepare("SELECT * FROM menu_items WHERE stall_id = :stall_id ORDER BY name ASC");
                    $menuStmt->execute(['stall_id' => $stall_id]);
                    $menu_items = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $review_message = "Failed to update menu item.";
                    $review_message_type = "danger";
                }
            } else {
                $review_message = implode("<br>", $errors);
                $review_message_type = "danger";
            }
        }
        // Delete menu item
        else if (isset($_POST['delete_menu_item'])) {
            $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
            
            if ($item_id > 0) {
                try {
                    // Get item image path
                    $stmt = $pdo->prepare("SELECT image_path FROM menu_items WHERE id = :item_id AND stall_id = :stall_id");
                    $stmt->execute([
                        'item_id' => $item_id,
                        'stall_id' => $stall_id
                    ]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Delete item from database
                    $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = :item_id AND stall_id = :stall_id");
                    $stmt->execute([
                        'item_id' => $item_id,
                        'stall_id' => $stall_id
                    ]);
                    
                    // Delete image file if it exists
                    if ($item && !empty($item['image_path']) && file_exists($item['image_path'])) {
                        unlink($item['image_path']);
                    }
                    
                    $review_message = "Menu item deleted successfully.";
                    $review_message_type = "success";
                    
                    // Refresh menu items
                    $menuStmt = $pdo->prepare("SELECT * FROM menu_items WHERE stall_id = :stall_id ORDER BY name ASC");
                    $menuStmt->execute(['stall_id' => $stall_id]);
                    $menu_items = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                } catch (PDOException $e) {
                    $review_message = "Failed to delete menu item.";
                    $review_message_type = "danger";
                }
            }
        }
    }
    
    // Check if user is the stall owner for reviews (non-owners can review)
    if ($stall['owner_id'] == $user_id && isset($_POST['submit_review'])) {
        $review_message = "As the stall owner, you cannot review your own stall.";
        $review_message_type = "danger";
    } else {
        // Process different review actions
        if (isset($_POST['submit_review'])) {
            // New review or update
            $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
            $title = trim($_POST['title']);
            $comment = trim($_POST['comment']);
            $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
            
            // Validate rating and title
            if ($rating < 1 || $rating > 5) {
                $review_message = "Please select a rating between 1 and 5 stars.";
                $review_message_type = "danger";
            } elseif (empty($title)) {
                $review_message = "Review title cannot be empty.";
                $review_message_type = "danger";
            } else {
                // Check if user already has a review for this stall
                $check_stmt = $pdo->prepare("SELECT id FROM reviews WHERE stall_id = :stall_id AND user_id = :user_id");
                $check_stmt->execute([
                    'stall_id' => $stall_id,
                    'user_id' => $user_id
                ]);
                $existing_review = $check_stmt->fetch();
                
                if ($existing_review) {
                    // Update existing review
                    $update_stmt = $pdo->prepare("UPDATE reviews SET rating = :rating, title = :title, comment = :comment, is_anonymous = :is_anonymous, updated_at = NOW() WHERE id = :id");
                    $update_stmt->execute([
                        'rating' => $rating,
                        'title' => $title,
                        'comment' => $comment,
                        'is_anonymous' => $is_anonymous,
                        'id' => $existing_review['id']
                    ]);
                    $review_message = "Your review has been updated successfully!";
                    $review_message_type = "success";
                } else {
                    // Insert new review
                    $insert_stmt = $pdo->prepare("INSERT INTO reviews (stall_id, user_id, rating, title, comment, is_anonymous) VALUES (:stall_id, :user_id, :rating, :title, :comment, :is_anonymous)");
                    $insert_stmt->execute([
                        'stall_id' => $stall_id,
                        'user_id' => $user_id,
                        'rating' => $rating,
                        'title' => $title,
                        'comment' => $comment,
                        'is_anonymous' => $is_anonymous
                    ]);
                    $review_message = "Your review has been submitted successfully!";
                    $review_message_type = "success";
                }
            }
        } elseif (isset($_POST['delete_review']) && isset($_POST['review_id'])) {
            // Delete review
            $review_id = intval($_POST['review_id']);
            
            // Verify the review belongs to the user
            $check_stmt = $pdo->prepare("SELECT id FROM reviews WHERE id = :id AND user_id = :user_id");
            $check_stmt->execute([
                'id' => $review_id,
                'user_id' => $user_id
            ]);
            
            if ($check_stmt->fetch()) {
                $delete_stmt = $pdo->prepare("DELETE FROM reviews WHERE id = :id");
                $delete_stmt->execute(['id' => $review_id]);
                $review_message = "Your review has been deleted successfully!";
                $review_message_type = "success";
            } else {
                $review_message = "You do not have permission to delete this review.";
                $review_message_type = "danger";
            }
        }
    }
}

// Note: Review voting is now handled via AJAX in process_vote.php

// Fetch reviews for this stall
$reviews = [];
$filtered_reviews = [];
$user_review = null;
$average_rating = 0;
$total_reviews = 0;
$menu_items = []; // Variable to store menu items
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'newest';
$filter_rating = isset($_GET['filter_rating']) ? intval($_GET['filter_rating']) : 0;
$has_filter_applied = $filter_rating > 0;

if ($stall) {
    try {
        // Check if menu_items table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'menu_items'");
        $menuTableExists = $tableCheck->rowCount() > 0;
        
        if (!$menuTableExists) {
            // Create menu_items table if it doesn't exist
            $createMenuTable = "CREATE TABLE IF NOT EXISTS menu_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                stall_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                price DECIMAL(10,2) NOT NULL,
                image_path VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (stall_id) REFERENCES food_stalls(id) ON DELETE CASCADE
            )";
            $pdo->exec($createMenuTable);
        }
        
        // Fetch menu items for this stall
        $menuStmt = $pdo->prepare("SELECT * FROM menu_items WHERE stall_id = :stall_id ORDER BY name ASC");
        $menuStmt->execute(['stall_id' => $stall_id]);
        $menu_items = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // First check if the reviews table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'reviews'");
        $reviewsTableExists = $tableCheck->rowCount() > 0;
        
        if (!$reviewsTableExists) {
            // Create the reviews table if it doesn't exist
            $createTable = "CREATE TABLE IF NOT EXISTS reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                stall_id INT NOT NULL,
                user_id INT NOT NULL,
                title VARCHAR(100) NOT NULL,
                rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
                comment TEXT,
                is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (stall_id) REFERENCES food_stalls(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_review (stall_id, user_id)
            )";
            $pdo->exec($createTable);
        }
        
        // First get all reviews to calculate total and average rating
        $allReviewsStmt = $pdo->prepare("SELECT * FROM reviews WHERE stall_id = :stall_id");
        $allReviewsStmt->execute(['stall_id' => $stall_id]);
        $all_reviews = $allReviewsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate average rating from all reviews
        if (!empty($all_reviews)) {
            $total_reviews = count($all_reviews);
            $sum_ratings = 0;
            
            // Sum ratings with defensive check
            foreach ($all_reviews as $review) {
                if (isset($review['rating'])) {
                    $sum_ratings += intval($review['rating']);
                }
            }
            
            $average_rating = $total_reviews > 0 ? round($sum_ratings / $total_reviews, 1) : 0;
        }
        
        // Modify the query to include vote counts and sorting options
        $reviewSql = "
            SELECT DISTINCT r.*, u.name as user_name,
            (SELECT COUNT(*) FROM review_votes WHERE review_id = r.id AND vote_type = 1) as upvotes,
            (SELECT COUNT(*) FROM review_votes WHERE review_id = r.id AND vote_type = -1) as downvotes,
            (SELECT vote_type FROM review_votes WHERE review_id = r.id AND user_id = :current_user_id) as user_vote
            FROM reviews r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.stall_id = :stall_id";
        
        // Apply rating filter if specified
        if ($filter_rating > 0) {
            $reviewSql .= " AND r.rating = :filter_rating";
        }
        
        // Apply sorting
        switch ($sort_by) {
            case 'most_votes':
                $reviewSql .= " ORDER BY (SELECT COUNT(*) FROM review_votes WHERE review_id = r.id AND vote_type = 1) DESC, r.created_at DESC";
                break;
            case 'most_downvoted':
                $reviewSql .= " ORDER BY (SELECT COUNT(*) FROM review_votes WHERE review_id = r.id AND vote_type = -1) DESC, r.created_at DESC";
                break;
            case 'oldest':
                $reviewSql .= " ORDER BY r.created_at ASC";
                break;
            case 'newest':
            default:
                $reviewSql .= " ORDER BY r.created_at DESC";
                break;
        }
        
        $stmt = $pdo->prepare($reviewSql);
        $params = ['stall_id' => $stall_id, 'current_user_id' => $_SESSION['user_id'] ?? 0];
        
        if ($filter_rating > 0) {
            $params['filter_rating'] = $filter_rating;
        }
        
        $stmt->execute($params);
        $filtered_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Use filtered reviews for display
        $reviews = $filtered_reviews;
        
        // Check if current user has already reviewed this stall
        if (isset($_SESSION['user_id'])) {
            foreach ($all_reviews as $review) {
                if (isset($review['user_id']) && $review['user_id'] == $_SESSION['user_id']) {
                    $user_review = $review;
                    break;
                }
            }
        }
    } catch (PDOException $e) {
        // Log the error for debugging
        error_log('Database error in stall-detail.php: ' . $e->getMessage());
        
        // Try a simpler query if the join failed
        try {
            $stmt = $pdo->prepare("SELECT DISTINCT * FROM reviews WHERE stall_id = :stall_id ORDER BY created_at DESC");
            $stmt->execute(['stall_id' => $stall_id]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($reviews)) {
                $total_reviews = count($reviews);
                $sum_ratings = 0;
                
                // Sum ratings with defensive check
                foreach ($reviews as $review) {
                    if (isset($review['rating'])) {
                        $sum_ratings += intval($review['rating']);
                    }
                }
                
                $average_rating = $total_reviews > 0 ? round($sum_ratings / $total_reviews, 1) : 0;
                
                // Add user names to reviews
                foreach ($reviews as &$review) {
                    if (isset($review['user_id'])) {
                        try {
                            $userStmt = $pdo->prepare("SELECT name FROM users WHERE id = :user_id");
                            $userStmt->execute(['user_id' => $review['user_id']]);
                            $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
                            if ($userData && isset($userData['name'])) {
                                $review['user_name'] = $userData['name'];
                            } else {
                                $review['user_name'] = 'User #' . $review['user_id'];
                            }
                        } catch (PDOException $e) {
                            $review['user_name'] = 'User #' . $review['user_id'];
                        }
                    } else {
                        $review['user_name'] = 'Unknown User';
                    }
                }
                
                // Check if current user has already reviewed this stall
                if (isset($_SESSION['user_id'])) {
                    foreach ($reviews as $review) {
                        if (isset($review['user_id']) && $review['user_id'] == $_SESSION['user_id']) {
                            $user_review = $review;
                            break;
                        }
                    }
                }
            }
        } catch (PDOException $innerEx) {
            // If even the simple query fails, just show the error
            $error_message = 'Error retrieving reviews: ' . $e->getMessage();
            error_log('Second database error in stall-detail.php: ' . $innerEx->getMessage());
        }
    }
}

// Set page title
$page_title = $stall ? $stall['name'] : 'Stall Details';

// Include header
include 'header.php';
?>

<style>
    .stall-detail-header {
        background-color: var(--primary-color);
        color: white;
        padding: 40px 0;
    }
    
    .back-link {
        display: inline-flex;
            align-items: center;
            gap: 5px;
        color: white;
        margin-bottom: 20px;
        text-decoration: none;
            transition: var(--transition);
        }
        
    .back-link:hover {
        opacity: 0.8;
    }
    
    .stall-detail-grid {
            display: grid;
        grid-template-columns: 300px 1fr;
        gap: 30px;
        align-items: start;
    }
    
    .stall-logo {
        width: 100%;
        height: 300px;
            overflow: hidden;
            border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        }
        
    .stall-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
    }
    
    .stall-info h1 {
        margin-bottom: 15px;
    }
    
    .stall-meta {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stall-category {
        background-color: var(--secondary-color);
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
    }
    
    .stall-content {
        padding: 50px 0;
        }
        
        .stall-description {
        margin-bottom: 30px;
        line-height: 1.8;
        }
        
    .stall-details {
            display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .detail-item {
        background-color: var(--light-bg);
        padding: 20px;
        border-radius: var(--border-radius);
    }
    
    .detail-label {
        font-weight: 600;
        margin-bottom: 5px;
        color: var(--primary-color);
    }
    
    .error-message {
        text-align: center;
        padding: 50px 0;
    }
    
    /* Review Styles */
    .reviews-section {
        margin-top: 50px;
    }
    
    .section-title {
        margin-bottom: 30px;
            display: flex;
            align-items: center;
        gap: 15px;
        }
        
    .rating-summary {
            display: flex;
            align-items: center;
        gap: 10px;
    }
    
    .average-rating {
        font-size: 1.8rem;
        font-weight: bold;
    }
    
    .total-reviews {
        color: #666;
    }
    
    .star-rating {
        color: #FFD700;
            font-size: 1.2rem;
        }
        
    .review-form {
        background-color: var(--light-bg);
        padding: 30px;
            border-radius: var(--border-radius);
        margin-bottom: 40px;
    }
    
    .form-group {
            margin-bottom: 20px;
        }
        
    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
        font-family: inherit;
        font-size: 1rem;
    }
    
    .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
    }
    
    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
            cursor: pointer;
    }
    
    .checkbox-group label {
        margin: 0;
        cursor: pointer;
    }
    
    .review-form label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
    }
    
    .rating-input {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-end;
        margin-bottom: 20px;
    }
    
    .rating-input input {
        display: none;
    }
    
    .rating-input label {
        cursor: pointer;
        color: #ccc;
        font-size: 1.8rem;
        padding: 0 5px;
    }
    
    .rating-input label:hover,
    .rating-input label:hover ~ label,
    .rating-input input:checked ~ label {
        color: #FFD700;
    }
    
    .rating-instructions {
        margin-bottom: 15px;
        color: #666;
            font-size: 0.9rem;
    }
    
    .rating-instructions p {
        margin: 5px 0 0 0;
    }
    
    .review-textarea {
        width: 100%;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
        resize: vertical;
        min-height: 120px;
        margin-bottom: 20px;
        font-family: inherit;
        font-size: 1rem;
        white-space: pre-wrap;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    
    .review-submit {
        background-color: var(--primary-color);
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: var(--border-radius);
        cursor: pointer;
        font-weight: 600;
        transition: var(--transition);
    }
    
    .review-submit:hover {
        background-color: #c31212;
    }
    
    .reviews-list {
            display: grid;
            gap: 20px;
        }
        
        .review-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            width: 100%;
            max-width: 100%;
            word-break: break-word;
        }
        
    .review-header {
            display: flex;
        justify-content: space-between;
            margin-bottom: 15px;
        }
        
    .reviewer-info {
            display: flex;
        align-items: flex-start;
        gap: 15px;
    }
    
    .reviewer-name {
        font-weight: 600;
        color: #333;
            margin-bottom: 5px;
        }
        
        .review-date {
        color: #666;
        font-size: 0.9rem;
    }
    
    .review-content {
            margin-bottom: 15px;
        line-height: 1.6;
    }
    
    .review-title {
            font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 8px;
        color: #333;
    }
    
    .review-text {
        margin: 0;
        color: #555;
        white-space: normal;
        word-wrap: break-word;
        overflow-wrap: break-word;
        word-break: normal;
        line-height: 1.5;
        max-width: 100%;
            overflow: hidden;
        text-align: justify;
        hyphens: auto;
    }
    
    .alert {
        padding: 15px;
            margin-bottom: 20px;
        border-radius: var(--border-radius);
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .login-prompt {
        text-align: center;
        padding: 30px;
        background-color: var(--light-bg);
        border-radius: var(--border-radius);
    }
    
    .review-actions {
            display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    
    .review-actions button {
        background: none;
            border: none;
        color: var(--primary-color);
            cursor: pointer;
        font-size: 0.9rem;
    }
    
    /* Menu Styles */
    .menu-section {
        margin-top: 50px;
        margin-bottom: 50px;
    }
    
    .menu-grid {
            display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        margin-bottom: 30px;
        }
        
    .menu-item-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        overflow: hidden;
        }
        
        .menu-item-image {
            height: 200px;
            overflow: hidden;
        }
        
        .menu-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
    .menu-item-details {
            padding: 20px;
        }
        
    .menu-item-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }
    
    .menu-item-name {
        font-weight: 700;
        font-size: 1.2rem;
        color: #333;
        }
        
        .menu-item-price {
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .menu-item-description {
        color: #666;
            margin-bottom: 15px;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.5;
        }
        
    .menu-item-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .menu-form {
        background-color: var(--light-bg);
        padding: 30px;
        border-radius: var(--border-radius);
        margin-bottom: 40px;
    }
    
    .no-menu-items {
            text-align: center;
        padding: 30px;
        background-color: var(--light-bg);
        border-radius: var(--border-radius);
        margin-bottom: 30px;
    }
    
    .edit-menu-btn, .delete-menu-btn {
        padding: 5px 10px;
            border-radius: var(--border-radius);
        font-size: 0.9rem;
        cursor: pointer;
        border: none;
    }
    
    .edit-menu-btn {
        background-color: #4e95ff;
        color: white;
    }
    
    .delete-menu-btn {
        background-color: #dc3545;
        color: white;
    }
    
    /* Vote Buttons Styles */
    .vote-buttons {
            display: flex;
        gap: 10px;
        margin-top: 10px;
    }
    
    .vote-btn {
        display: flex;
            align-items: center;
        gap: 5px;
        background: none;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px 10px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .vote-btn:hover {
        background-color: #f8f8f8;
    }
    
    .upvote-btn.active {
        color: #4caf50;
        border-color: #4caf50;
    }
    
    .downvote-btn.active {
        color: #f44336;
        border-color: #f44336;
    }
    
    .vote-count {
        font-weight: 600;
    }
    
    /* Filter Styles */
    .reviews-filter {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 30px;
            padding: 20px;
        background-color: #f9f9f9;
        border-radius: var(--border-radius);
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        align-items: center;
        border: 1px solid #eee;
    }
    
    .filter-group {
            display: flex;
            align-items: center;
        gap: 12px;
        position: relative;
    }
    
    .filter-label {
        font-weight: 600;
        font-size: 1rem;
        color: #333;
            display: flex;
            align-items: center;
        gap: 5px;
    }
    
    .filter-label i {
        color: var(--primary-color);
        font-size: 1.1rem;
    }
    
    .filter-select {
        padding: 10px 35px 10px 15px;
        border-radius: 6px;
        border: 1px solid #ddd;
        font-size: 0.95rem;
        background-color: white;
        cursor: pointer;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23333' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 1em;
        min-width: 180px;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .filter-select:hover {
        border-color: #bbb;
    }
    
    .filter-select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.1);
    }
    
    .filter-buttons {
        display: flex;
        gap: 10px;
        margin-left: auto;
    }
    
    .filter-btn {
        padding: 9px 15px;
        border-radius: 6px;
        border: none;
        background-color: var(--primary-color);
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .filter-btn:hover {
        background-color: #c31212;
    }
    
    .filter-btn.reset {
        background-color: #6c757d;
    }
    
    .filter-btn.reset:hover {
        background-color: #5a6268;
    }
    
    .no-reviews-message {
        text-align: center;
        padding: 30px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        margin-bottom: 30px;
    }
    
    .no-reviews-message p {
        font-size: 1.1rem;
        margin-bottom: 15px;
        color: #555;
    }
    
    .star-filter-options {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 20px;
    }
    
    .star-filter-option {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 8px 15px;
        border-radius: 30px;
        background-color: white;
        border: 1px solid #ddd;
        cursor: pointer;
        transition: all 0.2s ease;
        color: #555;
    }
    
    .star-filter-option:hover, .star-filter-option.active {
            background-color: var(--primary-color);
            color: white;
        border-color: var(--primary-color);
    }
    
    .star-filter-option i {
        color: #FFD700;
    }
    
    .star-filter-option:hover i, .star-filter-option.active i {
        color: white;
    }
    
    @media (max-width: 768px) {
        .stall-detail-grid {
            grid-template-columns: 1fr;
        }
        
        .stall-logo {
            height: 250px;
        }
        .reviews-filter {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
        }
        
        .filter-group {
            width: 100%;
        }
        
        .filter-select {
            flex: 1;
        }
        
        .filter-buttons {
            width: 100%;
            justify-content: space-between;
            margin-top: 10px;
        }
        
        .filter-btn {
            flex: 1;
            justify-content: center;
        }
        
        .star-filter-options {
            justify-content: center;
        }
        }
    </style>

<?php if ($error_message): ?>
    <div class="error-message">
        <div class="container">
            <h2><?php echo $error_message; ?></h2>
            <p>The stall you are looking for could not be found.</p>
            <a href="stalls" class="btn btn-primary">Browse All Stalls</a>
        </div>
    </div>
<?php else: ?>
    <!-- Stall Header -->
    <section class="stall-detail-header">
        <div class="container">
            <a href="stalls" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Stalls
            </a>
            
            <div class="stall-detail-grid">
                <div class="stall-logo">
                    <?php if (!empty($stall['logo_path']) && file_exists($stall['logo_path'])): ?>
                        <img src="<?php echo htmlspecialchars($stall['logo_path'] ?? ''); ?>" alt="<?php echo htmlspecialchars($stall['name'] ?? ''); ?>">
                    <?php else: ?>
                        <img src="public/images/stalls/default-stall.jpg" alt="<?php echo htmlspecialchars($stall['name'] ?? ''); ?>">
                    <?php endif; ?>
                </div>
                
                <div class="stall-info">
                    <h1><?php echo htmlspecialchars($stall['name'] ?? ''); ?></h1>
                    
                    <div class="stall-meta">
                        <?php if (!empty($stall['food_type'])): ?>
                            <div class="stall-category"><?php echo htmlspecialchars($stall['food_type'] ?? ''); ?></div>
                        <?php endif; ?>
                        <div class="stall-hours">
                            <i class="far fa-clock"></i>
                            <span>Monday to Saturday 09:00 to 18:00</span>
                </div>
                        
                        <?php if ($total_reviews > 0): ?>
                        <div class="rating-summary">
                            <div class="star-rating">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $average_rating) {
                                        echo '<i class="fas fa-star"></i>';
                                    } elseif ($i - 0.5 <= $average_rating) {
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
            </div>
                            <div class="total-reviews"><?php echo $total_reviews; ?> review<?php echo $total_reviews !== 1 ? 's' : ''; ?></div>
        </div>
                        <?php endif; ?>
                    </div>
                    
                    <p><?php echo nl2br(htmlspecialchars($stall['description'] ?? '')); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stall Content -->
    <section class="stall-content">
    <div class="container">
            <div class="stall-details">
                <div class="detail-item">
                    <div class="detail-label">Location</div>
                    <div><?php echo htmlspecialchars($stall['location'] ?? ''); ?></div>
            </div>
                
                <div class="detail-item">
                    <div class="detail-label">Food Type</div>
                    <div><?php echo htmlspecialchars($stall['food_type'] ?? ''); ?></div>
            </div>
                
                <div class="detail-item">
                    <div class="detail-label">Owner</div>
                    <div><?php echo htmlspecialchars($stall['owner_name'] ?? ''); ?></div>
            </div>
                
                <div class="detail-item">
                    <div class="detail-label">Since</div>
                    <div><?php echo date('F j, Y \a\t g:i a', strtotime($stall['created_at'])); ?></div>
        </div>
    </div>

            <!-- Reviews Section -->
            <div class="reviews-section">
                <div class="section-title">
                    <h2>Reviews</h2>
                    <?php if ($total_reviews > 0): ?>
                    <div class="rating-summary">
                        <div class="average-rating"><?php echo number_format($average_rating, 1); ?></div>
                        <div class="star-rating">
                            <?php 
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $average_rating) {
                                    echo '<i class="fas fa-star"></i>';
                                } elseif ($i - 0.5 <= $average_rating) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                        </div>
                        <div class="total-reviews">(<?php echo $total_reviews; ?> review<?php echo $total_reviews !== 1 ? 's' : ''; ?>)</div>
                        </div>
                    <?php endif; ?>
                        </div>
                
                <?php if ($review_message): ?>
                <div class="alert alert-<?php echo $review_message_type; ?>">
                    <?php echo $review_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($stall['owner_id'] != $_SESSION['user_id']): ?>
                        <!-- Review Form -->
                        <div class="review-form">
                            <h3><?php echo $user_review ? 'Edit Your Review' : 'Leave a Review'; ?></h3>
                            <form method="POST" action="stall-detail.php?id=<?php echo $stall_id; ?>">
                                <div class="rating-input">
                                    <input type="radio" name="rating" id="star5" value="5" <?php echo ($user_review && isset($user_review['rating']) && $user_review['rating'] == 5) ? 'checked' : ''; ?> required>
                                    <label for="star5" title="5 stars"><i class="fas fa-star"></i></label>
                                    
                                    <input type="radio" name="rating" id="star4" value="4" <?php echo ($user_review && isset($user_review['rating']) && $user_review['rating'] == 4) ? 'checked' : ''; ?>>
                                    <label for="star4" title="4 stars"><i class="fas fa-star"></i></label>
                                    
                                    <input type="radio" name="rating" id="star3" value="3" <?php echo ($user_review && isset($user_review['rating']) && $user_review['rating'] == 3) ? 'checked' : ''; ?>>
                                    <label for="star3" title="3 stars"><i class="fas fa-star"></i></label>
                                    
                                    <input type="radio" name="rating" id="star2" value="2" <?php echo ($user_review && isset($user_review['rating']) && $user_review['rating'] == 2) ? 'checked' : ''; ?>>
                                    <label for="star2" title="2 stars"><i class="fas fa-star"></i></label>
                                    
                                    <input type="radio" name="rating" id="star1" value="1" <?php echo ($user_review && isset($user_review['rating']) && $user_review['rating'] == 1) ? 'checked' : ''; ?>>
                                    <label for="star1" title="1 star"><i class="fas fa-star"></i></label>
                        </div>
                        
                                <div class="rating-instructions">
                                    <p>Please select a rating (required)</p>
                            </div>
                            
                                <div class="form-group">
                                    <label for="title">Review Title</label>
                                    <input type="text" name="title" id="title" class="form-control" placeholder="Summarize your experience" value="<?php echo $user_review ? htmlspecialchars($user_review['title']) : ''; ?>" required>
                            </div>
                            
                                <label for="comment">Review Details</label>
                                <textarea name="comment" id="comment" class="review-textarea" placeholder="Write the details of your experience here..."><?php echo $user_review ? htmlspecialchars($user_review['comment']) : ''; ?></textarea>
                                
                                <div class="form-group checkbox-group">
                                    <input type="checkbox" name="is_anonymous" id="is_anonymous" <?php echo ($user_review && isset($user_review['is_anonymous']) && $user_review['is_anonymous'] == 1) ? 'checked' : ''; ?>>
                                    <label for="is_anonymous">Post anonymously</label>
                            </div>
                            
                                <button type="submit" name="submit_review" class="review-submit">
                                    <?php echo $user_review ? 'Update Review' : 'Submit Review'; ?>
                                </button>
                                
                                <?php if ($user_review): ?>
                                <input type="hidden" name="review_id" value="<?php echo $user_review['id']; ?>">
                                <button type="submit" name="delete_review" class="review-submit" style="background-color: #dc3545; margin-left: 10px;" 
                                        onclick="return confirm('Are you sure you want to delete your review?')">
                                    Delete Review
                                </button>
                                <?php endif; ?>
                            </form>
                                </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            As the stall owner, you cannot review your own stall.
                                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="login-prompt">
                        <p>Please <a href="signin">sign in</a> to leave a review.</p>
                                </div>
                <?php endif; ?>
                
                <!-- Reviews Filtering Options - Always show regardless of review count -->
                <?php if ($total_reviews > 0 || $has_filter_applied): ?>
                <div class="reviews-filter">
                    <form action="stall-detail.php" method="GET" id="filter-form">
                        <input type="hidden" name="id" value="<?php echo $stall_id; ?>">
                        
                        <div class="filter-group">
                            <span class="filter-label"><i class="fas fa-sort"></i> Sort by:</span>
                            <select name="sort_by" class="filter-select" id="sort-select">
                                <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="most_votes" <?php echo $sort_by == 'most_votes' ? 'selected' : ''; ?>>Most Upvoted</option>
                                <option value="most_downvoted" <?php echo $sort_by == 'most_downvoted' ? 'selected' : ''; ?>>Most Downvoted</option>
                            </select>
                    </div>
                    
                        <div class="filter-group">
                            <span class="filter-label"><i class="fas fa-filter"></i> Filter by:</span>
                            <select name="filter_rating" class="filter-select" id="rating-select">
                                <option value="0" <?php echo $filter_rating == 0 ? 'selected' : ''; ?>>All Ratings</option>
                                <option value="5" <?php echo $filter_rating == 5 ? 'selected' : ''; ?>>5 Stars</option>
                                <option value="4" <?php echo $filter_rating == 4 ? 'selected' : ''; ?>>4 Stars</option>
                                <option value="3" <?php echo $filter_rating == 3 ? 'selected' : ''; ?>>3 Stars</option>
                                <option value="2" <?php echo $filter_rating == 2 ? 'selected' : ''; ?>>2 Stars</option>
                                <option value="1" <?php echo $filter_rating == 1 ? 'selected' : ''; ?>>1 Star</option>
                            </select>
                        </div>
                        
                        <div class="filter-buttons">
                            <?php if ($has_filter_applied || $sort_by != 'newest'): ?>
                            <a href="stall-detail.php?id=<?php echo $stall_id; ?>" class="filter-btn reset"><i class="fas fa-undo"></i> Reset</a>
                            <?php endif; ?>
                                    </div>
                    </form>
                                </div>
                
                <!-- Star Rating Quick Filters -->
                <?php if ($total_reviews > 0): ?>
                <div class="star-filter-options">
                    <a href="stall-detail.php?id=<?php echo $stall_id; ?>" class="star-filter-option <?php echo $filter_rating == 0 ? 'active' : ''; ?>">
                        All Ratings
                    </a>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <a href="stall-detail.php?id=<?php echo $stall_id; ?>&filter_rating=<?php echo $i; ?>&sort_by=<?php echo $sort_by; ?>" 
                       class="star-filter-option <?php echo $filter_rating == $i ? 'active' : ''; ?>">
                        <?php for ($j = 1; $j <= $i; $j++): ?>
                                    <i class="fas fa-star"></i>
                        <?php endfor; ?>
                        <?php for ($j = $i+1; $j <= 5; $j++): ?>
                        <i class="far fa-star"></i>
                        <?php endfor; ?>
                    </a>
                    <?php endfor; ?>
                                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <!-- Reviews List -->
                <div class="reviews-list">
                    <?php if (empty($reviews) && !$has_filter_applied): ?>
                        <p>No reviews yet. Be the first to review this stall!</p>
                    <?php elseif (empty($reviews) && $has_filter_applied): ?>
                        <div class="no-reviews-message">
                            <p>No <?php echo $filter_rating; ?>-star reviews found.</p>
                            <p>Try a different filter or view all reviews.</p>
                            <div class="star-filter-options">
                                <a href="stall-detail.php?id=<?php echo $stall_id; ?>" class="star-filter-option active">
                                    All Ratings
                                </a>
                                <?php for ($i = 5; $i >= 1; $i--): 
                                    if ($i != $filter_rating): ?>
                                <a href="stall-detail.php?id=<?php echo $stall_id; ?>&filter_rating=<?php echo $i; ?>&sort_by=<?php echo $sort_by; ?>" 
                                   class="star-filter-option">
                                    <?php for ($j = 1; $j <= $i; $j++): ?>
                                    <i class="fas fa-star"></i>
                                    <?php endfor; ?>
                                    <?php for ($j = $i+1; $j <= 5; $j++): ?>
                                    <i class="far fa-star"></i>
                                    <?php endfor; ?>
                                </a>
                                <?php endif; endfor; ?>
                                </div>
                            </div>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-card">
                        <div class="review-header">
                                    <div class="reviewer">
                                        <div class="reviewer-name">
                                            Reviewer: 
                                            <?php 
                                            if (!empty($review['is_anonymous']) && $review['is_anonymous']) {
                                                echo 'Anonymous';
                                            } else {
                                                $username = $review['user_name'] ?? 'Unknown User';
                                                echo htmlspecialchars($username);
                                            }
                                            ?>
                                    </div>
                                </div>
                                <div class="review-rating">
                                        <?php 
                                        $rating = $review['rating'] ?? 0;
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo '<i class="fa' . ($i <= $rating ? 's' : 'r') . ' fa-star"></i>';
                                        }
                                        ?>
                                </div>
                            </div>
                            
                                <h4 class="review-title"><?php echo htmlspecialchars($review['title'] ?? ''); ?></h4>
                                
                                <p class="review-text"><?php echo nl2br(htmlspecialchars($review['comment'] ?? '')); ?></p>
                                
                                <div class="review-date">
                                    <?php echo date('F j, Y', strtotime($review['created_at'] ?? 'now')); ?>
                                    </div>
                                
                                <!-- Upvote/Downvote System -->
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <div class="vote-buttons">
                                        <?php
                                        $upvotes = $review['upvotes'] ?? 0;
                                        $downvotes = $review['downvotes'] ?? 0;
                                        $userVote = $review['user_vote'] ?? 0;
                                        ?>
                                        
                                        <button type="button" class="vote-btn upvote-btn <?php echo ($userVote == 1) ? 'active' : ''; ?>" 
                                                data-review-id="<?php echo $review['id']; ?>" data-vote-type="1">
                                            <i class="fas fa-thumbs-up"></i>
                                            <span class="vote-count"><?php echo $upvotes; ?></span>
                                        </button>
                                        
                                        <button type="button" class="vote-btn downvote-btn <?php echo ($userVote == -1) ? 'active' : ''; ?>"
                                                data-review-id="<?php echo $review['id']; ?>" data-vote-type="-1">
                                            <i class="fas fa-thumbs-down"></i>
                                            <span class="vote-count"><?php echo $downvotes; ?></span>
                                        </button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $review['user_id']): ?>
                                    <div class="review-actions">
                                        <form method="POST" action="stall-detail.php?id=<?php echo $stall_id; ?>" style="display: inline;">
                                            <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                            <button type="submit" name="delete_review" onclick="return confirm('Are you sure you want to delete your review?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                            </div>
                        </div>
                        
            <!-- Menu Section -->
            <div class="menu-section">
                <div class="section-title">
                    <h2>Menu</h2>
                            </div>
                            
                <?php if ($review_message && (isset($_POST['add_menu_item']) || isset($_POST['update_menu_item']) || isset($_POST['delete_menu_item']))): ?>
                <div class="alert alert-<?php echo $review_message_type; ?>">
                    <?php echo $review_message; ?>
                                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['user_id']) && $stall['owner_id'] == $_SESSION['user_id']): ?>
                    <!-- Menu Management Form for Stall Owner -->
                    <div class="menu-form">
                        <h3><?php echo isset($_POST['edit_menu_form']) ? 'Edit Menu Item' : 'Add New Menu Item'; ?></h3>
                        
                        <?php if (isset($_POST['edit_menu_form']) && isset($_POST['item_id'])): 
                            // Fetch menu item details for editing
                            $item_id = intval($_POST['item_id']);
                            $editStmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = :id AND stall_id = :stall_id");
                            $editStmt->execute(['id' => $item_id, 'stall_id' => $stall_id]);
                            $editItem = $editStmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                            <form method="POST" action="stall-detail.php?id=<?php echo $stall_id; ?>" enctype="multipart/form-data">
                                <input type="hidden" name="item_id" value="<?php echo $editItem['id']; ?>">
                                
                                <div class="form-group">
                                    <label for="item_name">Item Name</label>
                                    <input type="text" id="item_name" name="item_name" class="form-control" value="<?php echo htmlspecialchars($editItem['name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="item_price">Price (PHP)</label>
                                    <input type="number" id="item_price" name="item_price" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars($editItem['price']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="item_description">Description</label>
                                    <textarea id="item_description" name="item_description" class="form-control" required><?php echo htmlspecialchars($editItem['description'] ?? ''); ?></textarea>
                                    </div>
                                
                                <button type="submit" name="update_menu_item" class="review-submit">Update Menu Item</button>
                                <a href="stall-detail.php?id=<?php echo $stall_id; ?>" class="review-submit" style="background-color: #6c757d; margin-left: 10px; display: inline-block; text-decoration: none;">Cancel</a>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="stall-detail.php?id=<?php echo $stall_id; ?>" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="item_name">Item Name</label>
                                    <input type="text" id="item_name" name="item_name" class="form-control" placeholder="e.g., Chicken Adobo" required>
                        </div>
                                
                                <div class="form-group">
                                    <label for="item_price">Price (PHP)</label>
                                    <input type="number" id="item_price" name="item_price" class="form-control" min="0" step="0.01" placeholder="e.g., 120.00" required>
                    </div>
                                
                                <div class="form-group">
                                    <label for="item_description">Description</label>
                                    <textarea id="item_description" name="item_description" class="form-control" placeholder="Describe your menu item" required></textarea>
                </div>
                
                                <div class="form-group">
                                    <label for="item_image">Item Image</label>
                                    <input type="file" id="item_image" name="item_image" class="form-control">
                                    <small class="text-muted">Optional. Max size: 5MB. Formats: JPG, PNG</small>
                    </div>
                    
                                <button type="submit" name="add_menu_item" class="review-submit">Add Menu Item</button>
                            </form>
                        <?php endif; ?>
                            </div>
                <?php endif; ?>
                
                <!-- Display Menu Items -->
                <?php if (empty($menu_items)): ?>
                    <div class="no-menu-items">
                        <p>No menu items available for this stall yet.</p>
                        </div>
                <?php else: ?>
                    <div class="menu-grid">
                        <?php foreach ($menu_items as $item): ?>
                            <div class="menu-item-card">
                                <div class="menu-item-image">
                                    <?php if(!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_path'] ?? ''); ?>" alt="<?php echo htmlspecialchars($item['name'] ?? ''); ?>">
                                    <?php else: ?>
                                        <img src="public/images/menu/default-food.jpg" alt="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" style="object-fit: cover;">
                                    <?php endif; ?>
                    </div>
                                <div class="menu-item-details">
                                    <div class="menu-item-header">
                                        <div class="menu-item-name"><?php echo htmlspecialchars($item['name'] ?? ''); ?></div>
                                        <div class="menu-item-price">₱<?php echo number_format((float)($item['price'] ?? 0), 2); ?></div>
                </div>
                                    <?php if(!empty($item['description'])): ?>
                                        <div class="menu-item-description"><?php echo htmlspecialchars($item['description'] ?? ''); ?></div>
                                    <?php endif; ?>
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $stall['owner_id']): ?>
                                        <div class="menu-item-actions">
                                            <form method="POST" action="stall-detail.php?id=<?php echo $stall_id; ?>" style="display: inline;">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" name="edit_menu_form" class="edit-menu-btn">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </form>
                                            
                                            <form method="POST" action="stall-detail.php?id=<?php echo $stall_id; ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this menu item?');">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" name="delete_menu_item" class="delete-menu-btn">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                    </div>
                                    <?php endif; ?>
                </div>
                    </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                        </div>
                    </div>
    </section>
<?php endif; ?>

<?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form when select values change (for better UX)
    const sortSelect = document.getElementById('sort-select');
    const ratingSelect = document.getElementById('rating-select');
    
    if (sortSelect && ratingSelect) {
        sortSelect.addEventListener('change', function() {
            document.getElementById('filter-form').submit();
        });
        
        ratingSelect.addEventListener('change', function() {
            document.getElementById('filter-form').submit();
        });
    }
    
    // AJAX voting functionality
    const voteButtons = document.querySelectorAll('.vote-btn');
    console.log('Found vote buttons:', voteButtons.length);
    
    voteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Vote button clicked');
            
            const reviewId = this.getAttribute('data-review-id');
            const voteType = this.getAttribute('data-vote-type');
            console.log('Review ID:', reviewId, 'Vote Type:', voteType);
            
            // Get the parent vote buttons container
            const voteButtonsContainer = this.closest('.vote-buttons');
            const upvoteBtn = voteButtonsContainer.querySelector('.upvote-btn');
            const downvoteBtn = voteButtonsContainer.querySelector('.downvote-btn');
            
            // Create FormData for AJAX request
            const formData = new FormData();
            formData.append('review_id', reviewId);
            formData.append('vote_type', voteType);
            
            // Show loading state
            this.disabled = true;
            
            // Function to handle successful response
            function handleResponse(data) {
                console.log('Response data:', data);
                if (data.status === 'success') {
                    // Update the vote counts
                    upvoteBtn.querySelector('.vote-count').textContent = data.upvotes;
                    downvoteBtn.querySelector('.vote-count').textContent = data.downvotes;
                    
                    // Update active state of buttons
                    upvoteBtn.classList.toggle('active', data.userVote === 1);
                    downvoteBtn.classList.toggle('active', data.userVote === -1);
                } else {
                    console.error('Error processing vote:', data.message);
                    alert('Error: ' + data.message);
                }
            }
            
            // Try POST request first
            fetch('process_vote.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin', // Include cookies for session
                cache: 'no-cache'
            })
            .then(response => {
                console.log('POST Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(handleResponse)
            .catch(error => {
                console.error('POST request failed, trying GET:', error);
                
                // If POST fails, try GET as a fallback
                const url = `process_vote.php?review_id=${reviewId}&vote_type=${voteType}`;
                return fetch(url, {
                    credentials: 'same-origin', // Include cookies for session
                    cache: 'no-cache'
                })
                    .then(response => {
                        console.log('GET Response status:', response.status);
                        if (!response.ok) {
                            throw new Error('GET request failed: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(handleResponse)
                    .catch(getError => {
                        console.error('Both POST and GET requests failed:', getError);
                        alert('Error: Could not process your vote. Please try again later.');
                    });
            })
            .finally(() => {
                this.disabled = false;
            });
                });
            });
        });
    </script>