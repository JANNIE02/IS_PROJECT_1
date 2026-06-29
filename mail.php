
<?php
// mail.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

function sendOTPMail($toEmail, $toName, $otp) {
    // EDIT these with your SMTP credentials
    $mailUsername = 'agnesmwandambo22@gmail.com';
    $mailPassword = 'vvdyyrtsmzhnqsuq'; // use your app password

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailUsername;
        $mail->Password   = $mailPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($mailUsername, 'Weston Hotel');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Weston Hotel — Email verification OTP';
        $mail->Body    = "<p>Hello <strong>".htmlspecialchars($toName)."</strong>,</p>
                          <p>Your verification code is: <strong>{$otp}</strong></p>
                          <p>Enter it on the verification page to activate your account.</p>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // return false if sending fails
        return false;}
}
