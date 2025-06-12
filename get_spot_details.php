<?php
require_once 'config/session.php';
require_once 'config/database.php';

// Check if spot_id is provided
if (!isset($_GET['spot_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Spot ID is required']);
    exit();
}

$spot_id = $_GET['spot_id'];

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get current and future reservations for the spot
    $query = "SELECT o.*, a.spot_number, u.username,
              CASE 
                  WHEN (o.reservation_date = CURDATE() AND CURTIME() BETWEEN o.start_time AND o.end_time) OR
                       (o.reservation_date < CURDATE() AND o.end_date >= CURDATE() AND CURTIME() <= o.end_time) OR
                       (o.reservation_date = CURDATE() AND o.start_time > o.end_time AND CURTIME() >= o.start_time) OR
                       (o.reservation_date = CURDATE() AND o.start_time > o.end_time AND CURTIME() <= o.end_time)
                  THEN 'ongoing'
                  ELSE 'reserved'
              END as current_status
              FROM occupied_spots o 
              JOIN available_spots a ON o.spot_id = a.id 
              JOIN users u ON o.user_id = u.id 
              WHERE o.spot_id = :spot_id 
              AND (
                  o.reservation_date > CURDATE() 
                  OR (o.reservation_date = CURDATE() AND o.end_time >= CURTIME())
                  OR (o.reservation_date < CURDATE() AND o.end_date >= CURDATE())
                  OR (o.reservation_date = CURDATE() AND o.start_time > o.end_time)
              )
              AND o.status != 'completed'
              ORDER BY o.reservation_date ASC, o.start_time ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":spot_id", $spot_id);
    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the reservations as JSON
    header('Content-Type: application/json');
    echo json_encode(['reservations' => $reservations]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 