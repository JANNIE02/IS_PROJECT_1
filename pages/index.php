<?php
session_start();
require_once 'dbconnect.php'; // Ensure this matches your filename

// Security: If there is no email in session, they shouldn't be here
// (Assuming you set $_SESSION['temp_email'] in your login/register page)
if (!isset($_SESSION['temp_email'])) {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['temp_email'];
$message = '';
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');

    if (!$otp) {
        $message = "Please enter the verification code.";
        $error = true;
    } else {
        // To find user by use of  email and OTP
        $stmt = $conn->prepare("SELECT id, otp_code FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $u = $stmt->fetch();

        if ($u && $u['otp_code'] === $otp) {
            // Verified - Update PostgreSQL boolean
            $update = $conn->prepare("
                UPDATE users 
                SET otp_verified = TRUE, otp_code = NULL 
                WHERE id = :id
            ");
            $update->execute([':id' => $u['id']]);

            // Clear temp session
            unset($_SESSION['temp_email']);

            echo "<script>
                    alert('Email verified successfully!');
                    window.location='login.php?verified=1';
                  </script>";
            exit;
        } else {
            $message = "The code you entered is invalid or expired.";
            $error = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verify Identity - Food Connect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f4f7fa; 
            color: #1e293b; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 20px;
        }
        
        .container { 
            width: 100%; 
            max-width: 450px; 
            background: white; 
            border-radius: 24px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.1); 
            padding: 40px; 
            text-align: center;
        }

        .logo-text { font-size: 2rem; font-weight: 700; margin-bottom: 10px; }
        .logo-text span { color: #06392f; }
        
        h1 { font-size: 1.5rem; margin-bottom: 10px; color: #0f172a; }
        p { color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-bottom: 25px; }
        .email-display { font-weight: 600; color: #1e293b; }

        .otp-input {
            width: 100%;
            padding: 15px;
            font-size: 1.5rem;
            letter-spacing: 8px;
            text-align: center;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 20px;
            outline: none;
            transition: 0.2s;
        }

        .otp-input:focus { border-color: #06392f; background: #f0fdf4; }

        .btn-verify {
            width: 100%;
            padding: 15px;
            background: #06392f;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-verify:hover { background: #042d25; transform: translateY(-2px); }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .error-msg { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }

        .back-link {
            display: block;
            margin-top: 25px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .back-link:hover { color: #06392f; }
        
        .footer-text { margin-top: 30px; font-size: 0.8rem; color: #94a3b8; }
    </style>
</head>
<body>

<div class="container">
    <div class="logo-text">Food<span>Connect</span></div>
    
    <h1>Verify Your Account</h1>
    <p>We've sent a 6-digit code to <br><span class="email-display"><?php echo htmlspecialchars($email); ?></span></p>

    <?php if($message): ?>
        <div class="message <?php echo $error ? 'error-msg' : ''; ?>">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" 
               name="otp" 
               class="otp-input" 
               placeholder="000000" 
               maxlength="6" 
               pattern="\d{6}" 
               required 
               autocomplete="one-time-code">
        
        <button type="submit" class="btn-verify">Verify Account</button>
    </form>

    <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to login</a>

    <div class="footer-text">
      &copy; <?php echo date("Y"); ?> Food Connect &bull; Security Verification
    </div>
</div>

</body>
</html>