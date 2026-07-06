<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Use require_once to prevent the library itself from loading twice
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';


if (!function_exists('sendOTPMail')) {

    function sendOTPMail($email, $name, $otp) {
        
        $mailUsername = 'sherlymosot@gmail.com';
        $mailPassword = 'celpdrnqvonhlcvl'; 
       

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->SMTPDebug = 0;
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'sherlymosoti@gmail.com';
            $mail->Password   = 'celpdrnqvonhlcvl';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom($mailUsername, 'Food Connect');
            $mail->addAddress($email, $name);

            $mail->isHTML(true);
            $mail->Subject = 'Food Connect — Email verification OTP';
            $mail->Body    = "<p>Hello <strong>".htmlspecialchars($name)."</strong>,</p>
                              <p>Your verification code is: <strong>{$otp}</strong></p>
                              <p>Enter it on the verification page to activate your account.</p>";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }
} // End of function_exists check