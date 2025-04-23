<?php
require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle status updates from respondents
    if (isset($_POST['status'], $_POST['complaint_id'])) {
        $complaint_id = $conn->real_escape_string($_POST['complaint_id']);
        $new_status = $conn->real_escape_string($_POST['status']);

        // Update complaint status
        $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $complaint_id);
        $stmt->execute();

        // If status is "Resolved", send email to complainant
        // If status is "Resolved", send email to complainant
if ($new_status === 'Resolved') {
    // Get complainant email and complaint details
    $result = $conn->query("
        SELECT u.email, c.subject 
        FROM complaints c
        JOIN users u ON c.complainant_id = u.id
        WHERE c.id = $complaint_id
    ");
    
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $complainant_email = $data['email'];
        $subject = $data['subject'];

        $mail = new PHPMailer(true);
        try {
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'adas94230@gmail.com'; // Replace with actual email
            $mail->Password   = 'yxqt yyph ggbv ctbe';    // Replace with app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            // Recipients
            $mail->setFrom('no-reply@complaintsystem.com', 'Complaint System');
            $mail->addAddress($complainant_email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Complaint Resolved: ' . $subject;
            $mail->Body    = "
                <h3>Your complaint has been resolved!</h3>
                <p><strong>Complaint ID:</strong> $complaint_id</p>
                <p><strong>Subject:</strong> $subject</p>
                <p>Please check your dashboard for more information.</p>
            ";

            if (!$mail->send()) {
                error_log("Resolution Email Failed: " . $mail->ErrorInfo);
                $_SESSION['error'] = "Failed to send resolution notification.";
            }
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            $_SESSION['error'] = "Email service temporarily unavailable.";
        }
    } else {
        error_log("No complainant found for complaint ID: $complaint_id");
        $_SESSION['error'] = "Error retrieving complaint details.";
    }
}

        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    // Handle new complaint submissions from complainants
    if (isset($_POST['type_id'], $_POST['subject'], $_POST['description'])) {
        $type_id = $conn->real_escape_string($_POST['type_id']);
        $subject = $conn->real_escape_string($_POST['subject']);
        $description = $conn->real_escape_string($_POST['description']);
        $complainant_id = $_SESSION['user_id'];

        // Get complaint type
        $type_result = $conn->query("SELECT type_name FROM types WHERE id = $type_id");
        $type_data = $type_result->fetch_assoc();
        $complaint_type = $type_data['type_name'];

        // Find appropriate respondent (lowest level with least open complaints)
        $respondent_query = $conn->query("
            SELECT u.id, COUNT(c.id) AS open_complaints 
            FROM users u
            LEFT JOIN complaints c 
                ON u.id = c.current_respondent_id 
                AND c.status IN ('Open', 'In Progress')
            WHERE u.role = 'respondent' 
            AND u.rsp_type = '$complaint_type'
            GROUP BY u.id
            ORDER BY u.rsp_level ASC, open_complaints ASC
            LIMIT 1
        ");

        $respondent_id = null;
        if ($respondent_query->num_rows > 0) {
            $respondent = $respondent_query->fetch_assoc();
            $respondent_id = $respondent['id'];
        }

        // Insert new complaint
        $insert_stmt = $conn->prepare("
            INSERT INTO complaints 
            (complainant_id, type_id, current_respondent_id, subject, description) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $insert_stmt->bind_param(
            "iiiss",
            $complainant_id,
            $type_id,
            $respondent_id,
            $subject,
            $description
        );

        // After successful complaint insertion
        if ($insert_stmt->execute()) {
            $complaint_id = $conn->insert_id;
            $log_desc = "Complaint #$complaint_id submitted: " . substr($subject, 0, 50);
            log_activity('COMPLAINT_CREATE', $log_desc, $conn);
            $complaint_id = $conn->insert_id;

            // Get complainant email
            $email_result = $conn->query("
        SELECT email FROM users WHERE id = $complainant_id
    ");
            $complainant_email = $email_result->fetch_assoc()['email'];

            // Send confirmation email
            $mail = new PHPMailer(true);
            try {
                // SMTP Configuration (Update with your credentials)
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'adas94230@gmail.com';
                $mail->Password   = 'yxqt yyph ggbv ctbe';
                $mail->SMTPSecure = 'ssl';
                $mail->Port       = 465;

                $mail->setFrom('no-reply@complaintsystem.com', 'Complaint System');
                $mail->addAddress($complainant_email);

                $mail->isHTML(true);
                $mail->Subject = 'Complaint Submission Confirmation';
                $mail->Body    = "
            <h3>Your complaint has been received!</h3>
            <p>Complaint ID: <strong>$complaint_id</strong></p>
            <p>Subject: $subject</p>
            <p>We will process your complaint shortly.</p>
            <p>Thank you for using our service.</p>
        ";

                if (!$mail->send()) {
                    error_log("Submission Email Failed: " . $mail->ErrorInfo);
                }
            } catch (Exception $e) {
                error_log("Mail Error: " . $e->getMessage());
            }

            $_SESSION['success'] = "Complaint submitted successfully!";
        } else {
            $_SESSION['error'] = "Error submitting complaint: " . $conn->error;
        }

        header("Location: complainant_dashboard.php");
        exit();
    }
}

// Redirect if invalid request
header("Location: login.php");
exit();
