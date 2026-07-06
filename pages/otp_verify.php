<?php
session_start();
require_once 'dbconnect.php'; // Ensure this uses your PDO $conn

if (!isset($_SESSION['temp_email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['temp_email'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');

    // Fetch user and OTP details
    $stmt = $conn->prepare("SELECT id, full_name, role, otp_code FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $u = $stmt->fetch();

    if ($u && $u['otp_code'] == $otp) {
        // SUCCESS: The user is now officially "Activated"
        
        // 1. Update the database: Set verified to TRUE
        $update = $conn->prepare("UPDATE users SET otp_verified = TRUE, otp_code = NULL WHERE id = :id");
        $update->execute([':id' => $u['id']]);

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

    } else {
        $message = "The code is incorrect. Please check your email.";
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
            <div class="alert alert-danger"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" action="otp_verify.php">

            <div class="mb-3">
                <label class="form-label">Enter OTP</label>
                <input type="text" name="otp" class="form-control" required>
            </div>

            

        <button type="submit" class="btn w-100" style="background-color: #06392f; color: white; border: none;">
    Login

            <p class="text-center mt-3">Didn't get OTP? <a href="resend_otp.php">Resend</a></p>

        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
