<?php
$page_title = "Terms of Service";
include 'header.php';
?>

<section class="terms-section">
    <div class="container">
        <div class="terms-container">
            <h1>Terms of Service</h1>
            <p class="last-updated">Last Updated: <?php echo date("F j, Y"); ?></p>
            
            <div class="terms-content">
                <div class="terms-section">
                    <h2>1. Introduction</h2>
                    <p>Welcome to Pinned! These Terms of Service ("Terms") govern your use of our website located at pinned.free.nf (the "Service") operated by Pinned.</p>
                    <p>By accessing or using the Service, you agree to be bound by these Terms. If you disagree with any part of the terms, you may not access the Service.</p>
                </div>
                
                <div class="terms-section">
                    <h2>2. User Accounts</h2>
                    <p>When you create an account with us, you must provide accurate, complete, and current information. Failure to do so constitutes a breach of the Terms, which may result in immediate termination of your account on our Service.</p>
                    <p>You are responsible for safeguarding the password that you use to access the Service and for any activities or actions under your password.</p>
                    <p>You agree not to disclose your password to any third party. You must notify us immediately upon becoming aware of any breach of security or unauthorized use of your account.</p>
                </div>
                
                <div class="terms-section">
                    <h2>3. Content</h2>
                    <p>Our Service allows you to post, link, store, share and otherwise make available certain information, text, graphics, videos, or other material ("Content"). You are responsible for the Content that you post to the Service, including its legality, reliability, and appropriateness.</p>
                    <p>By posting Content to the Service, you grant us the right to use, modify, publicly perform, publicly display, reproduce, and distribute such Content on and through the Service.</p>
                    <p>You represent and warrant that: (i) the Content is yours or you have the right to use it and grant us the rights and license as provided in these Terms, and (ii) the posting of your Content on or through the Service does not violate the privacy rights, publicity rights, copyrights, contract rights or any other rights of any person.</p>
                </div>
                
                <div class="terms-section">
                    <h2>4. Food Stall Reviews</h2>
                    <p>As a user, you may submit reviews for food stalls. All reviews should be honest, accurate, and based on your personal experience.</p>
                    <p>We reserve the right to remove reviews that contain inappropriate content, including but not limited to:</p>
                    <ul>
                        <li>Offensive or discriminatory language</li>
                        <li>Threats or harassment</li>
                        <li>False information intended to damage a stall's reputation</li>
                        <li>Content that violates privacy rights</li>
                        <li>Spam or commercial solicitations</li>
                    </ul>
                </div>
                
                <div class="terms-section">
                    <h2>5. Stall Owner Accounts</h2>
                    <p>If you register as a stall owner, you confirm that you are authorized to represent the food stall business. You must provide accurate information about your food stall.</p>
                    <p>Stall owners are responsible for maintaining updated information about their stalls, including menu items, descriptions, and images.</p>
                </div>
                
                <div class="terms-section">
                    <h2>6. Prohibited Uses</h2>
                    <p>You may use the Service only for lawful purposes and in accordance with these Terms.</p>
                    <p>You agree not to use the Service:</p>
                    <ul>
                        <li>In any way that violates any applicable national or international law or regulation.</li>
                        <li>For the purpose of exploiting, harming, or attempting to exploit or harm minors in any way.</li>
                        <li>To transmit, or procure the sending of, any advertising or promotional material, including any "junk mail", "chain letter," or "spam."</li>
                        <li>To impersonate or attempt to impersonate Pinned, a Pinned employee, another user, or any other person or entity.</li>
                        <li>In any way that infringes upon the rights of others, or in any way is illegal, threatening, fraudulent, or harmful.</li>
                    </ul>
                </div>
                
                <div class="terms-section">
                    <h2>7. Termination</h2>
                    <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach the Terms.</p>
                    <p>Upon termination, your right to use the Service will immediately cease. If you wish to terminate your account, you may simply discontinue using the Service or delete your account.</p>
                </div>
                
                <div class="terms-section">
                    <h2>8. Limitation of Liability</h2>
                    <p>In no event shall Pinned, nor its directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from your access to or use of or inability to access or use the Service.</p>
                </div>
                
                <div class="terms-section">
                    <h2>9. Changes</h2>
                    <p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time. If a revision is material we will try to provide at least 30 days' notice prior to any new terms taking effect. What constitutes a material change will be determined at our sole discretion.</p>
                </div>
                
                <div class="terms-section">
                    <h2>10. Contact Us</h2>
                    <p>If you have any questions about these Terms, please contact us at support@pinned.example.com.</p>
                </div>
            </div>
            
            <div class="terms-footer">
                <a href="signup" class="btn btn-primary">Return to Sign Up</a>
            </div>
        </div>
    </div>
</section>

<style>
    .terms-section {
        padding: 50px 0;
        background-color: #f9f9f9;
    }
    
    .terms-container {
        max-width: 800px;
        margin: 0 auto;
        background-color: white;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .terms-container h1 {
        color: var(--primary-color);
        margin-bottom: 10px;
    }
    
    .last-updated {
        color: #666;
        font-style: italic;
        margin-bottom: 30px;
        font-size: 0.9rem;
    }
    
    .terms-content {
        margin-bottom: 30px;
    }
    
    .terms-section {
        margin-bottom: 30px;
    }
    
    .terms-section h2 {
        color: #333;
        font-size: 1.5rem;
        margin-bottom: 15px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    
    .terms-section p {
        margin-bottom: 15px;
        line-height: 1.6;
    }
    
    .terms-section ul {
        margin-left: 20px;
        margin-bottom: 15px;
    }
    
    .terms-section li {
        margin-bottom: 8px;
        line-height: 1.5;
    }
    
    .terms-footer {
        text-align: center;
        margin-top: 40px;
    }
    
    .btn-primary {
        background-color: var(--primary-color);
        color: white;
        padding: 12px 25px;
        border-radius: 5px;
        text-decoration: none;
        display: inline-block;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
        background-color: #c31212;
    }
</style>

<?php include 'footer.php'; ?> 