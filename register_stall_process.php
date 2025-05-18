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
    $_SESSION['error_message'] = "Please log in to register a stall.";
    header('Location: signin.php');
    exit();
}

// Check if user is a stall owner
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'stall_owner') {
    $_SESSION['error_message'] = "Only stall owners can register stalls.";
    header('Location: index.php');
    exit();
}

// Check if database connection is available
if (!isset($pdo)) {
    $_SESSION['error_message'] = "Database connection error. Please try again later.";
    header('Location: register-stall.php');
    exit();
}

// Check if the stall owner already has a stall
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM food_stalls WHERE owner_id = :owner_id");
    $stmt->execute(['owner_id' => $_SESSION['user_id']]);
    $hasStall = ($stmt->fetchColumn() > 0);
    
    if ($hasStall) {
        $_SESSION['error_message'] = "You have already registered a stall. Please manage your existing stall.";
        header('Location: manage-stall.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error. Please try again later.";
    header('Location: register-stall.php');
    exit();
}

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $stallName = isset($_POST['stall_name']) ? trim($_POST['stall_name']) : '';
    $stallDescription = isset($_POST['stall_description']) ? trim($_POST['stall_description']) : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $foodType = isset($_POST['food_type']) ? trim($_POST['food_type']) : '';
    
    // Get pin location data
    $pinX = isset($_POST['pin_x']) ? trim($_POST['pin_x']) : '';
    $pinY = isset($_POST['pin_y']) ? trim($_POST['pin_y']) : '';
    
    // Debugging: Log received pin coordinates
    error_log("Received pin coordinates: x={$pinX}, y={$pinY}");
    
    // Store the food categories for repopulating the form if there are errors
    $foodCategories = isset($_POST['food_categories']) ? $_POST['food_categories'] : [];
    $otherCategory = isset($_POST['other_category']) ? trim($_POST['other_category']) : '';
    
    // If food_type is not set but we have food_categories, let's build food_type
    if (empty($foodType) && !empty($foodCategories)) {
        $selectedCategories = [];
        
        // Add regular categories
        foreach ($foodCategories as $category) {
            if ($category !== 'Other') {
                $selectedCategories[] = $category;
            }
        }
        
        // Add other category if specified
        if (in_array('Other', $foodCategories) && !empty($otherCategory)) {
            // Split by comma and trim each entry
            $otherCats = array_map('trim', explode(',', $otherCategory));
            $otherCats = array_filter($otherCats, function($cat) { return !empty($cat); });
            
            // Add filtered other categories
            $selectedCategories = array_merge($selectedCategories, $otherCats);
        }
        
        // Set the food type
        if (!empty($selectedCategories)) {
            $foodType = implode(', ', $selectedCategories);
        }
    }
    
    // Validate required fields
    $errors = [];
    
    if (empty($stallName)) {
        $errors[] = "Stall name is required";
    } elseif (strlen($stallName) > 16) {
        $errors[] = "Stall name must be 16 characters or less";
    }
    
    if (empty($stallDescription)) {
        $errors[] = "Stall description is required";
    }
    
    if (empty($location)) {
        $errors[] = "Location is required";
    }
    
    if (empty($foodType)) {
        $errors[] = "At least one food category is required";
    }
    
    // Extract pin coordinates from location if they're not provided separately
    if ((empty($pinX) || empty($pinY)) && !empty($location)) {
        // Try to extract coordinates from format like "PUP Campus Location (56%, 24%)"
        if (preg_match('/\((\d+\.?\d*)%,\s*(\d+\.?\d*)%\)/', $location, $matches)) {
            $pinX = $matches[1];
            $pinY = $matches[2];
            error_log("Extracted coordinates from location: x={$pinX}, y={$pinY}");
        }
    }
    
    // Process logo upload if file is selected
    $logoPath = '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['logo']['type'], $allowedTypes)) {
            $errors[] = "Invalid file type. Only PNG and JPEG files are allowed.";
        }
        
        if ($_FILES['logo']['size'] > $maxSize) {
            $errors[] = "File size exceeds the maximum allowed size (5MB).";
        }
        
        if (empty($errors)) {
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
            } else {
                $errors[] = "Failed to upload logo. Please try again.";
            }
        }
    }
    
    // If there are no errors, proceed with database insertion
    if (empty($errors)) {
        try {
            // Check if database connection is still available
            if (!isset($pdo)) {
                throw new PDOException("Database connection lost");
            }
            
            // Ensure pin coordinates are numeric values
            $pinX = !empty($pinX) ? floatval($pinX) : null;
            $pinY = !empty($pinY) ? floatval($pinY) : null;
            
            // Final validation to ensure we have valid coordinates
            if ($pinX === null || $pinY === null || !is_numeric($pinX) || !is_numeric($pinY)) {
                error_log("Pin coordinates invalid after processing: x={$pinX}, y={$pinY}");
                $pinX = 50; // Default to center if no valid coordinates
                $pinY = 50;
            }
            
            // Insert stall data into database
            $stmt = $pdo->prepare("INSERT INTO food_stalls (owner_id, name, description, location, food_type, logo_path, pin_x, pin_y, created_at) 
                                VALUES (:owner_id, :name, :description, :location, :food_type, :logo_path, :pin_x, :pin_y, NOW())");
            
            $stmt->execute([
                'owner_id' => $_SESSION['user_id'],
                'name' => $stallName,
                'description' => $stallDescription,
                'location' => $location,
                'food_type' => $foodType,
                'logo_path' => $logoPath,
                'pin_x' => $pinX,
                'pin_y' => $pinY
            ]);
            
            // Log success for debugging purposes
            error_log("Stall registered with pin coordinates: x={$pinX}, y={$pinY}");
            
            // Set success message and redirect to manage stall page
            $_SESSION['success_message'] = "Your stall has been registered successfully!";
            header('Location: manage-stall.php');
            exit();
            
        } catch (PDOException $e) {
            error_log("Database error in stall registration: " . $e->getMessage());
            $errors[] = "Database error: Unable to register stall. Please try again later.";
        }
    }
    
    // If there are errors, store them in session and redirect back to form
    if (!empty($errors)) {
        $_SESSION['error_messages'] = $errors;
        $_SESSION['form_data'] = $_POST; // Store form data for repopulating fields
        $_SESSION['food_categories'] = $foodCategories;
        $_SESSION['other_category'] = $otherCategory;
        header('Location: register-stall.php');
        exit();
    }
} else {
    // If not POST request, redirect to the form page
    header('Location: register-stall.php');
    exit();
}
?> 