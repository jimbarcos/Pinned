<?php
// Set timezone to Philippine Standard Time
date_default_timezone_set('Asia/Manila');

// Include the configuration file
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please log in to manage your stall.";
    header('Location: signin.php');
    exit();
}

// Check if user is a stall owner
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'stall_owner') {
    $_SESSION['error_message'] = "Only stall owners can access this page.";
    header('Location: index.php');
    exit();
}

// Set page title
$page_title = "Manage Your Stall";

// Get stall information
try {
    $stmt = $pdo->prepare("SELECT * FROM food_stalls WHERE owner_id = :owner_id");
    $stmt->execute(['owner_id' => $_SESSION['user_id']]);
    $stall = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stall) {
        // Redirect to stall registration if no stall exists
        $_SESSION['info_message'] = "You don't have any registered stalls. Register your stall now!";
        header('Location: register-stall.php');
        exit();
    }
    
    // Get menu items
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE stall_id = :stall_id ORDER BY created_at DESC");
    $stmt->execute(['stall_id' => $stall['id']]);
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent reviews
    $stmt = $pdo->prepare("
        SELECT r.*, u.name as reviewer_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.stall_id = :stall_id
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute(['stall_id' => $stall['id']]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error. Please try again later.";
    header('Location: index.php');
    exit();
}

// Process form submission
$message = '';
$message_type = '';

// Check if user is editing pin location
$edit_location = false;
if (isset($_GET['action']) && $_GET['action'] == 'edit_location') {
    $edit_location = true;
}

// Handle pin location update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pin_location'])) {
    $pinX = isset($_POST['pin_x']) ? trim($_POST['pin_x']) : '';
    $pinY = isset($_POST['pin_y']) ? trim($_POST['pin_y']) : '';
    $pinLocation = isset($_POST['pin_location']) ? trim($_POST['pin_location']) : '';
    
    // Ensure we have valid coordinates
    if (empty($pinX) || empty($pinY)) {
        $message = "Error: Pin coordinates are missing or invalid.";
        $message_type = "danger";
    } else {
        try {
            // Update pin coordinates and location text
            $stmt = $pdo->prepare("
                UPDATE food_stalls 
                SET pin_x = :pin_x, pin_y = :pin_y, location = :location,
                    updated_at = NOW()
                WHERE id = :stall_id
            ");
            
            $stmt->execute([
                'pin_x' => $pinX,
                'pin_y' => $pinY,
                'location' => $pinLocation,
                'stall_id' => $stall['id']
            ]);
            
            $message = "Pin location updated successfully.";
            $message_type = "success";
            
            // Log for debugging
            error_log("Stall {$stall['id']} pin location updated: x={$pinX}, y={$pinY}");
            
            // Refresh stall data
            $stmt = $pdo->prepare("SELECT * FROM food_stalls WHERE id = :stall_id");
            $stmt->execute(['stall_id' => $stall['id']]);
            $stall = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Reset edit_location flag
            $edit_location = false;
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_stall'])) {
        // Update stall information
        $stallName = isset($_POST['stall_name']) ? trim($_POST['stall_name']) : '';
        $stallDescription = isset($_POST['stall_description']) ? trim($_POST['stall_description']) : '';
        $location = isset($_POST['location']) ? trim($_POST['location']) : '';
        $foodType = isset($_POST['food_type']) ? trim($_POST['food_type']) : '';
        $hours = isset($_POST['hours']) ? trim($_POST['hours']) : '';
        
        // Validate required fields
        $errors = [];
        
        if (empty($stallName)) $errors[] = "Stall name is required";
        if (empty($stallDescription)) $errors[] = "Stall description is required";
        if (empty($location)) $errors[] = "Location is required";
        if (empty($foodType)) $errors[] = "Food type is required";
        
        if (empty($errors)) {
            try {
                // Handle logo upload if a new file is selected
                $logoPath = $stall['logo_path'];
                
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                    $maxSize = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($_FILES['logo']['type'], $allowedTypes)) {
                        $errors[] = "Invalid file type. Only PNG and JPEG files are allowed.";
                    } elseif ($_FILES['logo']['size'] > $maxSize) {
                        $errors[] = "File size exceeds the maximum allowed size (5MB).";
                    } else {
                        $uploadDir = 'public/images/stalls/';
                        
                        // Create directory if it doesn't exist
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        // Generate unique filename
                        $fileName = uniqid() . '_' . basename($_FILES['logo']['name']);
                        $uploadPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                            $logoPath = $uploadPath;
                            
                            // Delete old logo if it exists
                            if (!empty($stall['logo_path']) && file_exists($stall['logo_path']) && $stall['logo_path'] != 'public/images/stalls/default-stall.jpg') {
                                unlink($stall['logo_path']);
                            }
                        } else {
                            $errors[] = "Failed to upload logo. Please try again.";
                        }
                    }
                }
                
                if (empty($errors)) {
                    // Update stall information
                    $stmt = $pdo->prepare("
                        UPDATE food_stalls 
                        SET name = :name, description = :description, location = :location, 
                            food_type = :food_type, hours = :hours, logo_path = :logo_path,
                            updated_at = NOW()
                        WHERE id = :stall_id
                    ");
                    
                    $stmt->execute([
                        'name' => $stallName,
                        'description' => $stallDescription,
                        'location' => $location,
                        'food_type' => $foodType,
                        'hours' => $hours,
                        'logo_path' => $logoPath,
                        'stall_id' => $stall['id']
                    ]);
                    
                    $message = "Stall information updated successfully.";
                    $message_type = "success";
                    
                    // Refresh stall data
                    $stmt = $pdo->prepare("SELECT * FROM food_stalls WHERE id = :stall_id");
                    $stmt->execute(['stall_id' => $stall['id']]);
                    $stall = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            $message = implode("<br>", $errors);
            $message_type = "danger";
        }
    } elseif (isset($_POST['add_menu_item'])) {
        // Add new menu item
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
                        'stall_id' => $stall['id'],
                        'name' => $itemName,
                        'description' => $itemDescription,
                        'price' => $itemPrice,
                        'image_path' => $imagePath
                    ]);
                    
                    $message = "Menu item added successfully.";
                    $message_type = "success";
                    
                    // Refresh menu items
                    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE stall_id = :stall_id ORDER BY created_at DESC");
                    $stmt->execute(['stall_id' => $stall['id']]);
                    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            $message = implode("<br>", $errors);
            $message_type = "danger";
        }
    } elseif (isset($_POST['delete_menu_item'])) {
        // Delete menu item
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        
        if ($item_id > 0) {
            try {
                // Get item image path
                $stmt = $pdo->prepare("SELECT image_path FROM menu_items WHERE id = :item_id AND stall_id = :stall_id");
                $stmt->execute([
                    'item_id' => $item_id,
                    'stall_id' => $stall['id']
                ]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Delete item from database
                $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = :item_id AND stall_id = :stall_id");
                $stmt->execute([
                    'item_id' => $item_id,
                    'stall_id' => $stall['id']
                ]);
                
                // Delete image file if it exists
                if ($item && !empty($item['image_path']) && file_exists($item['image_path'])) {
                    unlink($item['image_path']);
                }
                
                $message = "Menu item deleted successfully.";
                $message_type = "success";
                
                // Refresh menu items
                $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE stall_id = :stall_id ORDER BY created_at DESC");
                $stmt->execute(['stall_id' => $stall['id']]);
                $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                $message = "Failed to delete menu item.";
                $message_type = "danger";
            }
        }
    } elseif (isset($_POST['edit_menu_item'])) {
        // Edit existing menu item
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
                // Handle item image upload if provided
                $imagePath = null;
                
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
                            
                            // Get current image path and delete old image if exists
                            $stmt = $pdo->prepare("SELECT image_path FROM menu_items WHERE id = :item_id AND stall_id = :stall_id");
                            $stmt->execute([
                                'item_id' => $item_id,
                                'stall_id' => $stall['id']
                            ]);
                            $oldItem = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($oldItem && !empty($oldItem['image_path']) && file_exists($oldItem['image_path'])) {
                                unlink($oldItem['image_path']);
                            }
                        } else {
                            $errors[] = "Failed to upload image. Please try again.";
                        }
                    }
                }
                
                if (empty($errors)) {
                    // Update menu item with or without new image
                    if ($imagePath) {
                        $stmt = $pdo->prepare("
                            UPDATE menu_items 
                            SET name = :name, description = :description, price = :price, image_path = :image_path, updated_at = NOW()
                            WHERE id = :item_id AND stall_id = :stall_id
                        ");
                        
                        $stmt->execute([
                            'name' => $itemName,
                            'description' => $itemDescription,
                            'price' => $itemPrice,
                            'image_path' => $imagePath,
                            'item_id' => $item_id,
                            'stall_id' => $stall['id']
                        ]);
                    } else {
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
                            'stall_id' => $stall['id']
                        ]);
                    }
                    
                    $message = "Menu item updated successfully.";
                    $message_type = "success";
                    
                    // Refresh menu items
                    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE stall_id = :stall_id ORDER BY created_at DESC");
                    $stmt->execute(['stall_id' => $stall['id']]);
                    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                $message = "Failed to update menu item: " . $e->getMessage();
                $message_type = "danger";
            }
        }
        
        if (!empty($errors)) {
            $message = implode("<br>", $errors);
            $message_type = "danger";
        }
    }
}

