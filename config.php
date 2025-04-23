<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$host = "localhost";
$user = "root";
$password = "ad17062001";
$dbname = "complaint_system";

// Database connection
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Security functions
function sanitizeInput($data)
{
    return htmlspecialchars(stripslashes(trim($data)));
}

// Authentication check
function checkAuth()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

function log_activity($action_type, $description, $conn) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("
        INSERT INTO audit_log 
        (user_id, action_type, description, ip_address)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $user_id, $action_type, $description, $ip);
    $stmt->execute();
}
    