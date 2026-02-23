<?php
date_default_timezone_set('Asia/Manila'); // Add this at the very top
require_once 'config/session.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Add end_date column if it doesn't exist
try {
    $check_column = "SHOW COLUMNS FROM occupied_spots LIKE 'end_date'";
    $stmt = $db->prepare($check_column);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // Column doesn't exist, add it
        $add_column = "ALTER TABLE occupied_spots ADD COLUMN end_date DATE NOT NULL AFTER reservation_date";
        $db->exec($add_column);
        
        // Update existing records to use reservation_date as end_date
        $update_dates = "UPDATE occupied_spots SET end_date = reservation_date";
        $db->exec($update_dates);
    }
} catch (PDOException $e) {
    // Log error but continue execution
    error_log("Error adding end_date column: " . $e->getMessage());
}

$error = '';
$success = '';

// Check for session messages
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']); // Clear the message after displaying
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']); // Clear the message after displaying
}

// Get all available spots
$query = "SELECT a.* FROM available_spots a
          ORDER BY CAST(SUBSTRING(a.spot_number, 2) AS UNSIGNED)";
$stmt = $db->prepare($query);
$stmt->execute();
$available_spots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $spot_id = $_POST['spot_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $end_date = $_POST['end_date'] ?? '';

    // Debug logging
    error_log("POST data: " . print_r($_POST, true));
    error_log("End date: " . $end_date);

    if (empty($spot_id) || empty($date) || empty($start_time) || empty($end_time) || empty($end_date)) {
        $error = "All fields are required";
    } else {
        // Calculate duration and cost
        $start = new DateTime($date . ' ' . $start_time);
        $end = new DateTime($end_date . ' ' . $end_time);
        
        // Debug logging
        error_log("Start datetime: " . $start->format('Y-m-d H:i:s'));
        error_log("End datetime: " . $end->format('Y-m-d H:i:s'));
        
        // Validate time range
        if ($end <= $start) {
            $error = "End time must be after start time";
        } else {
            // Calculate total hours including minutes
            $interval = $start->diff($end);
            $total_hours = $interval->h + ($interval->days * 24) + ($interval->i / 60);
            
            // Round up to the nearest hour
            $total_hours = ceil($total_hours);
            
            // Cost calculation (₱50 per hour)
            $cost = $total_hours * 50;

            try {
                // Check for time conflicts
                $conflict_query = "SELECT COUNT(*) as conflict_count 
                                 FROM occupied_spots 
                                 WHERE spot_id = :spot_id 
                                 AND status IN ('reserved', 'ongoing')
                                 AND (
                                     (reservation_date <= :end_date AND end_date >= :date)
                                     AND (
                                         (TIME_TO_SEC(start_time) - 600 <= TIME_TO_SEC(:end_time) AND TIME_TO_SEC(end_time) + 600 >= TIME_TO_SEC(:start_time))
                                         OR (TIME_TO_SEC(start_time) BETWEEN TIME_TO_SEC(:start_time) - 600 AND TIME_TO_SEC(:end_time) + 600)
                                         OR (TIME_TO_SEC(end_time) BETWEEN TIME_TO_SEC(:start_time) - 600 AND TIME_TO_SEC(:end_time) + 600)
                                     )
                                 )";
                
                $conflict_stmt = $db->prepare($conflict_query);
                $conflict_stmt->bindParam(":spot_id", $spot_id);
                $conflict_stmt->bindParam(":date", $date);
                $conflict_stmt->bindParam(":end_date", $end_date);
                $conflict_stmt->bindParam(":start_time", $start_time);
                $conflict_stmt->bindParam(":end_time", $end_time);
                $conflict_stmt->execute();
                $conflict_result = $conflict_stmt->fetch(PDO::FETCH_ASSOC);

                // Debug logging
                error_log("Conflict check result: " . print_r($conflict_result, true));

                if ($conflict_result['conflict_count'] > 0) {
                    $_SESSION['error'] = "This parking spot is already reserved for the selected time period or within 10 minutes of another reservation. Please choose a different time or spot.";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    // Start transaction
                    $db->beginTransaction();

                    try {
                        // Create occupied spot record
                        $occupied_query = "INSERT INTO occupied_spots 
                            (spot_id, user_id, reservation_date, end_date, start_time, end_time, cost, status) 
                            VALUES 
                            (:spot_id, :user_id, :reservation_date, :end_date, :start_time, :end_time, :cost, 'reserved')";
                        
                        $stmt = $db->prepare($occupied_query);
                        $user_id = $_SESSION['user_id'];
                        
                        $stmt->bindParam(":spot_id", $spot_id);
                        $stmt->bindParam(":user_id", $user_id);
                        $stmt->bindParam(":reservation_date", $date);
                        $stmt->bindParam(":end_date", $end_date);
                        $stmt->bindParam(":start_time", $start_time);
                        $stmt->bindParam(":end_time", $end_time);
                        $stmt->bindParam(":cost", $cost);
                        
                        // Debug logging
                        error_log("Executing insert with end_date: " . $end_date);
                        error_log("SQL Query: " . $occupied_query);
                        error_log("Parameters: " . print_r([
                            'spot_id' => $spot_id,
                            'user_id' => $user_id,
                            'reservation_date' => $date,
                            'end_date' => $end_date,
                            'start_time' => $start_time,
                            'end_time' => $end_time,
                            'cost' => $cost
                        ], true));
                        
                        if ($stmt->execute()) {
                            // Update available spot status
                            $update_query = "UPDATE available_spots SET 
                                status = 'occupied'
                                WHERE id = :spot_id";
                            
                            $update_stmt = $db->prepare($update_query);
                            $update_stmt->bindParam(":spot_id", $spot_id);
                            
                            if ($update_stmt->execute()) {
                                $db->commit();
                                $_SESSION['success'] = "Parking spot reserved successfully! Cost: ₱" . number_format($cost, 2);
                                header("Location: " . $_SERVER['PHP_SELF']);
                                exit();
                            } else {
                                throw new Exception("Failed to update spot status");
                            }
                        } else {
                            throw new Exception("Failed to create reservation");
                        }
                    } catch (Exception $e) {
                        $db->rollBack();
                        $_SESSION['error'] = "Error: " . $e->getMessage();
                        error_log("Error creating reservation: " . $e->getMessage());
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
                error_log("Database error: " . $e->getMessage());
            }
        }
    }
}

// Get user's active reservations
$user_id = $_SESSION['user_id'];

$current_date_php = date('Y-m-d');
$current_time_php = date('H:i:s');
// Update occupied spots status
$update_status_query = "UPDATE occupied_spots o
    JOIN available_spots a ON o.spot_id = a.id
    SET o.status = CASE 
        WHEN (o.reservation_date < :cur_date OR 
             (o.reservation_date = :cur_date AND o.end_time < :cur_time AND o.end_date <= :cur_date) OR
             (o.end_date < :cur_date))
        THEN 'completed'
        WHEN (o.reservation_date = :cur_date AND :cur_time BETWEEN o.start_time AND o.end_time) OR
             (o.reservation_date < :cur_date AND o.end_date >= :cur_date AND :cur_time <= o.end_time) OR
             (o.reservation_date = :cur_date AND o.start_time > o.end_time AND :cur_time >= o.start_time) OR
             (o.reservation_date = :cur_date AND o.start_time > o.end_time AND :cur_time <= o.end_time)
        THEN 'ongoing'
        ELSE 'reserved'
    END,
    a.status = CASE 
        WHEN (o.reservation_date < :cur_date OR 
             (o.reservation_date = :cur_date AND o.end_time < :cur_time AND o.end_date <= :cur_date) OR
             (o.end_date < :cur_date))
        THEN 'available'
        ELSE 'occupied'
    END
    WHERE o.user_id = :user_id AND o.status IN ('reserved', 'ongoing')";

$update_stmt = $db->prepare($update_status_query);
$update_stmt->bindParam(":user_id", $user_id);
$update_stmt->bindParam(":cur_date", $current_date_php);
$update_stmt->bindParam(":cur_time", $current_time_php);
$update_stmt->execute();

// Get user's reservations
$reservations_query = "SELECT o.*, a.spot_number, u.username,
                      CASE 
                          WHEN (o.reservation_date < :cur_date OR 
                               (o.reservation_date = :cur_date AND o.end_time < :cur_time AND o.end_date <= :cur_date) OR
                               (o.end_date < :cur_date))
                          THEN 'completed'
                          WHEN (o.reservation_date = :cur_date AND :cur_time BETWEEN o.start_time AND o.end_time) OR
                               (o.reservation_date < :cur_date AND o.end_date >= :cur_date AND :cur_time <= o.end_time) OR
                               (o.reservation_date = :cur_date AND o.start_time > o.end_time AND :cur_time >= o.start_time) OR
                               (o.reservation_date = :cur_date AND o.start_time > o.end_time AND :cur_time <= o.end_time)
                          THEN 'ongoing'
                          ELSE 'reserved'
                      END as current_status
                      FROM occupied_spots o 
                      JOIN available_spots a ON o.spot_id = a.id 
                      JOIN users u ON o.user_id = u.id 
                      WHERE o.user_id = :user_id 
                      ORDER BY o.reservation_date DESC, o.start_time DESC";
                      
$reservations_stmt = $db->prepare($reservations_query);
$reservations_stmt->bindParam(":user_id", $user_id);
$reservations_stmt->bindParam(":cur_date", $current_date_php);
$reservations_stmt->bindParam(":cur_time", $current_time_php);
$reservations_stmt->execute();
$user_reservations = $reservations_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Smart Parking System - Reserve Parking">
    <meta name="keywords" content="parking, system, management, reservation">
    <meta name="author" content="Satwinder Jeerh">
    <title>Reserve Parking - Smart Parking System</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <!-- CSS -->
    <link rel="stylesheet" href="css/stylesReservation.css?v=<?php echo time(); ?>">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

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
                <a href="reservation.php" class="nav-link active">Reserve Parking</a>
                <a href="profile.php" class="nav-link">Profile</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </div>
        </nav>
    </header>

    <div class="main-container">
        <div class="reservation-container">
            <h2>Reserve a Parking Spot</h2>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form" id="reservationForm">
                <div class="form-group">
                    <label>Select Parking Spot</label>
                    <div class="spot-grid">
                        <?php foreach ($available_spots as $spot): ?>
                            <div class="spot-item" 
                                 data-spot-id="<?php echo $spot['id']; ?>"
                                 title="Click to select this spot">
                                <?php echo htmlspecialchars($spot['spot_number']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="spot_id" id="selected_spot" required>
                </div>

                <div class="form-group fw">
                    <label for="date" >Start Date</label>
                    <input type="date" id="date" name="date" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="time-selection">
                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>

                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>
                </div>

                <div class="form-group fw">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>" readonly>
                    <small style="color: #FFF;">Automatically updates if reservation ends the next day</small>
                </div>

                <div class="cost-display" id="cost_display">
                    Estimated Cost: ₱0.00
                </div>

                <button type="submit" class="auth-button">Reserve Parking</button>
            </form>

            <!-- User's Reservations Section -->
            <div class="reservations-section">
                <h3>Your Reservations</h3>
                <?php if (empty($user_reservations)): ?>
                    <p>You have no active reservations.</p>
                <?php else: ?>
                    <div class="reservations-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Spot Number</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Duration</th>
                                    <th>Cost</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_reservations as $reservation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reservation['spot_number']); ?></td>
                                        <td><?php echo htmlspecialchars($reservation['reservation_date']); ?></td>
                                        <td>
                                            <?php 
                                            echo htmlspecialchars($reservation['start_time']) . ' - ' . 
                                                 htmlspecialchars($reservation['end_time']); 
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $start = new DateTime($reservation['reservation_date'] . ' ' . $reservation['start_time']);
                                            $end = new DateTime($reservation['reservation_date'] . ' ' . $reservation['end_time']);
                                            $duration = $start->diff($end);
                                            echo $duration->h . ' hours ' . $duration->i . ' minutes';
                                            ?>
                                        </td>
                                        <td>₱<?php echo number_format($reservation['cost'], 2); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($reservation['current_status']); ?>">
                                                <?php echo ucfirst($reservation['current_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="spot-details-container" id="spotDetails">
            <h3>Spot Details</h3>
            <div id="spotDetailsContent" class="spotDetailsContent"></div>
        </div>
    </div>

    <!-- Add Popup Message Elements -->
    <div id="customPopup" class="popup" style="display: none;">
        <div class="popup-content">
            <span class="close" onclick="closePopup()">&times;</span>
            <p id="popupMessage"></p>
        </div>
    </div>
    <div id="popupOverlay" class="popup-overlay" style="display: none;"></div>

    <script src="js/scriptReservations.js"></script>
</body>
</html> 
