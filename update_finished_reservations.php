<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

try {
    $sql = "UPDATE occupied_spots
            SET status = 'completed'
            WHERE status = 'ongoing'
              AND (
                reservation_date < CURDATE()
                OR (reservation_date = CURDATE() AND end_time < CURTIME())
              )";
    $affected = $db->exec($sql);
    echo "Updated $affected reservation(s) to completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 