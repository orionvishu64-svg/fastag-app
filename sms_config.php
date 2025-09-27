<?php
function send_sms(string $phone, string $text): bool {
  $apiKey = getenv('TWO_FACTOR_API_KEY') ?: 'YOUR_API_KEY';
  $url = "https://2factor.in/API/V1/{$apiKey}/SMS/{$phone}/" . urlencode($text);
  $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
  $resp=curl_exec($ch); $err=curl_error($ch); $code=curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if($err||$code>=400){ error_log("SMS send failed: {$code} {$err} resp={$resp}"); return false; }
  return true;
}
