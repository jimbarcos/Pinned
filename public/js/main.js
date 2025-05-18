/**
 * Pinned - Food Stall Discovery Platform
 * Main JavaScript file
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize carousels
    initStoreCarousel();
    initTestimonialsCarousel();
    
    // Initialize mobile menu with improved touch handling
    initMobileMenu();
    
    // Initialize other components
    enableTouchFriendlyInteractions();
    initSearch();
    
    // Initialize location interaction
    initLocationBadge();
});

/**
 * Initialize Store Carousel with modern UI/UX
 */
function initStoreCarousel() {
    const storeCarousel = document.querySelector('.store-carousel');
    if (!storeCarousel) return;
    
    const carouselItems = storeCarousel.querySelector('.carousel-items');
    const prevBtn = storeCarousel.querySelector('.prev');
    const nextBtn = storeCarousel.querySelector('.next');
    
    // Calculate how many slides we need based on actual stall count
    const storeLogos = carouselItems.querySelectorAll('.store-logo');
    
    // Responsive logo display - improved for mobile
    const getLogosPerSlide = () => {
        if (window.innerWidth < 480) return 3; // Show 3 on small phones
        if (window.innerWidth < 576) return 3; // Show 3 on phones
        if (window.innerWidth < 768) return 4; // Show 4 on tablets
        if (window.innerWidth < 992) return 5; // Show 5 on small desktops
        return 6; // Show 6 on larger desktops
    };
    
    let logosPerSlide = getLogosPerSlide();
    let totalSlides = Math.ceil(storeLogos.length / logosPerSlide) || 1;
    
    // Enhance logos with animated entrance
    storeLogos.forEach((logo, index) => {
        logo.style.opacity = '0';
        logo.style.transform = 'translateY(20px)';
        logo.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        
        // Staggered animation
        setTimeout(() => {
            logo.style.opacity = '1';
            logo.style.transform = 'translateY(0)';
        }, 100 * (index % logosPerSlide));
        
        // Add hover tooltip with store name - more mobile friendly
        const storeName = logo.querySelector('img').getAttribute('alt');
        if (storeName && storeName !== 'Store Logo') {
            const tooltip = document.createElement('div');
            tooltip.className = 'store-tooltip';
            tooltip.textContent = storeName;
            logo.appendChild(tooltip);
            
            // Mobile-friendly tooltip behavior
            const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            
            if (isTouchDevice) {
                // For touch devices: show tooltip on tap, hide after delay
                logo.addEventListener('touchstart', (e) => {
                    e.preventDefault(); // Prevent default tap behavior
                    
                    // Hide all other tooltips first
                    document.querySelectorAll('.store-tooltip').forEach(t => {
                        if (t !== tooltip) t.style.opacity = '0';
                    });
                    
                    // Toggle this tooltip
                    const isVisible = tooltip.style.opacity === '1';
                    tooltip.style.opacity = isVisible ? '0' : '1';
                    tooltip.style.transform = isVisible ? 'translateY(10px)' : 'translateY(0)';
                    
                    // If showing, set a timer to hide it
                    if (!isVisible) {
                        setTimeout(() => {
                            tooltip.style.opacity = '0';
                            tooltip.style.transform = 'translateY(10px)';
                        }, 2000);
                    }
                });
            } else {
                // For desktop: use hover
                logo.addEventListener('mouseenter', () => {
                    tooltip.style.opacity = '1';
                    tooltip.style.transform = 'translateY(0)';
                });
                
                logo.addEventListener('mouseleave', () => {
                    tooltip.style.opacity = '0';
                    tooltip.style.transform = 'translateY(10px)';
                });
            }
        }
    });
    
    // Create dynamic dots based on slide count
    const dotsContainer = document.querySelector('.carousel-dots');
    if (dotsContainer) {
        // First clear existing dots
        dotsContainer.innerHTML = '';
        
        // Create appropriate number of dots
        for (let i = 0; i < totalSlides; i++) {
            const dot = document.createElement('span');
            dot.classList.add('dot');
            if (i === 0) dot.classList.add('active');
            dot.dataset.index = i;
            
            // Add animated ripple effect on click
            dot.addEventListener('click', function() {
                const ripple = document.createElement('span');
                ripple.classList.add('dot-ripple');
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 1000);
            });
            
            dotsContainer.appendChild(dot);
        }
    }
    
    let currentIndex = 0;
    let isAnimating = false;
    
    // Smooth navigation with animation lock to prevent rapid clicking
    function navigateCarousel(direction) {
        if (isAnimating) return;
        isAnimating = true;
        
        currentIndex = (currentIndex + direction + totalSlides) % totalSlides;
        updateCarousel();
        
        setTimeout(() => {
            isAnimating = false;
        }, 600); // Match this to the CSS transition time
    }
    
    // Event listeners for navigation
    prevBtn.addEventListener('click', () => navigateCarousel(-1));
    nextBtn.addEventListener('click', () => navigateCarousel(1));
    
    // Add button press animation
    [prevBtn, nextBtn].forEach(btn => {
        btn.addEventListener('mousedown', () => {
            btn.style.transform = 'scale(0.95)';
        });
        
        btn.addEventListener('mouseup', () => {
            btn.style.transform = 'scale(1)';
        });
        
        btn.addEventListener('mouseleave', () => {
            btn.style.transform = 'scale(1)';
        });
    });
    
    // Re-select dots after they've been created
    const updatedDots = document.querySelectorAll('.carousel-dots .dot');
    
    // Event listeners for dots
    updatedDots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            if (isAnimating || currentIndex === index) return;
            isAnimating = true;
            
            currentIndex = index;
            updateCarousel();
            
            setTimeout(() => {
                isAnimating = false;
            }, 600);
        });
    });
    
    // Update carousel position and dots with smooth animation
    function updateCarousel() {
        // Calculate the transform position based on viewport width
        // This creates a smoother experience than using percentages
        const slideWidth = 100; // 100% width
        carouselItems.style.transform = `translateX(-${currentIndex * slideWidth}%)`;
        
        // Add animation class for transition effect
        carouselItems.classList.add('transitioning');
        setTimeout(() => {
            carouselItems.classList.remove('transitioning');
        }, 600);
        
        // Update active dot with animation
        updatedDots.forEach((dot, index) => {
            if (index === currentIndex) {
                dot.classList.add('active');
                // Animate the active dot
                dot.animate([
                    { transform: 'scale(1.2)' },
                    { transform: 'scale(1)' }
                ], {
                    duration: 300,
                    easing: 'ease-out'
                });
            } else {
                dot.classList.remove('active');
            }
        });
    }
    
    // Responsive handling
    function handleResize() {
        const newLogosPerSlide = getLogosPerSlide();
        
        if (newLogosPerSlide !== logosPerSlide) {
            logosPerSlide = newLogosPerSlide;
            const newTotalSlides = Math.ceil(storeLogos.length / logosPerSlide) || 1;
            
            // Only reload if the number of slides has changed
            if (newTotalSlides !== totalSlides) {
                totalSlides = newTotalSlides;
                
                // Reset to first slide if current slide would be out of bounds
                if (currentIndex >= totalSlides) {
                    currentIndex = 0;
                    updateCarousel();
                }
                
                // Update dots to match new slide count
                if (dotsContainer) {
                    dotsContainer.innerHTML = '';
                    
                    for (let i = 0; i < totalSlides; i++) {
                        const dot = document.createElement('span');
                        dot.classList.add('dot');
                        if (i === currentIndex) dot.classList.add('active');
                        dot.dataset.index = i;
                        dotsContainer.appendChild(dot);
                        
                        dot.addEventListener('click', function() {
                            if (isAnimating || currentIndex === i) return;
                            isAnimating = true;
                            
                            currentIndex = i;
                            updateCarousel();
                            
                            setTimeout(() => {
                                isAnimating = false;
                            }, 600);
                        });
                    }
                }
            }
        }
    }
    
    // Debounce resize events for better performance
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(handleResize, 250);
    });
    
    // Improved auto-rotate with pause on hover
    let autoSlideInterval;
    
    function startAutoSlide() {
        autoSlideInterval = setInterval(() => {
            if (!document.hidden) {
                navigateCarousel(1);
            }
        }, 5000);
    }
    
    function stopAutoSlide() {
        clearInterval(autoSlideInterval);
    }
    
    // Pause auto-rotation when hovering over carousel
    storeCarousel.addEventListener('mouseenter', stopAutoSlide);
    storeCarousel.addEventListener('mouseleave', startAutoSlide);
    
    // Stop on page visibility change (tab switch)
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopAutoSlide();
        } else {
            startAutoSlide();
        }
    });
    
    // Start auto-rotation
    startAutoSlide();
    
    // Improve touch interaction for mobile
    let touchStartX = 0;
    let touchEndX = 0;
    
    carouselItems.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });
    
    carouselItems.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, { passive: true });
    
    function handleSwipe() {
        const swipeThreshold = 50; // Minimum swipe distance
        if (touchEndX < touchStartX - swipeThreshold) {
            // Swiped left - go next
            navigateCarousel(1);
        } else if (touchEndX > touchStartX + swipeThreshold) {
            // Swiped right - go previous
            navigateCarousel(-1);
        }
    }
}

