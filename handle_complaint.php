<?php
require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    // New complaint submission
    if (isset($_POST['type_id']) && isset($_POST['subject']) && isset($_POST['description'])) {
        $type_id = $conn->real_escape_string($_POST['type_id']);
        $subject = $conn->real_escape_string($_POST['subject']);
        $description = $conn->real_escape_string($_POST['description']);
        $complainant_id = $_SESSION['user_id'];

        // Get complaint type
        $type_query = $conn->query("SELECT type_name FROM types WHERE id = $type_id");
        $type_data = $type_query->fetch_assoc();
        $complaint_type = $type_data['type_name'];

        // Find appropriate respondent
        $respondent_query = $conn->query("
            SELECT id 
            FROM users 
            WHERE role = 'respondent' 
            AND rsp_type = '$complaint_type'
            ORDER BY rsp_level ASC 
            LIMIT 1
        ");

        $respondent_id = null;
        if ($respondent_query->num_rows > 0) {
            $respondent = $respondent_query->fetch_assoc();
            $respondent_id = $respondent['id'];
        }

        // Insert complaint
        $insert_query = $conn->prepare("
            INSERT INTO complaints 
            (complainant_id, type_id, current_respondent_id, subject, description, escalation_level) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $insert_query->bind_param("iiiss", $complainant_id, $type_id, $respondent_id, $subject, $description);

        if ($insert_query->execute()) {
            $_SESSION['success'] = "Complaint submitted successfully!";
        } else {
            $_SESSION['error'] = "Error: " . $conn->error;
        }
    }
    header("Location: complainant_dashboard.php");
    exit();
}
