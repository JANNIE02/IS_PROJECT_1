<?php
require 'db_connection.php'; // Your PDO connection file

// Only allow admins to see this page

session_start();
if ($_SESSION['role'] !== 'admin') {
    die("Access Denied: You are not an admin.");
}

// 2. LOGIC: If an 'approve' button was clicked
if (isset($_GET['approve_id'])) {
    $id = $_GET['approve_id'];
    $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
    $stmt->execute([$id]);
    echo "<p style='color:green;'>User #$id has been approved!</p>";
}

// 3. FETCH: Get all users waiting for approval
$stmt = $pdo->query("SELECT id, username, role FROM users WHERE status = 'pending'");
$pending_users = $stmt->fetchAll();
?>

<h2>Pending User Approvals</h2>
<table border="1">
    <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Requested Role</th>
        <th>Action</th>
    </tr>
    <?php foreach ($pending_users as $user): ?>
    <tr>
        <td><?= $user['id'] ?></td>
        <td><?= htmlspecialchars($user['username']) ?></td>
        <td><?= htmlspecialchars($user['role']) ?></td>
        <td>
            <a href="?approve_id=<?= $user['id'] ?>">Approve</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>