/**
 * Initialize Testimonials Carousel
 */
function initTestimonialsCarousel() {
    const testimonialCarousel = document.querySelector('.testimonials-carousel');
    if (!testimonialCarousel) return;
    
    const testimonialCards = testimonialCarousel.querySelector('.testimonial-cards');
    const prevBtn = testimonialCarousel.querySelector('.prev');
    const nextBtn = testimonialCarousel.querySelector('.next');
    
    let scrollAmount = 0;
    const cardWidth = 300; // Width of a single card + gap
    
    // Event listeners for navigation
    prevBtn.addEventListener('click', () => {
        scrollAmount = Math.max(scrollAmount - cardWidth, 0);
        testimonialCards.scrollTo({
            left: scrollAmount,
            behavior: 'smooth'
        });
    });
    
    nextBtn.addEventListener('click', () => {
        scrollAmount = Math.min(scrollAmount + cardWidth, testimonialCards.scrollWidth - testimonialCards.clientWidth);
        testimonialCards.scrollTo({
            left: scrollAmount,
            behavior: 'smooth'
        });
    });
}

/**
 * Initialize mobile menu with improved UX
 */
function initMobileMenu() {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    
    if (!mobileMenuBtn || !navLinks) return;
    
    // Toggle menu with improved touch feedback
    mobileMenuBtn.addEventListener('click', (e) => {
        e.preventDefault();
        navLinks.classList.toggle('show');
        
        // Change icon based on state
        const icon = mobileMenuBtn.querySelector('i');
        if (icon) {
            if (navLinks.classList.contains('show')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }
        
        // Add ripple effect
        const ripple = document.createElement('span');
        ripple.style.position = 'absolute';
        ripple.style.top = '50%';
        ripple.style.left = '50%';
        ripple.style.transform = 'translate(-50%, -50%)';
        ripple.style.width = '0';
        ripple.style.height = '0';
        ripple.style.backgroundColor = 'rgba(255, 255, 255, 0.3)';
        ripple.style.borderRadius = '50%';
        ripple.style.transition = 'all 0.3s ease-out';
        
        mobileMenuBtn.appendChild(ripple);
        
        setTimeout(() => {
            ripple.style.width = '40px';
            ripple.style.height = '40px';
            ripple.style.opacity = '0';
        }, 10);
        
        setTimeout(() => {
            ripple.remove();
        }, 300);
    });
    
    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!mobileMenuBtn.contains(e.target) && !navLinks.contains(e.target) && navLinks.classList.contains('show')) {
            navLinks.classList.remove('show');
            const icon = mobileMenuBtn.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }
    });
    
    // Close menu when clicking on mobile nav links
    const mobileNavLinks = navLinks.querySelectorAll('a');
    mobileNavLinks.forEach(link => {
        link.addEventListener('click', () => {
            navLinks.classList.remove('show');
            const icon = mobileMenuBtn.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    });
}

/**
 * Form Validation
 */
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const inputs = form.querySelectorAll('input[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('error');
        } else {
            input.classList.remove('error');
        }
    });
    
    // Email validation for forms with email field
    const emailInput = form.querySelector('input[type="email"]');
    if (emailInput && emailInput.value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailInput.value)) {
            isValid = false;
            emailInput.classList.add('error');
        }
    }
    
    // Password validation for signup form
    const passwordInput = form.querySelector('input[name="password"]');
    const confirmPasswordInput = form.querySelector('input[name="confirm_password"]');
    if (passwordInput && confirmPasswordInput) {
        if (passwordInput.value !== confirmPasswordInput.value) {
            isValid = false;
            passwordInput.classList.add('error');
            confirmPasswordInput.classList.add('error');
            alert('Passwords do not match');
        }
    }
    
    return isValid;
}

