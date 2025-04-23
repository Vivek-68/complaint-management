<?php
require 'config.php';

if (isset($_GET['cid']) && isset($_GET['token']) && isset($_GET['response'])) {
    $complaint_id = $conn->real_escape_string($_GET['cid']);
    $token = $conn->real_escape_string($_GET['token']);
    $response = $_GET['response'];
    
    // Validate token
    $result = $conn->query("
        SELECT c.*, t.type_name 
        FROM complaints c
        JOIN types t ON c.type_id = t.id
        WHERE c.id = $complaint_id 
        AND c.feedback_token = '$token'
        AND c.token_expiry > NOW()
    ");
    
    if ($result->num_rows === 1) {
        $complaint = $result->fetch_assoc();
        
        if ($response === 'no') {
            // Escalate complaint
            $new_level = $complaint['escalation_level'] + 1;
            $complaint_type = $complaint['type_name'];
            
            // Find higher-level respondent with least workload
            $respondent_query = $conn->query("
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
            if ($respondent_query->num_rows > 0) {
                $respondent = $respondent_query->fetch_assoc();
                $respondent_id = $respondent['id'];
            }
            
            // Update complaint
            $conn->query("
                UPDATE complaints 
                SET status = 'Open',
                    escalation_level = $new_level,
                    current_respondent_id = " . ($respondent_id ?? 'NULL') . ",
                    feedback_token = NULL,
                    token_expiry = NULL
                WHERE id = $complaint_id
            ");
        } else {
            // Close complaint
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