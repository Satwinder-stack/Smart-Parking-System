<?php
echo "Current PHP time: " . date('Y-m-d H:i:s') . "\n";
echo "Current MySQL time: ";
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
$stmt = $db->query("SELECT NOW() as `current_time`");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo $result['current_time'] . "\n";
?> 