<?php 
// Include the configuration file
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
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

// Check if the stall owner already has a stall
try {
    // Check if PDO connection exists before using it
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM food_stalls WHERE owner_id = :owner_id");
        $stmt->execute(['owner_id' => $_SESSION['user_id']]);
        $hasStall = ($stmt->fetchColumn() > 0);
        
        if ($hasStall) {
            $_SESSION['error_message'] = "You have already registered a stall. Please manage your existing stall.";
            header('Location: manage-stall.php');
            exit();
        }
    }
} catch (PDOException $e) {
    // Log the error and continue with the registration form
    error_log("Database error: " . $e->getMessage());
}

// Get form data from session if available
$formData = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];
unset($_SESSION['form_data']); // Clear form data from session

// Set page title
$page_title = 'Register Your Stall';

// Include header
include 'header.php';
?>

<style>
    .location-section {
        background-color: var(--light-bg);
        padding: 40px 0;
    }
    
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
    
    .pin-location-btn {
        width: auto;
        padding: 12px 20px;
        background-color: white;
        color: var(--primary-color);
        border: 1px solid #ddd;
        border-radius: 30px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .pin-location-btn i {
        color: var(--primary-color);
        font-size: 1.1rem;
    }
    
    .pin-location-btn:hover {
        background-color: #f8f8f8;
        box-shadow: 0 3px 8px rgba(0,0,0,0.15);
    }
    
    .registration-section {
        padding: 50px 0;
    }
    
    .registration-form {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .form-title {
        font-size: 2rem;
        margin-bottom: 30px;
    }
    
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-control {
        width: 100%;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
        font-size: 1rem;
    }
    
    textarea.form-control {
        min-height: 150px;
        resize: vertical;
    }
    
    .upload-control {
        display: flex;
        align-items: center;
        gap: 15px;
        background-color: white;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
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
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 30px;
    }
    
    .register-btn {
        width: 100%;
        padding: 15px;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: var(--border-radius);
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: var(--transition);
    }
    
    .register-btn:hover {
        background-color: #c31212;
    }
    
    .alert {
        padding: 15px;
        border-radius: var(--border-radius);
        margin-bottom: 20px;
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

    /* Pin location display */
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
    
    .pin-icon {
        color: var(--primary-color);
        font-size: 1.2rem;
        margin-right: 10px;
    }
    
    .pin-location-text {
        flex: 1;
        font-size: 0.95rem;
    }
</style>

<!-- Location Section -->
<section class="location-section">
    <div class="container">
        <div class="search-bar">
            <i class="fas fa-map-marker-alt search-icon"></i>
            <input type="text" class="search-input" placeholder="PUP Lagoon, Santa Mesa, Manila, 1008 Metro Manila">
        </div>
        
        <div class="map-container">
            <img src="public/images/pup-map.jpg" alt="PUP Map" style="width: 100%;" onerror="this.onerror=null; this.src='public/images/map.jpg'; if(!this.complete) this.src='https://pinned.free.nf/public/images/pup-map.jpg';">
            <div class="map-pin" id="mapPin"></div>
        </div>
        
        <div class="pin-location-display" id="pinLocationDisplay">
            <i class="fas fa-map-marker-alt pin-icon"></i>
            <span class="pin-location-text">Click on the map to pin your location</span>
        </div>
        
        <!-- Hidden inputs to store the pin location values -->
        <input type="hidden" id="pin_location" name="pin_location" value="">
        <input type="hidden" id="pin_x" name="pin_x" value="">
        <input type="hidden" id="pin_y" name="pin_y" value="">
    </div>
</section>

<!-- Registration Form -->
<section class="registration-section">
    <div class="container">
        <div class="registration-form">
            <h2 class="form-title">Register your Stall:</h2>
            
            <?php if(isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php 
                        echo $_SESSION['error_message']; 
                        unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error_messages']) && is_array($_SESSION['error_messages'])): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php 
                            foreach($_SESSION['error_messages'] as $error) {
                                echo '<li>' . $error . '</li>';
                            }
                            unset($_SESSION['error_messages']);
                        ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form id="stallRegistrationForm" action="register_stall_process.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm('stallRegistrationForm')">
                <div class="form-group">
                    <input type="text" class="form-control" name="stall_name" placeholder="Name of Stall" value="<?php echo isset($formData['stall_name']) ? htmlspecialchars($formData['stall_name']) : ''; ?>" required maxlength="16">
                </div>
                
                <div class="form-group">
                    <textarea class="form-control" name="stall_description" placeholder="Stall Description" required><?php echo isset($formData['stall_description']) ? htmlspecialchars($formData['stall_description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <input type="text" class="form-control" name="location" placeholder="Location" value="<?php echo isset($formData['location']) ? htmlspecialchars($formData['location']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Food Category:</label>
                    <div class="category-checkboxes">
                        <div class="checkbox-item">
                            <input type="checkbox" id="category-beverages" name="food_categories[]" value="Beverages" <?php echo (isset($formData['food_categories']) && in_array('Beverages', $formData['food_categories'])) ? 'checked' : ''; ?>>
                            <label for="category-beverages">Beverages</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="category-rice-meals" name="food_categories[]" value="Rice Meals" <?php echo (isset($formData['food_categories']) && in_array('Rice Meals', $formData['food_categories'])) ? 'checked' : ''; ?>>
                            <label for="category-rice-meals">Rice Meals</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="category-snack" name="food_categories[]" value="Snack" <?php echo (isset($formData['food_categories']) && in_array('Snack', $formData['food_categories'])) ? 'checked' : ''; ?>>
                            <label for="category-snack">Snack</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="category-street-food" name="food_categories[]" value="Street Food" <?php echo (isset($formData['food_categories']) && in_array('Street Food', $formData['food_categories'])) ? 'checked' : ''; ?>>
                            <label for="category-street-food">Street Food</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="category-fast-food" name="food_categories[]" value="Fast Food" <?php echo (isset($formData['food_categories']) && in_array('Fast Food', $formData['food_categories'])) ? 'checked' : ''; ?>>
                            <label for="category-fast-food">Fast Food</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="category-other" name="food_categories[]" value="Other" <?php echo (isset($formData['food_categories']) && in_array('Other', $formData['food_categories'])) ? 'checked' : ''; ?>>
                            <label for="category-other">Other</label>
                        </div>
                    </div>
                    <div id="other-category-container" style="margin-top: 10px; display: <?php echo (isset($formData['food_categories']) && in_array('Other', $formData['food_categories'])) ? 'block' : 'none'; ?>;">
                        <input type="text" class="form-control" name="other_category" placeholder="Please specify other food category" value="<?php echo isset($formData['other_category']) ? htmlspecialchars($formData['other_category']) : ''; ?>">
                    </div>
                    <!-- Hidden input to store the final food type value -->
                    <input type="hidden" name="food_type" id="food_type_hidden" value="<?php echo isset($formData['food_type']) ? htmlspecialchars($formData['food_type']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <div class="upload-control">
                        <div class="upload-icon">
                            <i class="fas fa-upload"></i>
                        </div>
                        <div class="upload-text">
                            <label for="logoUpload">Upload Logo (.png, .jpeg format only)</label>
                            <p>Maximum file size: 5MB</p>
                        </div>
                    </div>
                    <input type="file" id="logoUpload" name="logo" style="display: none;" accept=".png,.jpg,.jpeg">
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="terms" required>
                    <label for="terms">I agree to all statements in <a href="#" class="red-text">Terms of Service</a></label>
                </div>
                
                <button type="submit" class="register-btn">REGISTER STALL</button>
            </form>
        </div>
    </div>
</section>

<?php
// Include footer
include 'footer.php';
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle the file upload click
        const uploadControl = document.querySelector('.upload-control');
        const fileInput = document.getElementById('logoUpload');
        
        if (uploadControl && fileInput) {
            uploadControl.addEventListener('click', () => {
                fileInput.click();
            });
            
            // Show selected file name
            fileInput.addEventListener('change', function() {
                const fileName = this.files[0]?.name;
                if (fileName) {
                    const uploadText = document.querySelector('.upload-text p');
                    uploadText.textContent = `Selected file: ${fileName}`;
                }
            });
        }
        
        // Check if map image loaded properly
        const mapImage = document.querySelector('.map-container img');
        if (mapImage) {
            if (!mapImage.complete || mapImage.naturalWidth === 0) {
                console.log('Map image failed to load, trying alternative source');
                mapImage.src = 'https://pinned.free.nf/public/images/pup-map.jpg';
            }
        }
        
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
                
                // Update pin position - already has transform: translate(-50%, -50%)
                mapPin.style.left = xPercent + '%';
                mapPin.style.top = yPercent + '%';
                mapPin.style.display = 'block';
                
                // Store coordinates in hidden inputs (without units)
                pinXInput.value = xPercent.toFixed(2);
                pinYInput.value = yPercent.toFixed(2);
                
                // Generate location description
                const locationDesc = `PUP Campus Location (${xPercent.toFixed(0)}%, ${yPercent.toFixed(0)}%)`;
                pinLocationText.textContent = locationDesc;
                pinLocationInput.value = locationDesc;
                
                // Visual feedback
                pinLocationDisplay.style.backgroundColor = '#f8f8f8';
                setTimeout(() => {
                    pinLocationDisplay.style.backgroundColor = 'white';
                }, 300);
            });
        }
        
        // Update pin location when search input changes (optional)
        if (searchInput && pinLocationText && pinLocationInput) {
            searchInput.addEventListener('input', function() {
                if (this.value) {
                    pinLocationText.textContent = this.value;
                    pinLocationInput.value = this.value;
                }
            });
        }
        
        // Category checkboxes handling
        const categoryCheckboxes = document.querySelectorAll('input[name="food_categories[]"]');
        const otherCheckbox = document.getElementById('category-other');
        const otherCategoryContainer = document.getElementById('other-category-container');
        const otherCategoryInput = document.querySelector('input[name="other_category"]');
        const foodTypeHidden = document.getElementById('food_type_hidden');
        
        // Show/hide other category input field
        if (otherCheckbox && otherCategoryContainer) {
            otherCheckbox.addEventListener('change', function() {
                otherCategoryContainer.style.display = this.checked ? 'block' : 'none';
                if (!this.checked) {
                    otherCategoryInput.value = '';
                }
            });
        }
        
        // Update the hidden food_type field before form submission
        const registrationForm = document.getElementById('stallRegistrationForm');
        if (registrationForm) {
            registrationForm.addEventListener('submit', function(e) {
                // Get all selected categories
                const selectedCategories = [];
                categoryCheckboxes.forEach(checkbox => {
                    if (checkbox.checked && checkbox.value !== 'Other') {
                        selectedCategories.push(checkbox.value);
                    }
                });
                
                // Add other category if specified
                if (otherCheckbox && otherCheckbox.checked && otherCategoryInput && otherCategoryInput.value.trim() !== '') {
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
                
                // Make sure pin location is included in the form
                const locationInput = document.querySelector('input[name="location"]');
                if (locationInput && pinLocationInput) {
                    locationInput.value = pinLocationInput.value;
                }
            });
        }
        
        // Form validation is handled by the main.js validateForm function
    });
</script> 