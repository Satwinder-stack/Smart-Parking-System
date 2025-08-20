<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($data->action)) {
        switch ($data->action) {
            case 'login':
                login($db, $data);
                break;
            case 'signup':
                signup($db, $data);
                break;
            default:
                echo json_encode(["message" => "Invalid action"]);
                break;
        }
    }
}

function login($db, $data) {
    if (!isset($data->username) || !isset($data->password)) {
        echo json_encode(["message" => "Missing required fields"]);
        return;
    }

    $query = "SELECT id, username, password FROM users WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":username", $data->username);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (password_verify($data->password, $row['password'])) {
            echo json_encode([
                "message" => "Login successful",
                "user" => [
                    "id" => $row['id'],
                    "username" => $row['username']
                ]
            ]);
        } else {
            echo json_encode(["message" => "Invalid password"]);
        }
    } else {
        echo json_encode(["message" => "User not found"]);
    }
}

function signup($db, $data) {
    if (!isset($data->fullname) || !isset($data->username) || 
        !isset($data->email) || !isset($data->password)) {
        echo json_encode(["message" => "Missing required fields"]);
        return;
    }

    // Check if username or email already exists
    $query = "SELECT id FROM users WHERE username = :username OR email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":username", $data->username);
    $stmt->bindParam(":email", $data->email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(["message" => "Username or email already exists"]);
        return;
    }

    // Create new user
    $query = "INSERT INTO users (fullname, username, email, password) 
              VALUES (:fullname, :username, :email, :password)";
    $stmt = $db->prepare($query);

    $hashed_password = password_hash($data->password, PASSWORD_DEFAULT);

    $stmt->bindParam(":fullname", $data->fullname);
    $stmt->bindParam(":username", $data->username);
    $stmt->bindParam(":email", $data->email);
    $stmt->bindParam(":password", $hashed_password);

    if ($stmt->execute()) {
        echo json_encode([
            "message" => "User created successfully",
            "user" => [
                "id" => $db->lastInsertId(),
                "username" => $data->username
            ]
        ]);
    } else {
        echo json_encode(["message" => "Unable to create user"]);
    }
}
?> 