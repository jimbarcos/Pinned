# Pinned - Food Stall Directory and Review Platform

Pinned is a web-based platform that connects food enthusiasts with local food stalls. It provides a comprehensive directory of food stalls, user reviews, and interactive features for both stall owners and food enthusiasts.

## Features

- **User Management**
  - User registration and authentication
  - Different user types (food enthusiasts and stall owners)
  - Account management and profile customization

- **Food Stall Directory**
  - Comprehensive listing of food stalls
  - Detailed stall profiles with information about:
    - Location and operating hours
    - Food types and specialties
    - Stall logos and images
    - Owner information

- **Review System**
  - User reviews and ratings
  - Anonymous review option
  - Review management for stall owners

- **Interactive Map**
  - Visual representation of stall locations
  - Easy navigation to find nearby stalls

- **Search and Filter**
  - Search functionality for stalls
  - Filtering options based on food type, location, and ratings

## Technical Stack

- **Backend**: PHP
- **Database**: MySQL
- **Frontend**: HTML, CSS, JavaScript
- **Additional Technologies**:
  - PHPMailer for email functionality
  - SMTP for email delivery
  - SSL for secure connections


## Database Schema

The application uses three main tables:

1. **users**
   - User accounts and authentication
   - User types and profile information

2. **food_stalls**
   - Stall information and details
   - Location and operating hours
   - Owner association

3. **reviews**
   - User reviews and ratings
   - Stall and user associations
   - Review content and metadata

## Setup Instructions

1. **Prerequisites**
   - PHP 7.4 or higher
   - MySQL 5.7 or higher
   - Web server (Apache/Nginx)
   - SSL certificate for secure connections

2. **Installation**
   - Clone the repository
   - Create a MySQL database
   - Import `setup_database.sql` to create the database schema
   - Copy `config.secret.example.php` to `config.secret.php` and update with your credentials
   - Configure your web server to point to the project directory
   - Set up SSL certificate for secure connections

3. **Configuration**
   - Update database credentials in `config.secret.php`
   - Configure email settings in `mail_config.php`
   - Set up SMTP settings for email functionality

4. **Security**
   - Ensure `config.secret.php` is not tracked in version control
   - Set up proper file permissions
   - Enable SSL for secure connections

