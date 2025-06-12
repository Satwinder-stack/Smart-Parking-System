<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check admin credentials
    $query = "SELECT id, username, password FROM admin_credentials WHERE username = 'Admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Admin user found:\n";
        echo "ID: " . $admin['id'] . "\n";
        echo "Username: " . $admin['username'] . "\n";
        echo "Password hash: " . $admin['password'] . "\n";
        
        // Test password verification
        $test_password = 'satwinder123';
        if (password_verify($test_password, $admin['password'])) {
            echo "\nPassword verification successful!";
        } else {
            echo "\nPassword verification failed!";
        }
    } else {
        echo "No admin user found with username 'Admin'";
    }
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?> 