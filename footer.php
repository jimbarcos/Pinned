    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <div class="logo">
                        <img src="public/images/logos/Logo-04.png" alt="Pinned Logo">
                    </div>
                    <p>Discover, explore, and enjoy the diverse food stalls in the PUP Lagoon.</p>
                    <?php if(!isset($_SESSION['user_id'])): ?>
                        <a href="signup" class="btn btn-outline">Sign Up</a>
                    <?php endif; ?>
                </div>
                <div class="footer-links">
                    <div class="footer-section">
                        <h3>DISCOVER</h3>
                        <ul>
                            <li><a href="stalls">Food Stalls</a></li>
                            <li><a href="map">Map</a></li>
                            <li><a href="about">About Us</a></li>
                        </ul>
                    </div>
                    <div class="footer-section">
                        <h3>ACCOUNT</h3>
                        <ul>
                            <?php if(isset($_SESSION['user_id'])): ?>
                                <li><a href="logout">Logout</a></li>
                                <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'stall_owner'): ?>
                                    <li><a href="manage-stall">Manage Stall</a></li>
                                <?php endif; ?>
                            <?php else: ?>
                                <li><a href="signin">Sign In</a></li>
                                <li><a href="signup">Sign Up</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="footer-section">
                        <h3>CONNECT WITH PUP</h3>
                        <div class="social-icons">
                            <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                            <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#" aria-label="Email"><i class="fas fa-envelope"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>Created by BSCS 3-5 Group 2 in completion for the course COMP 016 - Web Development. All rights reserved</p>
            </div>
        </div>
    </footer>

    <script src="public/js/main.js?v=<?php echo time(); ?>"></script>
    
    <!-- Script to handle viewport height for mobile browsers -->
    <script>
        // First we get the viewport height and multiply it by 1% to get a value for a vh unit
        let vh = window.innerHeight * 0.01;
        // Then we set the value in the --vh custom property to the root of the document
        document.documentElement.style.setProperty('--vh', `${vh}px`);
        
        // We listen to the resize event
        window.addEventListener('resize', () => {
            // We execute the same script as before
            let vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        });
    </script>
</body>
</html> 