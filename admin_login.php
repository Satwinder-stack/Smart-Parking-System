<?php
require_once 'config/database.php';
session_start();

// USB Checker Function
function checkUSBKey() {
    // Get encrypted codes from database
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Get the active USB key codes from database (cached query)
        $query = "SELECT expected_code_hash, file_content_hash FROM usb_key_codes WHERE is_active = TRUE ORDER BY id DESC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            error_log("No active USB key codes found in database");
            return false;
        }
        
        $usbKeyData = $stmt->fetch(PDO::FETCH_ASSOC);
        $expectedCodeHash = $usbKeyData['expected_code_hash'];
        $fileContentHash = $usbKeyData['file_content_hash'];
        
        // Optimized drive detection - only check common USB drive letters
        $drives = [];
        $commonDrives = ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        
        foreach ($commonDrives as $letter) {
            $drive = $letter . ':';
            if (is_dir($drive) && is_readable($drive)) {
                $drives[] = $drive;
            }
        }
        
        // Check each drive for steam_emu_r.txt
        foreach ($drives as $drive) {
            $result = searchForKeyFile($drive, $expectedCodeHash, $fileContentHash);
            if ($result) {
                return true;
            }
        }
        
    } catch (PDOException $e) {
        error_log("Database error in USB key verification: " . $e->getMessage());
    }
    
    error_log("No valid USB key found");
    return false;
}

// Optimized function to search for steam_emu_r.txt files
function searchForKeyFile($path, $expectedCodeHash, $fileContentHash) {
    if (!is_dir($path) || !is_readable($path)) {
        return false;
    }
    
    try {
        // Get all files in the directory
        $items = scandir($path);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $fullPath = $path . '/' . $item;
            
            if (is_file($fullPath)) {
                // Verify filename hash against database stored hash
                if (verifyFilenameHash($item, $fileContentHash)) {
                    $content = trim(file_get_contents($fullPath));
                    
                    // Check if any of the expected codes exist within the content
                    if (verifyExpectedCodeInContent($content, $expectedCodeHash)) {
                        error_log("USB key verification successful at: " . $fullPath);
                        return true;
                    } else {
                        error_log("USB key found but code doesn't match at: " . $fullPath);
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error scanning directory: " . $path . " - " . $e->getMessage());
    }
    
    return false;
}

// Function to verify filename hash against database stored hash
function verifyFilenameHash($filename, $fileContentHash) {
    // Verify the filename against the stored hash
    return password_verify($filename, $fileContentHash);
}

// Optimized function to verify expected code in content using database hash
function verifyExpectedCodeInContent($content, $expectedCodeHash) {
    // Check if content contains any of the common patterns we expect
    $patterns = [
        "À©È©@§m«§N_®#!",
        "ADMIN_ACCESS",
        "ACCESS_CODE"
    ];
    
    foreach ($patterns as $pattern) {
        if (strpos($content, $pattern) !== false) {
            // If we find a pattern, verify it against the stored hash
            if (password_verify($pattern, $expectedCodeHash)) {
                return true;
            }
        }
    }
    
    return false;
}

// Check if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: admin.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Debug information
    error_log("Login attempt - Username: " . $username);

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        try {
            // First, let's check if the table exists
            $table_check = $db->query("SHOW TABLES LIKE 'admin_credentials'");
            if ($table_check->rowCount() == 0) {
                error_log("Table 'admin_credentials' does not exist!");
                $error = "System configuration error. Please contact administrator.";
            } else {
                $query = "SELECT id, username, password FROM admin_credentials WHERE username = :username";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":username", $username);
                $stmt->execute();

                error_log("Query executed. Rows found: " . $stmt->rowCount());

                if ($stmt->rowCount() > 0) {
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                    error_log("Admin found - ID: " . $admin['id'] . ", Username: " . $admin['username']);
                    
                    // Debug password verification
                    $verify_result = password_verify($password, $admin['password']);
                    error_log("Password verification result: " . ($verify_result ? "true" : "false"));
                    error_log("Input password: " . $password);
                    error_log("Stored hash: " . $admin['password']);

                    if ($verify_result) {
                        // Check USB key before allowing login
                        if (checkUSBKey()) {
                            // Set admin session variables
                            $_SESSION['admin_id'] = $admin['id'];
                            $_SESSION['admin_username'] = $admin['username'];
                            $_SESSION['is_admin'] = true;
                            
                            error_log("Login successful for user: " . $admin['username']);
                            
                            // Redirect to admin.php
                            header("Location: admin.php");
                            exit();
                        } else {
                            $error = "Access denied. Please insert the authorized USB key with valid authentication file.";
                            error_log("USB key verification failed for user: " . $username);
                        }
                    } else {
                        $error = "Invalid username or password";
                        error_log("Password verification failed for user: " . $username);
                    }
                } else {
                    $error = "Invalid username or password";
                    error_log("No user found with username: " . $username);
                }
            }
        } catch (PDOException $e) {
            $error = "Database error occurred. Please try again.";
            error_log("Admin Login Error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Smart Parking System</title>

    <link rel="stylesheet" href="css/stylesAdminLogin.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="images/logo.png" alt="Smart Parking System Logo">
                <h1>Admin Login</h1>
                <p>Smart Parking System Management</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <i class="fas fa-user"></i>
                    <input type="text" 
                           name="username" 
                           class="form-control" 
                           placeholder="Username" 
                           required 
                           autocomplete="username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>

                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="Password" 
                           required 
                           autocomplete="current-password">
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div class="back-to-home">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
</body>
</html> 