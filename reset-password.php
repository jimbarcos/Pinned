<?php
// Include the configuration file
require_once 'config.php';

// Set page title
$page_title = 'Reset Password';

// Include header
include 'header.php';

// Process token validation
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$valid_token = false;
$user_id = null;

if (!empty($token)) {
    // Check if token exists and is not expired
    $stmt = $pdo->prepare("SELECT id FROM users WHERE password_reset_token = :token AND token_expiry > NOW()");
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $valid_token = true;
        $user_id = $user['id'];
    }
}

// Process form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate passwords
    if (empty($password) || empty($confirm_password)) {
        $message = "Please enter and confirm your new password.";
        $message_type = "danger";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "danger";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
        $message_type = "danger";
    } else {
        // Update password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = :password, password_reset_token = NULL, token_expiry = NULL WHERE id = :id");
        $stmt->execute([
            'password' => $hashed_password,
            'id' => $user_id
        ]);
        
        $message = "Your password has been reset successfully. You can now <a href='signin.php'>sign in</a> with your new password.";
        $message_type = "success";
        $valid_token = false; // Hide the form
    }
}
?>

<style>
    .reset-password-section {
        padding: 50px 0;
    }
    
    .reset-password-container {
        max-width: 500px;
        margin: 0 auto;
        background: white;
        padding: 30px;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
    }
    
    .reset-password-title {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
    }
    
    .submit-btn {
        width: 100%;
        padding: 12px;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: var(--border-radius);
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
    }
    
    .submit-btn:hover {
        background-color: #c31212;
    }
    
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: var(--border-radius);
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
    
    .back-to-signin {
        margin-top: 20px;
        text-align: center;
    }
</style>

<section class="reset-password-section">
    <div class="container">
        <div class="reset-password-container">
            <h2 class="reset-password-title">Reset Password</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$valid_token && empty($message)): ?>
                <div class="alert alert-danger">
                    Invalid or expired reset token. Please request a new password reset link from the <a href="forgot-password">forgot password</a> page.
                </div>
            <?php elseif ($valid_token): ?>
                <form method="POST" action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter new password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                    </div>
                    
                    <button type="submit" class="submit-btn">RESET PASSWORD</button>
                </form>
            <?php endif; ?>
            
            <div class="back-to-signin">
                <p>Remember your password? <a href="signin" class="red-text">Sign In</a></p>
            </div>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?> 