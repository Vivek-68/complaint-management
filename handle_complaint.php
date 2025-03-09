<?php
require 'config.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get complaint details
    $type_id = $conn->real_escape_string($_POST['type_id']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $description = $conn->real_escape_string($_POST['description']);
    $complainant_id = $_SESSION['user_id'];

    // Step 1: Get complaint type
    $type_result = $conn->query("
        SELECT type_name 
        FROM types 
        WHERE id = $type_id
    ");
    if ($type_result->num_rows === 0) {
        die("Invalid complaint type");
    }
    $type_data = $type_result->fetch_assoc();
    $complaint_type = $type_data['type_name'];

    // Step 2: Find appropriate respondent
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

    // Step 3: Insert complaint
    $insert_stmt = $conn->prepare("
        INSERT INTO complaints 
        (complainant_id, type_id, current_respondent_id, subject, description) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $insert_stmt->bind_param("iiiss", 
        $complainant_id,
        $type_id,
        $respondent_id,
        $subject,
        $description
    );

    if ($insert_stmt->execute()) {
        // Send confirmation to complainant
        $complaint_id = $conn->insert_id;
        $complainant_email = $conn->query("
            SELECT email FROM users WHERE id = $complainant_id
        ")->fetch_assoc()['email'];

        $mail = new PHPMailer(true);
        try {
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host       = 'smtp.example.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'your@email.com';
            $mail->Password   = 'your_password';
            $mail->SMTPSecure = 'ssl';
            $mail->Port       = 465;

            // Recipients
            $mail->setFrom('no-reply@complaintsystem.com', 'Complaint System');
            $mail->addAddress($complainant_email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Complaint Submitted';
            $mail->Body    = "
                <h3>Your complaint has been received!</h3>
                <p>Complaint ID: $complaint_id</p>
                <p>Subject: $subject</p>
                <p>We will review your complaint shortly.</p>
            ";

            $mail->send();
        } catch (Exception $e) {
            error_log("Mail Error: {$mail->ErrorInfo}");
        }

        $_SESSION['success'] = "Complaint submitted successfully!";
    } else {
        $_SESSION['error'] = "Error submitting complaint: " . $conn->error;
    }

    header("Location: complainant_dashboard.php");
    exit();
}
?>