<?php
require_once(__DIR__ . '/vendor/autoload.php');

// 1. REPLACE YOUR_API_KEY with your actual key from Bird.com
$apiKey = 'YOUR_API_KEY'; 
$messagebird = new \MessageBird\Client($apiKey);

try {
    $message = new \MessageBird\Objects\Message();
    
    // 2. SENDER ID: Use a name (no spaces) or a number
    $message->originator = 'MySystem'; 
    
    // 3. RECIPIENT: Change this to your actual phone number
    $message->recipients = array('+1234567890'); 
    
    $message->body = 'Hello! This is a test from my PHP system.';

    echo "Sending message...\n";
    $response = $messagebird->messages->create($message);
    
    echo "✅ Success! Message ID: " . $response->getId() . "\n";

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}