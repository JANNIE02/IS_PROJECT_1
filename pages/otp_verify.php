<?php
session_start();
require_once 'dbconnect.php';

$email = $_GET['e'] ?? ($_POST['email'] ?? '');
$message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = trim($_POST['email'] ?? '');
    $otp = trim($_POST['otp'] ?? '');

    if(!$email || !$otp){
        $message = "Enter email and OTP.";
    } else {
        $stmt = $conn->prepare("SELECT id, otp_code FROM users WHERE email=:email LIMIT 1");
        $stmt->execute([':email'=>$email]);
        $u = $stmt->fetch();

        if($u && $u['otp_code'] == $otp){

            // FIXED — Use TRUE, not 1 (PostgreSQL boolean!)
            $update = $conn->prepare("
                UPDATE users 
                SET otp_verified = TRUE, otp_code = NULL 
                WHERE id = :id
            ");
            $update->execute([':id' => $u['id']]);

            echo "<script>
                    alert('Verified! You may now login.');
                    window.location='login.php?verified=1';
                  </script>";
            exit;
        } else {
            $message = "Invalid OTP or email.";
        }
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Verify OTP — Food Connect</title>
<style>
body{font-family:Poppins, sans-serif;background:linear-gradient(135deg,#0d47a1,#42a5f5);display:flex;align-items:center;justify-content:center;height:100vh;margin:0;color:#fff}
.box{background:#fff;color:#0d47a1;padding:28px;border-radius:12px;width:380px;box-shadow:0 6px 20px rgba(0,0,0,0.15)}
input{width:100%;padding:10px;margin:8px 0;border-radius:8px;border:1px solid #d3e7ff}
button{width:100%;padding:10px;background:#0d47a1;border:none;color:#fff;border-radius:8px}
.error{color:#a00}
</style>
</head>
<body>
<div class="box">
  <h3>Verify your email</h3>
  <?php if($message) echo "<div class='error'>".htmlspecialchars($message)."</div>"; ?>
  <form method="POST">
    <input type="email" name="email" placeholder="Email" required value="<?=htmlspecialchars($email)?>">
    <input type="text" name="otp" placeholder="6-digit OTP" maxlength="6" required>
    <button type="submit">Verify</button>
  </form>
  <p style="font-size:13px;color:#666">Didn't get OTP? Check spam or register again.</p>
</div>
</body>
</html>
