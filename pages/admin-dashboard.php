<?php
include '../config.php';
session_start();

// Protect page - only admin can access
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "admin") {
    header("Location: login.php");
    exit();
}

// Handle status update when admin clicks approve or reject
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST["user_id"];
    $new_status = $_POST["new_status"];

    pg_query_params($conn,
        "UPDATE users SET status = $1 WHERE id = $2",
        array($new_status, $user_id)
    );
}

// Get all non-admin users
$users = pg_query($conn,
    "SELECT id, full_name, email, role, location, status, created_at 
     FROM users 
     WHERE role != 'admin' 
     ORDER BY created_at DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Food Redistribution System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="#">Food Relief System — Admin</a>
        <div class="ms-auto">
            <span class="text-white me-3">Welcome, <?php echo $_SESSION["user_name"]; ?></span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h4 class="mb-4">Manage Users</h4>

    <?php if (pg_num_rows($users) == 0): ?>
        <div class="alert alert-info">No users registered yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Location</th>
                        <th>Registered</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = pg_fetch_assoc($users)): ?>
                        <tr>
                            <td><?php echo $row["id"]; ?></td>
                            <td><?php echo $row["full_name"]; ?></td>
                            <td><?php echo $row["email"]; ?></td>
                            <td><?php echo ucfirst($row["role"]); ?></td>
                            <td><?php echo $row["location"]; ?></td>
                            <td><?php echo date("d M Y", strtotime($row["created_at"])); ?></td>
                            <td>
                                <?php if ($row["status"] == "approved"): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php elseif ($row["status"] == "pending"): ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row["status"] == "pending"): ?>
                                    <!-- Approve button -->
                                    <form method="POST" action="admin-dashboard.php" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?php echo $row["id"]; ?>">
                                        <input type="hidden" name="new_status" value="approved">
                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                    </form>
                                    <!-- Reject button -->
                                    <form method="POST" action="admin-dashboard.php" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?php echo $row["id"]; ?>">
                                        <input type="hidden" name="new_status" value="rejected">
                                        <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                    </form>
                                <?php elseif ($row["status"] == "approved"): ?>
                                    <!-- Revoke button -->
                                    <form method="POST" action="admin-dashboard.php" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?php echo $row["id"]; ?>">
                                        <input type="hidden" name="new_status" value="rejected">
                                        <button type="submit" class="btn btn-warning btn-sm">Revoke</button>
                                    </form>
                                <?php else: ?>
                                    <!-- Re-approve button -->
                                    <form method="POST" action="admin-dashboard.php" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?php echo $row["id"]; ?>">
                                        <input type="hidden" name="new_status" value="approved">
                                        <button type="submit" class="btn btn-success btn-sm">Re-approve</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>