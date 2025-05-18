<?php
/**
 * Mailer Class for Pinned
 * 
 * This class provides email functionality using PHPMailer and SMTP
 */

// Define paths for required PHPMailer files
$phpmailer_path = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
$smtp_path = __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
$exception_path = __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

// Check if PHPMailer is installed
if (!file_exists($phpmailer_path) || !file_exists($smtp_path) || !file_exists($exception_path)) {
    // PHPMailer not installed - display helpful message
    echo "<div style='border: 1px solid #f88; background-color: #fee; padding: 15px; margin: 15px; border-radius: 5px;'>";
    echo "<h2 style='color: #c00; margin-top: 0;'>PHPMailer Not Installed</h2>";
    echo "<p>The required PHPMailer library is not installed. Please run the <a href='../install_phpmailer.php'>PHPMailer Installer</a> first.</p>";
    echo "<p>If the installer doesn't work, you can manually install PHPMailer by following these steps:</p>";
    echo "<ol>";
    echo "<li>Download PHPMailer from <a href='https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip'>GitHub</a></li>";
    echo "<li>Extract the ZIP file on your computer</li>";
    echo "<li>Create a folder structure on your server: <code>/vendor/phpmailer/phpmailer/src/</code></li>";
    echo "<li>Upload at least these files to the src directory:";
    echo "<ul>";
    echo "<li>PHPMailer.php</li>";
    echo "<li>SMTP.php</li>";
    echo "<li>Exception.php</li>";
    echo "</ul></li>";
    echo "</ol>";
    echo "</div>";
    exit;
}

// Check if PHPMailer is already loaded
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    // Now we can safely include the files
    require_once $phpmailer_path;
    require_once $smtp_path;
    require_once $exception_path;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    private $error = '';
    
    /**
     * Constructor - initialize PHPMailer and set up SMTP
     */
    public function __construct() {
        $this->mail = new PHPMailer(true); // true enables exceptions
        
        // Configure SMTP using environment variables or constants
        $this->setupSMTP();
    }
    
    /**
     * Set up SMTP configuration
     */
    private function setupSMTP() {
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : getenv('SMTP_HOST');
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : getenv('SMTP_USERNAME');
            $this->mail->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : getenv('SMTP_PASSWORD');
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // or ENCRYPTION_SMTPS
            $this->mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : (getenv('SMTP_PORT') ? getenv('SMTP_PORT') : 587);
            
            // Email configuration
            $this->mail->setFrom(
                defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : getenv('MAIL_FROM_ADDRESS'), 
                defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : getenv('MAIL_FROM_NAME')
            );
            
            // Debug level (0 = no output, 2 = verbose output)
            $this->mail->SMTPDebug = 0;
            
            // Character set
            $this->mail->CharSet = 'UTF-8';
            
            // Format
            $this->mail->isHTML(true);
            
        } catch (Exception $e) {
            $this->error = "Mailer setup error: {$e->getMessage()}";
            error_log($this->error);
        }
    }
    
    /**
     * Send an email
     * 
     * @param string|array $to Recipient email or array of emails
     * @param string $subject Email subject
     * @param string $htmlBody HTML email body
     * @param string $textBody Plain text email body (optional)
     * @param array $attachments Array of file paths to attach (optional)
     * @param array $cc Array of CC recipients (optional)
     * @param array $bcc Array of BCC recipients (optional)
     * 
     * @return bool Success or failure
     */
    public function send($to, $subject, $htmlBody, $textBody = '', $attachments = [], $cc = [], $bcc = []) {
        if (!empty($this->error)) {
            return false;
        }
        
        try {
            // Recipients
            if (is_array($to)) {
                foreach ($to as $address) {
                    $this->mail->addAddress($address);
                }
            } else {
                $this->mail->addAddress($to);
            }
            
            // CC Recipients
            if (!empty($cc)) {
                foreach ($cc as $address) {
                    $this->mail->addCC($address);
                }
            }
            
            // BCC Recipients
            if (!empty($bcc)) {
                foreach ($bcc as $address) {
                    $this->mail->addBCC($address);
                }
            }
            
            // Attachments
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    $this->mail->addAttachment($attachment);
                }
            }
            
            // Content
            $this->mail->Subject = $subject;
            $this->mail->Body    = $htmlBody;
            
            // Add plain text version if provided
            if (!empty($textBody)) {
                $this->mail->AltBody = $textBody;
            }
            
            // Send the email
            $this->mail->send();
            
            // Clear all recipients and attachments for next send
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            return true;
            
        } catch (Exception $e) {
            $this->error = "Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}";
            error_log($this->error);
            return false;
        }
    }
    
    /**
     * Get last error message
     * 
     * @return string Error message
     */
    public function getError() {
        return $this->error;
    }
    
    /**
     * Enable SMTP debugging
     * 
     * @param int $level Debug level (0=off, 1=client, 2=client+server)
     */
    public function enableDebug($level = 2) {
        $this->mail->SMTPDebug = $level;
        
        // Output debug info to browser
        $this->mail->Debugoutput = function($str, $level) {
            echo "<pre style='margin: 5px; padding: 10px; background-color: #f8f8f8; border: 1px solid #ddd; font-family: monospace; font-size: 14px;'>" . htmlspecialchars($str) . "</pre>";
        };
    }
    
    /**
     * Utility method to send a simple verification email
     * 
     * @param string $to Recipient email
     * @param string $name Recipient name
     * @param string $verificationLink Verification link
     * 
     * @return bool Success or failure
     */
    public function sendVerificationEmail($to, $name, $verificationLink) {
        $subject = "Verify Your Pinned Account";
        
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
                    <h2>Hello $name,</h2>
                    <p>Thank you for registering with Pinned! Please verify your email address by clicking the button below:</p>
                    <p style='text-align: center;'>
                        <a href='$verificationLink' class='button'>Verify Email Address</a>
                    </p>
                    <p>If you did not create an account, no further action is required.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Pinned. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $textBody = "Hello $name,\n\nThank you for registering with Pinned! Please verify your email address by visiting the link below:\n\n$verificationLink\n\nIf you did not create an account, no further action is required.\n\n© " . date('Y') . " Pinned. All rights reserved.";
        
        return $this->send($to, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Utility method to send a password reset email
     * 
     * @param string $to Recipient email
     * @param string $name Recipient name
     * @param string $resetLink Password reset link
     * 
     * @return bool Success or failure
     */
    public function sendPasswordResetEmail($to, $name, $resetLink) {
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
                    <h2>Hello $name,</h2>
                    <p>You recently requested to reset your password for your Pinned account. Click the button below to reset it:</p>
                    <p style='text-align: center;'>
                        <a href='$resetLink' class='button'>Reset Password</a>
                    </p>
                    <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
                    <p>This password reset link is only valid for the next 24 hours.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Pinned. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $textBody = "Hello $name,\n\nYou recently requested to reset your password for your Pinned account. Please click the link below to reset it:\n\n$resetLink\n\nIf you did not request a password reset, please ignore this email.\n\nThis password reset link is only valid for the next 24 hours.\n\n© " . date('Y') . " Pinned. All rights reserved.";
        
        return $this->send($to, $subject, $htmlBody, $textBody);
    }
}
?> 