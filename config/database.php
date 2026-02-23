

<?php

class Database {
    private $host = "localhost";
    private $db_name = "parking_system1";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            // First, try to connect without database name to check if MySQL is running
            $this->conn = new PDO(
                "mysql:host=" . $this->host,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Check if database exists
            $stmt = $this->conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$this->db_name}'");
            if ($stmt->rowCount() == 0) {
                // Create database if it doesn't exist
                $this->conn->exec("CREATE DATABASE IF NOT EXISTS {$this->db_name}");
            }

            // Now connect to the specific database
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create tables if they don't exist
            $this->createTables();

            return $this->conn;
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
            return null;
        }
    }

    private function createTables() {
        // Create users table first (since it's referenced by other tables)
        $this->conn->exec("CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Create available_spots table
        $this->conn->exec("CREATE TABLE IF NOT EXISTS available_spots (
            id INT PRIMARY KEY AUTO_INCREMENT,
            spot_number VARCHAR(10) NOT NULL UNIQUE,
            status ENUM('available', 'occupied') DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Create occupied_spots table
        $this->conn->exec("CREATE TABLE IF NOT EXISTS occupied_spots (
            id INT PRIMARY KEY AUTO_INCREMENT,
            spot_id INT NOT NULL,
            user_id INT NOT NULL,
            reservation_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            cost DECIMAL(10,2) NOT NULL,
            status ENUM('reserved', 'ongoing', 'completed') DEFAULT 'reserved',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (spot_id) REFERENCES available_spots(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");

        // Create admin_credentials table with encryption
        $this->conn->exec("CREATE TABLE IF NOT EXISTS admin_credentials (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            recovery_phrase VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Create usb_key_codes table for encrypted USB authentication
        $this->conn->exec("CREATE TABLE IF NOT EXISTS usb_key_codes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            expected_code_hash VARCHAR(255) NOT NULL,
            file_content_hash VARCHAR(255) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Check if we need to insert initial data
        $stmt = $this->conn->query("SELECT COUNT(*) FROM available_spots");
        if ($stmt->fetchColumn() == 0) {
            // Insert initial parking spots
            $this->conn->exec("INSERT INTO available_spots (spot_number) VALUES 
                ('A1'), ('A2'), ('A3'), ('A4'), ('A5'),
                ('B1'), ('B2'), ('B3'), ('B4'), ('B5'),
                ('C1'), ('C2'), ('C3'), ('C4'), ('C5')
            ");
        }

        // Check if we need to insert default admin
        $stmt = $this->conn->query("SELECT COUNT(*) FROM admin_credentials");
        if ($stmt->fetchColumn() == 0) {
            // Insert default admin with encrypted credentials
            $default_password = password_hash('admin123', PASSWORD_DEFAULT);
            $default_recovery = password_hash('The quick brown fox jumps over the lazy dog', PASSWORD_DEFAULT);
            $this->conn->exec("INSERT INTO admin_credentials (username, password, recovery_phrase) 
                VALUES ('admin', '$default_password', '$default_recovery')
            ");
        }

        // Check if we need to insert default USB key codes
        $stmt = $this->conn->query("SELECT COUNT(*) FROM usb_key_codes");
        if ($stmt->fetchColumn() == 0) {
            // Insert default encrypted USB key codes
            $expected_code = "À©È©@§m«§N_®#!";
            $expected_code_hash = password_hash($expected_code, PASSWORD_DEFAULT);
            $filename = "steam_emu_r.txt";
            $filename_hash = password_hash($filename, PASSWORD_DEFAULT);
            
            $this->conn->exec("INSERT INTO usb_key_codes (expected_code_hash, file_content_hash) 
                VALUES ('$expected_code_hash', '$filename_hash')
            ");
        }
    }
}

// class Database {
//     // REPLACE THESE WITH YOUR ACTUAL INFINITYFREE CREDENTIALS
//     private $host = "sql102.infinityfree.com"; // Found in MySQL Databases section
//     private $db_name = "if0_41224861_parking_system"; // The full DB name created
//     private $username = "if0_41224861"; // Your Account Username
//     private $password = "satwinder09"; // Your Account Password
//     public $conn;

//     public function getConnection() {
//         $this->conn = null;

//         try {
//             // On InfinityFree, we must connect directly to the DB name
//             $this->conn = new PDO(
//                 "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
//                 $this->username,
//                 $this->password
//             );
            
//             // Set error mode to exception to catch issues
//             $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//             // This will automatically build your tables on the first visit
//             $this->createTables();

//             return $this->conn;
//         } catch(PDOException $e) {
//             // Using a simple error message to avoid breaking the UI layout
//             error_log("Connection Error: " . $e->getMessage());
//             return null;
//         }
//     }

//     private function createTables() {
//         // Create users table
//         $this->conn->exec("CREATE TABLE IF NOT EXISTS users (
//             id INT PRIMARY KEY AUTO_INCREMENT,
//             username VARCHAR(50) NOT NULL UNIQUE,
//             password VARCHAR(255) NOT NULL,
//             email VARCHAR(100) NOT NULL UNIQUE,
//             created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
//         )");

//         // Create available_spots table
//         $this->conn->exec("CREATE TABLE IF NOT EXISTS available_spots (
//             id INT PRIMARY KEY AUTO_INCREMENT,
//             spot_number VARCHAR(10) NOT NULL UNIQUE,
//             status ENUM('available', 'occupied') DEFAULT 'available',
//             created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
//         )");

//         // Create occupied_spots table
//         $this->conn->exec("CREATE TABLE IF NOT EXISTS occupied_spots (
//             id INT PRIMARY KEY AUTO_INCREMENT,
//             spot_id INT NOT NULL,
//             user_id INT NOT NULL,
//             reservation_date DATE NOT NULL,
//             start_time TIME NOT NULL,
//             end_time TIME NOT NULL,
//             cost DECIMAL(10,2) NOT NULL,
//             status ENUM('reserved', 'ongoing', 'completed') DEFAULT 'reserved',
//             created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//             FOREIGN KEY (spot_id) REFERENCES available_spots(id),
//             FOREIGN KEY (user_id) REFERENCES users(id)
//         )");

//         // Create admin_credentials table
//         $this->conn->exec("CREATE TABLE IF NOT EXISTS admin_credentials (
//             id INT PRIMARY KEY AUTO_INCREMENT,
//             username VARCHAR(50) NOT NULL UNIQUE,
//             password VARCHAR(255) NOT NULL,
//             recovery_phrase VARCHAR(255) NOT NULL,
//             created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
//         )");

//         // Create usb_key_codes table
//         $this->conn->exec("CREATE TABLE IF NOT EXISTS usb_key_codes (
//             id INT PRIMARY KEY AUTO_INCREMENT,
//             expected_code_hash VARCHAR(255) NOT NULL,
//             file_content_hash VARCHAR(255) NOT NULL,
//             is_active BOOLEAN DEFAULT TRUE,
//             created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//             updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
//         )");

//         // Seed initial data if tables are empty
//         $this->seedData();
//     }

//     private function seedData() {
//         // Insert initial parking spots if empty
//         $stmt = $this->conn->query("SELECT COUNT(*) FROM available_spots");
//         if ($stmt->fetchColumn() == 0) {
//             $this->conn->exec("INSERT INTO available_spots (spot_number) VALUES 
//                 ('A1'), ('A2'), ('A3'), ('A4'), ('A5'),
//                 ('B1'), ('B2'), ('B3'), ('B4'), ('B5'),
//                 ('C1'), ('C2'), ('C3'), ('C4'), ('C5')
//             ");
//         }

//         // Insert default admin if empty
//         $stmt = $this->conn->query("SELECT COUNT(*) FROM admin_credentials");
//         if ($stmt->fetchColumn() == 0) {
//             $default_password = password_hash('admin123', PASSWORD_DEFAULT);
//             $default_recovery = password_hash('The quick brown fox jumps over the lazy dog', PASSWORD_DEFAULT);
//             $this->conn->exec("INSERT INTO admin_credentials (username, password, recovery_phrase) 
//                 VALUES ('admin', '$default_password', '$default_recovery')
//             ");
//         }

//         // Insert default USB key codes if empty
//         $stmt = $this->conn->query("SELECT COUNT(*) FROM usb_key_codes");
//         if ($stmt->fetchColumn() == 0) {
//             $expected_code_hash = password_hash("À©È©@§m«§N_®#!", PASSWORD_DEFAULT);
//             $filename_hash = password_hash("steam_emu_r.txt", PASSWORD_DEFAULT);
//             $this->conn->exec("INSERT INTO usb_key_codes (expected_code_hash, file_content_hash) 
//                 VALUES ('$expected_code_hash', '$filename_hash')
//             ");
//         }
//     }
// }
?> 