/**
 * Search functionality for stalls page
 */
function initSearch() {
    const searchInput = document.querySelector('.search-input');
    if (!searchInput) return;
    
    const storeCards = document.querySelectorAll('.store-card');
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    // Search by text input
    searchInput.addEventListener('input', filterStores);
    
    // Filter by category buttons
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            filterButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            filterStores();
        });
    });
    
    function filterStores() {
        const searchTerm = searchInput.value.toLowerCase();
        const activeFilter = document.querySelector('.filter-btn.active');
        const filterCategory = activeFilter ? activeFilter.getAttribute('data-category') : 'all';
        
        storeCards.forEach(card => {
            const storeName = card.querySelector('h3').textContent.toLowerCase();
            const storeDescription = card.querySelector('p').textContent.toLowerCase();
            const storeCategory = card.getAttribute('data-category');
            
            const matchesSearch = storeName.includes(searchTerm) || storeDescription.includes(searchTerm);
            const matchesCategory = filterCategory === 'all' || storeCategory === filterCategory;
            
            if (matchesSearch && matchesCategory) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
}

/**
 * Enable touch-friendly interactions for mobile devices
 */
function enableTouchFriendlyInteractions() {
    // Improve carousel swiping on touch devices
    const carousels = document.querySelectorAll('.carousel-items, .testimonial-cards');
    carousels.forEach(carousel => {
        let startX, endX;
        let threshold = 50; // minimum distance for swipe
        
        carousel.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
        }, { passive: true });
        
        carousel.addEventListener('touchend', (e) => {
            if (!startX) return;
            
            endX = e.changedTouches[0].clientX;
            const diff = startX - endX;
            
            if (Math.abs(diff) < threshold) return;
            
            // Find the closest prev/next buttons
            const container = carousel.closest('.store-carousel') || carousel.closest('.testimonials-carousel');
            if (!container) return;
            
            if (diff > 0) {
                // Swipe left, go next
                const nextBtn = container.querySelector('.next');
                if (nextBtn) nextBtn.click();
            } else {
                // Swipe right, go prev
                const prevBtn = container.querySelector('.prev');
                if (prevBtn) prevBtn.click();
            }
            
            startX = null;
        }, { passive: true });
    });
    
    // Make buttons more touch-friendly
    const buttons = document.querySelectorAll('button, .btn');
    buttons.forEach(button => {
        // Add active state for touch feedback
        button.addEventListener('touchstart', () => {
            button.classList.add('touch-active');
        }, { passive: true });
        
        button.addEventListener('touchend', () => {
            button.classList.remove('touch-active');
        }, { passive: true });
    });
}

/**
 * Initialize location badge with enhanced interactions
 */
function initLocationBadge() {
    const locationBadge = document.querySelector('.location');
    if (!locationBadge) return;
    
    // For touch devices, add active state
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    
    if (isTouchDevice) {
        locationBadge.addEventListener('touchstart', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 6px 15px rgba(0, 0, 0, 0.1)';
        });
        
        locationBadge.addEventListener('touchend', function() {
            setTimeout(() => {
                this.style.transform = '';
                this.style.boxShadow = '';
            }, 300);
        });
    }
} 