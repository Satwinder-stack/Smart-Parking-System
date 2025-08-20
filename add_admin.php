<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // First, check if the table exists
    $table_check = $db->query("SHOW TABLES LIKE 'admin_credentials'");
    if ($table_check->rowCount() == 0) {
        // Create the table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS admin_credentials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $db->exec($create_table);
        echo "Created admin_credentials table\n";
    }

    // Admin credentials
    $username = 'Admin';
    $password = 'satwinder123';
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    error_log("Generated password hash: " . $hashed_password);

    // Verify the hash works
    if (!password_verify($password, $hashed_password)) {
        throw new Exception("Password hash verification failed!");
    }

    // Check if admin already exists
    $check_query = "SELECT id, username, password FROM admin_credentials WHERE username = :username";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":username", $username);
    $check_stmt->execute();

    if ($check_stmt->rowCount() > 0) {
        $existing_admin = $check_stmt->fetch(PDO::FETCH_ASSOC);
        echo "Admin user already exists!\n";
        echo "ID: " . $existing_admin['id'] . "\n";
        echo "Username: " . $existing_admin['username'] . "\n";
        echo "Current password hash: " . $existing_admin['password'] . "\n";
        
        // Verify if the existing password works
        if (password_verify($password, $existing_admin['password'])) {
            echo "Existing password verification successful!\n";
        } else {
            echo "Existing password verification failed! Updating password...\n";
            
            // Update the password
            $update_query = "UPDATE admin_credentials SET password = :password WHERE username = :username";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(":password", $hashed_password);
            $update_stmt->bindParam(":username", $username);
            
            if ($update_stmt->execute()) {
                echo "Password updated successfully!\n";
            } else {
                echo "Failed to update password.\n";
            }
        }
    } else {
        // Insert new admin
        $query = "INSERT INTO admin_credentials (username, password) VALUES (:username, :password)";
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":password", $hashed_password);
        
        if ($stmt->execute()) {
            echo "Admin user created successfully!\n";
            echo "Username: " . $username . "\n";
            echo "Password hash: " . $hashed_password . "\n";
            
            // Verify the new admin can be retrieved
            $verify_query = "SELECT id, username, password FROM admin_credentials WHERE username = :username";
            $verify_stmt = $db->prepare($verify_query);
            $verify_stmt->bindParam(":username", $username);
            $verify_stmt->execute();
            
            if ($verify_stmt->rowCount() > 0) {
                $new_admin = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                echo "\nVerification:\n";
                echo "Admin retrieved successfully\n";
                echo "ID: " . $new_admin['id'] . "\n";
                echo "Username: " . $new_admin['username'] . "\n";
                echo "Stored password hash: " . $new_admin['password'] . "\n";
                
                if (password_verify($password, $new_admin['password'])) {
                    echo "Password verification successful!\n";
                } else {
                    echo "Password verification failed!\n";
                }
            }
        } else {
            echo "Error creating admin user.\n";
            print_r($stmt->errorInfo());
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Admin Creation Error: " . $e->getMessage());
}
?> 