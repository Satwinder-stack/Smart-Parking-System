<?php
require_once 'config/session.php';
requireLogin();

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle parking spot updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spot_id'])) {
    $spot_id = $_POST['spot_id'];
    $action = $_POST['action'] ?? '';

    if ($action === 'occupy') {
        $query = "UPDATE parking_spots SET status = 'occupied', user_id = :user_id, entry_time = NOW() 
                 WHERE id = :spot_id AND status = 'available'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->bindParam(":spot_id", $spot_id);
        $stmt->execute();
    } elseif ($action === 'vacate') {
        $query = "UPDATE parking_spots SET status = 'available', user_id = NULL, exit_time = NOW() 
                 WHERE id = :spot_id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->bindParam(":spot_id", $spot_id);
        $stmt->execute();
    }
}

// Reset all parking spots to available
$query = "UPDATE parking_spots SET status = 'available', user_id = NULL, entry_time = NULL, exit_time = NULL";
$stmt = $db->prepare($query);
$stmt->execute();

// Get parking spots with their current status and reservations
$query = "SELECT a.*, 
          CASE 
              WHEN EXISTS (
                  SELECT 1 FROM occupied_spots o 
                  WHERE o.spot_id = a.id 
                  AND o.status != 'completed'
                  AND (
                      (o.reservation_date = CURDATE() AND CURTIME() BETWEEN o.start_time AND o.end_time)
                      OR (o.reservation_date < CURDATE() AND o.end_date >= CURDATE() AND CURTIME() <= o.end_time)
                      OR (o.reservation_date = CURDATE() AND o.start_time > o.end_time AND CURTIME() >= o.start_time)
                      OR (o.reservation_date = CURDATE() AND o.start_time > o.end_time AND CURTIME() <= o.end_time)
                  )
              ) THEN 'occupied'
              WHEN EXISTS (
                  SELECT 1 FROM occupied_spots o 
                  WHERE o.spot_id = a.id 
                  AND o.status = 'reserved'
                  AND (
                      o.reservation_date > CURDATE()
                      OR (o.reservation_date = CURDATE() AND o.start_time > CURTIME())
                      OR (o.reservation_date < CURDATE() AND o.end_date >= CURDATE())
                  )
              ) THEN 'reserved'
              ELSE 'available'
          END as current_status,
          (
              SELECT GROUP_CONCAT(
                  CONCAT(
                      DATE_FORMAT(o.reservation_date, '%Y-%m-%d'),
                      '|',
                      DATE_FORMAT(o.end_date, '%Y-%m-%d'),
                      '|',
                      TIME_FORMAT(o.start_time, '%H:%i'),
                      '|',
                      TIME_FORMAT(o.end_time, '%H:%i'),
                      '|',
                      CASE 
                          WHEN (o.reservation_date = CURDATE() AND CURTIME() BETWEEN o.start_time AND o.end_time) OR
                               (o.reservation_date < CURDATE() AND o.end_date >= CURDATE() AND CURTIME() <= o.end_time) OR
                               (o.reservation_date = CURDATE() AND o.start_time > o.end_time AND CURTIME() >= o.start_time) OR
                               (o.reservation_date = CURDATE() AND o.start_time > o.end_time AND CURTIME() <= o.end_time)
                          THEN 'ongoing'
                          ELSE o.status
                      END
                  ) SEPARATOR '||'
              )
              FROM occupied_spots o
              WHERE o.spot_id = a.id
              AND o.status IN ('reserved', 'ongoing')
              AND (
                  o.reservation_date > CURDATE()
                  OR (o.reservation_date = CURDATE() AND o.end_time > CURTIME())
                  OR (o.reservation_date < CURDATE() AND o.end_date >= CURDATE())
                  OR (o.reservation_date = CURDATE() AND o.start_time > o.end_time)
              )
          ) as reservations
          FROM available_spots a
          ORDER BY CAST(SUBSTRING(a.spot_number, 2) AS UNSIGNED)";
$stmt = $db->prepare($query);
$stmt->execute();
$parking_spots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug logging
error_log("Parking spots data: " . print_r($parking_spots, true));

