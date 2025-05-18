<?php
$page_title = "Sign Up";
include 'header.php';

// Get any errors from session
$errors = $_SESSION['signup_errors'] ?? [];
$form_data = $_SESSION['signup_form_data'] ?? [];

// Clear session data
unset($_SESSION['signup_errors']);
unset($_SESSION['signup_form_data']);
?>

<!-- Sign Up Section -->
<section class="auth-container">
    <div class="auth-form">
        <div class="auth-form-container">
            <h1>Sign Up</h1>
            <p>Already have an account? <a href="signin" class="red-text">Sign In</a></p>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form id="signupForm" action="signup_process.php" method="POST">
                <div class="form-group">
                    <input type="text" class="form-control" placeholder="Name" name="name" value="<?php echo $form_data['name'] ?? ''; ?>" required maxlength="16">
                </div>
                <div class="form-group">
                    <input type="email" class="form-control" placeholder="Email" name="email" value="<?php echo $form_data['email'] ?? ''; ?>" required>
                </div>
                <div class="form-group">
                    <div class="password-field">
                        <input type="password" class="form-control" placeholder="Password" name="password" id="password" required>
                        <span class="password-toggle" onclick="togglePassword('password')">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="password-field">
                        <input type="password" class="form-control" placeholder="Confirm Password" name="confirm_password" id="confirm_password" required>
                        <span class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Account Type:</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="user_type" value="food_enthusiast" <?php echo (isset($form_data['user_type']) && $form_data['user_type'] === 'food_enthusiast') || !isset($form_data['user_type']) ? 'checked' : ''; ?>>
                            Food Enthusiast
                        </label>
                        <label>
                            <input type="radio" name="user_type" value="stall_owner" <?php echo (isset($form_data['user_type']) && $form_data['user_type'] === 'stall_owner') ? 'checked' : ''; ?>>
                            Stall Owner
                        </label>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="terms" required>
                    <label for="terms">I agree to all statements in <a href="terms" class="red-text" target="_blank">Terms of Service</a></label>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; background-color: #b91c1c;">CREATE ACCOUNT</button>
            </form>
        </div>
    </div>
    <div class="auth-image" style="background-image: url('public/images/stalls/stall1.jpg');">
        <div class="auth-text">
            <div class="logo">
                <img src="public/images/logos/Logo-04.png" alt="Pinned Logo" width="150">
            </div>
            <div style="margin-top: 100px;">
                <img src="public/images/food-containers.png" alt="Food Containers" width="500">
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
.radio-group {
    display: flex;
    gap: 20px;
    margin-top: 5px;
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