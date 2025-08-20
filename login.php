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

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $query = "SELECT id, username, password FROM users WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: login.php?success=1&username=" . urlencode($user['username']));
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "User not found";
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
    <title>Login - Parking System</title>
    <link rel="stylesheet" href="css/stylesLogin.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

</head>
<body>
    <div class="loading-screen" id="loadingScreen">
        <div class="loading-content">
            <img src="images/car login final.gif" alt="Loading" class="loading-gif">
            <div class="loading-text" id="welcomeText">Welcome to Parking System</div>
        </div>
    </div>
    <div class="login-container">
        <div class="info-table-section" id="infoSection">
            <button class="toggle-table" onclick="toggleTable()">Parking Info</button>
            <img src="images/looking for a parking spot.gif" alt="Looking for a parking spot" class="info-gif">
            <div class="info-content">
                <h3>Parking System Information</h3>
                <table class="info-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>First Hour</td>
                            <td>₱50.00</td>
                        </tr>
                        <tr>
                            <td>Overnight (12 AM - 6 AM)</td>
                            <td>₱300.00</td>
                        </tr>
                        <tr>
                            <td>Maximum Daily Rate</td>
                            <td>₱1200.00</td>
                        </tr>
                    </tbody>
                </table>
                <div style="margin-top: 2rem;">
                    <h4>Parking Rules:</h4>
                    <ul style="list-style-type: none; padding: 0;">
                        <li>✓ 24/7 Access</li>
                        <li>✓ Security Cameras</li>
                        <li>✓ Well-lit Parking</li>
                        <li>✓ Reserved Spots Available</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="auth-section">
            <div class="auth-box">
                <h2>Login to Parking System</h2>
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="auth-button" id="auth-button">Login</button>
                </form>
                <p class="auth-links">
                    Don't have an account? <a href="signup.php">Sign up</a>
                </p>
            </div>
        </div>
    </div>

    <script src="js/scriptLogin.js"></script>

</body>
</html> 