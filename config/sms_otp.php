<?php
// config/sms_otp.php
// Unified SMS OTP handler for Signup, Phone Verification, and mPIN Reset.

// === Configuration ===
$DIGIMATE_SECRET_KEY = getenv('DIGIMATE_SECRET_KEY') ?: 'GV@Secure9044';
$DIGIMATE_ENDPOINT   = getenv('DIGIMATE_SEND_ENDPOINT') ?: 'https://www.apnapayment.com/api/agent/otp/sendOtp';
$LOG_FILE            = __DIR__ . '/../logs/sms_otp.log';
$SIMULATE_MODE       = getenv('SIMULATE_DIGIMATE') === '1';

// --- Utility: log helper ---
function sms_write_log($msg) {
    global $LOG_FILE;
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($LOG_FILE, "[$ts] $msg\n", FILE_APPEND | LOCK_EX);
}

/**
 * Send OTP via provider or simulate.
 */
function sms_send_provider($mobile_clean, $otp) {
    global $DIGIMATE_ENDPOINT, $DIGIMATE_SECRET_KEY, $SIMULATE_MODE;

    if ($SIMULATE_MODE) {
        sms_write_log("SIMULATE: would send OTP {$otp} to {$mobile_clean}");
        return ['success' => true, 'simulated' => true, 'response' => 'simulated'];
    }

    $payload = json_encode(['mobile' => $mobile_clean, 'otp' => (string)$otp]);
    $ch = curl_init($DIGIMATE_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Secret-Key: ' . $DIGIMATE_SECRET_KEY
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) return ['success' => false, 'error' => $err];

    $decoded = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE) $decoded = $resp;

    return [
        'success' => ($code >= 200 && $code < 300),
        'http_code' => $code,
        'response' => $decoded
    ];
}

/**
 * Generate OTP, store hashed in session, return plain OTP.
 */
function sms_generate_and_store_otp($mobile_clean, $ttl = 300) {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $otp = random_int(100000, 999999);
    $_SESSION['otp_' . $mobile_clean] = password_hash((string)$otp, PASSWORD_DEFAULT);
    $_SESSION['otp_exp_' . $mobile_clean] = time() + $ttl;
    sms_write_log("Generated OTP for {$mobile_clean} (ttl {$ttl}s)");
    return $otp;
}

/**
 * Verify stored OTP for a phone.
 */
function sms_verify_stored_otp($mobile_clean, $otp_given) {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $hash_key = 'otp_' . $mobile_clean;
    $exp_key  = 'otp_exp_' . $mobile_clean;
    $stored   = $_SESSION[$hash_key] ?? null;
    $exp      = $_SESSION[$exp_key] ?? 0;

    if (!$stored || time() > $exp)
        return ['success' => false, 'error' => 'otp_expired_or_not_found'];

    if (password_verify((string)$otp_given, $stored)) {
        unset($_SESSION[$hash_key], $_SESSION[$exp_key]);
        sms_write_log("OTP verified for {$mobile_clean}");
        return ['success' => true];
    }

    sms_write_log("OTP verification failed for {$mobile_clean}");
    return ['success' => false, 'error' => 'invalid_otp'];
}

// === Endpoint Handler (only when called directly) ===
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    // JSON response helper
    function json_response($code, $data) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    $raw = file_get_contents('php://input');
    if (!$raw) json_response(400, ['success' => false, 'error' => 'empty request']);
    $input = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE)
        json_response(400, ['success' => false, 'error' => 'invalid JSON']);

    $action = $input['action'] ?? 'send_otp';
    $mobile = $input['mobile'] ?? null;
    if (!$mobile)
        json_response(400, ['success' => false, 'error' => 'missing mobile']);

    $mobile_clean = preg_replace('/\D+/', '', $mobile);
    if (strlen($mobile_clean) < 9)
        json_response(400, ['success' => false, 'error' => 'invalid mobile number']);

    // ---- SEND OTP ----
    if ($action === 'send_otp') {
        $otp = sms_generate_and_store_otp($mobile_clean);
        $provider = sms_send_provider($mobile_clean, $otp);

        if (isset($provider['error'])) {
            sms_write_log("Provider error: " . $provider['error']);
            json_response(502, ['success' => false, 'error' => 'provider_error', 'detail' => $provider['error']]);
        }

        if ($provider['success']) {
            json_response(200, ['success' => true, 'message' => 'otp_sent']);
        } else {
            json_response(502, ['success' => false, 'error' => 'provider_failed', 'detail' => $provider]);
        }
    }

    // ---- VERIFY OTP ----
    elseif ($action === 'verify_otp') {
        $otp_input = $input['otp'] ?? null;
        if (!$otp_input)
            json_response(400, ['success' => false, 'error' => 'missing otp']);

        $v = sms_verify_stored_otp($mobile_clean, $otp_input);
        if ($v['success']) {
            if (session_status() === PHP_SESSION_NONE) session_start();

            // Default: mark phone as verified (signup / collect_phone)
            $_SESSION['phone_verified'] = $mobile_clean;

            // If caller requested purpose 'reset' -> set separate flag for mPIN reset flow
            if (!empty($input['purpose']) && $input['purpose'] === 'reset') {
                $_SESSION['reset_phone_verified'] = $mobile_clean;
            }

            json_response(200, ['success' => true, 'message' => 'otp_verified']);
        } else {
            json_response(400, $v);
        }
    }

    // ---- INVALID ----
    else {
        json_response(400, ['success' => false, 'error' => 'invalid_action']);
    }
}
?>
