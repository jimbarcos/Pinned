<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include configuration file to connect to the database
require_once 'config.php';

// Fetch all food stalls from the database
$stalls = [];
try {
    if (isset($pdo)) {
        // Enable direct debugging
        echo "<!-- Starting stall database query -->";
        
        // Get all stalls without filtering in the query - we'll filter in PHP
        $stmt = $pdo->prepare("
            SELECT * FROM food_stalls 
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $stalls = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalStalls = count($stalls);
        echo "<!-- Found {$totalStalls} total stalls -->";
        
        // Manually check for pins and fix any issues
        $validPins = 0;
        foreach ($stalls as $key => $stall) {
            // Make sure pin_x and pin_y are valid numeric values
            if (isset($stall['pin_x']) && is_numeric($stall['pin_x']) && 
                isset($stall['pin_y']) && is_numeric($stall['pin_y'])) {
                // Valid numeric coordinates
                $validPins++;
                // Ensure coordinates are properly formatted as floats
                $stalls[$key]['pin_x'] = floatval($stall['pin_x']);
                $stalls[$key]['pin_y'] = floatval($stall['pin_y']);
            } else {
                // Try to extract numeric values if they exist but are in wrong format
                $pinX = null;
                $pinY = null;
                
                if (!empty($stall['pin_x']) && preg_match('/(\d+\.?\d*)/', $stall['pin_x'], $matches)) {
                    $pinX = $matches[1];
                }
                
                if (!empty($stall['pin_y']) && preg_match('/(\d+\.?\d*)/', $stall['pin_y'], $matches)) {
                    $pinY = $matches[1];
                }
                
                if ($pinX !== null && $pinY !== null) {
                    // Extracted valid coordinates
                    $stalls[$key]['pin_x'] = floatval($pinX);
                    $stalls[$key]['pin_y'] = floatval($pinY);
                    $validPins++;
                } else {
                    // No valid coordinates found
                    $stalls[$key]['pin_x'] = null;
                    $stalls[$key]['pin_y'] = null;
                }
            }
            
            // Debug each stall
            echo "<!-- Stall ID: {$stall['id']}, Name: {$stall['name']}, pin_x: {$stalls[$key]['pin_x']}, pin_y: {$stalls[$key]['pin_y']} -->";
        }
        
        echo "<!-- Found {$validPins} stalls with valid pin coordinates -->";
    }
} catch (PDOException $e) {
    // Log the error
    error_log("Map stalls fetch error: " . $e->getMessage());
    echo "<!-- Database error: " . $e->getMessage() . " -->";
}

// Only show fix button to admin users
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    echo "<div style='position: fixed; bottom: 10px; right: 10px; z-index: 1000;'>
    <a href='fix_pin_coordinates.php' style='padding: 10px; background: #f00; color: #fff; text-decoration: none; border-radius: 5px;'>Fix Pin Coordinates</a>
    </div>";
}

// Set page title
$page_title = "Food Stall Map";

