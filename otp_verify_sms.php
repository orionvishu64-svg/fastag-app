<?php
// otp_verify_sms.php — verifies a 2Factor SMS OTP and marks the session as verified
require_once __DIR__ . '/common_start.php';
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    // Accept JSON or form
    $in    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $phone = preg_replace('/\D/', '', $in['phone'] ?? '');
    $code  = trim($in['code'] ?? '');

    // Basic validation
    if (!preg_match('/^\d{10}$/', $phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid phone']); exit;
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid OTP']); exit;
    }

    // Must have a pending OTP session matching this phone
    $rec = $_SESSION['sms_otp'] ?? null;
    if (!$rec || ($rec['phone'] ?? '') !== $phone) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request a new OTP']); exit;
    }
    if (time() > (int)($rec['exp'] ?? 0)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'OTP expired']); exit;
    }
    if ((int)($rec['tries'] ?? 0) >= 5) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many attempts']); exit;
    }
    // increment local attempt counter
    $_SESSION['sms_otp']['tries'] = (int)($rec['tries'] ?? 0) + 1;

    // 2Factor credentials
    $apiKey = getenv('TWO_FACTOR_API_KEY');
    if (!$apiKey) {
        error_log('2F VERIFY(SMS): missing TWO_FACTOR_API_KEY');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'SMS provider not configured']); exit;
    }

    // Session id from the send step
    $sid = $rec['session_id'] ?? '';
    if ($sid === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request a new OTP']); exit;
    }

    // Call provider
    $url = "https://2factor.in/API/V1/{$apiKey}/SMS/VERIFY/{$sid}/{$code}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $http !== 200) {
        error_log("2F VERIFY(SMS) failed http={$http} err={$err} resp={$resp}");
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Verification failed']); exit;
    }

    $j = json_decode($resp, true);

    if (is_array($j) && ($j['Status'] ?? '') === 'Success') {
        // ✅ Mark phone verified in session exactly as register.php expects
        $_SESSION['phone_verified']     = $phone;     // normalized 10-digit
        $_SESSION['phone_verified_at']  = time();     // optional audit
        unset($_SESSION['sms_otp']);                  // prevent reuse
        echo json_encode(['success' => true]); exit;
    }

    // Non-success from provider
    error_log("2F VERIFY(SMS) non-success resp={$resp}");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP']); exit;

} catch (Throwable $e) {
    error_log('otp_verify_sms error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']); exit;
}
