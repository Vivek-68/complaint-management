<?php
include 'config.php';
// session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle user updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = $conn->real_escape_string($_POST['user_id']);
    $new_role = $conn->real_escape_string($_POST['role']);
    $rsp_type = isset($_POST['rsp_type']) ? $conn->real_escape_string($_POST['rsp_type']) : null;
    $rsp_level = isset($_POST['rsp_level']) ? (int)$_POST['rsp_level'] : null;

    // Validate respondent fields
    if ($new_role === 'respondent') {
        if (empty($rsp_type)) {
            $_SESSION['error'] = "Respondent must have a specialization type";
            header("Location: admin_dashboard.php");
            exit();
        }
        
        // Check if type exists
        $type_check = $conn->prepare("SELECT type_name FROM types WHERE type_name = ?");
        $type_check->bind_param("s", $rsp_type);
        $type_check->execute();
        if (!$type_check->get_result()->num_rows) {
            $_SESSION['error'] = "Invalid specialization type selected";
            header("Location: admin_dashboard.php");
            exit();
        }
    } else {
        $rsp_type = null;
        $rsp_level = null;
    }

    $stmt = $conn->prepare("
        UPDATE users 
        SET role = ?, rsp_type = ?, rsp_level = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssi", $new_role, $rsp_type, $rsp_level, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "User updated successfully";
    } else {
        $_SESSION['error'] = "Error updating user: " . $conn->error;
    }
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch all complaints
$complaints = $conn->query("
    SELECT c.*, 
           u.username AS complainant_name,
           r.username AS respondent_name,
           t.type_name,
           t.subtype
    FROM complaints c
    JOIN users u ON c.complainant_id = u.id
    LEFT JOIN users r ON c.current_respondent_id = r.id
    JOIN types t ON c.type_id = t.id
    ORDER BY c.submitted_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Fetch all users
$users = $conn->query("SELECT * FROM users")->fetch_all(MYSQLI_ASSOC);

// Fetch available complaint types
$types = $conn->query("SELECT DISTINCT type_name FROM types")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="logo">Complaint System</div>
        <div class="nav-links">
            <span>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if(isset($_SESSION['error'])): ?>
            <div class="error-message"><?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="success-message"><?= $_SESSION['success'] ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="dashboard-card">
            <h2>All Complaints</h2>
            <div class="complaint-list">
                <?php foreach($complaints as $c): ?>
                    <div class="complaint-item">
                        <h3>
                            <?= htmlspecialchars($c['subject']) ?>
                            <span class="status-<?= strtolower(str_replace(' ', '-', $c['status'])) ?>">
                                <?= $c['status'] ?>
                            </span>
                        </h3>
                        <p><?= htmlspecialchars($c['description']) ?></p>
                        <div class="complaint-meta">
                            <div class="meta-item">
                                <strong>Complainant:</strong>
                                <?= htmlspecialchars($c['complainant_name']) ?>
                            </div>
                            <?php if($c['respondent_name']): ?>
                                <div class="meta-item">
                                    <strong>Assigned To:</strong>
                                    <?= htmlspecialchars($c['respondent_name']) ?> 
                                    (Level <?= $c['escalation_level'] ?>)
                                </div>
                            <?php endif; ?>
                            <div class="meta-item">
                                <strong>Type:</strong>
                                <?= htmlspecialchars($c['type_name']) ?> - <?= htmlspecialchars($c['subtype']) ?>
                            </div>
                            <div class="meta-item">
                                <strong>Submitted:</strong>
                                <?= date('M d, Y H:i', strtotime($c['submitted_at'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="dashboard-card">
            <h2>User Management</h2>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Specialization</th>
                        <th>Level</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <select name="role" class="role-select">
                                        <option value="complainant" <?= $u['role'] === 'complainant' ? 'selected' : '' ?>>Complainant</option>
                                        <option value="respondent" <?= $u['role'] === 'respondent' ? 'selected' : '' ?>>Respondent</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="rsp_type" <?= $u['role'] !== 'respondent' ? 'disabled' : '' ?>>
                                        <option value="">Select Type</option>
                                        <?php foreach($types as $type): ?>
                                            <option value="<?= $type['type_name'] ?>" 
                                                <?= $u['rsp_type'] === $type['type_name'] ? 'selected' : '' ?>>
                                                <?= $type['type_name'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="rsp_level" 
                                           value="<?= $u['rsp_level'] ?? '' ?>" 
                                           min="1" max="5"
                                           <?= $u['role'] !== 'respondent' ? 'disabled' : '' ?>>
                                </td>
                                <td>
                                    <button type="submit" name="update_user" class="btn-small">Update</button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Enable/disable respondent fields based on role selection
        document.querySelectorAll('.role-select').forEach(select => {
            select.addEventListener('change', function() {
                const row = this.closest('tr');
                const isRespondent = this.value === 'respondent';
                
                row.querySelector('[name="rsp_type"]').disabled = !isRespondent;
                row.querySelector('[name="rsp_level"]').disabled = !isRespondent;
            });
        });
    </script>
</body>
</html>