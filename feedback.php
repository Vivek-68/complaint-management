<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $complaint_id = $_POST['complaint_id'];
    $token = $_POST['token'];
    $feedback = $_POST['feedback'];

    // Validate token
    $stmt = $conn->prepare("
        SELECT * FROM complaints 
        WHERE id = ? 
        AND feedback_token = ? 
        AND token_expiry > NOW()
    ");
    $stmt->bind_param("is", $complaint_id, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        if ($feedback == 'no') {
            // Escalate complaint
            $conn->begin_transaction();
            try {
                // Increment escalation level
                $conn->query("
                    UPDATE complaints 
                    SET escalation_level = escalation_level + 1, 
                        status = 'Escalated',
                        feedback_token = NULL,
                        token_expiry = NULL
                    WHERE id = $complaint_id
                ");

                // Find higher-level respondent
                $stmt = $conn->prepare("
                    SELECT u.id 
                    FROM users u
                    WHERE u.rsp_type = (
                        SELECT t.type_name 
                        FROM types t
                        JOIN complaints c ON t.id = c.type_id 
                        WHERE c.id = ?
                    )
                    AND u.rsp_level = (
                        SELECT escalation_level 
                        FROM complaints 
                        WHERE id = ?
                    )
                    ORDER BY rsp_level ASC 
                    LIMIT 1
                ");
                $stmt->bind_param("ii", $complaint_id, $complaint_id);
                $stmt->execute();
                $new_respondent = $stmt->get_result()->fetch_assoc();

                if ($new_respondent) {
                    $conn->query("
                        UPDATE complaints 
                        SET current_respondent_id = {$new_respondent['id']}
                        WHERE id = $complaint_id
                    ");
                } else {
                    // No higher-level respondent found
                    $conn->query("
                        UPDATE complaints 
                        SET status = 'Escalated - No Respondent Available'
                        WHERE id = $complaint_id
                    ");
                }

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                die("Error processing escalation: " . $e->getMessage());
            }
        }

        // Clear token regardless of feedback
        $conn->query("
            UPDATE complaints 
            SET feedback_token = NULL, 
                token_expiry = NULL 
            WHERE id = $complaint_id
        ");
    }
    header("Location: login.php");
    exit();
}

// If accessed via GET with token
if (isset($_GET['cid']) && isset($_GET['token'])) {
    $complaint_id = $_GET['cid'];
    $token = $_GET['token'];

    // Verify token
    $stmt = $conn->prepare("
        SELECT * FROM complaints 
        WHERE id = ? 
        AND feedback_token = ? 
        AND token_expiry > NOW()
    ");
    $stmt->bind_param("is", $complaint_id, $token);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 1) {
        // Show feedback form
?>
        <!DOCTYPE html>
        <html>

        <head>
            <title>Provide Feedback</title>
            <link rel="stylesheet" href="style.css">
        </head>

        <body>
            <div class="auth-container">
                <h2>Complaint Feedback</h2>
                <form method="POST">
                    <input type="hidden" name="complaint_id" value="<?= $complaint_id ?>">
                    <input type="hidden" name="token" value="<?= $token ?>">
                    <div class="form-group">
                        <label>Are you satisfied with the resolution?</label>
                        <select name="feedback" required>
                            <option value="yes">Yes, close complaint</option>
                            <option value="no">No, escalate complaint</option>
                        </select>
                    </div>
                    <button type="submit" class="btn">Submit Feedback</button>
                </form>
            </div>
        </body>

        </html>
<?php
        exit();
    }
}

// Invalid or expired token
header("Location: login.php");
exit();
?>