<?php
$page_title = "Home";
include 'header.php';

// Connect to database and fetch stalls
$all_stalls = [];
$featured_stalls = [];
$top_reviews = []; // Array to store top reviews

try {
    if (isset($pdo)) {
        // Get all stalls for carousel
        $stmt = $pdo->query("SELECT id, name, logo_path FROM food_stalls ORDER BY created_at DESC");
        $all_stalls = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get featured stalls (limit to 3)
        $featuredStmt = $pdo->query("SELECT id, name, hours, description, logo_path FROM food_stalls ORDER BY created_at DESC LIMIT 3");
        $featured_stalls = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get top reviews with stall information - Using a simpler query
        $reviewsStmt = $pdo->query("
            SELECT r.id, r.title, r.comment, r.rating, r.is_anonymous, r.created_at,
                   s.id AS stall_id, s.name AS stall_name, 
                   u.name AS user_name
            FROM reviews r
            JOIN food_stalls s ON r.stall_id = s.id
            JOIN users u ON r.user_id = u.id
            ORDER BY r.created_at DESC
            LIMIT 8
        ");
        
        if ($reviewsStmt) {
            $top_reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug - Log the number of reviews found
            error_log('Number of reviews fetched: ' . count($top_reviews));
            
            // If there are no reviews, try a more basic query
            if (empty($top_reviews)) {
                error_log('No reviews found with first query, trying simpler query');
                $simpleReviewsStmt = $pdo->query("SELECT * FROM reviews LIMIT 8");
                $simpleReviews = $simpleReviewsStmt->fetchAll(PDO::FETCH_ASSOC);
                error_log('Simple query found ' . count($simpleReviews) . ' reviews');
                
                // If we have reviews from the simple query, get the stall data separately
                if (!empty($simpleReviews)) {
                    foreach ($simpleReviews as $review) {
                        try {
                            // Get stall info
                            $stallStmt = $pdo->prepare("SELECT id, name FROM food_stalls WHERE id = :stall_id");
                            $stallStmt->execute(['stall_id' => $review['stall_id']]);
                            $stall = $stallStmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Get user info
                            $userStmt = $pdo->prepare("SELECT name FROM users WHERE id = :user_id");
                            $userStmt->execute(['user_id' => $review['user_id']]);
                            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Add stall and user info to review
                            $review['stall_name'] = $stall['name'] ?? 'Unknown Stall';
                            $review['stall_id'] = $stall['id'] ?? 0;
                            $review['user_name'] = $user['name'] ?? 'Unknown User';
                            
                            // Add to top reviews
                            $top_reviews[] = $review;
                        } catch (PDOException $innerEx) {
                            error_log('Error getting stall/user data: ' . $innerEx->getMessage());
                        }
                    }
                }
            }
        } else {
            error_log('Review query statement failed');
        }
    }
} catch (PDOException $e) {
    // Silently fail and use default values
    error_log('Database error: ' . $e->getMessage());
}

// Fallback testimonials if no reviews are found
$fallback_testimonials = [
    [
        'quote' => "Sulit na mura sa Kape Kuripot!",
        'text' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
        'user' => "User 1",
        'stall' => "Kape Kuripot"
    ],
    [
        'quote' => "Grabe ang lakas ng milktea place!",
        'text' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
        'user' => "User 2",
        'stall' => "Milktea House"
    ],
    [
        'quote' => "I think homemade yung patty sa Yema!",
        'text' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
        'user' => "User 3",
        'stall' => "Yema Burger"
    ],
    [
        'quote' => "Worth it nag sa ang porksilog dito!",
        'text' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
        'user' => "User 4",
        'stall' => "Silog Express"
    ]
];

// Debug - Output warning if using fallback
if (empty($top_reviews)) {
    error_log('WARNING: Using fallback testimonials because no reviews were found in the database!');
}
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <div class="hero-text">
                <h1><img src="public/images/logos/Logo-06.png" alt="Pinned" style="max-width: 600px;"></h1>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
                <a href="stalls" class="btn btn-secondary">Discover Stores</a>
            </div>
            <div class="hero-image">
                <img src="public/images/food-containers.png" alt="Food Containers">
            </div>
        </div>
    </div>
</section>

<!-- Stores Section -->
<section class="stores">
    <div class="container">
        <h2>Over <?php echo count($all_stalls); ?>+ Stores</h2>
        <div class="store-carousel" role="region" aria-label="Featured Stores Carousel">
            <button class="carousel-control prev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
            <div class="carousel-items">
                <?php if (!empty($all_stalls)): ?>
                    <?php foreach ($all_stalls as $stall): ?>
                        <a href="stall-detail?id=<?php echo $stall['id']; ?>" class="store-logo" role="img" aria-label="<?php echo htmlspecialchars($stall['name'] ?? ''); ?>">
                            <?php if (!empty($stall['logo_path']) && file_exists($stall['logo_path'])): ?>
                                <img src="<?php echo $stall['logo_path']; ?>" alt="<?php echo htmlspecialchars($stall['name'] ?? ''); ?>" loading="lazy">
                            <?php else: ?>
                                <img src="public/images/stores/default-logo.png" alt="<?php echo htmlspecialchars($stall['name'] ?? ''); ?>" loading="lazy">
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback if no stalls found -->
                    <div class="store-logo"><img src="public/images/stores/store1.png" alt="Store Logo" loading="lazy"></div>
                    <div class="store-logo"><img src="public/images/stores/store2.png" alt="Store Logo" loading="lazy"></div>
                    <div class="store-logo"><img src="public/images/stores/store3.png" alt="Store Logo" loading="lazy"></div>
                    <div class="store-logo"><img src="public/images/stores/store4.png" alt="Store Logo" loading="lazy"></div>
                    <div class="store-logo"><img src="public/images/stores/store5.png" alt="Store Logo" loading="lazy"></div>
                <?php endif; ?>
            </div>
            <button class="carousel-control next" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
        </div>
        <div class="carousel-dots" role="tablist">
            <span class="dot active" role="tab" aria-selected="true" aria-label="Slide 1"></span>
            <span class="dot" role="tab" aria-selected="false" aria-label="Slide 2"></span>
            <span class="dot" role="tab" aria-selected="false" aria-label="Slide 3"></span>
        </div>
    </div>
</section>

<!-- Ensure carousel is properly initialized -->
<script>
// Immediate self-executing function to initialize the carousel
(function() {
    // Function to manually initialize store carousel
    function initStoreCarouselNow() {
        const storeCarousel = document.querySelector('.store-carousel');
        if (!storeCarousel) return;
        
        const storeLogos = storeCarousel.querySelectorAll('.store-logo');
        
        // Enhance logos with animated entrance
        storeLogos.forEach((logo, index) => {
            // Reset any existing styles that might be cached
            logo.style.opacity = '0';
            logo.style.transform = 'translateY(20px)';
            logo.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            
            // Staggered animation
            setTimeout(() => {
                logo.style.opacity = '1';
                logo.style.transform = 'translateY(0)';
            }, 100 * (index % 6));
            
            // Add hover effects for desktop
            if (!('ontouchstart' in window)) {
                logo.addEventListener('mouseenter', () => {
                    logo.style.transform = 'translateY(-10px) scale(1.05)';
                    logo.style.boxShadow = '0 15px 25px rgba(0, 0, 0, 0.2)';
                });
                
                logo.addEventListener('mouseleave', () => {
                    logo.style.transform = 'translateY(0) scale(1)';
                    logo.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.15)';
                });
            }
        });
        
        // Setup carousel controls
        const prevBtn = storeCarousel.querySelector('.prev');
        const nextBtn = storeCarousel.querySelector('.next');
        const carouselItems = storeCarousel.querySelector('.carousel-items');
        const dots = document.querySelectorAll('.carousel-dots .dot');
        
        if (prevBtn && nextBtn && dots.length) {
            let currentIndex = 0;
            
            // Navigation functions
            function goToSlide(index) {
                carouselItems.style.transform = `translateX(-${index * 100}%)`;
                
                // Update active dot
                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                });
                
                currentIndex = index;
            }
            
            // Event listeners
            prevBtn.addEventListener('click', () => {
                const newIndex = (currentIndex - 1 + dots.length) % dots.length;
                goToSlide(newIndex);
            });
            
            nextBtn.addEventListener('click', () => {
                const newIndex = (currentIndex + 1) % dots.length;
                goToSlide(newIndex);
            });
            
            // Dot navigation
            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    goToSlide(index);
                });
            });
        }
    }
    
    // Initialize now
    initStoreCarouselNow();
    
    // Fallback - also initialize on window load
    window.addEventListener('load', initStoreCarouselNow);
})();
</script>

