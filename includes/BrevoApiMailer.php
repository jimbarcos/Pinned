<?php
/**
 * Brevo API Mailer Class for Pinned
 * 
 * This class provides email functionality using Brevo's HTTP API
 * instead of SMTP, useful for hosting environments that block SMTP.
 */

class BrevoApiMailer {
    private $apiKey;
    private $apiUrl = 'https://api.brevo.com/v3/smtp/email';
    private $fromEmail;
    private $fromName;
    private $error = '';
    
    /**
     * Constructor - initialize with API key
     * 
     * @param string $apiKey Brevo API key
     * @param string $fromEmail Sender email address
     * @param string $fromName Sender name
     */
    public function __construct($apiKey, $fromEmail, $fromName) {
        $this->apiKey = $apiKey;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }
    
    /**
     * Send an email using Brevo API
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
        if (empty($this->apiKey)) {
            $this->error = "API key is not set";
            return false;
        }
        
        // Format recipients
        $recipients = [];
        if (is_array($to)) {
            foreach ($to as $email) {
                $recipients[] = ['email' => $email];
            }
        } else {
            $recipients[] = ['email' => $to];
        }
        
        // Format CC recipients
        $ccRecipients = [];
        if (!empty($cc)) {
            foreach ($cc as $email) {
                $ccRecipients[] = ['email' => $email];
            }
        }
        
        // Format BCC recipients
        $bccRecipients = [];
        if (!empty($bcc)) {
            foreach ($bcc as $email) {
                $bccRecipients[] = ['email' => $email];
            }
        }
        
        // Prepare email data
        $data = [
            'sender' => [
                'name' => $this->fromName,
                'email' => $this->fromEmail
            ],
            'to' => $recipients,
            'subject' => $subject,
            'htmlContent' => $htmlBody
        ];
        
        // Add text content if provided
        if (!empty($textBody)) {
            $data['textContent'] = $textBody;
        }
        
        // Add CC if provided
        if (!empty($ccRecipients)) {
            $data['cc'] = $ccRecipients;
        }
        
        // Add BCC if provided
        if (!empty($bccRecipients)) {
            $data['bcc'] = $bccRecipients;
        }
        
        // Add attachments if provided
        if (!empty($attachments)) {
            $data['attachment'] = [];
            foreach ($attachments as $filepath) {
                if (file_exists($filepath)) {
                    $content = base64_encode(file_get_contents($filepath));
                    $name = basename($filepath);
                    $data['attachment'][] = [
                        'content' => $content,
                        'name' => $name
                    ];
                }
            }
        }
        
        // Convert data to JSON
        $jsonData = json_encode($data);
        
        // Check if cURL is available
        if (!function_exists('curl_init')) {
            $this->error = "cURL extension is not available. Please enable it to use the API.";
            return false;
        }
        
        // Initialize cURL
        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            $this->error = "Failed to initialize cURL";
            return false;
        }
        
        // Create a logs directory for debugging
        $log_dir = 'logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        // Enable verbose debugging
        $verbose = fopen($log_dir . '/curl_verbose_' . date('Ymd_His') . '.txt', 'w+');
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        // Log the request
        file_put_contents(
            $log_dir . '/api_request_' . date('Ymd_His') . '.txt',
            "URL: " . $this->apiUrl . "\n" .
            "API Key: " . substr($this->apiKey, 0, 10) . "..." . substr($this->apiKey, -5) . "\n" .
            "Data: " . $jsonData
        );
        
        // Set cURL options
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $this->apiKey
        ]);
        
        // Set SSL options - disable verification if needed
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Set timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Execute cURL request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Log response
        file_put_contents(
            $log_dir . '/api_response_' . date('Ymd_His') . '.txt',
            "HTTP Code: " . $httpCode . "\n" .
            "Response: " . ($response ? $response : "Empty response")
        );
        
        // Check for errors
        if (curl_errno($ch)) {
            $this->error = 'cURL error (' . curl_errno($ch) . '): ' . curl_error($ch);
            curl_close($ch);
            fclose($verbose);
            return false;
        }
        
        // Close cURL
        curl_close($ch);
        fclose($verbose);
        
        // Process response
        $responseData = json_decode($response, true);
        
        // Check HTTP status code
        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        } else {
            if (is_array($responseData) && isset($responseData['message'])) {
                $this->error = 'API error: ' . $responseData['message'];
            } else if (is_array($responseData) && isset($responseData['error'])) {
                $this->error = 'API error: ' . $responseData['error'];
            } else {
                $this->error = 'API error: HTTP code ' . $httpCode . ' - ' . ($response ?: 'No response body');
            }
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
     * Enable debug mode - does nothing for API calls but included for compatibility
     */
    public function enableDebug($level = 2) {
        // This is just a stub for compatibility with the SMTP mailer class
        return true;
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