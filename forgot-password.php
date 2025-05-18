<?php
// Include the configuration file
require_once 'config.php';
require_once 'mail_config.php';
require_once 'includes/Mailer.php';
require_once 'includes/BrevoApiMailer.php';

// Set page title
$page_title = 'Forgot Password';

// Include header
include 'header.php';

// Process form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    
    // Validate email
    if (empty($email)) {
        $message = "Please enter your email address.";
        $message_type = "danger";
    } else {
        // Check if email exists in database
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate a random token
            $token = bin2hex(random_bytes(32));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $stmt = $pdo->prepare("UPDATE users SET password_reset_token = :token, token_expiry = :expiry WHERE id = :id");
            $stmt->execute([
                'token' => $token,
                'expiry' => $token_expiry,
                'id' => $user['id']
            ]);
            
            // Create reset URL using the server's hostname
            $reset_url = "http://{$_SERVER['HTTP_HOST']}/reset-password.php?token=" . $token;
            
            // Setup environment variables for the Mailer class if using SMTP
            if ($mail_config['method'] == 'smtp') {
                define('SMTP_HOST', $mail_config['smtp']['host']);
                define('SMTP_USERNAME', $mail_config['smtp']['username']);
                define('SMTP_PASSWORD', $mail_config['smtp']['password']);
                define('SMTP_PORT', $mail_config['smtp']['port']);
                define('MAIL_FROM_ADDRESS', $mail_config['from_email']);
                define('MAIL_FROM_NAME', $mail_config['from_name']);
            }
            
            // Create logs directory if it doesn't exist
            $log_dir = 'logs';
            if (!is_dir($log_dir)) {
                mkdir($log_dir, 0755, true);
            }
            
            // Initialize mailer
            $mailer = new Mailer();
            
            // Send email with Mailer class
            $sent = false;
            
            if ($mail_config['method'] == 'smtp') {
                // Send email using SMTP through the Mailer class
                $sent = $mailer->sendPasswordResetEmail($email, $user['name'], $reset_url);
            } else if ($mail_config['method'] == 'api') {
                // Send email using Brevo API
                $apiMailer = new BrevoApiMailer(
                    $mail_config['api']['key'], 
                    $mail_config['from_email'], 
                    $mail_config['from_name']
                );
                
                // Debug information
                $debug_info = "API Key: " . substr($mail_config['api']['key'], 0, 10) . "..." . substr($mail_config['api']['key'], -5) . "\n";
                $debug_info .= "From Email: " . $mail_config['from_email'] . "\n";
                $debug_info .= "From Name: " . $mail_config['from_name'] . "\n";
                $debug_info .= "To Email: " . $email . "\n";
                $debug_info .= "Reset URL: " . $reset_url . "\n";
                file_put_contents($log_dir . '/api_debug_' . date('Ymd_His') . '.txt', $debug_info);
                
                // Create email content
                $subject = "Reset Your Pinned Password";
                
                $htmlBody = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { text-align: center; margin-bottom: 20px; }
                        .header img { max-width: 150px; }
                        .content { background-color: #f9f9f9; padding: 20px; border-radius: 5px; }
                        .button { display: inline-block; background-color: #e32929; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; }
                        .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <img src='https://pinned.free.nf/public/images/logos/Logo-04.png' alt='Pinned Logo'>
                        </div>
                        <div class='content'>
                            <h2>Hello {$user['name']},</h2>
                            <p>You recently requested to reset your password for your Pinned account. Click the button below to reset it:</p>
                            <p style='text-align: center;'>
                                <a href='{$reset_url}' class='button'>Reset Password</a>
                            </p>
                            <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
                            <p>This password reset link is only valid for the next 1 hour.</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " Pinned. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $textBody = "Hello {$user['name']},\n\nYou recently requested to reset your password for your Pinned account. Please click the link below to reset it:\n\n{$reset_url}\n\nIf you did not request a password reset, please ignore this email.\n\nThis password reset link is only valid for the next 1 hour.\n\n© " . date('Y') . " Pinned. All rights reserved.";
                
                // Try direct send instead of the utility method
                $sent = $apiMailer->send($email, $subject, $htmlBody, $textBody);
                
                // Store error if needed for later
                $error_message = $sent ? '' : $apiMailer->getError();
                
                // Log API response
                file_put_contents(
                    $log_dir . '/api_result_' . date('Ymd_His') . '.txt',
                    "Result: " . ($sent ? "Success" : "Failed") . "\nError: " . $error_message
                );
            } else {
                // Fallback to PHP mail() function if SMTP is not configured
                // Email content
                $to = $email;
                $subject = "Pinned - Password Reset";
                
                // Create email body
                $message_body = "Hello {$user['name']},\n\n";
                $message_body .= "You have requested to reset your password for your Pinned account.\n\n";
                $message_body .= "Please click the link below to reset your password. This link will expire in 1 hour.\n\n";
                $message_body .= $reset_url . "\n\n";
                $message_body .= "If you did not request this password reset, please ignore this email.\n\n";
                $message_body .= "Regards,\nThe Pinned Team";
                
                // Headers
                $from_name = $mail_config['from_name'];
                $from_email = $mail_config['from_email'];
                
                $headers = "From: {$from_name} <{$from_email}>\r\n";
                $headers .= "Reply-To: {$from_email}\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
                
                // Enable error reporting for mail errors
                $old_error_reporting = error_reporting();
                error_reporting(E_ALL);
                
                // Attempt to send email
                $sent = mail($to, $subject, $message_body, $headers);
                
                // Capture any errors
                $mail_error = error_get_last();
                error_reporting($old_error_reporting);
                
                // Log mail attempt
                $timestamp = date('Y-m-d H:i:s');
                $filename = $log_dir . '/email_log_' . date('Ymd') . '.txt';
                
                $log_content = "==========\n";
                $log_content .= "Time: $timestamp\n";
                $log_content .= "To: $to\n";
                $log_content .= "Subject: $subject\n";
                $log_content .= "Headers: $headers\n";
                $log_content .= "Result: " . ($sent ? "Success" : "Failed") . "\n";
                if (!$sent && $mail_error) {
                    $log_content .= "Error: " . print_r($mail_error, true) . "\n";
                }
                $log_content .= "Body:\n$message_body\n";
                $log_content .= "==========\n\n";
                
                file_put_contents($filename, $log_content, FILE_APPEND);
            }
            
            if ($sent) {
                $message = "A password reset link has been sent to your email address. Please check your inbox and follow the instructions to reset your password.";
                $message_type = "success";
            } else {
                // Get error message if using Mailer
                if ($mail_config['method'] == 'smtp') {
                    $error_message = $mailer->getError();
                } else if ($mail_config['method'] == 'api') {
                    // Error message already set above
                } else {
                    $error_message = "Unknown mail error";
                }
                
                // Fallback if mail function fails - show the reset link <a href=\"$reset_url\">$reset_url</a>
                $message = "Our email system encountered an issue. As a temporary measure, you can use this link to reset your password: ";
                $message_type = "warning";
                
                // Log specific mail failure
                $error_filename = $log_dir . '/mail_error_' . date('Ymd_His') . '_' . md5($email . time()) . '.txt';
                $error_log = "Time: " . date('Y-m-d H:i:s') . "\n";
                $error_log .= "Mail Method: " . ($mail_config['method'] == 'smtp' ? "SMTP" : ($mail_config['method'] == 'api' ? "API" : "PHP mail()")) . "\n";
                $error_log .= "Mail Result: Failed\n";
                $error_log .= "Error Message: " . $error_message . "\n";
                
                // Add PHP configuration information
                $error_log .= "\nPHP Mail Configuration:\n";
                $error_log .= "PHP Version: " . phpversion() . "\n";
                $error_log .= "sendmail_path: " . ini_get('sendmail_path') . "\n";
                $error_log .= "SMTP: " . ini_get('SMTP') . "\n";
                $error_log .= "smtp_port: " . ini_get('smtp_port') . "\n";
                
                // Add server information
                $error_log .= "\nServer Information:\n";
                $error_log .= "HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "\n";
                $error_log .= "SERVER_SOFTWARE: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
                
                file_put_contents($error_filename, $error_log);
            }
        } else {
            $message = "Email address not found. Please check your email or sign up for a new account.";
            $message_type = "danger";
        }
    }
}
?>

<style>
    .forgot-password-section {
        padding: 50px 0;
    }
    
    .forgot-password-container {
        max-width: 500px;
        margin: 0 auto;
        background: white;
        padding: 30px;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
    }
    
    .forgot-password-title {
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
    
    .alert-warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
    }
    
    .back-to-signin {
        margin-top: 20px;
        text-align: center;
    }
</style>

<section class="forgot-password-section">
    <div class="container">
        <div class="forgot-password-container">
            <h2 class="forgot-password-title">Forgot Password</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="forgot-password.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                </div>
                
                <button type="submit" class="submit-btn">RESET PASSWORD</button>
            </form>
            
            <div class="back-to-signin">
                <p>Remember your password? <a href="signin" class="red-text">Sign In</a></p>
            </div>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?> 