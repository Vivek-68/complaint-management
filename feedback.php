<?php
require 'config.php';

if (isset($_GET['cid']) && isset($_GET['token']) && isset($_GET['response'])) {
    $complaint_id = $conn->real_escape_string($_GET['cid']);
    $token = $conn->real_escape_string($_GET['token']);
    $response = $_GET['response'];
    
    // Validate token
    $result = $conn->query("
        SELECT * FROM complaints 
        WHERE id = $complaint_id 
        AND feedback_token = '$token'
        AND token_expiry > NOW()
    ");
    
    if ($result->num_rows === 1) {
        if ($response === 'no') {
            // Escalate complaint
            $complaint_data = $result->fetch_assoc();
            $current_level = $complaint_data['escalation_level'];
            $new_level = $current_level + 1;
            $type_id = $complaint_data['type_id'];
            
            // Get next level respondent
            $respondent = $conn->query("
                SELECT u.id 
                FROM users u
                JOIN types t ON u.rsp_type = t.type_name
                WHERE t.id = $type_id
                AND u.rsp_level = $new_level
                ORDER BY u.rsp_level ASC
                LIMIT 1
            ");
            
            if ($respondent->num_rows > 0) {
                $respondent_id = $respondent->fetch_assoc()['id'];
                $conn->query("
                    UPDATE complaints 
                    SET status = 'Open',
                        escalation_level = $new_level,
                        current_respondent_id = $respondent_id,
                        feedback_token = NULL,
                        token_expiry = NULL
                    WHERE id = $complaint_id
                ");
            } else {
                // No higher level respondent found - assign to admin
                $conn->query("
                    UPDATE complaints 
                    SET status = 'Open',
                        escalation_level = $new_level,
                        current_respondent_id = NULL,
                        feedback_token = NULL,
                        token_expiry = NULL
                    WHERE id = $complaint_id
                ");
            }
        } else {
            // Mark as closed
            $conn->query("
                UPDATE complaints 
                SET status = 'Closed',
                    feedback_token = NULL,
                    token_expiry = NULL
                WHERE id = $complaint_id
            ");
        }
    }
    
    header("Location: login.php");
    exit();
}
?>