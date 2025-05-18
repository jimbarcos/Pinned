<?php
// Set timezone to Philippine Standard Time
date_default_timezone_set('Asia/Manila');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set page title
$page_title = "Food Stalls";

// Include configuration file to connect to the database
require_once 'config.php';

// Get sort parameter from URL (default to highest rating)
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'highest_rating';

// Fetch all food stalls from the database with average ratings
$stalls = [];
try {
    if (isset($pdo)) {
        // Define ORDER BY clause based on sort parameter
        $orderBy = "COALESCE(AVG(r.rating), 0) DESC"; // Default: highest rating
        
        switch ($sort) {
            case 'lowest_rating':
                $orderBy = "COALESCE(AVG(r.rating), 0) ASC";
                break;
            case 'newest':
                $orderBy = "s.created_at DESC";
                break;
            case 'oldest':
                $orderBy = "s.created_at ASC";
                break;
            case 'highest_rating':
            default:
                $orderBy = "COALESCE(AVG(r.rating), 0) DESC";
                break;
        }
        
        // Query to get stalls with average ratings
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   COALESCE(AVG(r.rating), 0) as average_rating,
                   COUNT(r.id) as review_count
            FROM food_stalls s
            LEFT JOIN reviews r ON s.id = r.stall_id
            GROUP BY s.id
            ORDER BY $orderBy
        ");
        $stmt->execute();
        $stalls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Silently fail and show empty stalls
}

// Include header
include 'header.php';
?>
    <style>
        .search-container {
            background-color: var(--light-bg);
            padding: 30px 0;
        }
    
    .location-banner {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-bottom: 25px;
        padding: 12px 20px;
        background-color: white;
        border-radius: 50px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        transition: all 0.3s ease;
    }
    
    .location-banner:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    .location-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background-color: var(--primary-color);
        color: white;
        border-radius: 50%;
        font-size: 1.2rem;
    }
    
    .location-text {
        font-weight: 500;
        color: #333;
        font-size: 1rem;
    }
    
    .location-text span {
        display: inline-block;
        color: var(--primary-color);
        font-weight: 700;
    }
        
        .search-bar {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 20px;
            padding-left: 50px;
            border: 1px solid #ddd;
            border-radius: 30px;
            font-size: 1rem;
        }
        
        .search-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
        }
        
        .filters {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 20px;
            border: 1px solid #ddd;
            border-radius: 30px;
            background-color: white;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .filter-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .stalls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            padding: 50px 0;
        }
        
        .stall-card {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        position: relative;
        display: flex;
        flex-direction: column;
        min-height: 450px;
        }
        
        .stall-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stall-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .stall-info {
            padding: 20px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        }
        
        .stall-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .stall-price {
            background-color: var(--secondary-color);
            padding: 2px 10px;
            border-radius: 15px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .stall-category {
            font-size: 0.9rem;
            color: #777;
        }
        
        .stall-hours {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 15px;
        }
        
        .stall-hours i {
            color: var(--primary-color);
        }
    
    .star-rating {
        color: #FFD700;
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 5px;
        margin-top: 10px;
    }
    
    .rating-count {
        color: #777;
        font-size: 0.9rem;
    }
    
    .no-stalls-message {
        text-align: center;
        padding: 40px 0;
        color: #777;
    }
    
    .stall-card .btn {
        position: relative;
        z-index: 10;
    }
    
    .view-details-btn {
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
        float: right;
    }
    
    .view-details-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        background-color: #a31818; /* Darker shade on hover */
    }
    
    .stall-action {
        margin-top: auto;
        padding-top: 15px;
        width: 100%;
        display: block;
        clear: both;
        text-align: right;
    }
    
    .sort-container {
        display: flex;
        justify-content: flex-start;
        margin-top: 20px;
        align-items: center;
    }
    
    .sort-label {
        font-size: 0.9rem;
        margin-right: 10px;
        color: #555;
        font-weight: 500;
    }
    
    .sort-select {
        padding: 8px 15px;
        border-radius: 20px;
        border: 1px solid #ddd;
        font-size: 0.9rem;
        background-color: white;
        cursor: pointer;
        outline: none;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }
    
    .sort-select:hover {
        border-color: var(--primary-color);
    }
    
    .sort-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    </style>

    <!-- Search Section -->
    <section class="search-container">
        <div class="container">
        <div class="location-banner">
            <div class="location-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="location-text">
                <span>PUP Lagoon</span>, Santa Mesa, Manila, 1008 Metro Manila
            </div>
            </div>
            <div class="search-bar">
                <i class="fas fa-search search-icon"></i>
            <input type="text" id="stallSearch" class="search-input" placeholder="What are you looking for?">
            </div>
            <div class="sort-container">
                <span class="sort-label">Sort by:</span>
                <select id="sortSelect" class="sort-select">
                    <option value="highest_rating" <?php echo $sort === 'highest_rating' ? 'selected' : ''; ?>>Highest Rating</option>
                    <option value="lowest_rating" <?php echo $sort === 'lowest_rating' ? 'selected' : ''; ?>>Lowest Rating</option>
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                </select>
            </div>
            <div class="filters">
                <button class="filter-btn active" data-category="all">All</button>
                <button class="filter-btn" data-category="beverages">Beverages</button>
                <button class="filter-btn" data-category="rice-meals">Rice Meals</button>
                <button class="filter-btn" data-category="snack">Snack</button>
                <button class="filter-btn" data-category="street-food">Street Food</button>
            <button class="filter-btn" data-category="fast-food">Fast Food</button>
            </div>
        </div>
    </section>

    <!-- Stalls Section -->
    <section>
        <div class="container">
        <div class="stalls-grid" id="stallsGrid">
            <?php if (empty($stalls)): ?>
                <div class="no-stalls-message" style="grid-column: 1 / -1;">
                    <h3>No food stalls found</h3>
                    <p>There are no registered food stalls yet. If you are a stall owner, <a href="register-stall">register your stall</a>!</p>
                </div>
            <?php else: ?>
                <?php foreach ($stalls as $stall): 
                    // Determine the category for filtering
                    $category = strtolower(str_replace(' ', '-', $stall['food_type']));
                    // Define a default image if no logo is available
                    $logoImage = !empty($stall['logo_path']) && file_exists($stall['logo_path']) 
                              ? $stall['logo_path'] 
                              : 'public/images/stalls/default-stall.jpg';
                    // Format rating for display
                    $avgRating = round($stall['average_rating'], 1);
                    $reviewCount = (int)$stall['review_count'];
                ?>
                <div class="stall-card" data-category="<?php echo htmlspecialchars($category ?? ''); ?>">
                    <img src="<?php echo htmlspecialchars($logoImage ?? ''); ?>" alt="<?php echo htmlspecialchars($stall['name'] ?? ''); ?>">
                    <div class="stall-info">
                        <h3><?php echo htmlspecialchars($stall['name'] ?? ''); ?></h3>
                        <div class="stall-meta">
                            <?php if(!empty($stall['hours'])): ?>
                        <div class="stall-hours">
                            <i class="far fa-clock"></i>
                                    <?php echo htmlspecialchars($stall['hours'] ?? ''); ?>
                        </div>
                            <?php endif; ?>
                            <div class="stall-category">• <?php echo htmlspecialchars($stall['food_type'] ?? ''); ?></div>
                </div>
                
                        <?php if ($reviewCount > 0): ?>
                        <div class="star-rating">
                            <?php 
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $avgRating) {
                                    echo '<i class="fas fa-star"></i>';
                                } elseif ($i - 0.5 <= $avgRating) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                            <span class="rating-count">(<?php echo $reviewCount; ?> review<?php echo $reviewCount !== 1 ? 's' : ''; ?>)</span>
                        </div>
                        <?php else: ?>
                        <div class="star-rating">
                            <i class="far fa-star"></i>
                            <i class="far fa-star"></i>
                            <i class="far fa-star"></i>
                            <i class="far fa-star"></i>
                            <i class="far fa-star"></i>
                            <span class="rating-count">(No reviews yet)</span>
                        </div>
                        <?php endif; ?>
                
                        <?php if(!empty($stall['description'])): ?>
                            <p><?php echo substr(htmlspecialchars($stall['description'] ?? ''), 0, 100) . '...'; ?></p>
                        <?php endif; ?>
                        <div class="stall-action">
                            <a href="stall-detail?id=<?php echo $stall['id']; ?>" class="btn view-details-btn">View Details</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>
    </section>

