<?php

use PHPMailer\PHPMailer\PHPMailer;
//use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


require  'C:/Apache24/htdocs/pro/Plugins/PHPMailer/vendor/autoload.php';

$mailUsername = 'sherly.mosoti@strathmore.edu';
$password = 'oksi juad idbr ytoi';

$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      
    $mail->isSMTP();                                            
    $mail->Host       = 'smtp.gmail.com';                     
    $mail->SMTPAuth   = true;                                   
    $mail->Username   = $mailUsername;                     
    $mail->Password   = $password;                               
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;            
    $mail->Port       = 587;                                    

    //Recipients
    $mail->setFrom( $mailUsername,'Weston Hotel');
    $mail->addAddress($toEmail, $toName);     
   // $mail->addAddress('ellen@example.com');              
    //$mail->addReplyTo('info@example.com', 'Information');
    //$mail->addCC('cc@example.com');
   // $mail->addBCC('bcc@example.com');

    //Attachments
    //$mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
    //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

    //Content
    $mail->isHTML(true);                                  //Set email format to HTML
    $mail->Subject = 'Food Connect - Email Verification OTP';

    $mail->isHTML(true);
        $mail->Subject = 'Food Connect — Email verification OTP';
        $mail->Body    = "<p>Hello <strong>".htmlspecialchars($toName)."</strong>,</p>
                          <p>Your verification code is: <strong>{$otp}</strong></p>
                          <p>Enter it on the verification page to activate your account.</p>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // return false if sending fails
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
        
