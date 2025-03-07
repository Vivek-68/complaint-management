<?php
include 'config.php';
checkAuth();
if ($_SESSION['role'] !== 'complainant') header("Location: login.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Complaints</title>
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
            <h2>New Complaint</h2>
            <form action="handle_complaint.php" method="POST">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type_id" required>
                        <?php
                        $types = $conn->query("SELECT * FROM types");
                        while($type = $types->fetch_assoc()):
                        ?>
                        <option value="<?= $type['id'] ?>"><?= $type['type_name'] ?> - <?= $type['subtype'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4" required></textarea>
                </div>
                <button type="submit">Submit</button>
            </form>
        </div>

        <div class="dashboard-card">
            <h2>My Complaints</h2>
            <div class="complaint-list">
                <?php
                $stmt = $conn->prepare("SELECT c.*, t.type_name, t.subtype 
                                       FROM complaints c
                                       JOIN types t ON c.type_id = t.id
                                       WHERE complainant_id = ?");
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                foreach($complaints as $c):
                ?>
                <div class="complaint-item">
                    <h3><?= $c['subject'] ?> <span class="status-<?= strtolower($c['status']) ?>"><?= $c['status'] ?></span></h3>
                    <p><?= $c['description'] ?></p>
                    <div class="complaint-meta">
                        <span>Type: <?= $c['type_name'] ?> / <?= $c['subtype'] ?></span>
                        <span>Submitted: <?= date('d M Y', strtotime($c['submitted_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>