<?php
include 'config.php';

$error = '';
$types = [];

// Fetch available complaint types for respondent selection
$type_result = $conn->query("SELECT DISTINCT type_name FROM types");
if ($type_result) {
    $types = $type_result->fetch_all(MYSQLI_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = password_hash(sanitizeInput($_POST['password']), PASSWORD_DEFAULT);
    $role = sanitizeInput($_POST['role']);
    
    // Initialize respondent fields
    $rsp_type = null;
    $rsp_level = null;

    // Validate respondent-specific fields
    if ($role === 'respondent') {
        if (empty($_POST['rsp_type']) || empty($_POST['rsp_level'])) {
            $error = "Respondents must select a type and level";
        } else {
            $rsp_type = sanitizeInput($_POST['rsp_type']);
            $rsp_level = (int)sanitizeInput($_POST['rsp_level']);
            
            // Validate type exists
            $type_check = $conn->prepare("SELECT type_name FROM types WHERE type_name = ?");
            $type_check->bind_param("s", $rsp_type);
            $type_check->execute();
            if (!$type_check->get_result()->num_rows) {
                $error = "Invalid specialization type selected";
            }
        }
    } else {
        // Clear fields for non-respondents
        $rsp_type = null;
        $rsp_level = null;
    }

    if (empty($error)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO users 
                (username, email, password, role, rsp_type, rsp_level) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssssi", 
                $username,
                $email,
                $password,
                $role,
                $rsp_type,
                $rsp_level
            );

            if ($stmt->execute()) {
                $new_user_id = $conn->insert_id;
                
                // Log the registration activity
                $log_description = "New $role registered: $username";
                if ($role === 'respondent') {
                    $log_description .= " (Type: $rsp_type, Level: $rsp_level)";
                }
                log_activity('USER_REGISTER', $log_description, $conn);
                
                header("Location: login.php");
                exit();
            } else {
                $error = "Registration failed: " . $conn->error;
                log_activity('REGISTER_FAIL', "Failed registration attempt for $username", $conn);
            }
        } catch (mysqli_sql_exception $e) {
            $error = "Database error: " . $e->getMessage();
            log_activity('DB_ERROR', "Registration error: " . $e->getMessage(), $conn);
        }
    } else {
        // Log validation errors
        log_activity('VALIDATION_ERROR', "Registration validation failed: $error", $conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - Complaint System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Hide browser validation UI */
        input:invalid, select:invalid {
            box-shadow: none;
        }
        .required-asterisk {
            color: red;
            margin-left: 3px;
        }
        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .auth-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .text-center {
            text-align: center;
            margin-top: 20px;
        }
    </style>
    <script>
        function toggleRespondentFields() {
            const role = document.getElementById('role').value;
            const respondentFields = document.getElementById('respondent-fields');
            respondentFields.style.display = role === 'respondent' ? 'block' : 'none';
            
            // Manage required attributes
            const typeField = document.getElementById('rsp_type');
            const levelField = document.getElementById('rsp_level');
            
            if (role === 'respondent') {
                typeField.required = true;
                levelField.required = true;
            } else {
                typeField.required = false;
                levelField.required = false;
                typeField.value = '';
                levelField.value = '';
            }
        }

        function validateForm() {
            const role = document.getElementById('role').value;
            if (role === 'respondent') {
                const type = document.getElementById('rsp_type').value;
                const level = document.getElementById('rsp_level').value;
                
                if (!type || !level) {
                    alert('Please fill in all required respondent fields');
                    return false;
                }
            }
            return true;
        }

        // Initialize fields on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleRespondentFields();
        });
    </script>
</head>
<body>
    <div class="auth-container">
        <h2 class="form-title">Create Account</h2>
        <?php if(!empty($error)): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" onsubmit="return validateForm()">
            <div class="form-group">
                <label>Username<span class="required-asterisk">*</span></label>
                <input type="text" name="username" required>
            </div>

            <div class="form-group">
                <label>Email<span class="required-asterisk">*</span></label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>Password<span class="required-asterisk">*</span></label>
                <input type="password" name="password" required>
            </div>

            <div class="form-group">
                <label>Role<span class="required-asterisk">*</span></label>
                <select name="role" id="role" onchange="toggleRespondentFields()" required>
                    <option value="complainant">Complainant</option>
                    <option value="respondent">Respondent</option>
                </select>
            </div>

            <div id="respondent-fields" style="display: none;">
                <div class="form-group">
                    <label>Specialization Type<span class="required-asterisk">*</span></label>
                    <select name="rsp_type" id="rsp_type">
                        <option value="">Select Type</option>
                        <?php foreach($types as $type): ?>
                            <option value="<?= $type['type_name'] ?>">
                                <?= $type['type_name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Experience Level<span class="required-asterisk">*</span></label>
                    <input type="number" name="rsp_level" id="rsp_level" 
                           min="1" max="5" placeholder="1-5">
                </div>
            </div>

            <button type="submit" class="btn">Sign Up</button>
        </form>

        <p class="text-center">
            Already have an account? <a href="login.php">Sign in</a>
        </p>
    </div>
</body>
</html>