// Include header
include 'header.php';
?>

<style>
    /* Add styles for pin location editing */
    .map-container {
        width: 100%;
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--box-shadow);
        margin-bottom: 30px;
        position: relative;
        cursor: crosshair;
    }
    
    .map-container img {
        width: 100%;
        display: block;
    }
    
    .map-pin {
        position: absolute;
        width: 20px;
        height: 20px;
        background-color: var(--primary-color);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        z-index: 10;
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        display: none;
    }
    
    .map-pin::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 30px;
        height: 30px;
        background-color: rgba(239, 28, 28, 0.3);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        z-index: -1;
    }
    
    .search-bar {
        position: relative;
        max-width: 700px;
        margin: 0 auto 30px;
        background-color: white;
        border-radius: 30px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        padding: 12px 20px;
        border: 1px solid #ddd;
    }
    
    .search-icon {
        color: var(--primary-color);
        font-size: 1.2rem;
        margin-right: 10px;
    }
    
    .search-input {
        border: none;
        outline: none;
        width: 100%;
        font-size: 0.95rem;
        padding: 0;
        background: transparent;
    }
    
    .pin-location-display {
        margin: 30px auto;
        max-width: 700px;
        padding: 12px 20px;
        background-color: white;
        border-radius: 30px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        border: 1px solid #ddd;
        transition: all 0.3s ease;
    }
    
    .pin-location-display:hover {
        box-shadow: 0 3px 8px rgba(0,0,0,0.15);
        cursor: pointer;
    }
    
    .pin-preview {
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.3s ease;
    }
    
    .pin-location-display:hover .pin-preview {
        transform: rotate(45deg) scale(1.2);
    }
    
    .pin-location-text {
        flex: 1;
        font-size: 0.95rem;
    }
    
    .manage-stall-section {
        padding: 50px 0;
    }
    
    .manage-stall-header {
        margin-bottom: 30px;
    }
    
    .nav-tabs {
        display: flex;
        border-bottom: 1px solid #ddd;
        margin-bottom: 30px;
    }
    
    .nav-tabs .nav-item {
        margin-right: 10px;
    }
    
    .nav-tabs .nav-link {
        padding: 10px 20px;
        border: 1px solid transparent;
        border-top-left-radius: 5px;
        border-top-right-radius: 5px;
        cursor: pointer;
    }
    
    .nav-tabs .nav-link.active {
        border-color: #ddd #ddd #fff;
        border-bottom: 2px solid var(--primary-color);
        color: var(--primary-color);
        font-weight: 600;
    }
    
    .tab-content > .tab-pane {
        display: none;
    }
    
    .tab-content > .active {
        display: block;
    }
    
    .stall-info-card, .add-menu-item-card, .menu-item-card, .review-card {
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }
    
    .card-header {
        background-color: var(--light-bg);
        padding: 20px;
        border-bottom: 1px solid #eee;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
    }
    
    textarea.form-control {
        min-height: 100px;
        resize: vertical;
    }
    
    .btn {
        padding: 12px 20px;
        border: none;
        border-radius: var(--border-radius);
        cursor: pointer;
        font-weight: 600;
        transition: var(--transition);
    }
    
    .btn-primary {
        background-color: var(--primary-color);
        color: white;
    }
    
    .btn-primary:hover {
        background-color: #c31212;
    }
    
    .btn-danger {
        background-color: #dc3545;
        color: white;
    }
    
    .btn-danger:hover {
        background-color: #bd2130;
    }
    
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: var(--border-radius);
    }
    
    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .upload-control {
        display: flex;
        align-items: center;
        gap: 15px;
        background-color: white;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
        cursor: pointer;
    }
    
    .upload-icon {
        background-color: #f5f5f5;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #777;
    }
    
    .upload-text {
        flex: 1;
    }
    
    .upload-text p {
        margin: 0;
        font-size: 0.9rem;
        color: #777;
    }
    
    .menu-items-grid, .reviews-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .menu-item-card, .review-card {
        border: 1px solid #eee;
    }
    
    .menu-item-card img {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }
    
    .menu-item-info {
        padding: 15px;
    }
    
    .menu-item-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .menu-item-price {
        color: var(--primary-color);
        font-weight: 600;
    }
    
    .menu-item-actions {
        margin-top: 15px;
        display: flex;
        justify-content: flex-end;
    }
    
    .review-card {
        padding: 15px;
    }
    
    .review-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    
    .rating {
        color: #ffc107;
    }
    
    .no-items-message {
        grid-column: 1 / -1;
        text-align: center;
        padding: 30px;
    }
    
    .category-checkboxes {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-top: 10px;
    }
    
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        background-color: #f9f9f9;
        border-radius: 6px;
        transition: all 0.2s ease;
    }
    
    .checkbox-item:hover {
        background-color: #f0f0f0;
    }
    
    .checkbox-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .checkbox-item label {
        cursor: pointer;
        font-weight: 500;
        margin: 0;
    }
    
    /* Animations for map pin */
    @keyframes pinDrop {
        0% {
            transform: translate(-50%, -200%) rotate(45deg);
            opacity: 0;
        }
        60% {
            transform: translate(-50%, -40%) rotate(45deg);
        }
        100% {
            transform: translate(-50%, -50%) rotate(45deg);
            opacity: 1;
        }
    }
    
    @keyframes pinPop {
        0% {
            transform: translate(-50%, -50%) rotate(45deg) scale(1);
        }
        50% {
            transform: translate(-50%, -50%) rotate(45deg) scale(1.3);
        }
        100% {
            transform: translate(-50%, -50%) rotate(45deg) scale(1.2);
        }
    }
