<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check all reservations for spots 1 and 7
    $query = "SELECT o.*, a.spot_number, u.username
        FROM occupied_spots o
        JOIN available_spots a ON o.spot_id = a.id
        JOIN users u ON o.user_id = u.id
        WHERE a.spot_number IN ('P1', 'P7')
        AND o.status != 'completed'
        ORDER BY o.reservation_date, o.start_time";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";
    echo "All active reservations for spots 1 and 7:\n";
    echo "----------------------------------------\n";

    foreach ($reservations as $res) {
        echo "Spot: " . $res['spot_number'] . "\n";
        echo "Status: " . $res['status'] . "\n";
        echo "Date: " . $res['reservation_date'] . "\n";
        echo "Time: " . $res['start_time'] . " - " . $res['end_time'] . "\n";
        echo "User: " . $res['username'] . "\n";
        echo "----------------------------------------\n";
    }

    // Check if spots are actually occupied right now
    $current_query = "SELECT a.spot_number, 
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM occupied_spots o 
                WHERE o.spot_id = a.id 
                AND o.reservation_date = CURDATE() 
                AND CURTIME() BETWEEN o.start_time AND o.end_time
                AND o.status IN ('ongoing', 'reserved')
            ) THEN 'occupied'
            ELSE 'available'
        END as current_status
        FROM available_spots a
        WHERE a.spot_number IN ('P1', 'P7')";

    $current_stmt = $db->prepare($current_query);
    $current_stmt->execute();
    $current_status = $current_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nCurrent status check:\n";
    echo "----------------------------------------\n";
    foreach ($current_status as $status) {
        echo "Spot " . $status['spot_number'] . ": " . $status['current_status'] . "\n";
    }

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?> 