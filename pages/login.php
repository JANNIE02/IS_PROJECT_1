<?php
include '../config.php';
session_start();

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
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["full_name"];
            $_SESSION["user_role"] = $user["role"];

            // Send them to the right dashboard based on role
            if ($user["role"] == "admin") {
                header("Location: admin-dashboard.php");
            } elseif ($user["role"] == "donor") {
                header("Location: donor-dashboard.php");
            } elseif ($user["role"] == "recipient") {
                header("Location: recipient-dashboard.php");
            } elseif ($user["role"] == "rider") {
                header("Location: rider-dashboard.php");
            }
            exit();
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