</style>

<section class="manage-stall-section">
    <div class="container">
        <div class="manage-stall-header">
            <h2>Manage Your Stall</h2>
            <p>Update your stall information, add menu items, and view customer reviews.</p>
        </div>
        
        <?php if(!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($edit_location): ?>
        <!-- Pin Location Edit Section -->
        <div class="card mb-4 location-edit-card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, var(--primary-color), #ee2f2f); color: white; border-bottom: none;">
                <h5 class="mb-0 d-flex align-items-center">
                    <div class="icon-circle" style="width: 32px; height: 32px; background-color: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    Edit Stall Location
                </h5>
                <a href="manage-stall" class="cancel-btn" style="background-color: rgba(255, 255, 255, 0.2); color: white; border: none; border-radius: 20px; padding: 8px 16px; display: flex; align-items: center; text-decoration: none; transition: all 0.2s ease;">
                    <i class="fas fa-times mr-2" style="margin-right: 6px;"></i> Cancel
                </a>
            </div>
            <div class="card-body" style="padding: 25px;">
                <div class="location-section">
                
                <style>
                    .location-edit-card {
                        border: none;
                        border-radius: 12px;
                        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                        overflow: hidden;
                    }
                    
                    .cancel-btn:hover {
                        background-color: rgba(255, 255, 255, 0.3);
                        transform: translateY(-2px);
                    }
                </style>
                    <div class="search-bar" style="background: white; border-radius: 50px; box-shadow: 0 3px 15px rgba(0,0,0,0.1); padding: 15px 20px; margin-bottom: 20px; display: flex; align-items: center; border: 1px solid #eaeaea;">
                        <div class="location-icon" style="background: linear-gradient(135deg, var(--primary-color), #ee2f2f); width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; box-shadow: 0 3px 8px rgba(239, 28, 28, 0.2);">
                            <i class="fas fa-map-marker-alt" style="color: white; font-size: 18px;"></i>
                        </div>
                        <input type="text" class="search-input" style="border: none; outline: none; width: 100%; font-size: 1rem; background: transparent;" placeholder="PUP Lagoon, Santa Mesa, Manila, 1008 Metro Manila" value="<?php echo htmlspecialchars($stall['location']); ?>">
                    </div>
                    
                    <div class="location-instructions" style="background-color: #f8f9fa; border-left: 4px solid var(--primary-color); padding: 12px 15px; margin-bottom: 20px; border-radius: 0 8px 8px 0;">
                        <h6 style="margin: 0 0 5px 0; font-weight: 600; color: #333;"><i class="fas fa-info-circle" style="color: var(--primary-color); margin-right: 6px;"></i> How to set your stall location</h6>
                        <p style="margin: 0; color: #6c757d; font-size: 0.9rem;">Enter your stall's general location above, then click on the exact spot on the map below to place your pin.</p>
                    </div>
                    
                    <div class="map-container" style="height: auto; position: relative; cursor: crosshair;">
                        <img src="public/images/pup-map.jpg" alt="PUP Map" style="width: 100%; display: block;" onerror="this.onerror=null; this.src='public/images/map.jpg'; if(!this.complete) this.src='https://pinned.free.nf/public/images/pup-map.jpg';">
                        <div class="map-pin" id="mapPin" style="<?php if(!empty($stall['pin_x']) && !empty($stall['pin_y'])): ?>left: <?php echo $stall['pin_x']; ?>%; top: <?php echo $stall['pin_y']; ?>%; display: block; position: absolute; width: 30px; height: 42px; background-color: var(--primary-color); border-radius: 50% 50% 0 50%; transform: translate(-50%, -50%) rotate(45deg); z-index: 10; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.4);<?php else: ?>display: none; position: absolute; width: 30px; height: 42px; background-color: var(--primary-color); border-radius: 50% 50% 0 50%; transform: translate(-50%, -50%) rotate(45deg); z-index: 10; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.4);<?php endif; ?>"></div>
                    </div>
                    
                    <div class="pin-location-display" id="pinLocationDisplay" style="margin: 20px auto; max-width: 100%; padding: 15px; background-color: #f8f9fa; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; align-items: center; border: 1px solid #e9ecef; position: relative;">
                        <div class="pin-preview-container" style="background: rgba(239, 28, 28, 0.1); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <div class="pin-preview" style="width: 20px; height: 28px; background-color: var(--primary-color); border-radius: 50% 50% 0 50%; transform: rotate(45deg); border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); position: relative;">
                                <div style="position: absolute; width: 8px; height: 8px; background: white; border-radius: 50%; top: 5px; left: 5px;"></div>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 0.8rem; font-weight: 600; color: #6c757d; margin-bottom: 3px; text-transform: uppercase;">Current Pin Location</div>
                            <span class="pin-location-text" style="font-size: 1rem; color: #333;"><?php echo !empty($stall['location']) ? htmlspecialchars($stall['location']) : 'Click on the map to pin your location'; ?></span>
                        </div>
                    </div>
                    
                    <form id="updatePinLocationForm" action="manage-stall" method="POST">
                        <!-- Hidden inputs to store the pin location values -->
                        <input type="hidden" id="pin_location" name="pin_location" value="<?php echo htmlspecialchars($stall['location']); ?>">
                        <input type="hidden" id="pin_x" name="pin_x" value="<?php echo htmlspecialchars($stall['pin_x'] ?? ''); ?>">
                        <input type="hidden" id="pin_y" name="pin_y" value="<?php echo htmlspecialchars($stall['pin_y'] ?? ''); ?>">
                        <input type="hidden" name="update_pin_location" value="1">
                        
                        <div class="text-center mt-4">
                            <div class="action-buttons">
                                <button type="submit" class="save-location-btn">
                                    <i class="fas fa-check-circle"></i> Save Location
                                </button>
                                <a href="manage-stall" class="cancel-action-btn">
                                    <i class="fas fa-times-circle"></i> Cancel
                                </a>
                            </div>
                        </div>
                        
                        <style>
                            .action-buttons {
                                display: flex;
                                justify-content: center;
                                gap: 15px;
                            }
                            
                            .save-location-btn {
                                background: linear-gradient(135deg, #28a745, #20c997);
                                color: white;
                                border: none;
                                padding: 12px 25px;
                                border-radius: 30px;
                                font-weight: 600;
                                font-size: 1rem;
                                cursor: pointer;
                                transition: all 0.3s ease;
                                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
                                display: flex;
                                align-items: center;
                            }
                            
                            .save-location-btn i {
                                margin-right: 8px;
                            }
                            
                            .save-location-btn:hover {
                                transform: translateY(-3px);
                                box-shadow: 0 6px 15px rgba(40, 167, 69, 0.4);
                            }
                            
                            .cancel-action-btn {
                                background-color: #f8f9fa;
                                color: #6c757d;
                                border: 1px solid #dee2e6;
                                padding: 12px 25px;
                                border-radius: 30px;
                                font-weight: 600;
                                font-size: 1rem;
                                cursor: pointer;
                                transition: all 0.3s ease;
                                text-decoration: none;
                                display: flex;
                                align-items: center;
                            }
                            
                            .cancel-action-btn i {
                                margin-right: 8px;
                            }
                            
                            .cancel-action-btn:hover {
                                background-color: #e9ecef;
                                color: #495057;
                            }
                        </style>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Pin location functionality
                const searchInput = document.querySelector('.search-input');
                const pinLocationDisplay = document.getElementById('pinLocationDisplay');
                const pinLocationInput = document.getElementById('pin_location');
                const pinLocationText = document.querySelector('.pin-location-text');
                const mapContainer = document.querySelector('.map-container');
                const mapPin = document.getElementById('mapPin');
                const pinXInput = document.getElementById('pin_x');
                const pinYInput = document.getElementById('pin_y');
                
                // Set pin location on clicking the map
                if (mapContainer && mapPin) {
                    mapContainer.addEventListener('click', function(e) {
                        // Calculate position relative to the map
                        const rect = this.getBoundingClientRect();
                        const x = e.clientX - rect.left; // x position within the element
                        const y = e.clientY - rect.top;  // y position within the element
                        
                        // Calculate percentage positions (useful for responsive design)
                        const xPercent = (x / rect.width) * 100;
                        const yPercent = (y / rect.height) * 100;
                        
                        // Update pin position with the new teardrop-shaped pin style
                        mapPin.style.left = xPercent + '%';
                        mapPin.style.top = yPercent + '%';
                        mapPin.style.display = 'block';
                        
                        // Add inner circle to pin for better visual appearance
                        if (!mapPin.querySelector('.pin-inner-circle')) {
                            const innerCircle = document.createElement('div');
                            innerCircle.className = 'pin-inner-circle';
                            innerCircle.style.position = 'absolute';
                            innerCircle.style.width = '12px';
                            innerCircle.style.height = '12px';
                            innerCircle.style.background = 'white';
                            innerCircle.style.borderRadius = '50%';
                            innerCircle.style.top = '8px';
                            innerCircle.style.left = '8px';
                            mapPin.appendChild(innerCircle);
                        }
                        
                        // Store coordinates in hidden inputs
                        pinXInput.value = xPercent.toFixed(2);
                        pinYInput.value = yPercent.toFixed(2);
                        
                        // Create location text with pin coordinates
                        const pinLocationDesc = `${searchInput.value || 'PUP Campus Location'} (${xPercent.toFixed(0)}%, ${yPercent.toFixed(0)}%)`;
                        pinLocationText.textContent = pinLocationDesc;
                        
                        // Update pin_location hidden input with the full description
                        pinLocationInput.value = pinLocationDesc;
                        
                        // Visual feedback with animation
                        pinLocationDisplay.style.backgroundColor = '#f8f8f8';
                        
                        // Add animation to the pin
                        mapPin.style.animation = 'none';
                        setTimeout(() => {
                            mapPin.style.animation = 'pinDrop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards';
                        }, 10);
                        setTimeout(() => {
                            pinLocationDisplay.style.backgroundColor = 'white';
                        }, 300);
                    });
                }
                
                // Update pin location text when search input changes
                if (searchInput && pinLocationText && pinLocationInput) {
                    searchInput.addEventListener('input', function() {
                        if (this.value) {
                            // If pin is already set, update with coordinates included
                            if (mapPin.style.display === 'block') {
                                const xPercentValue = pinXInput.value;
                                const yPercentValue = pinYInput.value;
                                const pinLocationDesc = `${this.value} (${parseFloat(xPercentValue).toFixed(0)}%, ${parseFloat(yPercentValue).toFixed(0)}%)`;
                                pinLocationText.textContent = pinLocationDesc;
                                pinLocationInput.value = pinLocationDesc;
                            } else {
                                // If pin is not set, just update the description
                                pinLocationText.textContent = this.value;
                                pinLocationInput.value = this.value;
                            }
                        }
                    });
                }
            });
        </script>
        <?php endif; ?>
        
        <ul class="nav-tabs" id="stallTabs">
            <li class="nav-item">
                <a class="nav-link active" data-tab="stall-info">Stall Information</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-tab="menu-items">Menu Items</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-tab="reviews">Recent Reviews</a>
            </li>
        </ul>
        
        <div class="tab-content">
            <!-- Stall Information Tab -->
            <div class="tab-pane active" id="stall-info">
                <div class="stall-info-card">
                    <div class="card-header">
                        <h3>Update Stall Information</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="manage-stall" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="stall_name">Stall Name</label>
                                <input type="text" id="stall_name" name="stall_name" class="form-control" value="<?php echo htmlspecialchars($stall['name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="stall_description">Stall Description</label>
                                <textarea id="stall_description" name="stall_description" class="form-control" required><?php echo htmlspecialchars($stall['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="location">Location</label>
                                <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($stall['location'] ?? ''); ?>" required>
                            </div>
                            
                            <!-- Current Pin Location Display -->
                            <div class="form-group mt-4">
                                <label>Current Pin Location</label>
                                <div class="current-pin-container" style="position: relative; border-radius: 8px; overflow: hidden; margin-top: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                    <img src="public/images/pup-map.jpg" alt="PUP Map" style="width: 100%; display: block;" onerror="this.onerror=null; this.src='public/images/map.jpg'; if(!this.complete) this.src='https://pinned.free.nf/public/images/pup-map.jpg';">
                                    
                                    <?php if(!empty($stall['pin_x']) && !empty($stall['pin_y'])): ?>
                                    <div class="current-pin" style="position: absolute; top: <?php echo $stall['pin_y']; ?>%; left: <?php echo $stall['pin_x']; ?>%; width: 30px; height: 42px; background-color: var(--primary-color); border-radius: 50% 50% 0 50%; transform: translate(-50%, -50%) rotate(45deg); z-index: 10; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.4);">
                                        <div style="position: absolute; width: 12px; height: 12px; background: white; border-radius: 50%; top: 8px; left: 8px;"></div>
                                    </div>
                                    <div class="current-pin-info" style="position: absolute; bottom: 15px; left: 15px; background: white; padding: 8px 15px; border-radius: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); display: flex; align-items: center; max-width: 80%;">
                                        <div class="pin-preview" style="width: 16px; height: 22px; background-color: var(--primary-color); border-radius: 50% 50% 0 50%; transform: rotate(45deg); margin-right: 10px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); position: relative;">
                                            <div style="position: absolute; width: 6px; height: 6px; background: white; border-radius: 50%; top: 4px; left: 4px;"></div>
                                        </div>
                                        <span style="font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($stall['pin_x']); ?>%, <?php echo htmlspecialchars($stall['pin_y']); ?>%</span>
                                    </div>
                                    <?php else: ?>
                                    <div class="no-pin-message" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.9); padding: 10px 20px; border-radius: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                                        <span style="font-size: 0.9rem;">No pin location set</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-3 text-center">
                                    <a href="manage-stall?action=edit_location" class="edit-location-btn">
                                        <span class="icon-wrapper">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </span>
                                        <span>Edit Stall Location</span>
                                    </a>
                                    <small class="d-block text-muted mt-2">Click to place your stall's exact location on the map</small>
                                </div>
                                
                                <style>
                                    .edit-location-btn {
                                        display: inline-flex;
                                        align-items: center;
                                        background: linear-gradient(135deg, var(--primary-color), #ee2f2f);
                                        color: white;
                                        padding: 10px 20px;
                                        border-radius: 30px;
                                        text-decoration: none;
                                        font-weight: 600;
                                        font-size: 0.95rem;
                                        transition: all 0.3s ease;
                                        box-shadow: 0 3px 10px rgba(239, 28, 28, 0.2);
                                        border: none;
                                    }
                                    
                                    .edit-location-btn:hover {
                                        transform: translateY(-2px);
                                        box-shadow: 0 5px 15px rgba(239, 28, 28, 0.3);
                                        color: white;
                                    }
                                    
                                    .edit-location-btn:active {
                                        transform: translateY(0);
                                        box-shadow: 0 2px 8px rgba(239, 28, 28, 0.2);
                                    }
                                    
                                    .edit-location-btn .icon-wrapper {
                                        background-color: rgba(255, 255, 255, 0.2);
                                        width: 28px;
                                        height: 28px;
                                        border-radius: 50%;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin-right: 10px;
                                    }
                                </style>
                            </div>
                            
                            <div class="form-group">
                                <label>Food Categories</label>
                                <div class="category-checkboxes">
                                    <?php
                                    $food_types = explode(', ', $stall['food_type'] ?? '');
                                    $categories = ['Beverages', 'Rice Meals', 'Snack', 'Street Food', 'Fast Food'];
                                    $other_categories = array_diff($food_types, $categories);
                                    
                                    foreach ($categories as $category) {
                                        $checked = in_array($category, $food_types) ? 'checked' : '';
                                        $category_id = strtolower(str_replace(' ', '-', $category));
                                        echo "
                                        <div class='checkbox-item'>
                                            <input type='checkbox' id='category-{$category_id}' name='food_categories[]' value='{$category}' {$checked}>
                                            <label for='category-{$category_id}'>{$category}</label>
                                        </div>
                                        ";
                                    }
                                    
                                    $other_checked = !empty($other_categories) ? 'checked' : '';
                                    $other_display = !empty($other_categories) ? 'block' : 'none';
                                    $other_value = !empty($other_categories) ? implode(', ', $other_categories) : '';
                                    ?>
                                    
                                    <div class='checkbox-item'>
                                        <input type='checkbox' id='category-other' name='food_categories[]' value='Other' <?php echo $other_checked; ?>>
                                        <label for='category-other'>Other</label>
                                    </div>
                                </div>
                                
                                <div id="other-category-container" style="margin-top: 10px; display: <?php echo $other_display; ?>;">
                                    <input type="text" class="form-control" name="other_category" placeholder="Please specify other food category" value="<?php echo htmlspecialchars($other_value ?? ''); ?>">
                                </div>
                                
                                <!-- Hidden input to store the final food type value -->
                                <input type="hidden" name="food_type" id="food_type_hidden" value="<?php echo htmlspecialchars($stall['food_type'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="hours">Operating Hours</label>
                                <input type="text" id="hours" name="hours" class="form-control" value="<?php echo htmlspecialchars($stall['hours'] ?? ''); ?>" placeholder="e.g., Monday to Saturday 09:00 - 18:00">
                            </div>
                            
                            <div class="form-group">
                                <label>Current Logo</label>
                                <?php if (!empty($stall['logo_path']) && file_exists($stall['logo_path'])): ?>
                                    <div>
                                        <img src="<?php echo htmlspecialchars($stall['logo_path'] ?? ''); ?>" alt="Stall Logo" style="max-width: 200px; max-height: 200px; margin-top: 10px;">
                                    </div>
                                <?php else: ?>
                                    <p>No logo uploaded.</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <div class="upload-control" id="logoUploadControl">
                                    <div class="upload-icon">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                    <div class="upload-text">
                                        <label for="logoUpload">Upload New Logo (.png, .jpeg format only)</label>
                                        <p>Maximum file size: 5MB</p>
                                    </div>
                                </div>
                                <input type="file" id="logoUpload" name="logo" style="display: none;" accept=".png,.jpg,.jpeg">
                            </div>
                            
                            <button type="submit" name="update_stall" class="btn btn-primary">Update Stall Information</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Menu Items Tab -->
            <div class="tab-pane" id="menu-items">
                <div class="add-menu-item-card">
                    <div class="card-header">
                        <h3>Add New Menu Item</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="manage-stall" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="item_name">Item Name</label>
                                <input type="text" id="item_name" name="item_name" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="item_description">Item Description</label>
                                <textarea id="item_description" name="item_description" class="form-control"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="item_price">Price (PHP)</label>
                                <input type="number" id="item_price" name="item_price" class="form-control" min="0" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <div class="upload-control" id="itemImageUploadControl">
                                    <div class="upload-icon">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                    <div class="upload-text">
                                        <label for="itemImageUpload">Upload Item Image (.png, .jpeg format only)</label>
                                        <p>Maximum file size: 5MB</p>
                                    </div>
                                </div>
                                <input type="file" id="itemImageUpload" name="item_image" style="display: none;" accept=".png,.jpg,.jpeg">
                            </div>
                            
                            <button type="submit" name="add_menu_item" class="btn btn-primary">Add Menu Item</button>
                        </form>
                    </div>
                </div>
                
                <h3>Your Menu Items</h3>
                <div class="menu-items-grid">
                    <?php if (empty($menu_items)): ?>
                        <div class="no-items-message">
                            <p>You haven't added any menu items yet. Add your first menu item above!</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        // Check if we're in edit mode for a specific item
                        $edit_item_id = isset($_POST['edit_item']) ? intval($_POST['item_id']) : 0;
                        
                        // If in edit mode, get the item details first
                        $edit_item = null;
                        if ($edit_item_id > 0) {
                            foreach ($menu_items as $item) {
                                if ($item['id'] == $edit_item_id) {
                                    $edit_item = $item;
                                    break;
                                }
                            }
                        }
                        
                        // If in edit mode, show edit form instead of regular items grid
                        if ($edit_item): 
                        ?>
                            <div style="grid-column: 1 / -1;">
                                <div class="add-menu-item-card">
                                    <div class="card-header">
                                        <h3>Edit Menu Item</h3>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="manage-stall" enctype="multipart/form-data">
                                            <input type="hidden" name="item_id" value="<?php echo $edit_item['id']; ?>">
                                            
                                            <div class="form-group">
                                                <label for="item_name">Item Name</label>
                                                <input type="text" id="item_name" name="item_name" class="form-control" value="<?php echo htmlspecialchars($edit_item['name'] ?? ''); ?>" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="item_description">Item Description</label>
                                                <textarea id="item_description" name="item_description" class="form-control"><?php echo htmlspecialchars($edit_item['description'] ?? ''); ?></textarea>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="item_price">Price (PHP)</label>
                                                <input type="number" id="item_price" name="item_price" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars($edit_item['price'] ?? ''); ?>" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <div class="upload-control" id="editItemImageUploadControl">
                                                    <div class="upload-icon">
                                                        <i class="fas fa-upload"></i>
                                                    </div>
                                                    <div class="upload-text">
                                                        <label for="editItemImageUpload">Upload New Item Image (.png, .jpeg format only)</label>
                                                        <p><?php echo !empty($edit_item['image_path']) ? 'Current image: ' . basename($edit_item['image_path']) : 'No image currently uploaded'; ?></p>
                                                    </div>
                                                </div>
                                                <input type="file" id="editItemImageUpload" name="item_image" style="display: none;" accept=".png,.jpg,.jpeg">
                                                <small class="text-muted">Leave empty to keep current image</small>
                                            </div>
                                            
                                            <button type="submit" name="edit_menu_item" class="btn btn-primary">Update Menu Item</button>
                                            <a href="manage-stall" class="btn" style="background-color: #6c757d; color: white; margin-left: 10px;">Cancel</a>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($menu_items as $item): ?>
                                <div class="menu-item-card">
                                    <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_path'] ?? ''); ?>" alt="<?php echo htmlspecialchars($item['name'] ?? ''); ?>">
                                    <?php else: ?>
                                        <div style="height: 200px; background-color: #eee; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-utensils" style="font-size: 3rem; color: #ccc;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="menu-item-info">
                                        <div class="menu-item-title">
                                            <h4><?php echo htmlspecialchars($item['name'] ?? ''); ?></h4>
                                            <div class="menu-item-price">₱<?php echo number_format($item['price'] ?? 0, 2); ?></div>
                                        </div>
                                        
                                        <?php if (!empty($item['description'])): ?>
                                            <p><?php echo htmlspecialchars($item['description'] ?? ''); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="menu-item-actions">
                                            <form method="POST" action="manage-stall" style="display: inline; margin-right: 5px;">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" name="edit_item" class="btn" style="background-color: #4e95ff; color: white; padding: 8px 15px; font-size: 0.9rem;">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </form>
                                            
                                            <form method="POST" action="manage-stall" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this menu item?');">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" name="delete_menu_item" class="btn btn-danger" style="padding: 8px 15px; font-size: 0.9rem;">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reviews Tab -->
            <div class="tab-pane" id="reviews">
                <h3>Recent Reviews</h3>
                <div class="reviews-list">
                    <?php if (empty($reviews)): ?>
                        <div class="no-items-message">
                            <p>Your stall hasn't received any reviews yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <div class="review-rating">
                                        <?php
                                        $rating = $review['rating'] ?? 0;
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $rating) {
                                                echo '<i class="fas fa-star"></i>';
                                            } else {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                        }
                                        ?>
                                    </div>
                                    <h4><?php echo isset($review['title']) ? htmlspecialchars($review['title'] ?? '') : 'Review'; ?></h4>
                                </div>
                                
                                <div class="review-content">
                                    <p><?php echo htmlspecialchars($review['comment'] ?? ''); ?></p>
                                </div>
                                
                                <div class="review-footer">
                                    <div>By: <?php echo htmlspecialchars($review['reviewer_name'] ?? ''); ?></div>
                                    <div class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'] ?? 'now')); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab functionality
        const tabLinks = document.querySelectorAll('.nav-link');
        const tabPanes = document.querySelectorAll('.tab-pane');
        
        tabLinks.forEach(link => {
            link.addEventListener('click', function() {
                // Remove active class from all tabs
                tabLinks.forEach(tab => tab.classList.remove('active'));
                tabPanes.forEach(pane => pane.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Show corresponding content
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // File upload handling
        const logoUploadControl = document.getElementById('logoUploadControl');
        const logoFileInput = document.getElementById('logoUpload');
        
        if (logoUploadControl && logoFileInput) {
            logoUploadControl.addEventListener('click', () => {
                logoFileInput.click();
            });
            
            logoFileInput.addEventListener('change', function() {
                const fileName = this.files[0]?.name;
                if (fileName) {
                    logoUploadControl.querySelector('.upload-text p').textContent = `Selected file: ${fileName}`;
                }
            });
        }
        
        const itemImageUploadControl = document.getElementById('itemImageUploadControl');
        const itemImageFileInput = document.getElementById('itemImageUpload');
        
        if (itemImageUploadControl && itemImageFileInput) {
            itemImageUploadControl.addEventListener('click', () => {
                itemImageFileInput.click();
            });
            
            itemImageFileInput.addEventListener('change', function() {
                const fileName = this.files[0]?.name;
                if (fileName) {
                    itemImageUploadControl.querySelector('.upload-text p').textContent = `Selected file: ${fileName}`;
                }
            });
        }
        
        // Edit item image upload handling
        const editItemImageUploadControl = document.getElementById('editItemImageUploadControl');
        const editItemImageFileInput = document.getElementById('editItemImageUpload');
        
        if (editItemImageUploadControl && editItemImageFileInput) {
            editItemImageUploadControl.addEventListener('click', () => {
                editItemImageFileInput.click();
            });
            
            editItemImageFileInput.addEventListener('change', function() {
                const fileName = this.files[0]?.name;
                if (fileName) {
                    editItemImageUploadControl.querySelector('.upload-text p').textContent = `Selected file: ${fileName}`;
                }
            });
        }
        
        // Other category handling
        const categoryOther = document.getElementById('category-other');
        const otherCategoryContainer = document.getElementById('other-category-container');
        const otherCategoryInput = document.querySelector('input[name="other_category"]');
        const foodTypeHidden = document.getElementById('food_type_hidden');
        
        if (categoryOther && otherCategoryContainer) {
            categoryOther.addEventListener('change', function() {
                otherCategoryContainer.style.display = this.checked ? 'block' : 'none';
                if (!this.checked) {
                    otherCategoryInput.value = '';
                }
            });
        }
        
        // Form submission handler for stall update
        const updateStallForm = document.querySelector('form[action="manage-stall"]');
        if (updateStallForm) {
            updateStallForm.addEventListener('submit', function(e) {
                // Only process if this is the stall update form
                if (!this.querySelector('button[name="update_stall"]')) {
                    return true;
                }
                
                // Get all selected categories
                const selectedCategories = [];
                const categoryCheckboxes = document.querySelectorAll('input[name="food_categories[]"]');
                
                categoryCheckboxes.forEach(checkbox => {
                    if (checkbox.checked && checkbox.value !== 'Other') {
                        selectedCategories.push(checkbox.value);
                    }
                });
                
                // Add other category if specified
                if (categoryOther && categoryOther.checked && otherCategoryInput && otherCategoryInput.value.trim() !== '') {
                    // Split by comma and trim each entry
                    const otherCats = otherCategoryInput.value.split(',').map(cat => cat.trim()).filter(cat => cat !== '');
                    selectedCategories.push(...otherCats);
                }
                
                // Validate that at least one category is selected
                if (selectedCategories.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one food category');
                    return false;
                }
                
                // Update the hidden input with the combined categories
                if (foodTypeHidden) {
                    foodTypeHidden.value = selectedCategories.join(', ');
                }
            });
        }
    });
</script>

<?php include 'footer.php'; ?> 