<?php
require_once(__DIR__ . '/../vendor/autoload.php');

$messagebird = new \MessageBird\Client('YOUR_API_KEY');
$message = new \MessageBird\Objects\Message();

$message->originator = 'Food Connect';
$message->recipients = array('+254741690058');
$message->body = 'Thank you for your donations.';
$response = $messagebird->messages->create($message);
print_r($response);

?>