// Calculate available spots count
$total_spots = count($parking_spots);
$available_spots = 0;
foreach ($parking_spots as $spot) {
    if ($spot['current_status'] === 'available') {
        $available_spots++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Parking System Management">
    <meta name="keywords" content="parking, system, management">
    <meta name="author" content="Your Name">
    <title>Smart Parking System</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <!-- CSS -->
    <link rel="stylesheet" href="css/stylesIndex.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav class="navbar">
            <a href="index.php" class="nav-brand">
                <img src="images/logo.png" alt="Smart Parking System Logo" class="nav-logo">
                <span>Smart Parking System</span>
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link active">Home</a>
                <a href="reservation.php" class="nav-link">Reserve Parking</a>
                <a href="profile.php" class="nav-link">Profile</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </div>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Parking Dashboard</h1>
            <p class="welcome-message">Welcome to the Smart Parking System</p>
        </div>

        <div class="parking-layout">
            <div class="parking-info">
                <div class="info-card">
                    <h3>Available Spots</h3>
                    <p class="count"><?php echo $available_spots; ?></p>
                </div>
                <div class="info-card">
                    <h3>Total Spots</h3>
                    <p class="count"><?php echo $total_spots; ?></p>
                </div>
                <div class="info-card">
                    <h3>Find Nearest Time</h3>
                    <div class="button-group">
                        <button id="findVacantSpots" class="find-time-btn">Find Vacant Spots</button>
                        <button id="findNearestTime" class="find-time-btn">Find Available Time</button>
                    </div>
                </div>
            </div>

            <div class="parking-grid">
                <div class="parking-row">
                    <?php
                    $first_row = array_slice($parking_spots, 0, 7);
                    foreach ($first_row as $spot): ?>
                        <div class="parking-spot <?php echo $spot['current_status']; ?>"
                             data-reservations="<?php echo htmlspecialchars($spot['reservations'] ?? ''); ?>">
                            <span class="spot-number"><?php echo htmlspecialchars($spot['spot_number']); ?></span>
                            <span class="spot-status"><?php echo ucfirst($spot['current_status']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="road">
                    <div class="road-markings"></div>
                </div>

                <div class="parking-row">
                    <?php
                    $second_row = array_slice($parking_spots, 7);
                    foreach ($second_row as $spot): ?>
                        <div class="parking-spot <?php echo $spot['current_status']; ?>"
                             data-reservations="<?php echo htmlspecialchars($spot['reservations'] ?? ''); ?>">
                            <span class="spot-number"><?php echo htmlspecialchars($spot['spot_number']); ?></span>
                            <span class="spot-status"><?php echo ucfirst($spot['current_status']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <p>Smart parking solutions for modern cities. Making parking easier, one spot at a time.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <p><a href="index.php">Home</a></p>
                <p><a href="reservation.php">Reserve Parking</a></p>
                <p><a href="profile.php">My Profile</a></p>
            </div>
            <div class="footer-section">
                <h3>Contact</h3>
                <p>Email: srjeerh09@gmail.com</p>
                <p>Phone: (123) 456-7890</p>
                <p>Address: 123 Parking Street, City</p>
            </div>
            <div class="footer-section">
                <h3>Developer</h3>
                <p><strong>Developer:</strong> Satwinder Jeerh</p>
                <p><strong>Role:</strong> Full Stack Developer</p>
                <p><strong>Specialization:</strong> Web Development, UI/UX Designer, Frontend Designer, Backend Developer</p>
                <div class="developer-links">
                    <a href="https://github.com/Satwinder-stack" target="_blank" class="dev-link">
                        <i class="fab fa-github"></i> GitHub
                    </a>
                    <a href="https://www.linkedin.com/in/satwinder-jeerh-120331322/" target="_blank" class="dev-link">
                        <i class="fab fa-linkedin"></i> LinkedIn
                    </a>
                    <a href="" target="_blank" class="dev-link">
                        <i class="fas fa-globe"></i> Portfolio
                    </a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Other Projects</h3>
                <p><a href="https:" target="_blank">E-Commerce Platform</a></p>
                <p><a href="https:" target="_blank">Task Management System</a></p>
                <p><a href="https:" target="_blank">Weather Dashboard</a></p>
            </div>
        </div>
        <div class="social-links">
            <a href="#" class="social-link">📱</a>
            <a href="#" class="social-link">💬</a>
            <a href="#" class="social-link">📧</a>
        </div>
        <div class="footer-bottom">
            <p>Parking System Management © <?php echo date('Y'); ?> | All rights reserved</p>
            <p class="developer-credit">Developed with ❤️ by <a href="https://" target="_blank">Satwinder Jeerh</a></p>
        </div>
    </footer>

    <!-- Sliding Panels -->
    <div class="panel-overlay" id="panelOverlay"></div>
    
    <!-- Reservation Details Panel -->
    <div id="reservationPanel" class="sliding-panel">
        <div class="sliding-panel-header">
            <h2>Reservation Details</h2>
            <button class="sliding-panel-close">&times;</button>
        </div>
        <div class="sliding-panel-content">
            <div id="spotNumber" class="spot-header"></div>
            <div class="reservations-list" id="reservationsList"></div>
        </div>
    </div>

    <!-- Nearest Time Panel -->
    <div id="nearestTimePanel" class="sliding-panel">
        <div class="sliding-panel-header">
            <h2>Nearest Available Time</h2>
            <button class="sliding-panel-close">&times;</button>
        </div>
        <div class="sliding-panel-content" id="nearestTimeResult"></div>
    </div>

    <!-- Vacant Spots Panel -->
    <div id="vacantSpotsPanel" class="sliding-panel">
        <div class="sliding-panel-header">
            <h2>Currently Vacant Spots</h2>
            <button class="sliding-panel-close">&times;</button>
        </div>
        <div class="sliding-panel-content" id="vacantSpotsResult"></div>
    </div>

    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <script src="js/scriptIndex.js"></script>

</body>
</html> 