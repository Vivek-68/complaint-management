<?php
include 'config.php';
// session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'respondent') {
    header("Location: login.php");
    exit();
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $complaint_id = $_POST['complaint_id'];
    $new_status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $complaint_id);
    $stmt->execute();
    
    header("Location: respondent_dashboard.php");
    exit();
}

// Fetch assigned complaints
$stmt = $conn->prepare("
    SELECT c.*, u.username AS complainant_name, t.type_name, t.subtype 
    FROM complaints c
    JOIN users u ON c.complainant_id = u.id
    JOIN types t ON c.type_id = t.id
    WHERE c.current_respondent_id = ?
    ORDER BY c.submitted_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Respondent Dashboard</title>
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
        <div class="dashboard-card">
            <h2>Assigned Complaints</h2>
            <div class="complaint-list">
                <?php if(empty($complaints)): ?>
                    <p>No complaints assigned to you.</p>
                <?php else: ?>
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
                                <div class="meta-item">
                                    <strong>Type:</strong>
                                    <?= htmlspecialchars($c['type_name']) ?> - <?= htmlspecialchars($c['subtype']) ?>
                                </div>
                                <div class="meta-item">
                                    <strong>Submitted:</strong>
                                    <?= date('M d, Y H:i', strtotime($c['submitted_at'])) ?>
                                </div>
                                <div class="meta-item">
                                    <strong>Escalation Level:</strong>
                                    <?= $c['escalation_level'] ?>
                                </div>
                            </div>
                            
                            <!-- Status Update Form -->
                            <form method="POST" class="status-form">
                                <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                                <select name="status">
                                    <option value="Open" <?= $c['status'] == 'Open' ? 'selected' : '' ?>>Open</option>
                                    <option value="In Progress" <?= $c['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Resolved" <?= $c['status'] == 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                </select>
                                <button type="submit" class="btn-small">Update Status</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>