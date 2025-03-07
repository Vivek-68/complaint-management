<?php
include 'config.php';
checkAuth();
if ($_SESSION['role'] !== 'admin') header("Location: login.php");

// Handle user updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['role'];
    $rsp_type = $_POST['rsp_type'];
    $rsp_level = $_POST['rsp_level'];

    $stmt = $conn->prepare("UPDATE users SET role = ?, rsp_type = ?, rsp_level = ? WHERE id = ?");
    $stmt->bind_param("sssi", $new_role, $rsp_type, $rsp_level, $user_id);
    $stmt->execute();
}
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
            <span>Welcome, <?= $_SESSION['username'] ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="dashboard-card">
            <h2>All Complaints</h2>
            <div class="complaint-list">
                <?php
                $complaints = $conn->query("
                    SELECT c.*, u.username AS complainant_name, r.username AS respondent_name,
                           t.type_name, t.subtype 
                    FROM complaints c
                    JOIN users u ON c.complainant_id = u.id
                    LEFT JOIN users r ON c.current_respondent_id = r.id
                    JOIN types t ON c.type_id = t.id
                ")->fetch_all(MYSQLI_ASSOC);

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
                            <?php if ($c['respondent_name']): ?>
                                <span>Assigned to: <?= $c['respondent_name'] ?></span>
                            <?php endif; ?>
                            <span>Type: <?= $c['type_name'] ?> / <?= $c['subtype'] ?></span>
                            <span>Submitted: <?= date('d M Y', strtotime($c['submitted_at'])) ?></span>
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
                        <th>Respondent Type</th>
                        <th>Respondent Level</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = $conn->query("SELECT * FROM users")->fetch_all(MYSQLI_ASSOC);
                    foreach ($users as $u):
                    ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <td><?= $u['username'] ?></td>
                                <td><?= $u['email'] ?></td>
                                <td>
                                    <select name="role">
                                        <option value="complainant" <?= $u['role'] == 'complainant' ? 'selected' : '' ?>>Complainant</option>
                                        <option value="respondent" <?= $u['role'] == 'respondent' ? 'selected' : '' ?>>Respondent</option>
                                        <option value="admin" <?= $u['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="rsp_type" value="<?= $u['rsp_type'] ?? '' ?>">
                                </td>
                                <td>
                                    <input type="number" name="rsp_level" value="<?= $u['rsp_level'] ?? '' ?>">
                                </td>
                                <td>
                                    <button type="submit" name="update_user">Update</button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>