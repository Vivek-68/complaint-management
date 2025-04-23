<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="audit_log.csv"');

$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, ['Timestamp', 'Action Type', 'User', 'IP Address', 'Description']);

// Build query
$query = "SELECT a.created_at, a.action_type, u.username, a.ip_address, a.description 
          FROM audit_log a
          LEFT JOIN users u ON a.user_id = u.id";

// Add date filters if provided
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $query .= " WHERE a.created_at BETWEEN '".$conn->real_escape_string($_GET['start_date'])." 00:00:00'
               AND '".$conn->real_escape_string($_GET['end_date'])." 23:59:59'";
}

$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['created_at'],
        $row['action_type'],
        $row['username'] ?: 'System',
        $row['ip_address'],
        $row['description']
    ]);
}

fclose($output);
exit;