<?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
    // Filter functionality
    const filterButtons = document.querySelectorAll('.filter-btn');
    const stallCards = document.querySelectorAll('.stall-card');
    const searchInput = document.getElementById('stallSearch');
            const sortSelect = document.getElementById('sortSelect');
    
    // Ensure the "View Details" links are always clickable
    document.querySelectorAll('.stall-card a.btn').forEach(link => {
        link.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent event from bubbling up
        });
    });
    
    // Filter by category
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Update active button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            
            const category = button.getAttribute('data-category');
            filterStalls();
        });
    });
    
    // Search functionality
    searchInput.addEventListener('input', filterStalls);
            
            // Sort functionality
            sortSelect.addEventListener('change', function() {
                window.location.href = 'stalls.php?sort=' + this.value;
            });
    
    function filterStalls() {
        const searchTerm = searchInput.value.toLowerCase();
        const activeCategory = document.querySelector('.filter-btn.active').getAttribute('data-category');
        
        stallCards.forEach(card => {
            const stallName = card.querySelector('h3').textContent.toLowerCase();
            const stallCategory = card.getAttribute('data-category');
            const stallDescription = card.querySelector('p') ? card.querySelector('p').textContent.toLowerCase() : '';
            
            const matchesSearch = stallName.includes(searchTerm) || stallDescription.includes(searchTerm);
            const matchesCategory = activeCategory === 'all' || stallCategory.includes(activeCategory);
            
            if (matchesSearch && matchesCategory) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
        });
    </script>