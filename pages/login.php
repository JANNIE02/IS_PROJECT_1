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
            $error = "No account found with that email.";
        } 
        elseif ($user["status"] == "pending") {
            $error = "Your account is still pending admin approval.";
        } 
        elseif ($user["status"] == "rejected") {
            $error = "Your account has been rejected. Contact admin.";
        } 
        elseif (!password_verify($password, $user["password_hash"])) {
            $error = "Incorrect password.";
        } 
        else {
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
            // PASSWORD IS CORRECT - Now checking for OTP
            

            if ($user['otp_verified'] === false || $user['otp_verified'] === 'f') {
                
                // 1. For generating OTP
                $otp = rand(100000, 999999);
                $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                // 2. Saving OTP to Postgres automatically
                $update_query = "UPDATE users SET otp_code = $1, otp_expiry = $2 WHERE id = $3";
                pg_query_params($conn, $update_query, array($otp, $expiry, $user["id"]));

                // 3. Send the Email
                if (sendOTPMail($user['email'], $user['full_name'], $otp)) {
                    $_SESSION['temp_email'] = $user['email'];
                    header("Location: otp_verify.php");
                    exit();
                } else {
                    $error = "Failed to send verification email. Please try again.";
                }
            } 
            else {
                // USER IS ALREADY VERIFIED - Log in normally
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_name"] = $user["full_name"];
                $_SESSION["user_role"] = $user["role"];

                $role = strtolower($user["role"]);
                header("Location: " . $role . "-dashboard.php");
                exit();
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
    <title>Login - Food Connect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow p-4" style="width: 100%; max-width: 450px;">
        
        <h3 class="text-center mb-4">Login</h3>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            
            <button type="submit" class="btn w-100" style="background-color: #06392f; color: white; border: none;">
    Login
</button>
            <p class="text-center mt-3">Don't have an account? <a href="register.php">Register here</a></p>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>