<!-- Add inline styles to ensure store section styling is applied -->
<style>
/* Force override for store section styles */
.stores {
    padding: 60px 0;
    background-color: var(--dark-bg);
    color: var(--text-light);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.store-logo {
    background-color: white;
    border-radius: 50%;
    width: 120px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 15px;
    transition: all 0.3s ease-out;
    text-decoration: none;
    margin: 0 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
    position: relative;
    overflow: hidden;
    transform: scale(1);
}

.store-logo::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle, rgba(255,255,255,0) 60%, rgba(255,255,255,0.7) 100%);
    opacity: 0.7;
    z-index: 1;
    pointer-events: none;
}

.location {
    display: flex;
    align-items: center;
    background-color: #ffffff;
    padding: 15px 20px;
    border-radius: 50px;
    margin-top: 15px;
    margin-bottom: 20px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    max-width: 100%;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.location i {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    background-color: var(--primary-color);
    color: white;
    border-radius: 50%;
    margin-right: 15px;
    font-size: 1rem;
}

.location p {
    margin: 0;
    font-size: 0.95rem;
    color: #444;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* View Details Button Styling */
.view-details-btn {
    display: inline-block;
    background-color: #b91c1c;
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
}

.store-action {
    margin-top: 15px;
    width: 100%;
    display: block;
    clear: both;
    text-align: right;
}

.store-info {
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
}
</style>

<!-- Featured Stores Section -->
<section class="featured-stores">
    <div class="container">
        <div class="section-header">
            <div>
                <h2>Browse through</h2>
                <h2 class="red-text">Featured Stores</h2>
                <a href="map" class="location" title="View on map">
                    <i class="fas fa-map-marker-alt"></i>
                    <p>PUP Lagoon, Santa Mesa, Manila, 1008 Metro Manila</p>
                </a>
            </div>
            <a href="stalls" class="btn btn-outline">Browse</a>
        </div>
        <div class="store-cards">
            <?php if (!empty($featured_stalls)): ?>
                <?php foreach ($featured_stalls as $stall): ?>
                    <div class="store-card">
                        <?php if (!empty($stall['logo_path']) && file_exists($stall['logo_path'])): ?>
                            <img src="<?php echo $stall['logo_path']; ?>" alt="<?php echo htmlspecialchars($stall['name'] ?? ''); ?>">
                        <?php else: ?>
                            <img src="public/images/stalls/stall1.jpg" alt="<?php echo htmlspecialchars($stall['name'] ?? ''); ?>">
                        <?php endif; ?>
                        <div class="store-info">
                            <h3><?php echo htmlspecialchars($stall['name'] ?? ''); ?></h3>
                            <?php if (!empty($stall['hours'])): ?>
                                <div class="store-hours">
                                    <i class="far fa-clock"></i>
                                    <p><?php echo htmlspecialchars($stall['hours'] ?? ''); ?></p>
                                </div>
                            <?php endif; ?>
                            <p><?php 
                            $description = !empty($stall['description']) ? htmlspecialchars($stall['description'] ?? '') : 'No description available.';
                            echo (strlen($description) > 100) ? substr($description, 0, 100) . '...' : $description; 
                            ?></p>
                            <div class="store-action">
                                <a href="stall-detail?id=<?php echo $stall['id']; ?>" class="btn view-details-btn">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback stalls if none found in database -->
                <?php
                $fallback_stalls = [
                    [
                        'name' => 'Kape Kuripot',
                        'hours' => 'Monday to Saturday 09:00 to 18:00',
                        'description' => 'Lorem ipsum dolor sit amet, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.'
                    ],
                    [
                        'name' => 'PUP Meal Deal',
                        'hours' => 'Monday to Friday 07:00 to 19:00',
                        'description' => 'Lorem ipsum dolor sit amet, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.'
                    ],
                    [
                        'name' => 'Meryenda Express',
                        'hours' => 'Monday to Saturday 10:00 to 20:00',
                        'description' => 'Lorem ipsum dolor sit amet, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.'
                    ]
                ];
                
                foreach ($fallback_stalls as $stall): 
                ?>
                <div class="store-card">
                    <img src="public/images/stalls/stall1.jpg" alt="<?php echo $stall['name']; ?>">
                    <div class="store-info">
                        <h3><?php echo $stall['name']; ?></h3>
                        <div class="store-hours">
                            <i class="far fa-clock"></i>
                            <p><?php echo $stall['hours']; ?></p>
                        </div>
                        <p><?php 
                        $description = $stall['description'];
                        echo (strlen($description) > 100) ? substr($description, 0, 100) . '...' : $description; 
                        ?></p>
                        <div class="store-action">
                            <a href="#" class="btn view-details-btn">View Details</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="testimonials">
    <div class="container">
        <h2>Check out what people say...</h2>
        <div class="testimonials-carousel">
            <button class="carousel-control prev"><i class="fas fa-chevron-left"></i></button>
            <div class="testimonial-cards">
                <?php if (!empty($top_reviews)): ?>
                    <?php foreach ($top_reviews as $review): ?>
                        <div class="testimonial-card">
                            <div class="review-title">
                                "<?php 
                                $title = htmlspecialchars($review['title']);
                                echo (strlen($title) > 17) ? substr($title, 0, 17) . '...' : $title; 
                                ?>"
                            </div>
                            
                            <div class="stall-name">
                                <i class="fas fa-store"></i> <?php echo htmlspecialchars($review['stall_name']); ?>
                            </div>
                            
                            <p class="review-text">
                                <?php 
                                $comment = htmlspecialchars($review['comment']);
                                echo (strlen($comment) > 30) ? substr($comment, 0, 30) . '...' : $comment; 
                                ?>
                            </p>
                            
                            <div class="store-action">
                                <a href="stall-detail?id=<?php echo $review['stall_id']; ?>" class="btn view-details-btn">Read More</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($fallback_testimonials as $testimonial): ?>
                        <div class="testimonial-card">
                            <div class="review-title">
                                "<?php 
                                $quote = $testimonial['quote'];
                                echo (strlen($quote) > 17) ? substr($quote, 0, 17) . '...' : $quote; 
                                ?>"
                            </div>
                            
                            <div class="stall-name">
                                <i class="fas fa-store"></i> <?php echo $testimonial['stall']; ?>
                            </div>
                            
                            <p class="review-text">
                                <?php 
                                $text = $testimonial['text'];
                                echo (strlen($text) > 30) ? substr($text, 0, 30) . '...' : $text;
                                ?>
                            </p>
                            
                            <div class="store-action">
                                <a href="#" class="btn view-details-btn">Read More</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button class="carousel-control next"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
</section>

<!-- Join Section -->
<section class="join-section">
    <div class="container">
        <h2>Join us to get <span class="red-text">Pinned!</span></h2>
        <div class="join-options">
            <div class="join-card">
                <h3>Are you a...</h3>
                <h2>Food Enthusiast</h2>
                <ul class="benefits-list">
                    <li>Access to a variety food reviews</li>
                    <li>Unlimited food review posts</li>
                    <li>Access to the save feature</li>
                </ul>
                <a href="signup?type=food_enthusiast" class="btn btn-outline">Get Started</a>
            </div>
            <div class="join-card">
                <h3>Are you a...</h3>
                <h2>Food Stall Owner</h2>
                <ul class="benefits-list">
                    <li>Access to a variety customer reviews</li>
                    <li>Access to a dedicated stall page</li>
                    <li>Free food stall promotion</li>
                    <li>Access to the save feature</li>
                </ul>
                <a href="signup?type=stall_owner" class="btn btn-outline">Get Started</a>
            </div>
        </div>
    </div>
</section>

<style>
    /* Updated testimonial styles to match the screenshot */
    .testimonials {
        background-color: var(--primary-color);
        color: white;
        padding: 60px 0;
        margin-top: 40px;
    }
    
    .testimonials h2 {
        text-align: center;
        margin-bottom: 40px;
        font-size: 2.5rem;
    }
    
    .testimonials-carousel {
        position: relative;
        padding: 0 60px;
    }
    
    .testimonial-cards {
        display: flex;
        overflow-x: hidden;
        scroll-behavior: smooth;
        gap: 20px;
    }
    
    .testimonial-card {
        background-color: #1a1a1a;
        padding: 20px;
        border-radius: 10px;
        min-width: 280px;
        max-width: 300px;
        display: flex;
        flex-direction: column;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        height: 220px; /* Slightly reduced height */
    }
    
    .review-title {
        font-weight: 700;
        font-size: 1.2rem;
        color: white;
        margin-bottom: 10px;
        line-height: 1.4;
        height: 40px; /* Fixed height */
        overflow: hidden;
    }
    
    .stall-name {
        color: var(--primary-color);
        font-size: 0.9rem;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .review-text {
        color: #ccc;
        margin-bottom: 15px;
        line-height: 1.5;
        flex-grow: 1;
        font-size: 0.95rem;
        overflow: hidden;
        height: 60px; /* Fixed height for text */
    }
    
    .read-more-btn {
        border: 1px solid white;
        color: white;
        padding: 10px 20px;
        border-radius: 30px;
        text-decoration: none;
        display: inline-block;
        font-size: 0.9rem;
        align-self: flex-start;
        transition: all 0.3s ease;
    }
    
    .read-more-btn:hover {
        background-color: white;
        color: #1a1e23;
    }
    
    .carousel-control {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: rgba(0,0,0,0.2);
        border: none;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 5;
    }
    
    .carousel-control.prev {
        left: 10px;
    }
    
    .carousel-control.next {
        right: 10px;
    }
    
    @media (max-width: 768px) {
        .testimonial-cards {
            gap: 15px;
        }
        
        .testimonial-card {
            min-width: 250px;
        }
        
        .testimonials h2 {
            font-size: 2rem;
        }
    }
    
    .testimonial-card .view-details-btn {
        background-color: transparent;
        color: white;
        border: 1px solid white;
        border-radius: 50px;
        padding: 6px 15px;
        font-size: 0.9rem;
        font-weight: 500;
        float: right;
        box-shadow: none;
    }
    
    .testimonial-card .view-details-btn:hover {
        background-color: rgba(255, 255, 255, 0.1);
        transform: none;
        box-shadow: none;
    }
</style>

<?php include 'footer.php'; ?> 