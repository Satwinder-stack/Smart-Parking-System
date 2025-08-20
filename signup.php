<?php
require_once 'config/session.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    $fullname = $_POST['fullname'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    if ($password !== $confirmPassword) {
        $error = "Passwords do not match";
    } else if (!empty($fullname) && !empty($username) && !empty($email) && !empty($password)) {
        // Check if username already exists
        $query = "SELECT id FROM users WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $error = "Username already exists";
        } else {
            // Check if password already exists (by hash)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "SELECT id FROM users WHERE password = :password";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":password", $hashed_password);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $error = "Password already in use. Please choose a different password.";
            } else {
                // Create new user (email can be duplicate)
                $query = "INSERT INTO users (fullname, username, email, password) 
                         VALUES (:fullname, :username, :email, :password)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":fullname", $fullname);
                $stmt->bindParam(":username", $username);
                $stmt->bindParam(":email", $email);
                $stmt->bindParam(":password", $hashed_password);
                if ($stmt->execute()) {
                    $_SESSION['user_id'] = $db->lastInsertId();
                    $_SESSION['username'] = $username;
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "Unable to create user";
                }
            }
        }
    } else {
        $error = "Please fill in all fields";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Smart Parking System</title>

    <link rel="stylesheet" href="css/stylesSignup.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
</head>
<body>
    <div class="signup-container">
        <div class="signup-card">
            <div class="signup-header">
                <img src="images/logo.png" alt="Smart Parking System Logo" id="img1">
                <h1>Create Account</h1>
                <p>Join Smart Parking System</p>
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
                           name="fullname" 
                           class="form-control" 
                           placeholder="Full Name" 
                           required 
                           autocomplete="name"
                           value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>">
                </div>

                <div class="form-group">
                    <i class="fas fa-at"></i>
                    <input type="text" 
                           name="username" 
                           class="form-control" 
                           placeholder="Username" 
                           required 
                           autocomplete="username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>

                <div class="form-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" 
                           name="email" 
                           class="form-control" 
                           placeholder="Email Address" 
                           required 
                           autocomplete="email"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="Password" 
                           required 
                           autocomplete="new-password">
                </div>

                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" 
                           name="confirmPassword" 
                           class="form-control" 
                           placeholder="Confirm Password" 
                           required 
                           autocomplete="new-password">
                </div>

                <button type="submit" class="signup-btn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>

            <div class="auth-links">
                Already have an account? <a href="login.php">Login here</a>
            </div>

            <div class="back-to-home">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
</body>
</html> 