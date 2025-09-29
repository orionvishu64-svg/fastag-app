<?php
function send_sms(string $phone, string $text): bool {
  $apiKey = getenv('TWO_FACTOR_API_KEY');
  if (!$apiKey) { error_log('Missing TWO_FACTOR_API_KEY'); return false; }
  $url = "https://2factor.in/API/V1/{$apiKey}/SMS/{$phone}/" . urlencode($text);
  $ch = curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($err || $code >= 400) { error_log("SMS send failed: {$code} {$err} resp={$resp}"); return false; }
  return true;
}
