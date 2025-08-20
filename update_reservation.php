<?php
session_start();
require_once 'config/database.php';

// Verify admin session
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$reservation_id = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$reservation_id || !in_array($action, ['start', 'end'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // First, get the current reservation status
    $check_query = "SELECT status, spot_id FROM occupied_spots WHERE id = :id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":id", $reservation_id);
    $check_stmt->execute();

    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
        exit();
    }

    $reservation = $check_stmt->fetch(PDO::FETCH_ASSOC);
    $current_status = $reservation['status'];
    $spot_id = $reservation['spot_id'];

    // Validate the action based on current status
    if ($action === 'start' && $current_status !== 'reserved') {
        echo json_encode(['success' => false, 'message' => 'Can only start reserved reservations']);
        exit();
    }

    if ($action === 'end' && $current_status !== 'ongoing') {
        echo json_encode(['success' => false, 'message' => 'Can only end ongoing reservations']);
        exit();
    }

    // Begin transaction
    $db->beginTransaction();

    try {
        // Update the reservation status
        $new_status = $action === 'start' ? 'ongoing' : 'completed';
        $update_query = "UPDATE occupied_spots SET status = :status WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(":status", $new_status);
        $update_stmt->bindParam(":id", $reservation_id);
        $update_stmt->execute();

        // If ending a reservation, update the spot status
        if ($action === 'end') {
            // Check if there are any other active reservations for this spot
            $check_active_query = "SELECT COUNT(*) as active_count FROM occupied_spots 
                                 WHERE spot_id = :spot_id 
                                 AND status IN ('reserved', 'ongoing')
                                 AND id != :reservation_id";
            $check_active_stmt = $db->prepare($check_active_query);
            $check_active_stmt->bindParam(":spot_id", $spot_id);
            $check_active_stmt->bindParam(":reservation_id", $reservation_id);
            $check_active_stmt->execute();
            $active_count = $check_active_stmt->fetch(PDO::FETCH_ASSOC)['active_count'];

            if ($active_count === 0) {
                // No other active reservations, mark spot as available
                $update_spot_query = "UPDATE available_spots SET status = 'available' WHERE id = :spot_id";
                $update_spot_stmt = $db->prepare($update_spot_query);
                $update_spot_stmt->bindParam(":spot_id", $spot_id);
                $update_spot_stmt->execute();
            }
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Reservation updated successfully']);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    error_log("Reservation Update Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating the reservation']);
}
?> 