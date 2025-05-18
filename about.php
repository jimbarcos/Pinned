<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set page title
$page_title = "About";

// Include header
include 'header.php';
?>
<link rel="stylesheet" href="public/css/about.css">

<!-- Hero Section -->
<section class="about-hero">
    <div class="container">
        <h1>About Pinned</h1>
        <p>Discover, explore, and enjoy the diverse food stalls in the PUP Lagoon. Pinned is your guide to finding delicious and affordable food options on campus.</p>
    </div>
</section>

<!-- About Section -->
<section class="about-section">
    <div class="container">
        <div class="about-grid">
            <div class="about-image">
                <img src="public/images/about-image.jpg" alt="About Pinned">
            </div>
            <div class="about-content">
                <h2>Our Story</h2>
                <p>Pinned started as a project by BSCS 3-5 Group 2 students who wanted to create a platform that would make it easier for PUP students to discover and support local food stalls within the campus. The idea originated from the frustration of not knowing all the available food options and missing out on hidden gems in the PUP Lagoon area.</p>
                <p>With the goal of connecting students with local food vendors, we developed Pinned as a comprehensive platform that provides all the information students need to make informed decisions about where to eat, while also helping food stall owners reach more customers.</p>
                <p>Today, Pinned has become the go-to resource for PUP students looking for affordable and delicious food options on campus. We're proud to support both students and local food entrepreneurs in building a stronger campus food community.</p>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <div class="section-title">
            <h2>Why Choose Pinned?</h2>
            <p>Discover the features that make Pinned the ultimate campus food guide</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h3 class="feature-title">Find Nearby Stalls</h3>
                <p class="feature-text">Easily locate food stalls near you with our interactive map. Never miss a hidden gem in the campus again.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3 class="feature-title">No-Filter Reviews</h3>
                <p class="feature-text">Read honest reviews from fellow students to make informed decisions about where to eat.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-store"></i>
                </div>
                <h3 class="feature-title">Support Local Vendors</h3>
                <p class="feature-text">Help local food entrepreneurs thrive by discovering and supporting their businesses.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <h3 class="feature-title">Diverse Food Options</h3>
                <p class="feature-text">Explore a wide range of cuisines and food types to satisfy any craving you might have.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 class="feature-title">Real-Time Updates</h3>
                <p class="feature-text">Get the latest information on opening hours, special offers, and new menu items.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <h3 class="feature-title">Save Your Favorites</h3>
                <p class="feature-text">Create a personalized list of your favorite stalls for quick access whenever you're hungry.</p>
            </div>
        </div>
    </div>
</section>

<!-- Team Section -->
<section class="team-section">
    <div class="container">
        <div class="section-title">
            <h2>Meet the Team</h2>
            <p>The talented individuals behind Pinned</p>
        </div>
        
        <div class="team-grid">
            <!-- First row - 3 members -->
            <div class="team-row">
                <div class="team-card">
                    <div class="team-image">
                        <img src="public/images/team/team1.jpg" alt="Team Member">
                    </div>
                    <div class="team-info">
                        <h3 class="team-name">Jim Aerol S. Barcos</h3>
                        <p class="team-role">Role</p>
                        <div class="team-social">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="team-card">
                    <div class="team-image">
                        <img src="public/images/team/team2.jpg" alt="Team Member">
                    </div>
                    <div class="team-info">
                        <h3 class="team-name">Francine Nastassja P. Jara</h3>
                        <p class="team-role">Role</p>
                        <div class="team-social">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="team-card">
                    <div class="team-image">
                        <img src="public/images/team/team3.jpg" alt="Team Member">
                    </div>
                    <div class="team-info">
                        <h3 class="team-name">Jashlein Leanne T. Marquez</h3>
                        <p class="team-role">Role</p>
                        <div class="team-social">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Second row - 2 members -->
            <div class="team-row">
                <div class="team-card">
                    <div class="team-image">
                        <img src="public/images/team/team3.jpg" alt="Team Member">
                    </div>
                    <div class="team-info">
                        <h3 class="team-name">Duane Kyros DR. Marzan</h3>
                        <p class="team-role">Role</p>
                        <div class="team-social">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="team-card">
                    <div class="team-image">
                        <img src="public/images/team/team4.jpg" alt="Team Member">
                    </div>
                    <div class="team-info">
                        <h3 class="team-name">Keith Reijay M. Montemayor</h3>
                        <p class="team-role">Role</p>
                        <div class="team-social">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content">
            <h2>Ready to Get Pinned?</h2>
            <p>Join our community to discover amazing food stalls, share your experiences, and support local food entrepreneurs in PUP.</p>
            <div class="cta-buttons">
                <a href="signup.php" class="btn btn-white">Sign Up Now</a>
                <a href="stalls.php" class="btn btn-outline-white">Explore Stalls</a>
            </div>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?> 