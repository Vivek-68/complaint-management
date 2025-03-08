<?php
session_start();
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
    