<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set page title
$page_title = 'Email Configuration Check';

// Include header if available
if (file_exists('header.php')) {
    include 'header.php';
} else {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>$page_title</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; }
            .container { max-width: 1000px; margin: 0 auto; }
            h1, h2, h3 { color: #333; }
            pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
            .success { color: green; }
            .error { color: red; }
            .warning { color: orange; }
            .section { margin-bottom: 30px; border: 1px solid #ddd; padding: 20px; border-radius: 5px; }
            button { padding: 10px 15px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background: #45a049; }
            input[type='email'], input[type='text'] { width: 100%; padding: 10px; margin: 8px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        </style>
    </head>
    <body>
    <div class='container'>";
}
?>

<div class="section">
    <h1>Email Configuration Checker</h1>
    <p>This tool will help you diagnose and fix email sending issues with your website.</p>
</div>

<div class="section">
    <h2>1. PHP Mail Configuration</h2>
    
    <h3>PHP Version and Settings</h3>
    <pre>
PHP Version: <?php echo phpversion(); ?>

Mail Settings:
- sendmail_path: <?php echo ini_get('sendmail_path') ?: 'Not set'; ?>
- SMTP: <?php echo ini_get('SMTP') ?: 'Not set'; ?>
- smtp_port: <?php echo ini_get('smtp_port') ?: 'Not set'; ?>
- mail.add_x_header: <?php echo ini_get('mail.add_x_header') ?: 'Not set'; ?>
- mail.log: <?php echo ini_get('mail.log') ?: 'Not set'; ?>

Server Information:
- HTTP_HOST: <?php echo $_SERVER['HTTP_HOST'] ?? 'Not available'; ?>
- SERVER_SOFTWARE: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Not available'; ?>
- SERVER_ADMIN: <?php echo $_SERVER['SERVER_ADMIN'] ?? 'Not available'; ?>
    </pre>

    <?php
    // Check for common issues
    $issues = [];
    
    if (empty(ini_get('sendmail_path')) && empty(ini_get('SMTP'))) {
        $issues[] = 'Neither sendmail_path nor SMTP server is configured. This might cause email sending issues.';
    }
    
    if (!empty(ini_get('SMTP')) && empty(ini_get('smtp_port'))) {
        $issues[] = 'SMTP server is set but smtp_port is not configured.';
    }
    
    // Display issues if any
    if (!empty($issues)) {
        echo '<h3>Potential Issues Detected</h3>';
        echo '<ul class="error">';
        foreach ($issues as $issue) {
            echo '<li>' . htmlspecialchars($issue) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="success">No obvious configuration issues detected.</p>';
    }
    ?>
</div>

<div class="section">
    <h2>2. Test Email Functionality</h2>
    
    <form method="post" action="">
        <div>
            <label for="test_email">Email Address to Send Test To:</label>
            <input type="email" id="test_email" name="test_email" value="<?php echo $_POST['test_email'] ?? ''; ?>" required>
        </div>
        <div>
            <label for="from_email">From Email Address (optional):</label>
            <input type="email" id="from_email" name="from_email" value="<?php echo $_POST['from_email'] ?? ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'example.com')); ?>">
        </div>
        <div>
            <label for="from_name">From Name (optional):</label>
            <input type="text" id="from_name" name="from_name" value="<?php echo $_POST['from_name'] ?? 'Pinned Support'; ?>">
        </div>
        <br>
        <button type="submit" name="send_test">Send Test Email</button>
    </form>
    
    <?php
    // Process test email form
    if (isset($_POST['send_test'])) {
        $to = $_POST['test_email'];
        $from_email = $_POST['from_email'];
        $from_name = $_POST['from_name'];
        
        $subject = "Test Email from " . ($_SERVER['HTTP_HOST'] ?? 'Your Website') . " - " . date('Y-m-d H:i:s');
        
        // Create email body
        $message = "Hello,\n\n";
        $message .= "This is a test email sent from your website at " . date('Y-m-d H:i:s') . ".\n\n";
        $message .= "Server Information:\n";
        $message .= "- PHP Version: " . phpversion() . "\n";
        $message .= "- HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'Not available') . "\n";
        $message .= "- SERVER_SOFTWARE: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Not available') . "\n\n";
        $message .= "If you received this email, your mail configuration is working correctly!\n\n";
        $message .= "Regards,\nThe Pinned Team";
        
        // Headers
        $headers = "From: " . $from_name . " <" . $from_email . ">\r\n";
        $headers .= "Reply-To: " . $from_email . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        // Enable error reporting for mail errors
        $old_error_reporting = error_reporting();
        error_reporting(E_ALL);
        
        // Attempt to send email
        $mail_result = mail($to, $subject, $message, $headers);
        
        // Capture any errors
        $mail_error = error_get_last();
        error_reporting($old_error_reporting);
        
        // Create logs directory if it doesn't exist
        $log_dir = 'logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        // Log mail attempt
        $timestamp = date('Y-m-d H:i:s');
        $filename = $log_dir . '/email_test_' . date('Ymd_His') . '.txt';
        
        $log_content = "==========\n";
        $log_content .= "Time: $timestamp\n";
        $log_content .= "To: $to\n";
        $log_content .= "From: $from_name <$from_email>\n";
        $log_content .= "Subject: $subject\n";
        $log_content .= "Headers: $headers\n";
        $log_content .= "Result: " . ($mail_result ? "Success" : "Failed") . "\n";
        if ($mail_error) {
            $log_content .= "Error: " . print_r($mail_error, true) . "\n";
        }
        $log_content .= "Body:\n$message\n";
        $log_content .= "==========\n";
        
        file_put_contents($filename, $log_content);
        
        // Display result
        if ($mail_result) {
            echo '<div class="success">';
            echo '<h3>Test Email Sent Successfully!</h3>';
            echo '<p>A test email has been sent to ' . htmlspecialchars($to) . '. Please check your inbox (and spam folder) to confirm receipt.</p>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<h3>Failed to Send Test Email</h3>';
            echo '<p>The mail() function returned false, indicating a failure to send the email.</p>';
            
            if ($mail_error) {
                echo '<h4>PHP Error:</h4>';
                echo '<pre>' . htmlspecialchars(print_r($mail_error, true)) . '</pre>';
            }
            
            echo '<p>The test has been logged to: ' . htmlspecialchars($filename) . '</p>';
            echo '</div>';
        }
    }
    ?>
</div>

<div class="section">
    <h2>3. Troubleshooting Guide</h2>
    
    <h3>Common Issues</h3>
    <ul>
        <li><strong>Shared Hosting Limitations:</strong> Many shared hosting providers restrict or disable the mail() function for security reasons.</li>
        <li><strong>Missing Mail Server:</strong> The server might not have a properly configured mail server.</li>
        <li><strong>Email Deliverability:</strong> Emails might be sent but end up in spam due to improper SPF/DKIM configuration.</li>
        <li><strong>Rate Limiting:</strong> Your hosting provider might limit the number of emails you can send.</li>
    </ul>
    
    <h3>Recommended Solutions</h3>
    <ol>
        <li>
            <strong>Check with Your Hosting Provider:</strong>
            <ul>
                <li>Confirm that the mail() function is enabled for your account</li>
                <li>Ask about any email sending limitations or requirements</li>
                <li>For InfinityFree or other free hosting, check their documentation for email restrictions</li>
            </ul>
        </li>
        <li>
            <strong>Use a Third-Party SMTP Service:</strong>
            <ul>
                <li>Consider using services like SendGrid, Mailgun, or SMTP.com</li>
                <li>These services often provide free tiers and are more reliable for email delivery</li>
                <li>You'll need to use a PHP library like PHPMailer to connect to these services</li>
            </ul>
        </li>
        <li>
            <strong>Set Up SPF Records:</strong>
            <ul>
                <li>Configure SPF records for your domain to improve email deliverability</li>
                <li>This requires access to your domain's DNS settings</li>
            </ul>
        </li>
        <li>
            <strong>Use Fallback Mechanisms:</strong>
            <ul>
                <li>For password reset and other critical emails, always provide fallback mechanisms</li>
                <li>For example, display the reset link on screen if email sending fails</li>
            </ul>
        </li>
    </ol>
</div>

<?php
// Include footer if available
if (file_exists('footer.php')) {
    include 'footer.php';
} else {
    echo "</div>
    </body>
    </html>";
}
?> 