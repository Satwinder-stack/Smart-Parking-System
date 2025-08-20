<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Start transaction
    $db->beginTransaction();

    // First, add new hashed columns
    $db->exec("ALTER TABLE users 
               ADD COLUMN phone_hash VARCHAR(255) AFTER phone,
               ADD COLUMN address_hash VARCHAR(255) AFTER address,
               ADD COLUMN vehicle_number_hash VARCHAR(255) AFTER vehicle_number,
               ADD COLUMN vehicle_type_hash VARCHAR(255) AFTER vehicle_type");

    // Get all users with sensitive data
    $query = "SELECT id, phone, address, vehicle_number, vehicle_type FROM users 
              WHERE phone IS NOT NULL OR address IS NOT NULL 
              OR vehicle_number IS NOT NULL OR vehicle_type IS NOT NULL";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update each user's data with hashed values
    $update_query = "UPDATE users SET 
                    phone_hash = :phone_hash,
                    address_hash = :address_hash,
                    vehicle_number_hash = :vehicle_number_hash,
                    vehicle_type_hash = :vehicle_type_hash
                    WHERE id = :user_id";
    $update_stmt = $db->prepare($update_query);

    foreach ($users as $user) {
        $phone_hash = $user['phone'] ? password_hash($user['phone'], PASSWORD_DEFAULT) : null;
        $address_hash = $user['address'] ? password_hash($user['address'], PASSWORD_DEFAULT) : null;
        $vehicle_number_hash = $user['vehicle_number'] ? password_hash($user['vehicle_number'], PASSWORD_DEFAULT) : null;
        $vehicle_type_hash = $user['vehicle_type'] ? password_hash($user['vehicle_type'], PASSWORD_DEFAULT) : null;

        $update_stmt->bindParam(":phone_hash", $phone_hash);
        $update_stmt->bindParam(":address_hash", $address_hash);
        $update_stmt->bindParam(":vehicle_number_hash", $vehicle_number_hash);
        $update_stmt->bindParam(":vehicle_type_hash", $vehicle_type_hash);
        $update_stmt->bindParam(":user_id", $user['id']);
        $update_stmt->execute();
    }

    // Drop original columns
    $db->exec("ALTER TABLE users 
               DROP COLUMN phone,
               DROP COLUMN address,
               DROP COLUMN vehicle_number,
               DROP COLUMN vehicle_type");

    // Rename hashed columns to original names
    $db->exec("ALTER TABLE users 
               CHANGE phone_hash phone VARCHAR(255),
               CHANGE address_hash address VARCHAR(255),
               CHANGE vehicle_number_hash vehicle_number VARCHAR(255),
               CHANGE vehicle_type_hash vehicle_type VARCHAR(255)");

    // Commit transaction
    $db->commit();
    echo "Migration completed successfully!";

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Migration failed: " . $e->getMessage();
}
?> 