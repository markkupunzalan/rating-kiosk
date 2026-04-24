<?php
$ch = curl_init('http://localhost/rating-kiosk/api/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username'=>'admin', 'password'=>'password123']));
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
$response = curl_exec($ch);
echo "LOGIN RESPONSE: " . $response . "\n";

$ch2 = curl_init('http://localhost/rating-kiosk/api/authCheck.php');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_COOKIEFILE, 'cookies.txt');
$response2 = curl_exec($ch2);
echo "AUTH CHECK RESPONSE: " . $response2 . "\n";
