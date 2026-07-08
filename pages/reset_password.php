<?php
session_start();
require_once 'dbconnect.php';
require_once 'mail.php';

$step = $_GET['step'] ?? 'request';
$message = '';

if($step === 'request' && $_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = trim($_POST['email']);
    $stmt = $conn->prepare("SELECT id,full_name FROM users WHERE email=:e");
    $stmt->execute([':e'=>$email]); $user = $stmt->fetch();
    if(!$user){ $message="Email not found."; }
    else {
        $otp = rand(100000,999999);
        $conn->prepare("UPDATE users SET otp_code=:otp WHERE id=:id")->execute([':otp'=>$otp,':id'=>$user['id']]);
        sendOTPMail($email, $user['full_name'], $otp);
        header("Location: reset_password.php?step=verify&e=".urlencode($email)); exit;
    }
}

if($step === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = $_POST['email']; $otp = $_POST['otp'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=:e AND otp_code=:otp");
    $stmt->execute([':e'=>$email,':otp'=>$otp]); $u = $stmt->fetch();
    if($u) { header("Location: reset_password.php?step=reset&e=".urlencode($email)); exit; } else $message = "Invalid OTP";
}

if($step === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = $_POST['email']; $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $conn->prepare("UPDATE users SET password_hash=:p, otp_code=NULL WHERE email=:e")->execute([':p'=>$pass,':e'=>$email]);
    echo "<script>alert('Password reset. You can login.'); window.location='login.php';</script>"; exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Food Connect</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #F5EFEB, #FFF);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(6, 57, 47, 0.3);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #06392f, #06392f);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 20%;
            right: 20%;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .step {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            position: relative;
            z-index: 2;
        }
        
        .step.active {
            background: #06392F;
            color: white;
        }
        
        .step.completed {
            background: #06392f;
            color: white;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #06392f;
            box-shadow: 0 0 0 3px rgba(6, 57, 47, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #06392f, #06392f);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(6, 57, 47, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .alert-success {
            background: #e8f5e8;
            color: #06392f;
            border: 1px solid #c8e6c9;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .footer-links a {
            color: #06392f;
            text-decoration: none;
            font-size: 14px;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #7F1635; width: 33%; }
        .strength-medium { background: #D08327; width: 66%; }
        .strength-strong { background: #06392f; width: 100%; }
        
        @media (max-width: 480px) {
            .content {
                padding: 20px;
            }
            
            .header {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reset Your Password</h1>
            <p>Secure access to your Food Connect account</p>
        </div>
        
        <div class="content">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?= $step === 'request' ? 'active' : ($step === 'verify' || $step === 'reset' ? 'completed' : '') ?>">1</div>
                <div class="step <?= $step === 'verify' ? 'active' : ($step === 'reset' ? 'completed' : '') ?>">2</div>
                <div class="step <?= $step === 'reset' ? 'active' : '' ?>">3</div>
            </div>
            
            <!-- Error/Success Messages -->
            <?php if($message): ?>
                <div class="alert <?= strpos(strtolower($message), 'success') !== false ? 'alert-success' : 'alert-error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <!-- Request OTP Step -->
            <?php if($step === 'request'): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" required placeholder="Enter your registered email">
                    </div>
                    <button type="submit" class="btn">Send Verification Code</button>
                </form>
                
            <!-- Verify OTP Step -->
            <?php elseif($step === 'verify'): ?>
                <form method="POST">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($_GET['e'] ?? '') ?>">
                    <div class="form-group">
                        <label for="otp">Verification Code</label>
                        <input type="text" id="otp" name="otp" class="form-control" required placeholder=" 000000" maxlength="6" 
                               oninput="validateOTP(this)">
                    </div>
                    <button type="submit" class="btn">Verify Code</button>
                </form>
                <div class="footer-links">
                    <a href="?step=request">Resend code</a>
                </div>
                
            <!-- Reset Password Step -->
            <?php else: ?>
                <form method="POST" id="resetForm">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($_GET['e'] ?? '') ?>">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" class="form-control" required placeholder="Create a strong password" minlength="8">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrength"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Re-enter your password">
                    </div>
                    <button type="submit" class="btn">Reset Password</button>
                </form>
            <?php endif; ?>
            
            <div class="footer-links">
                <a href="login.php">← Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        // Password strength indicator
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const strengthBar = document.getElementById('passwordStrength');
            const confirmInput = document.getElementById('confirm_password');
            
            if (passwordInput && strengthBar) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    
                    if (password.length >= 8) strength++;
                    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
                    if (password.match(/\d/)) strength++;
                    if (password.match(/[^a-zA-Z\d]/)) strength++;
                    
                    strengthBar.className = 'password-strength-bar';
                    if (strength > 0) {
                        strengthBar.classList.add(
                            strength <= 2 ? 'strength-weak' : 
                            strength === 3 ? 'strength-medium' : 'strength-strong'
                        );
                    }
                });
            }
            
            // Password confirmation validation
            if (confirmInput && passwordInput) {
                confirmInput.addEventListener('input', function() {
                    if (this.value !== passwordInput.value) {
                        this.style.borderColor = '#f44336';
                    } else {
                        this.style.borderColor = '#4caf50';
                    }
                });
            }
            
            // Form validation for reset step
            const resetForm = document.getElementById('resetForm');
            if (resetForm) {
                resetForm.addEventListener('submit', function(e) {
                    const password = document.getElementById('password').value;
                    const confirm = document.getElementById('confirm_password').value;
                    
                    if (password !== confirm) {
                        e.preventDefault();
                        alert('Passwords do not match. Please try again.');
                        return false;
                    }
                    
                    if (password.length < 8) {
                        e.preventDefault();
                        alert('Password must be at least 8 characters long.');
                        return false;
                    }
                });
            }
        });
    </script>
    </body>
</html>