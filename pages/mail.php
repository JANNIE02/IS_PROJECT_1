
<?php
// mail.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

function sendOTPMail($toEmail, $toName, $otp) {
    
    $mailUsername = 'sherlymosoti@strathmore.edu';
    $mailPassword =  'oksi juad idbr ytoi'; 

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailUsername;
        $mail->Password   = $mailPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($mailUsername, 'Food Connect');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Food Connect — Email verification OTP';
        $mail->Body    = "<p>Hello <strong>".htmlspecialchars($toName)."</strong>,</p>
                          <p>Your verification code is: <strong>{$otp}</strong></p>
                          <p>Enter it on the verification page to activate your account.</p>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        
        return false;}
}
