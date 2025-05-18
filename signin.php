<?php
$page_title = "Sign In";
include 'header.php';

// Get any errors from session
$errors = $_SESSION['login_errors'] ?? [];
$login_email = $_SESSION['login_email'] ?? '';

// Clear session data
unset($_SESSION['login_errors']);
unset($_SESSION['login_email']);
?>

<!-- Sign In Section -->
<section class="auth-container">
    <div class="auth-image" style="background-image: url('public/images/pup-lagoon.jpg');">
        <div class="auth-text">
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
        </div>
    </div>
    <div class="auth-form">
        <div class="auth-form-container">
            <div class="auth-header">
                <a href="index" class="back-link"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
            
            <h1>Sign In</h1>
            <p>Don't have an account? <a href="signup" class="red-text">Create Account</a></p>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form id="signinForm" action="login_process.php" method="POST">
                <div class="form-group">
                    <input type="email" class="form-control" placeholder="Email Address" name="email" value="<?php echo $login_email; ?>" required>
                </div>
                <div class="form-group">
                    <div class="password-field">
                        <input type="password" class="form-control" placeholder="Password" name="password" id="password" required>
                        <span class="password-toggle" onclick="togglePassword('password')">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-group" style="text-align: right;">
                    <a href="forgot-password" class="red-text">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; background-color: #b91c1c;">SIGN IN</button>
            </form>
            
            <div class="social-login">
                <p>or Connect with Social Media</p>
                <div class="social-buttons">
                    <a href="#" class="social-button"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-button"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-button"><i class="fab fa-google"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
}
.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.alert ul {
    margin: 0;
    padding-left: 20px;
}

/* Password field styling */
.password-field {
    position: relative;
    width: 100%;
}

.password-toggle {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #555;
    z-index: 10;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.password-toggle:hover {
    color: #000;
}
</style>

<script>
function togglePassword(fieldId) {
    const passwordField = document.getElementById(fieldId);
    const toggleIcon = passwordField.nextElementSibling.querySelector('i');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordField.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}
</script>

<?php include 'footer.php'; ?> 