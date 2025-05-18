<?php
/**
 * Email Configuration File
 * 
 * This file contains the configuration settings for sending emails.
 * You can modify these settings based on your provider (SMTP or API).
 */

// Load sensitive credentials from config.secret.php
$secrets = include __DIR__ . '/config.secret.php';

// Email settings
$mail_config = [
    // SMTP or API - Set to 'smtp' for SMTP connections or 'api' for HTTP API
    'method' => 'api',  // Changed from 'use_smtp' => true to 'method' => 'api'
    
    // SMTP Settings (only used if method = 'smtp')
    'smtp' => [
        'host' => $secrets['MAIL_SMTP_HOST'],
        'username' => $secrets['MAIL_SMTP_USERNAME'],
        'password' => $secrets['MAIL_SMTP_PASSWORD'],
        'port' => $secrets['MAIL_SMTP_PORT'],
        'encryption' => $secrets['MAIL_SMTP_ENCRYPTION'],
        'auth' => $secrets['MAIL_SMTP_AUTH'],
    ],
    
    // API Settings (only used if method = 'api')
    'api' => [
        'key' => $secrets['MAIL_API_KEY'],
        'url' => $secrets['MAIL_API_URL'],
    ],
    
    // Sender information - Used for the From header in emails
    'from_email' => $secrets['MAIL_FROM_EMAIL'],
    'from_name' => $secrets['MAIL_FROM_NAME'],
    
    // Debug level (0 = off, 1 = client messages, 2 = client and server messages)
    'debug' => $secrets['MAIL_DEBUG']
];

/**
 * Alternative email solution for environments where mail() and SMTP don't work
 * Useful for development and testing
 */
function log_email_to_file($to, $subject, $body, $headers) {
    $log_dir = 'logs';
    
    // Create logs directory if it doesn't exist
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $filename = $log_dir . '/email_' . date('Ymd_His') . '_' . md5($to . time()) . '.txt';
    
    $content = "Time: $timestamp\n";
    $content .= "To: $to\n";
    $content .= "Subject: $subject\n";
    $content .= "Headers: $headers\n\n";
    $content .= "Body:\n$body\n";
    
    return file_put_contents($filename, $content) !== false;
}
?> 