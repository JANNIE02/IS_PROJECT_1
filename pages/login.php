<?php
session_start();
include '../config.php';
require_once '../pages/mail.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Basic validation
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Find user by email
        $result = pg_query_params($conn, 
            "SELECT * FROM users WHERE email = $1", 
            array($email)
        );

        $user = pg_fetch_assoc($result);

        if (!$user) {
            // No account found with that email
            $error = "No account found with that email.";
        } elseif ($user["status"] == "pending") {
            // Account exists but not approved yet
            $error = "Your account is still pending admin approval.";
        } elseif ($user["status"] == "rejected") {
            // Account was rejected
            $error = "Your account has been rejected. Contact admin.";
        } elseif (!password_verify($password, $user["password_hash"])) {
            // Wrong password
            $error = "Incorrect password.";
        } else {
            // Everything checks out - log them in
            //$_SESSION["user_id"] = $user["id"];
            //$_SESSION["user_name"] = $user["full_name"];
            //$_SESSION["user_role"] = $user["role"];

            // Send them to the right dashboard based on role
            //if ($user["role"] == "admin") {
              //  header("Location: admin-dashboard.php");
            //} elseif ($user["role"] == "donor") {
              //  header("Location: donor-dashboard.php");
            //} elseif ($user["role"] == "recipient") {
              //  header("Location: recipient-dashboard.php");
            //} elseif ($user["role"] == "rider") {
              //  header("Location: rider-dashboard.php");
            //}
            //exit();
        //}// 1. Generate a 6-digit OTP
            $otp = rand(100000, 999999);
            // Set expiry for 10 minutes from now (Postgres compatible format)
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            // 2. Save OTP to your PostgreSQL database
            // Note: Make sure you added these columns to your users table!
            $update_query = "UPDATE users SET otp_code = $1, otp_expiry = $2 WHERE id = $3";
            $update_result = pg_query_params($conn, $update_query, array($otp, $expiry, $user["id"]));

            if ($update_result) {
                // 3. Send the Email
                require 'mail.php'; // Ensure this file has your PHPMailer settings
                
                $subject = "Your Login Verification Code";
                $message = "
                    <h3>Hello, " . htmlspecialchars($user['full_name']) . "</h3>
                    <p>Your verification code for the Food Redistribution System is: <b>$otp</b></p>
                    <p>This code will expire in 10 minutes.</p>
                ";

                if (sendOTPMail($user['email'], $subject, $message)) {
                    // 4. Store user ID temporarily but NOT fully logged in yet
                    $_SESSION["temp_user_id"] = $user["id"];
                    $_SESSION["temp_user_role"] = $user["role"];
                    $_SESSION["temp_user_name"] = $user["full_name"];
                    $_SESSION["temp_email"] = $user["email"];
                    
                    // Redirect to OTP verification page
                    header("Location: otp_verify.php");
                    exit();
                } else {
                    $error = "Password correct, but failed to send OTP email. Please try again.";
                }
            } else {
                $error = "A database error occurred. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Food Redistribution System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow p-4" style="width: 100%; max-width: 450px;">
        
        <h3 class="text-center mb-4">Login</h3>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-success w-100">Login</button>

            <p class="text-center mt-3">Don't have an account? <a href="register.php">Register here</a></p>

        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>