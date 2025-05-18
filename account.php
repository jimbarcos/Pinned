<?php
// Include the configuration file
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please log in to access your account.";
    header('Location: signin.php');
    exit();
}

// Set page title
$page_title = "My Account";

// Get user information
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error_message'] = "User not found.";
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error. Please try again later.";
    header('Location: index.php');
    exit();
}

// Process form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update profile form submitted
        $name = trim($_POST['name']);
        
        // Validate name
        if (empty($name)) {
            $message = "Name cannot be empty.";
            $message_type = "danger";
        } elseif (strlen($name) > 16) {
            $message = "Name must be 16 characters or less.";
            $message_type = "danger";
        } else {
            try {
                // Check if the new name is already taken by another user
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE name = :name AND id != :user_id");
                $check_stmt->execute([
                    'name' => $name,
                    'user_id' => $_SESSION['user_id']
                ]);
                
                if ($check_stmt->rowCount() > 0) {
                    $message = "This username is already taken. Please choose a different one.";
                    $message_type = "danger";
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = :name, updated_at = NOW() WHERE id = :user_id");
                    $stmt->execute([
                        'name' => $name,
                        'user_id' => $_SESSION['user_id']
                    ]);
                    
                    // Update session data
                    $_SESSION['user_name'] = $name;
                    
                    $message = "Your profile has been updated successfully.";
                    $message_type = "success";
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
                    $stmt->execute(['user_id' => $_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                $message = "Database error. Please try again later.";
                $message_type = "danger";
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password form submitted
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        // Validate passwords
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = "All password fields are required.";
            $message_type = "danger";
        } elseif (!password_verify($current_password, $user['password'])) {
            $message = "Current password is incorrect.";
            $message_type = "danger";
        } elseif ($new_password !== $confirm_password) {
            $message = "New passwords do not match.";
            $message_type = "danger";
        } elseif (strlen($new_password) < 8) {
            $message = "New password must be at least 8 characters long.";
            $message_type = "danger";
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id");
                $stmt->execute([
                    'password' => $hashed_password,
                    'user_id' => $_SESSION['user_id']
                ]);
                
                $message = "Your password has been changed successfully.";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Database error. Please try again later.";
                $message_type = "danger";
            }
        }
    }
}

// Include header
include 'header.php';
?>

<style>
    .account-section {
        padding: 50px 0;
    }
    
    .account-container {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .account-title {
        margin-bottom: 30px;
    }
    
    .account-card {
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }
    
    .account-card-header {
        background-color: var(--light-bg);
        padding: 20px;
        border-bottom: 1px solid #eee;
    }
    
    .account-card-body {
        padding: 20px;
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
    
    .btn {
        padding: 12px 20px;
        border: none;
        border-radius: var(--border-radius);
        cursor: pointer;
        font-weight: 600;
        transition: var(--transition);
    }
    
    .btn-primary {
        background-color: var(--primary-color);
        color: white;
    }
    
    .btn-primary:hover {
        background-color: #c31212;
    }
    
    .btn-danger {
        background-color: #dc3545;
        color: white;
    }
    
    .btn-danger:hover {
        background-color: #bd2130;
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
    
    .danger-zone {
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: var(--border-radius);
        padding: 20px;
        margin-top: 40px;
    }
    
    .danger-zone h3 {
        color: #721c24;
        margin-top: 0;
    }
    
    .delete-account-form {
        margin-top: 20px;
    }
    
    .delete-account-form label {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .delete-account-form input[type="checkbox"] {
        margin-right: 10px;
    }
</style>

<section class="account-section">
    <div class="container">
        <div class="account-container">
            <h2 class="account-title">My Account</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="account-card">
                <div class="account-card-header">
                    <h3>Profile Information</h3>
                </div>
                <div class="account-card-body">
                    <form method="POST" action="account.php">
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required maxlength="16">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            <small>Email address cannot be changed.</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Account Type</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['user_type']))); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label>Member Since</label>
                            <input type="text" class="form-control" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" disabled>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <div class="account-card">
                <div class="account-card-header">
                    <h3>Change Password</h3>
                </div>
                <div class="account-card-body">
                    <form method="POST" action="account.php">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
            
            <div class="danger-zone">
                <h3>Danger Zone</h3>
                <p>Once you delete your account, there is no going back. Please be certain.</p>
                
                <form id="deleteAccountForm" method="POST" action="delete_account.php" class="delete-account-form">
                    <label>
                        <input type="checkbox" id="confirmDelete" required>
                        I understand that this action is irreversible and will permanently delete my account.
                    </label>
                    
                    <button type="submit" class="btn btn-danger">Delete My Account</button>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteAccountForm = document.getElementById('deleteAccountForm');
        
        deleteAccountForm.addEventListener('submit', function(e) {
            const confirmDelete = document.getElementById('confirmDelete');
            
            if (!confirmDelete.checked) {
                e.preventDefault();
                alert('Please confirm that you understand the consequences of account deletion.');
                return;
            }
            
            if (!confirm('Are you sure you want to permanently delete your account? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
</script>

<?php include 'footer.php'; ?> 