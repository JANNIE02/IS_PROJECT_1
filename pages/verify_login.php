<?php
session_start();
require_once 'db_connect.php';
require_once 'mail.php'; // sendOTPMail($email,$name,$otp)

$action = $_POST['action'] ?? 'login';

if($action === 'register'){
    $name = trim($_POST['name'] ?? 'New User');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if(!$email || !$password){
        echo "<script>alert('Provide email & password'); window.location='login.php';</script>"; exit;
    }

    // check exists
    $check = $conn->prepare("SELECT id FROM users WHERE email=:email");
    $check->execute([':email'=>$email]);
    if($check->rowCount()>0){
        echo "<script>alert('Email already registered. Login instead.'); window.location='login.php';</script>"; exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $otp = rand(100000,999999);
    // store user with otp_code and otp_verified=0
    $ins = $conn->prepare("INSERT INTO users (name,email,password,role,otp_verified,otp_code,created_at) VALUES (:name,:email,:pass,'client',0,:otp,NOW())");
    $ins->execute([':name'=>$name,':email'=>$email,':pass'=>$hash,':otp'=>$otp]);

    // attempt send email
    sendOTPMail($email, $name, $otp);
    // redirect to otp page (prefill)
    header("Location: otp_verify.php?e=" . urlencode($email)); exit;
}

if($action === 'login'){
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if(!$email || !$password){ echo "<script>alert('Provide credentials'); window.location='login.php';</script>"; exit; }

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=:email LIMIT 1");
    $stmt->execute([':email'=>$email]);
    $user = $stmt->fetch();

    if(!$user){ echo "<script>alert('No account found. Please register.'); window.location='login.php';</script>"; exit; }

    if((int)$user['otp_verified'] === 0){
        // instruct to verify
        echo "<script>alert('Your email is not verified. Please enter the OTP sent to your email.'); window.location='otp_verify.php?e=".urlencode($email)."';</script>"; exit;
    }

    if(!password_verify($password, $user['password'])){
        echo "<script>alert('Invalid password'); window.location='login.php';</script>"; exit;
    }

    // login success
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    header("Location: " . ($user['role'] === 'admin' ? 'admin_dashboard.php' : 'view_room.php'));
    exit;
}

echo "Invalid action.";
