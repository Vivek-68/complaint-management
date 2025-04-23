<?php
include 'config.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'complainant') {
    header("Location: login.php");
    exit();
}

// Fetch user's complaints
$stmt = $conn->prepare("
    SELECT c.*, t.type_name, t.subtype 
    FROM complaints c
    JOIN types t ON c.type_id = t.id
    WHERE c.complainant_id = ?
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
    <title>Complainant Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Additional CSS for feedback buttons */
        .feedback-buttons {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }
        .btn-satisfied {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-not-satisfied {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-satisfied:hover {
            background-color: #218838;
        }
        .btn-not-satisfied:hover {
            background-color: #c82333;
        }
    </style>
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
        <!-- Success/Error Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="success-message"><?= $_SESSION['success'] ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div class="error-message"><?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- New Complaint Form -->
        <div class="dashboard-card">
            <h2>Submit New Complaint</h2>
            <form method="POST" action="handle_complaint.php">
                <div class="form-group">
                    <label>Complaint Type</label>
                    <select name="type_id" required>
                        <?php
                        $types = $conn->query("SELECT * FROM types");
                        while($type = $types->fetch_assoc()):
                        ?>
                        <option value="<?= $type['id'] ?>">
                            <?= $type['type_name'] ?> - <?= $type['subtype'] ?>
                        </option>
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
                <button type="submit" class="btn">Submit Complaint</button>
            </form>
        </div>

        <!-- List of Complaints -->
        <div class="dashboard-card">
            <h2>My Complaints</h2>
            <div class="complaint-list">
                <?php if(empty($complaints)): ?>
                    <p>No complaints submitted yet.</p>
                <?php else: ?>
                    <?php foreach($complaints as $c): ?>
                        <div class="complaint-item">
                            <h3><?= htmlspecialchars($c['subject']) ?></h3>
                            <p><?= htmlspecialchars($c['description']) ?></p>
                            <div class="complaint-meta">
                                <span>Type: <?= htmlspecialchars($c['type_name']) ?> - <?= htmlspecialchars($c['subtype']) ?></span>
                                <span>Status: <?= $c['status'] ?></span>
                                <span>Submitted: <?= date('M d, Y H:i', strtotime($c['submitted_at'])) ?></span>
                            </div>

                            <!-- Feedback Buttons for Resolved Complaints -->
                            <?php if($c['status'] == 'Resolved'): ?>
                                <div class="feedback-buttons">
                                    <form method="POST" action="handle_feedback.php">
                                        <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                                        <button type="submit" name="feedback" value="satisfied" class="btn-satisfied">
                                            Satisfied
                                        </button>
                                        <button type="submit" name="feedback" value="not_satisfied" class="btn-not-satisfied">
                                            Not Satisfied
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>