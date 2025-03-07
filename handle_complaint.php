<?php
include 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// require 'vendor/phpmailer/PHPMailerAutoload.php';
// require 'vendor/phpmailer/phpmailer/'

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle status updates from respondents
    if (isset($_POST['complaint_id']) && isset($_POST['status'])) {
        $complaint_id = $_POST['complaint_id'];
        $new_status = $_POST['status'];

        $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $complaint_id);
        $stmt->execute();

        // Send email only when status changes to Resolved
        if ($new_status == 'Resolved') {
            // Get complaint details
            $stmt = $conn->prepare("
                SELECT c.*, u.email, u.username 
                FROM complaints c
                JOIN users u ON c.complainant_id = u.id
                WHERE c.id = ?
            ");
            $stmt->bind_param("i", $complaint_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $complaint = $result->fetch_assoc();

            // Generate unique feedback token
            $token = bin2hex(random_bytes(32));
            $stmt = $conn->prepare("
                UPDATE complaints 
                SET feedback_token = ?, token_expiry = DATE_ADD(NOW(), INTERVAL 1 DAY)
                WHERE id = ?
            ");
            $stmt->bind_param("si", $token, $complaint_id);
            $stmt->execute();

            // Configure PHPMailer
            $mail = new PHPMailer;
            $mail->isSMTP();
            $mail->Host = 'smtp.example.com'; // Your SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = 'your_email@example.com'; // SMTP username
            $mail->Password = 'your_email_password'; // SMTP password
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('support@complaintsystem.com', 'Complaint System');
            $mail->addAddress($complaint['email'], $complaint['username']);
            $mail->isHTML(true);

            $mail->Subject = 'Your Complaint Has Been Resolved';
            $feedback_link = "http://yourdomain.com/feedback.php?cid=$complaint_id&token=$token";
            $mail->Body = "
                <h3>Dear {$complaint['username']},</h3>
                <p>Your complaint (#{$complaint_id}) has been marked as resolved.</p>
                <p>Please provide feedback: <a href='$feedback_link'>Feedback Portal</a></p>
                <p>This link expires in 24 hours.</p>
            ";

            if (!$mail->send()) {
                error_log("Mailer Error: " . $mail->ErrorInfo);
            }
        }
    }
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}
