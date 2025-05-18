<?php 
// Include the configuration file
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title><?php echo isset($page_title) ? $page_title . ' - Pinned' : 'Pinned - Food Stall Discovery'; ?></title>
    <link rel="icon" href="References/Logo/Logo-03.png" type="image/png">
    <link rel="stylesheet" href="public/css/styles.css?v=<?php echo time(); ?>">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Enhanced header and navigation styles */
        header {
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.2);
            padding: 12px 0;
            background-color: var(--primary-color);
        }
        
        .nav-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .brand {
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: transform 0.3s ease;
        }
        
        .brand:hover {
            transform: scale(1.02);
        }
        
        .logo img {
            height: 42px;
            filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.15));
            transition: filter 0.3s ease;
        }
        
        .brand:hover .logo img {
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.25));
        }
        
        .nav-links {
            display: flex;
            gap: 35px;
            margin: 0;
            padding: 0;
        }
        
        .nav-links li {
            margin: 0;
        }
        
        .nav-links a {
            color: white;
            font-weight: 600;
            padding: 10px 18px;
            border-radius: 20px;
            transition: all 0.3s ease;
            position: relative;
            letter-spacing: 0.5px;
        }
        
        .nav-links a:after {
            content: '';
            position: absolute;
            width: 0;
            height: 3px;
            bottom: -5px;
            left: 50%;
            background-color: var(--secondary-color);
            transform: translateX(-50%);
            transition: width 0.3s ease;
            border-radius: 2px;
        }
        
        .nav-links a:hover:after,
        .nav-links a.active:after {
            width: 60%;
        }
        
        .nav-links a.active {
            color: var(--secondary-color);
        }
        
        .nav-links a:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }
        
        .auth-buttons {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: 20px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .welcome-message {
            font-weight: 600;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 30px;
            background-color: rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .welcome-message:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        }
        
        .user-icon {
            color: white;
            background-color: var(--secondary-color);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }

        /* Improved button styles */
        .btn {
            padding: 10px 22px;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
        }
        
        .btn-outline {
            border: 1px solid rgba(255, 255, 255, 0.8);
            color: white;
            background-color: transparent;
            padding: 9px 22px;
        }
        
        .btn-outline:hover {
            background-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            color: var(--dark-bg);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Mobile menu styles - enhanced for touch */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 200;
            width: 44px;
            height: 44px;
            padding: 8px;
            border-radius: 50%;
            transition: background-color 0.2s;
        }
        
        .mobile-menu-btn:active {
            background-color: rgba(255, 255, 255, 0.2);
        }

        /* Alert styles improvement */
        .alert {
            padding: 15px 20px;
            margin: 15px auto;
            border-radius: 8px;
            max-width: 1200px;
            width: 90%;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        
        /* Fix for mobile-only class */
        .mobile-only {
            display: none;
        }

        @media (max-width: 992px) {
            .nav-links {
                gap: 15px;
            }
            
            .logo img {
                height: 38px;
            }
            
            .auth-buttons .btn {
                padding: 8px 16px;
                font-size: 0.9rem;
            }
            
            .nav-links a {
                padding: 8px 15px;
            }
        }

        @media (max-width: 768px) {
            .logo img {
                height: 36px;
            }
            
            .mobile-menu-btn {
                display: block;
                width: 44px;
                height: 44px;
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
            }
            
            .nav-links {
                position: fixed;
                top: 0;
                right: -100%;
                width: 80%;
                max-width: 300px;
                height: 100vh;
                background-color: var(--primary-color);
                flex-direction: column;
                padding: 80px 20px 20px;
                transition: right 0.3s ease;
                z-index: 90;
                box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
                gap: 10px;
            }
            
            .nav-links.show {
                right: 0;
            }
            
            .nav-links li {
                width: 100%;
            }
            
            .nav-links a {
                display: block;
                padding: 15px;
                width: 100%;
                border-radius: 8px;
                transition: background-color 0.2s;
                text-align: left;
            }
            
            .nav-links a:after {
                display: none;
            }
            
            .nav-links a:hover,
            .nav-links a.active {
                background-color: rgba(255, 255, 255, 0.15);
            }
            
            .auth-buttons {
                display: none;
            }
            
            .user-menu {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                width: 100%;
                margin-top: 15px;
            }
            
            .welcome-message {
                width: 100%;
                justify-content: flex-start;
            }
            
            .auth-buttons .btn,
            .user-menu .btn {
                width: 100%;
                justify-content: center;
                margin: 5px 0;
            }
            
            /* Show mobile-only elements */
            .mobile-only {
                display: block;
            }
        }
        
        /* Loading performance */
        @media (max-width: 480px) {
            body {
                contain: content;
            }
            
            img {
                content-visibility: auto;
            }
            
            .logo img {
                height: 32px;
            }
        }
    </style>
</head>
<body>
    <!-- Header Navigation -->
    <header>
        <div class="container">
            <div class="nav-wrapper">
                <a href="index" class="brand">
                    <div class="logo">
                        <img src="/References/Logo/Logo-05.png" alt="Pinned Logo">
                    </div>
                </a>
                
                <nav>
                    <ul class="nav-links">
                        <li><a href="stalls" <?php echo (basename($_SERVER['PHP_SELF']) == 'stalls.php') ? 'class="active"' : ''; ?>>Stalls</a></li>
                        <li><a href="about" <?php echo (basename($_SERVER['PHP_SELF']) == 'about.php') ? 'class="active"' : ''; ?>>About</a></li>
                        <li><a href="map" <?php echo (basename($_SERVER['PHP_SELF']) == 'map.php') ? 'class="active"' : ''; ?>>Map</a></li>
                        
                        <!-- Mobile-only auth links -->
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <li class="mobile-only">
                                <div class="welcome-message">
                                    <div class="user-icon"><i class="fas fa-user"></i></div>
                                    Welcome, <?php echo $_SESSION['user_name']; ?>
                                </div>
                            </li>
                            <li class="mobile-only"><a href="account">My Account</a></li>
                            <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'stall_owner'): 
                                // Check if stall owner has already registered a stall
                                $hasStall = false;
                                try {
                                    if(isset($pdo)) {
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM food_stalls WHERE owner_id = :owner_id");
                                        $stmt->execute(['owner_id' => $_SESSION['user_id']]);
                                        $hasStall = ($stmt->fetchColumn() > 0);
                                    }
                                } catch (Exception $e) {
                                    // Silently fail and assume no stall
                                }
                                
                                if($hasStall): ?>
                                    <li class="mobile-only"><a href="manage-stall">Manage Your Stall</a></li>
                                <?php else: ?>
                                    <li class="mobile-only"><a href="register-stall">Register Your Stall</a></li>
                                <?php endif; ?>
                            <?php endif; ?>
                            <li class="mobile-only"><a href="logout">Logout</a></li>
                        <?php else: ?>
                            <li class="mobile-only"><a href="signin">Sign In</a></li>
                            <li class="mobile-only"><a href="signup">Sign Up</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <button class="mobile-menu-btn">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="auth-buttons">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <div class="user-menu">
                            <div class="welcome-message">
                                <div class="user-icon"><i class="fas fa-user"></i></div>
                                Welcome, <?php echo $_SESSION['user_name']; ?>
                            </div>
                            <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'stall_owner'): 
                                // Check if stall owner has already registered a stall
                                $hasStall = false;
                                try {
                                    if(isset($pdo)) {
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM food_stalls WHERE owner_id = :owner_id");
                                        $stmt->execute(['owner_id' => $_SESSION['user_id']]);
                                        $hasStall = ($stmt->fetchColumn() > 0);
                                    }
                                } catch (Exception $e) {
                                    // Silently fail and assume no stall
                                }
                                
                                if($hasStall): ?>
                                    <a href="manage-stall" class="btn btn-secondary">Manage Your Stall</a>
                                <?php else: ?>
                                    <a href="register-stall" class="btn btn-secondary">Register Your Stall</a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="account" class="btn btn-outline">My Account</a>
                            <a href="logout" class="btn btn-outline">Logout</a>
                        </div>
                    <?php else: ?>
                        <a href="signin" class="btn btn-outline">Sign In</a>
                        <a href="signup" class="btn btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success_message']; 
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>
</body>
</html> 