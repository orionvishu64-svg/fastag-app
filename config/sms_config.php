<?php
function send_sms(string $phone, string $text): bool {
  $apiKey = getenv('TWO_FACTOR_API_KEY');
  if (!$apiKey) { error_log('2Factor SMS: missing TWO_FACTOR_API_KEY'); return false; }

  $raw = preg_replace('/\D/', '', $phone);
  if (!preg_match('/^\d{10}$/', $raw)) { error_log("2Factor SMS: invalid phone {$phone}"); return false; }
  $candidates = [$raw, '91'.$raw]; // try both formats

  foreach ($candidates as $num) {
    $url = "https://2factor.in/API/V1/{$apiKey}/SMS/{$num}/" . urlencode($text);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) { error_log("2Factor SMS failed curl err={$err}"); continue; }
    if ($code !== 200) { error_log("2Factor SMS failed http={$code} resp={$resp}"); continue; }

    $j = json_decode($resp, true);
    if (is_array($j) && ($j['Status'] ?? '') === 'Success') {
      error_log("2Factor SMS OK num={$num} id=".($j['Details'] ?? 'n/a'));
      return true;
    } else {
      error_log("2Factor SMS non-success resp={$resp}");
    }
  }
  return false;
}
