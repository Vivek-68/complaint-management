<?php
include 'config.php';
checkAuth();
if ($_SESSION['role'] !== 'respondent') header("Location: login.php");

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $complaint_id = $_POST['complaint_id'];
    $new_status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $complaint_id);
    $stmt->execute();
}
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
            <span>Welcome, <?= $_SESSION['username'] ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="dashboard-card">
            <h2>Assigned Complaints</h2>
            <div class="complaint-list">
                <?php
                $stmt = $conn->prepare("
                    SELECT c.*, u.username AS complainant_name, t.type_name, t.subtype 
                    FROM complaints c
                    JOIN users u ON c.complainant_id = u.id
                    JOIN types t ON c.type_id = t.id
                    WHERE c.current_respondent_id = ?
                ");
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                foreach ($complaints as $c):
                ?>
                    <div class="complaint-item">
                        <h3><?= $c['subject'] ?>
                            <span class="status-<?= strtolower(str_replace(' ', '-', $c['status'])) ?>">
                                <?= $c['status'] ?>
                            </span>
                        </h3>
                        <p><?= $c['description'] ?></p>
                        <div class="complaint-meta">
                            <span>From: <?= $c['complainant_name'] ?></span>
                            <span>Type: <?= $c['type_name'] ?> / <?= $c['subtype'] ?></span>
                            <span>Submitted: <?= date('d M Y', strtotime($c['submitted_at'])) ?></span>
                        </div>
                        <form method="POST" class="status-form">
                            <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                            <select name="status">
                                <option value="Open" <?= $c['status'] == 'Open' ? 'selected' : '' ?>>Open</option>
                                <option value="In Progress" <?= $c['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="Resolved" <?= $c['status'] == 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                            </select>
                            <button type="submit">Update Status</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>

</html>