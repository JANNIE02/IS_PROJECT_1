<?php
// 1. Start session and show errors
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Include the connection
require_once 'dbconnect.php'; 

// 3. Gatekeeper
if (!isset($_SESSION['temp_email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['temp_email'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');

    if (empty($otp)) {
        $message = "Please enter the OTP code.";
    } else {
        // 4. Fetch the user. 
        // NOTE: We fetch 'full_name' and 'role' NOW to avoid extra queries later.
        $stmt = $conn->prepare("SELECT id, full_name, role, otp_code FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $u = $stmt->fetch();

        // 5. Compare OTP
        if ($u && $u['otp_code'] == $otp) {
            
            // 6. Mark as verified in PostgreSQL
            $update = $conn->prepare("UPDATE users SET otp_verified = TRUE, otp_code = NULL WHERE id = :id");
            $update->execute([':id' => $u['id']]);

            // 7. SET THE LOGIN SESSION
            $_SESSION['user_id']   = $u['id'];
            $_SESSION['user_name'] = $u['full_name'];
            $_SESSION['user_role'] = $u['role'];

            // 8. Clear temp ticket
            unset($_SESSION['temp_email']);

            // 9. REDIRECT BASED ON ROLE
            $role = strtolower($u['role']); // Convert to lowercase to be safe

            if ($role == 'admin') {
                header("Location: admin-dashboard.php");
            } elseif ($role == 'donor') {
                header("Location: donor-dashboard.php");
            } elseif ($role == 'recipient') {
                header("Location: recipient-dashboard.php");
            } elseif ($role == 'rider') {
                header("Location: rider-dashboard.php");
            } else {
                // If the role doesn't match any above, show an error instead of a blank page
                die("Error: User has an unrecognized role: " . htmlspecialchars($role));
            }
            exit();

        } else {
            $message = "Wrong OTP. Please check your email and try again.";
        }
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

            

            <button type="submit" class="btn btn-success w-100">Verify OTP</button>

            <p class="text-center mt-3">Didn't get OTP? <a href="resend_otp.php">Resend</a></p>

        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
