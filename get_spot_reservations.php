<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent any output before headers
ob_start();

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/php_errors.log');

// Log the request
error_log("Request received for spot reservations. GET params: " . print_r($_GET, true));
error_log("Session status: " . print_r($_SESSION, true));

// Set proper content type
header('Content-Type: application/json');

// Include database configuration
require_once 'config.php';

// Function to send JSON response
function sendJsonResponse($data, $status = 200) {
    error_log("Sending response with status $status: " . print_r($data, true));
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    error_log("Access denied: No admin session");
    sendJsonResponse(['error' => 'Unauthorized access'], 401);
}

try {
    // Basic connection test
    if (!isset($pdo)) {
        throw new Exception("Database connection not initialized");
    }

    // Test the connection
    $pdo->query("SELECT 1");
    error_log("Database connection successful");

    // Get the spot number
    $spot = isset($_GET['spot']) ? trim($_GET['spot']) : '';
    if (empty($spot)) {
        throw new Exception("Spot number is required");
    }

    error_log("Attempting to fetch reservations for spot: " . $spot);

    // Get the spot ID first
    $spot_query = "SELECT id FROM available_spots WHERE spot_number = :spot";
    $spot_stmt = $pdo->prepare($spot_query);
    $spot_stmt->execute(['spot' => $spot]);
    $spot_data = $spot_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$spot_data) {
        throw new Exception("Invalid spot number");
    }

    $spot_id = $spot_data['id'];

    // Get reservations for this spot
    $query = "
        SELECT 
            o.id,
            o.spot_id,
            o.user_id,
            o.reservation_date,
            o.end_date,
            o.start_time,
            o.end_time,
            o.status,
            o.cost,
            u.username,
            u.fullname,
            CASE 
                WHEN (o.reservation_date = CURDATE() AND CURTIME() BETWEEN o.start_time AND o.end_time) OR
                     (o.reservation_date < CURDATE() AND o.end_date >= CURDATE() AND CURTIME() <= o.end_time) OR
                     (o.reservation_date = CURDATE() AND o.start_time > o.end_time AND CURTIME() >= o.start_time) OR
                     (o.reservation_date = CURDATE() AND o.start_time > o.end_time AND CURTIME() <= o.end_time)
                THEN 'ongoing'
                ELSE o.status
            END as current_status
        FROM occupied_spots o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE o.spot_id = :spot_id
        AND o.status != 'completed'
        ORDER BY o.reservation_date DESC, o.start_time ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['spot_id' => $spot_id]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Successfully fetched " . count($reservations) . " reservations");

    // Format the data
    $formatted_reservations = array_map(function($reservation) {
        return [
            'id' => (int)$reservation['id'],
            'spot_number' => $spot, // Use the original spot number from the request
            'username' => $reservation['username'] ?? 'Unknown',
            'fullname' => $reservation['fullname'] ?? 'Unknown',
            'reservation_date' => $reservation['reservation_date'],
            'end_date' => $reservation['end_date'],
            'start_time' => $reservation['start_time'],
            'end_time' => $reservation['end_time'],
            'status' => $reservation['current_status'],
            'cost' => number_format((float)$reservation['cost'], 2, '.', '')
        ];
    }, $reservations);

    sendJsonResponse($formatted_reservations);

} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("SQL State: " . $e->errorInfo[0]);
    error_log("Driver Code: " . $e->errorInfo[1]);
    error_log("Driver Message: " . $e->errorInfo[2]);
    
    sendJsonResponse([
        'error' => 'Database error occurred',
        'details' => $e->getMessage(),
        'code' => $e->getCode(),
        'sqlstate' => $e->errorInfo[0] ?? null,
        'driver_code' => $e->errorInfo[1] ?? null
    ], 500);
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    sendJsonResponse([
        'error' => 'An error occurred',
        'details' => $e->getMessage()
    ], 500);
}

// Clean any output buffer
ob_end_flush();
?> 