// Include header
include 'header.php';
?>
    <style>
        .map-header {
            background-color: var(--primary-color);
            color: white;
            padding: 40px 0;
            text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .map-header:before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
        z-index: 1;
        }
        
        .map-header h1 {
            margin-bottom: 10px;
        position: relative;
        z-index: 2;
        font-size: 2.5rem;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    .map-header p {
        position: relative;
        z-index: 2;
        font-size: 1.1rem;
        max-width: 600px;
        margin: 0 auto;
        opacity: 0.9;
        }
        
        .map-container {
            padding: 50px 0;
        background-color: var(--light-bg);
        }
        
        .map-wrapper {
            position: relative;
            width: 100%;
        height: auto;
            background-color: #f5f5f5;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        transition: all 0.3s ease;
        margin-bottom: 30px;
        cursor: crosshair;
    }
    
    .map-wrapper:hover {
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .map-image {
            width: 100%;
        height: auto;
        display: block;
            background-color: #fff;
        position: relative;
        z-index: 1;
        object-fit: cover;
        }
        
    /* Enhanced map pin styling */
        .map-pin {
            position: absolute;
        width: 30px; /* Slightly smaller size */
        height: 42px; /* Taller to account for teardrop shape */
            background-color: var(--primary-color);
        border-radius: 50% 50% 0 50%;
        transform: translate(-50%, -50%) rotate(45deg);
            cursor: pointer;
        box-shadow: 0 2px 8px rgba(0,0,0,0.4);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        z-index: 100;
        border: 2px solid white;
        }
        
        .map-pin:hover {
        transform: translate(-50%, -50%) rotate(45deg) scale(1.2);
        z-index: 101;
        box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }
        
        .map-pin::after {
            content: '';
            position: absolute;
        width: 12px;
        height: 12px;
            background-color: white;
            border-radius: 50%;
            top: 8px;
            left: 8px;
        }
        
    /* Updated tooltip styling with adaptive positioning */
        .map-pin-tooltip {
            position: absolute;
    width: 200px;
    bottom: 40px;
            left: 50%;
    transform: translateX(-50%);
            background-color: white;
    padding: 12px;
    border-radius: 8px;
    box-shadow: 0 3px 14px rgba(0,0,0,0.3);
            opacity: 0;
            visibility: hidden;
    transition: all 0.2s ease;
    z-index: 200;
    pointer-events: none;
    border: 1px solid rgba(0,0,0,0.1);
        }
        
/* Show tooltip on hover */
        .map-pin:hover .map-pin-tooltip {
            opacity: 1;
            visibility: visible;
    pointer-events: auto;
    bottom: 45px; 
}

/* Arrow at bottom of tooltip */
.map-pin-tooltip:after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 50%;
    transform: translateX(-50%) rotate(45deg);
    width: 16px;
    height: 16px;
    background: white;
    border-bottom: 1px solid rgba(0,0,0,0.1);
    border-right: 1px solid rgba(0,0,0,0.1);
}

/* Adaptive positioning for pins near top */
.map-pin.pin-top .map-pin-tooltip {
    bottom: auto;
    top: 40px;
}

.map-pin.pin-top:hover .map-pin-tooltip {
    top: 45px;
}

.map-pin.pin-top .map-pin-tooltip:after {
    bottom: auto;
    top: -8px;
    transform: translateX(-50%) rotate(-135deg);
    border: none;
    border-top: 1px solid rgba(0,0,0,0.1);
    border-left: 1px solid rgba(0,0,0,0.1);
}
    
    /* Tooltip content styling */
        .tooltip-content {
        position: relative;
        }
        
        .tooltip-stall-name {
            font-weight: 700;
        font-size: 16px;
        color: #222;
        margin: 0 0 5px 0;
        line-height: 1.2;
        }
        
        .tooltip-stall-type {
        font-size: 13px;
            color: #777;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
    }
    
    .tooltip-stall-type:before {
        content: '•';
        margin-right: 5px;
        color: var(--primary-color);
    }
    
    .tooltip-location {
        font-size: 12px;
        color: #555;
        margin: 8px 0;
        display: flex;
        align-items: center;
        padding-top: 8px;
        border-top: 1px solid #eee;
    }
    
    .tooltip-actions {
        display: flex;
        margin-top: 10px;
        justify-content: center;
        gap: 6px;
        }
        
        .tooltip-action {
            display: inline-block;
        padding: 6px 12px;
            background-color: var(--primary-color);
            color: white;
        border-radius: 30px;
        font-size: 12px;
            text-align: center;
        transition: all 0.2s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        text-decoration: none;
        font-weight: 600;
    }
    
    .tooltip-edit-action {
        background-color: #4CAF50;
    }
    
    .tooltip-action:hover {
        background-color: #c01515;
        transform: translateY(-2px);
        box-shadow: 0 3px 8px rgba(0,0,0,0.3);
        }
        
        .map-legend {
            display: flex;
            justify-content: center;
            gap: 20px;
        margin: 30px auto 0;
            flex-wrap: wrap;
        max-width: 800px;
        background-color: white;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
        padding: 5px 10px;
        border-radius: 20px;
        transition: all 0.2s ease;
    }
    
    .legend-item:hover {
        background-color: #f5f5f5;
        transform: translateY(-2px);
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .legend-text {
            font-size: 0.9rem;
        font-weight: 500;
        }
        
        .legend-color.beverages {
            background-color: #e71d1d;
        }
        
        .legend-color.rice-meals {
            background-color: #f9a825;
        }
        
        .legend-color.snack {
            background-color: #4caf50;
        }
        
        .legend-color.street-food {
            background-color: #2196f3;
        }
        
        .map-filters {
            display: flex;
            justify-content: center;
            gap: 15px;
        margin: 0 auto 30px;
            flex-wrap: wrap;
        max-width: 800px;
        padding: 5px;
        background-color: rgba(255, 255, 255, 0.7);
        border-radius: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .map-filter-btn {
        padding: 10px 20px;
            border: 1px solid #ddd;
            border-radius: 30px;
            background-color: white;
            cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        }
        
        .map-filter-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    .map-filter-btn:hover:not(.active) {
        background-color: #f5f5f5;
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .nearby-stalls {
            padding: 50px 0;
            background-color: var(--light-bg);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .stall-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }
    
    /* View Details button styling for stall cards */
    .stall-card .btn-outline {
        display: inline-block;
        background-color: transparent;
        color: var(--primary-color);
        border: 2px solid var(--primary-color);
        text-decoration: none;
        border-radius: 50px;
        font-weight: 600;
        padding: 8px 20px;
        text-align: center;
        transition: all 0.3s ease;
        margin-top: auto;
    }
    
    .stall-card .btn-outline:hover {
        background-color: var(--primary-color);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    /* Animation for pins */
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
    
    .map-pin {
        animation: pinDrop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        animation-delay: calc(var(--pin-index) * 0.1s);
        opacity: 0;
    }
    
    /* Enhanced styling for Nearby Food Stalls section */
    .stall-card {
        background-color: white;
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--box-shadow);
        transition: all 0.3s ease;
        position: relative;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .stall-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .stall-card img {
        width: 100%;
        height: 180px;
        object-fit: cover;
    }
    
    .stall-info {
        padding: 20px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }
    
    .stall-info h3 {
        margin-top: 0;
        margin-bottom: 12px;
        font-size: 1.2rem;
        color: #333;
    }
    
    .stall-hours {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        color: #777;
        margin-bottom: 12px;
    }
    
    .stall-hours i {
        color: var(--primary-color);
    }
    
    .stall-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 15px;
    }
    
    .stall-category {
        font-size: 0.9rem;
        color: #777;
    }
    
    .view-details {
        display: inline-block;
        background-color: var(--primary-color);
        color: white;
        text-decoration: none;
        border-radius: 50px;
        font-weight: 600;
        padding: 8px 20px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
        margin-top: auto;
        align-self: flex-end;
    }
    
    .view-details:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        background-color: #a31818;
    }
    
    /* Animation for tooltip pulse */
    @keyframes pulse {
        0% {
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        }
        50% {
            box-shadow: 0 6px 25px rgba(239, 28, 28, 0.35); 
        }
        100% {
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        }
    }
    </style>

    <!-- Map Header -->
    <section class="map-header">
        <div class="container">
            <h1>Food Stall Map</h1>
        <p>Find the nearest food stalls at PUP Lagoon with easy navigation and filters</p>
        </div>
    </section>

    <!-- Map Section -->
    <section class="map-container">
        <div class="container">
            <div class="map-filters">
            <button class="map-filter-btn active" data-category="all">All Stalls</button>
                <button class="map-filter-btn" data-category="beverages">Beverages</button>
                <button class="map-filter-btn" data-category="rice-meals">Rice Meals</button>
                <button class="map-filter-btn" data-category="snack">Snack</button>
                <button class="map-filter-btn" data-category="street-food">Street Food</button>
            <button class="map-filter-btn" data-category="fast-food">Fast Food</button>
            </div>
            
            <div class="map-wrapper">
            <img src="public/images/pup-map.jpg" alt="PUP Campus Map" class="map-image" onerror="this.onerror=null; this.src='public/images/map.jpg';">
                
                <!-- Map Pins -->
            <?php 
            // Array of example positions to randomly place stalls on the map
            $positions = [
                ['top' => '30%', 'left' => '40%'],
                ['top' => '45%', 'left' => '55%'],
                ['top' => '60%', 'left' => '35%'],
                ['top' => '25%', 'left' => '65%'],
                ['top' => '55%', 'left' => '70%'],
                ['top' => '70%', 'left' => '50%'],
                ['top' => '40%', 'left' => '25%'],
                ['top' => '20%', 'left' => '50%']
            ];
            
            // Display actual stalls on map
            $displayedStalls = 0;
            echo "<!-- Beginning pin display loop -->";
            foreach ($stalls as $index => $stall):
                // Only proceed if we have valid coordinates
                if (empty($stall['pin_x']) || empty($stall['pin_y']) || 
                    $stall['pin_x'] === null || $stall['pin_y'] === null) {
                    echo "<!-- Skipping stall {$stall['id']} due to missing coordinates -->";
                    continue;
                }
                
                $displayedStalls++;
                
                // Determine the category for filtering
                $category = strtolower(str_replace(' ', '-', explode(',', $stall['food_type'])[0]));
                
                // Use stored pin coordinates
                $pinX = floatval($stall['pin_x']);
                $pinY = floatval($stall['pin_y']);
                
                // Ensure values are in the valid range
                $pinX = max(0, min(100, $pinX));
                $pinY = max(0, min(100, $pinY));
                
                echo "<!-- 💥 Displaying pin for stall {$stall['id']} at position: {$pinX}%, {$pinY}% -->";
                
                // For food category-based pin colors
                $pinColor = 'var(--primary-color)';
                if (stripos($stall['food_type'], 'beverages') !== false) {
                    $pinColor = '#e71d1d';
                } elseif (stripos($stall['food_type'], 'rice') !== false) {
                    $pinColor = '#f9a825';
                } elseif (stripos($stall['food_type'], 'snack') !== false) {
                    $pinColor = '#4caf50';
                } elseif (stripos($stall['food_type'], 'street') !== false) {
                    $pinColor = '#2196f3';
                } elseif (stripos($stall['food_type'], 'fast') !== false) {
                    $pinColor = '#ff5722';
                }
            ?>
            <div class="map-pin" style="top: <?php echo $pinY; ?>%; left: <?php echo $pinX; ?>%; z-index: 100; background-color: <?php echo $pinColor; ?>; --pin-index: <?php echo $index; ?>;" data-category="<?php echo htmlspecialchars($category); ?>" data-stall-id="<?php echo $stall['id']; ?>">
                    <div class="map-pin-tooltip">
                        <div class="tooltip-content">
                        <div class="tooltip-stall-name"><?php echo htmlspecialchars($stall['name']); ?></div>
                        <div class="tooltip-stall-type">
                            <?php echo htmlspecialchars($stall['food_type']); ?>
                        </div>
                        <?php if (!empty($stall['location'])): ?>
                        <div class="tooltip-location">
                            <i class="fas fa-map-marker-alt" style="margin-right: 8px; color: var(--primary-color);"></i>
                            <span><?php echo htmlspecialchars($stall['location']); ?></span>
                    </div>
                        <?php endif; ?>
                        <div class="tooltip-actions">
                            <a href="stall-detail?id=<?php echo $stall['id']; ?>" class="tooltip-action">View Details</a>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $stall['owner_id']): ?>
                            <a href="manage-stall?action=edit_location" class="tooltip-action tooltip-edit-action">Edit Location</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php 
            echo "<!-- Displayed {$displayedStalls} stalls on the map -->";
                
            // If no stalls were displayed, add a sample pin for testing
            if ($displayedStalls == 0):
            ?>
                <div class="map-pin" style="top: 40%; left: 45%; z-index: 100; background-color: #FF0000; --pin-index: 0;" data-category="test">
                    <div class="map-pin-tooltip">
                        <div class="tooltip-content">
                            <div class="tooltip-stall-name">Sample</div>
                            <div class="tooltip-stall-type">
                                Snack
                            </div>
                            <div class="tooltip-location">
                                <i class="fas fa-map-marker-alt" style="margin-right: 8px; color: var(--primary-color);"></i>
                                <span>Stall #2 (28%, 57%)</span>
                            </div>
                            <div class="tooltip-actions">
                            <a href="#" class="tooltip-action">View Details</a>
                                <a href="#" class="tooltip-action tooltip-edit-action">Edit Location</a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Added test pin since no stalls with coordinates were found -->
            <?php endif; ?>
            </div>
            
            <div class="map-legend">
                <div class="legend-item">
                    <div class="legend-color beverages"></div>
                    <div class="legend-text">Beverages</div>
                </div>
                <div class="legend-item">
                    <div class="legend-color rice-meals"></div>
                    <div class="legend-text">Rice Meals</div>
                </div>
                <div class="legend-item">
                    <div class="legend-color snack"></div>
                    <div class="legend-text">Snacks</div>
                </div>
                <div class="legend-item">
                    <div class="legend-color street-food"></div>
                    <div class="legend-text">Street Food</div>
                </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #ff5722;"></div>
                <div class="legend-text">Fast Food</div>
            </div>
            </div>
        </div>
    </section>

    <!-- Nearby Stalls Section -->
    <section class="nearby-stalls">
        <div class="container">
            <div class="section-header">
                    <h2>Nearby Food Stalls</h2>
            <a href="stalls" class="btn btn-outline">View All</a>
            </div>
            
            <div class="stall-cards">
            <?php if (empty($stalls)): ?>
            <!-- Default stall cards if no stalls in database -->
            <!-- Stall Card -->
            <div class="stall-card">
                    <img src="public/images/stalls/stall1.jpg" alt="Kape Kuripot">
                <div class="stall-info">
                        <h3>Kape Kuripot</h3>
                    <div class="stall-hours">
                            <i class="far fa-clock"></i>
                        Monday to Saturday 09:00 to 18:00
                        </div>
                    <div class="stall-meta">
                        <div class="stall-category">• Coffee, Beverages</div>
                    </div>
                    <a href="#" class="view-details">View Details</a>
                    </div>
                </div>
                
            <!-- Stall Card -->
            <div class="stall-card">
                <img src="public/images/stalls/stall2.jpg" alt="PUP Meal Deal">
                <div class="stall-info">
                        <h3>PUP Meal Deal</h3>
                    <div class="stall-hours">
                            <i class="far fa-clock"></i>
                        Monday to Friday 10:00 to 19:00
                    </div>
                    <div class="stall-meta">
                        <div class="stall-category">• Rice Meals</div>
                    </div>
                    <a href="#" class="view-details">View Details</a>
                </div>
            </div>
            
            <!-- Stall Card -->
            <div class="stall-card">
                <img src="public/images/stalls/stall3.jpg" alt="Snack Shack">
                <div class="stall-info">
                    <h3>Snack Shack</h3>
                    <div class="stall-hours">
                        <i class="far fa-clock"></i>
                        Monday to Saturday 08:00 to 17:00
        </div>
                    <div class="stall-meta">
                        <div class="stall-category">• Snacks</div>
                    </div>
                    <a href="#" class="view-details">View Details</a>
                </div>
                    </div>
            <?php else: ?>
            <!-- Display actual stalls from database -->
            <?php 
            // Get up to 3 stalls to display
            $displayStalls = array_slice($stalls, 0, 3);
            
            foreach ($displayStalls as $stall):
                // Define a default image if no logo is available
                $logoImage = !empty($stall['logo_path']) && file_exists($stall['logo_path']) 
                          ? $stall['logo_path'] 
                          : 'public/images/stalls/default-stall.jpg';
            ?>
            <div class="stall-card">
                <img src="<?php echo htmlspecialchars($logoImage); ?>" alt="<?php echo htmlspecialchars($stall['name']); ?>">
                <div class="stall-info">
                    <h3><?php echo htmlspecialchars($stall['name']); ?></h3>
                    <div class="stall-hours">
                        <i class="far fa-clock"></i>
                        Monday to Saturday 09:00 to 18:00
                    </div>
                    <div class="stall-meta">
                        <div class="stall-category">• <?php echo htmlspecialchars($stall['food_type']); ?></div>
                    </div>
                    <a href="stall-detail?id=<?php echo $stall['id']; ?>" class="view-details">View Details</a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Map filter functionality
            const filterButtons = document.querySelectorAll('.map-filter-btn');
            const mapPins = document.querySelectorAll('.map-pin');
    
    // Determine if pins are near the top or edges of the map and adjust tooltip position
    function adjustPinTooltipPositions() {
        const topThreshold = 30; // Consider pins in top 30% of the map as "top pins"
        const mapWrapper = document.querySelector('.map-wrapper');
        const mapHeight = mapWrapper.offsetHeight;
        const mapWidth = mapWrapper.offsetWidth;
        
        mapPins.forEach(pin => {
            // Get pin position and calculate its percentage from the top
            const pinStyle = window.getComputedStyle(pin);
            const pinTop = parseFloat(pinStyle.top);
            const pinLeft = parseFloat(pinStyle.left);
            const topPercentage = (pinTop / mapHeight) * 100;
            const leftPercentage = (pinLeft / mapWidth) * 100;
            
            // Reset positioning classes
            pin.classList.remove('pin-top', 'pin-left', 'pin-right');
            
            // Add positioning classes based on position
            if (topPercentage <= topThreshold) {
                pin.classList.add('pin-top');
            }
        });
    }
    
    // Adjust tooltip positions on load
    setTimeout(adjustPinTooltipPositions, 300);
    
    // Adjust tooltip positions when window is resized
    window.addEventListener('resize', adjustPinTooltipPositions);
            
            filterButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Update active button
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
            
            const category = button.getAttribute('data-category');
                    
                    // Filter pins
                    mapPins.forEach(pin => {
                        const pinCategory = pin.getAttribute('data-category');
                        
                        if (category === 'all' || category === pinCategory) {
                            pin.style.display = 'block';
                        } else {
                            pin.style.display = 'none';
                        }
                    });
            
            // Reapply tooltip positioning after filtering
            setTimeout(adjustPinTooltipPositions, 100);
        });
    });
    
    // Add hover effects to legend items
    const legendItems = document.querySelectorAll('.legend-item');
    legendItems.forEach(item => {
        item.addEventListener('mouseenter', () => {
            const category = item.querySelector('.legend-text').textContent.toLowerCase().replace(' ', '-');
            
            // Highlight related pins
            mapPins.forEach(pin => {
                if (pin.getAttribute('data-category') === category) {
                    pin.style.transform = 'translate(-50%, -50%) rotate(45deg) scale(1.2)';
                    pin.style.zIndex = '102';
                }
            });
        });
        
        item.addEventListener('mouseleave', () => {
            // Reset pins
            mapPins.forEach(pin => {
                pin.style.transform = 'translate(-50%, -50%) rotate(45deg)';
                pin.style.zIndex = '100';
            });
                });
            });
        });
    </script>

<?php include 'footer.php'; ?> 