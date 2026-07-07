<?php
session_start();
require_once '../config.php'; // Provides $conn as a pg_connect() resource

if (!isset($_SESSION['temp_email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['temp_email'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');

    // Fetch user and OTP details
    $result = pg_query_params(
        $conn,
        "SELECT id, full_name, role, otp_code, otp_expiry FROM users WHERE email = $1 LIMIT 1",
        array($email)
    );
    $u = pg_fetch_assoc($result);

    if (!$u) {
        // Shouldn't normally happen since temp_email came from a valid login
        $message = "Something went wrong. Please log in again.";
        unset($_SESSION['temp_email']);
    } elseif (empty($u['otp_code']) || $u['otp_code'] !== $otp) {
        $message = "The code is incorrect. Please check your email.";
    } elseif (!empty($u['otp_expiry']) && strtotime($u['otp_expiry']) < time()) {
        $message = "This code has expired. Please request a new one.";
    } else {
        // SUCCESS: The user is now officially "Activated"

        // 1. Update the database: Set verified to TRUE, clear the OTP
        pg_query_params(
            $conn,
            "UPDATE users SET otp_verified = TRUE, otp_code = NULL, otp_expiry = NULL WHERE id = $1",
            array($u['id'])
        );

        // 2. Set the Full Login Session
        $_SESSION['user_id']   = $u['id'];
        $_SESSION['user_name'] = $u['full_name'];
        $_SESSION['user_role'] = $u['role'];

        // 3. Delete the temporary email ticket
        unset($_SESSION['temp_email']);

        // 4. Send them to their dashboard
        $role = strtolower($u['role']);
        header("Location: " . $role . "-dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification | Food Connect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow p-4" style="width: 100%; max-width: 450px;">

        <h3 class="text-center mb-4">OTP Verification</h3>

        <?php if ($message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="otp_verify.php">

            <div class="mb-3">
                <label class="form-label">Enter OTP</label>
                <input type="text" name="otp" class="form-control" required>
            </div>

            <button type="submit" class="btn w-100" style="background-color: #06392f; color: white; border: none;">
                Login
            </button>

            <p class="text-center mt-3">Didn't get OTP? <a href="resend_otp.php">Resend</a></p>

        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>