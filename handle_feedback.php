<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $complaint_id = $_POST['complaint_id'];
    $feedback = $_POST['feedback'];
    
    if ($feedback === 'satisfied') {
        // Close the complaint
        $conn->query("UPDATE complaints SET status = 'Closed' WHERE id = $complaint_id");
        $_SESSION['success'] = "Thank you for your feedback! Complaint closed.";
    } else {
        // Escalate complaint
        $complaint = $conn->query("
            SELECT c.*, t.type_name 
            FROM complaints c
            JOIN types t ON c.type_id = t.id
            WHERE c.id = $complaint_id
        ")->fetch_assoc();
        
        $new_level = $complaint['escalation_level'] + 1;
        $complaint_type = $complaint['type_name'];

        // Try to find higher-level respondent
        $respondent = $conn->query("
            SELECT u.id, COUNT(c.id) AS open_complaints 
            FROM users u
            LEFT JOIN complaints c 
                ON u.id = c.current_respondent_id 
                AND c.status IN ('Open', 'In Progress')
            WHERE u.role = 'respondent' 
            AND u.rsp_type = '$complaint_type'
            AND u.rsp_level > {$complaint['escalation_level']}
            GROUP BY u.id
            ORDER BY u.rsp_level ASC, open_complaints ASC
            LIMIT 1
        ");

        $respondent_id = null;
        $message = "";
        
        if ($respondent->num_rows > 0) {
            // Assign to higher-level respondent
            $respondent_id = $respondent->fetch_assoc()['id'];
            $message = "Complaint has been escalated to a higher level respondent";
        } else {
            // Find admin user
            $admin = $conn->query("
                SELECT id 
                FROM users 
                WHERE role = 'admin'
                LIMIT 1
            ");
            
            if ($admin->num_rows > 0) {
                // Assign to admin
                $respondent_id = $admin->fetch_assoc()['id'];
                $message = "Complaint escalated to system administrator";
            } else {
                // No admin found
                $respondent_id = null;
                $message = "Complaint escalated - awaiting manual assignment";
            }
        }

        // Update complaint
        $conn->query("
            UPDATE complaints 
            SET status = 'Open',
                escalation_level = $new_level,
                current_respondent_id = " . ($respondent_id ?? 'NULL') . "
            WHERE id = $complaint_id
        ");

        $_SESSION['info'] = $message;
    }
    
    header("Location: complainant_dashboard.php");
    exit();
}