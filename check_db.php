<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include configuration file to connect to the database
require_once 'config.php';

echo "<h1>Database Structure Check</h1>";

try {
    if (!isset($pdo)) {
        throw new Exception("Database connection not available");
    }
    
    // Check if reviews table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'reviews'");
    $reviewsTableExists = $stmt->rowCount() > 0;
    
    echo "<h2>Reviews Table</h2>";
    if ($reviewsTableExists) {
        echo "<p style='color: green;'>✓ Reviews table exists</p>";
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE reviews");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Check for foreign keys
        $stmt = $pdo->query("
            SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = 'reviews' AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($foreignKeys) > 0) {
            echo "<h3>Foreign Keys:</h3>";
            echo "<ul>";
            foreach ($foreignKeys as $fk) {
                echo "<li>" . htmlspecialchars($fk['COLUMN_NAME']) . " references " . 
                     htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . "." . 
                     htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>✗ No foreign keys defined for reviews table</p>";
        }
        
        // Check if there are any records
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM reviews");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>Total reviews: " . $count . "</p>";
        
    } else {
        echo "<p style='color: red;'>✗ Reviews table does not exist</p>";
        
        // Show SQL to create the table
        echo "<h3>SQL to create reviews table:</h3>";
        echo "<pre>";
        echo "CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stall_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (stall_id) REFERENCES food_stalls(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (stall_id, user_id)
);";
        echo "</pre>";
    }
    
    // Check food_stalls table
    $stmt = $pdo->query("SHOW TABLES LIKE 'food_stalls'");
    $stallsTableExists = $stmt->rowCount() > 0;
    
    echo "<h2>Food Stalls Table</h2>";
    if ($stallsTableExists) {
        echo "<p style='color: green;'>✓ food_stalls table exists</p>";
        
        // Check if the id column exists in the food_stalls table
        $stmt = $pdo->query("DESCRIBE food_stalls");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $idColumnExists = false;
        
        foreach ($columns as $column) {
            if ($column['Field'] === 'id') {
                $idColumnExists = true;
                break;
            }
        }
        
        if ($idColumnExists) {
            echo "<p style='color: green;'>✓ id column exists in food_stalls table</p>";
        } else {
            echo "<p style='color: red;'>✗ id column does not exist in food_stalls table</p>";
        }
        
        // Show sample data
        $stmt = $pdo->query("SELECT id, name FROM food_stalls LIMIT 5");
        $stalls = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($stalls) > 0) {
            echo "<h3>Sample Stalls:</h3>";
            echo "<ul>";
            foreach ($stalls as $stall) {
                echo "<li>ID: " . htmlspecialchars($stall['id']) . " - " . htmlspecialchars($stall['name']) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No food stalls found in the database.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ food_stalls table does not exist</p>";
    }
    
    // Check users table
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersTableExists = $stmt->rowCount() > 0;
    
    echo "<h2>Users Table</h2>";
    if ($usersTableExists) {
        echo "<p style='color: green;'>✓ users table exists</p>";
        
        // Check if the id column exists in the users table
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $idColumnExists = false;
        
        foreach ($columns as $column) {
            if ($column['Field'] === 'id') {
                $idColumnExists = true;
                break;
            }
        }
        
        if ($idColumnExists) {
            echo "<p style='color: green;'>✓ id column exists in users table</p>";
        } else {
            echo "<p style='color: red;'>✗ id column does not exist in users table</p>";
        }
        
        // Show count of users
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>Total users: " . $count . "</p>";
        
    } else {
        echo "<p style='color: red;'>✗ users table does not exist</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
    echo "<h3>Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?> 