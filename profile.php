<?php
require_once 'config/session.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Get user data
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Normalize profile photo path
if (isset($user['profile_photo']) && $user['profile_photo']) {
    // Convert backslashes to forward slashes
    $user['profile_photo'] = str_replace('\\', '/', $user['profile_photo']);
    // Remove any leading slashes to make it relative
    $user['profile_photo'] = ltrim($user['profile_photo'], '/');
    
    // Check if the file exists using absolute path
    $absolute_path = __DIR__ . '/' . $user['profile_photo'];
    if (!file_exists($absolute_path)) {
        // If file doesn't exist, clear the profile photo
        $user['profile_photo'] = null;
        
        // Update database to clear the profile photo
        $update_query = "UPDATE users SET profile_photo = NULL WHERE id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(":user_id", $_SESSION['user_id']);
        $update_stmt->execute();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug log
    error_log("POST request received");
    
    // Handle profile photo upload first
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        error_log("Photo upload detected");
        
        // Check if this is a duplicate submission
        if (isset($_SESSION['last_upload']) && $_SESSION['last_upload'] === $_FILES['profile_photo']['name']) {
            error_log("Duplicate upload detected - redirecting");
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        
        $upload_dir = 'uploads/profile_photos/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid('profile_') . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                error_log("File moved successfully to: " . $upload_path);
                
                // Delete old photo if exists
                if (isset($user['profile_photo']) && $user['profile_photo'] && file_exists($user['profile_photo'])) {
                    unlink($user['profile_photo']);
                    error_log("Old photo deleted: " . $user['profile_photo']);
                }

                // Update only the profile photo
                $query = "UPDATE users SET profile_photo = :profile_photo WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":profile_photo", $upload_path);
                $stmt->bindParam(":user_id", $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    error_log("Database updated successfully");
                    // Store the upload info in session to prevent duplicates
                    $_SESSION['last_upload'] = $_FILES['profile_photo']['name'];
                    $_SESSION['success'] = "Profile photo updated successfully!";
                    
                    // Clear any existing output
                    if (ob_get_length()) ob_clean();
                    
                    // Redirect with a unique parameter to prevent caching
                    header("Location: " . $_SERVER['PHP_SELF'] . "?t=" . time());
                    exit();
                } else {
                    error_log("Database update failed");
                    $error = "Failed to update profile photo in database";
                }
            } else {
                error_log("Failed to move uploaded file");
                $error = "Failed to upload photo";
            }
        } else {
            error_log("Invalid file type: " . $file_extension);
            $error = "Invalid file type. Allowed types: " . implode(', ', $allowed_extensions);
        }
    }

    // Handle other form submissions
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $vehicle_number = $_POST['vehicle_number'] ?? '';
    $vehicle_type = $_POST['vehicle_type'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Update basic info
    if (!empty($fullname) && !empty($email)) {
        try {
            // Hash sensitive data
            $phone_hash = !empty($phone) ? password_hash($phone, PASSWORD_DEFAULT) : null;
            $address_hash = !empty($address) ? password_hash($address, PASSWORD_DEFAULT) : null;
            $vehicle_number_hash = !empty($vehicle_number) ? password_hash($vehicle_number, PASSWORD_DEFAULT) : null;
            $vehicle_type_hash = !empty($vehicle_type) ? password_hash($vehicle_type, PASSWORD_DEFAULT) : null;

            $query = "UPDATE users SET 
                      fullname = :fullname, 
                      email = :email,
                      phone = :phone,
                      address = :address,
                      vehicle_number = :vehicle_number,
                      vehicle_type = :vehicle_type
                      WHERE id = :user_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":fullname", $fullname);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":phone", $phone_hash);
            $stmt->bindParam(":address", $address_hash);
            $stmt->bindParam(":vehicle_number", $vehicle_number_hash);
            $stmt->bindParam(":vehicle_type", $vehicle_type_hash);
            $stmt->bindParam(":user_id", $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success = "Profile updated successfully!";
                // Refresh user data
                $query = "SELECT * FROM users WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":user_id", $_SESSION['user_id']);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "Failed to update profile";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }

    // Update password if provided
    if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
        if ($new_password !== $confirm_password) {
            $error = "New passwords do not match";
        } else {
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                // Check if new password is already in use
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "SELECT id FROM users WHERE password = :password AND id != :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":password", $hashed_new_password);
                $stmt->bindParam(":user_id", $_SESSION['user_id']);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error = "This password is already in use. Please choose a different one.";
                } else {
                    // Update password
                    $query = "UPDATE users SET password = :password WHERE id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":password", $hashed_new_password);
                    $stmt->bindParam(":user_id", $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $success = "Password updated successfully!";
                    } else {
                        $error = "Failed to update password";
                    }
                }
            } else {
                $error = "Current password is incorrect";
            }
        }
    }
}

