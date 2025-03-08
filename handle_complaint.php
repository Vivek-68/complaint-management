<?php
require 'config.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Remove session_start() here since it's already in config.php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $complaint_id = $_POST['complaint_id'];
    $new_status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $complaint_id);
    
    if ($stmt->execute()) {
        if ($new_status == 'Resolved') {
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Store token
            $conn->query("UPDATE complaints 
                         SET feedback_token = '$token', 
                             token_expiry = '$expiry' 
                         WHERE id = $complaint_id");
            
            // Get complaint details
            $result = $conn->query("
                SELECT u.email, c.subject 
                FROM complaints c
                JOIN users u ON c.complainant_id = u.id
                WHERE c.id = $complaint_id
            ");
            $data = $result->fetch_assoc();
            
            // Configure PHPMailer
            $mail = new PHPMailer(true);
            try {
                // SMTP Configuration (Update these with your details)
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'akashda21_ug@cse.nits.ac.in'; // Your email
                $mail->Password   = 'ad17062001'; // App password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;
                
                // Recipients
                $mail->setFrom('no-reply@complaintsystem.com', 'Complaint System');
                $mail->addAddress($data['email']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Complaint Resolution Feedback';
                $feedback_link = "http://localhost/complaint_system/feedback.php?cid=$complaint_id&token=$token";
                $mail->Body    = "
                    <h3>Your complaint has been resolved!</h3>
                    <p>Complaint: {$data['subject']}</p>
                    <p>Are you satisfied with the resolution?</p>
                    <p>
                        <a href='$feedback_link&response=yes'>Yes</a> | 
                        <a href='$feedback_link&response=no'>No</a>
                    </p>
                    <p>This link expires in 24 hours.</p>
                ";
                
                if(!$mail->send()) {
                    error_log("Mailer Error: " . $mail->ErrorInfo);
                }
            } catch (Exception $e) {
                error_log("Mail Error: {$mail->ErrorInfo}");
            }
        }
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
}
?>