// Check for session messages
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Add this function at the top of the file after the require statements
function verifyHashedData($hashed_value, $input_value) {
    if (empty($hashed_value) || empty($input_value)) {
        return false;
    }
    return password_verify($input_value, $hashed_value);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Parking System</title>
    <link rel="stylesheet" href="css/stylesProfile.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav class="navbar">
            <a href="index.php" class="nav-brand">
                <img src="images/logo.png" alt="Smart Parking System Logo" class="nav-logo">
                <span class="nav-brand-text">SMART PARKING SYSTEM</span>
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link">Home</a>
                <a href="reservation.php" class="nav-link">Reserve Parking</a>
                <a href="profile.php" class="nav-link active">Profile</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </div>
        </nav>
    </header>
    <div class="profile-container" id="profile-container">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if (isset($user['profile_photo']) && $user['profile_photo'] && file_exists($user['profile_photo'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Photo">
                <?php else: ?>
                    <div class="default-icon">
                        <img src="images/icon.png" alt="Default Profile">
                    </div>
                <?php endif; ?>
                <label for="profile-photo-input" class="upload-overlay">Change Photo</label>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($user['fullname'] ?? ''); ?></h2>
                <p>@<?php echo htmlspecialchars($user['username'] ?? ''); ?></p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="profile-section">
            <h3>Personal Information</h3>
            <form method="POST" class="auth-form" enctype="multipart/form-data" id="profileForm">
                <input type="file" id="profile-photo-input" name="profile_photo" accept="image/*" style="display: none;">
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" class="text-color" style="color: white;" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="text-color" style="color: white;" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="text-color" style="color: white;" 
                           value="<?php echo !empty($user['phone']) && verifyHashedData($user['phone'], $user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="text-color" style="color: white;" rows="3"><?php 
                        echo !empty($user['address']) && verifyHashedData($user['address'], $user['address']) ? 
                             htmlspecialchars($user['address']) : ''; 
                    ?></textarea>
                </div>
                <div class="form-group">
                    <label for="vehicle_number">Vehicle Number</label>
                    <input type="text" id="vehicle_number" name="vehicle_number" class="text-color" style="color: white;" 
                           value="<?php echo !empty($user['vehicle_number']) && verifyHashedData($user['vehicle_number'], $user['vehicle_number']) ? 
                                        htmlspecialchars($user['vehicle_number']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="vehicle_type">Vehicle Type</label>
                    <input type="text" id="vehicle_type" name="vehicle_type" class="text-color" style="color: white;" 
                           value="<?php echo !empty($user['vehicle_type']) && verifyHashedData($user['vehicle_type'], $user['vehicle_type']) ? 
                                        htmlspecialchars($user['vehicle_type']) : ''; ?>">
                </div>
                <button type="submit" class="auth-button">Update Profile</button>
            </form>
        </div>

        <div class="profile-section">
            <h3>Change Password</h3>
            <form method="POST" class="auth-form" id="password-form">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" style="color: black;">
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" style="color: black;">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" style="color: black;">
                </div>
                <button type="submit" class="auth-button">Change Password</button>
            </form>
        </div>

        <div class="auth-links">
            <a href="index.php">Back to Dashboard</a>
        </div>
    </div>
        
    <script src="js/scriptProfile.js"></script>
</body>
</html> 