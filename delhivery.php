<?php
define("DELHIVERY_API_TOKEN", "YOUR_DELVIVERY_TOKEN");
define("DELHIVERY_ORIGIN_PINCODE", "110001"); // change to your warehouse pincode

/**
 * Calculate shipping cost (Delhivery API)
 */
function delhivery_calculate_shipping($to_pincode) {
    $api_url = "https://track.delhivery.com/api/kinko/v1/invoice/charges/.json";

    $params = [
        "md"    => "Prepaid",                 // ✅ always prepaid
        "o_pin" => DELHIVERY_ORIGIN_PINCODE,
        "d_pin" => $to_pincode
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Token " . DELHIVERY_API_TOKEN]);
    $resp = curl_exec($ch);

    if ($resp === false) {
        error_log("Delhivery calculate_shipping curl error: " . curl_error($ch));
        curl_close($ch);
        return 0;
    }

    curl_close($ch);
    $data = json_decode($resp, true);

    if (isset($data['charges']['total_amount'])) {
        return (float)$data['charges']['total_amount'];
    }

    error_log("Delhivery calculate_shipping invalid response: " . substr($resp, 0, 300));
    return 0;
}

/**
 * Create shipment in Delhivery
 */
function delhivery_create_shipment($order_id, $address, $items, $amount) {
    $api_url = "https://track.delhivery.com/api/cmu/create.json";

    // build product description with quantities
    $desc = [];
    foreach ($items as $it) {
        $qty = isset($it['quantity']) ? intval($it['quantity']) : 1;
        $desc[] = ($it['product_name'] ?? 'Item') . " x" . $qty;
    }

    $payload = [
        "shipments" => [[
            "add"           => ($address['house_no'] ?? '') . ", " . ($address['landmark'] ?? '') . ", " . ($address['city'] ?? ''),
            "pin"           => $address['pincode'],
            "phone"         => $address['phone'] ?? '',
            "name"          => $address['name'] ?? 'Customer',
            "order"         => (string)$order_id,
            "payment_mode"  => "Prepaid",       // ✅ both methods are prepaid
            "cod_amount"    => 0,               // ✅ no COD in your system
            "products_desc" => implode(", ", $desc),
            "total_amount"  => $amount
        ]]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Token " . DELHIVERY_API_TOKEN,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $resp = curl_exec($ch);

    if ($resp === false) {
        error_log("Delhivery create_shipment curl error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);
    $data = json_decode($resp, true);
    if (!is_array($data)) {
        error_log("Delhivery create_shipment invalid JSON: " . substr($resp, 0, 300));
        return null;
    }

    return $data;
}

/**
 * Get shipping label / AWB PDF for a given AWB
 * Returns assoc decoded JSON or null on failure.
 */
function delhivery_get_label($awb) {
    // NOTE: Delhivery has different label endpoints; this is a recommended pattern.
    // Replace with the exact endpoint from your Delhivery account/docs if different.
    $api_url = "https://track.delhivery.com/api/packages/labels/"; // example base

    // Some Delhivery APIs expect AWB in path or body; we'll try POST to /api/packages/labels/
    $payload = ["package_ids" => [$awb]];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Token " . DELHIVERY_API_TOKEN,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $resp = curl_exec($ch);
    if ($resp === false) {
        error_log("delhivery_get_label curl error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        error_log("delhivery_get_label invalid JSON: " . substr($resp,0,300));
        return null;
    }
    return $data;
}

/**
 * Create a manifest for a list of AWBs (or by date)
 * $awbs: array of AWB strings. Returns decoded JSON or null.
 */
function delhivery_create_manifest($awbs = []) {
    $api_url = "https://track.delhivery.com/api/p/manifest/create.json"; // example
    $payload = [
        "manifest" => [
            "awb_list" => $awbs
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Token " . DELHIVERY_API_TOKEN,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $resp = curl_exec($ch);
    if ($resp === false) {
        error_log("delhivery_create_manifest curl error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        error_log("delhivery_create_manifest invalid JSON: " . substr($resp,0,500));
        return null;
    }
    return $data;
}

/**
 * Cancel a shipment by AWB
 * Returns decoded JSON or null.
 */
function delhivery_cancel_shipment($awb) {
    $api_url = "https://track.delhivery.com/api/cmu/cancel.json"; // example
    $payload = ["shipments" => [["waybill" => $awb]]];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Token " . DELHIVERY_API_TOKEN,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    if ($resp === false) {
        error_log("delhivery_cancel_shipment curl error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        error_log("delhivery_cancel_shipment invalid JSON: " . substr($resp,0,300));
        return null;
    }
    return $data;
}

 // pincode serviceability
function delhivery_check_pincode($pincode) {
    $api_key = DELHIVERY_API_KEY; // define in your config
    $url = "https://track.delhivery.com/c/api/pin-codes/json/?token={$api_key}&pincode=" . urlencode($pincode);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Token $api_key"]
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'message' => "CURL Error: $err"];
    }

    $data = json_decode($resp, true);
    if (isset($data['delivery_codes'][0]['postal_code'])) {
        return ['success' => true, 'data' => $data['delivery_codes'][0]['postal_code']];
    } else {
        return ['success' => false, 'message' => 'Invalid response from Delhivery', 'raw' => $resp];
    }
}

/**
 * Fetch expected TAT from Delhivery between origin and destination pincodes.
 *
 * @param string $origin_pin  Origin pincode (warehouse)
 * @param string $dest_pin    Destination pincode (customer)
 * @param float|int $weight_kg Chargeable weight in kg (optional, default 1)
 * @param string $mode        Mode, e.g. 'E' (Express) or 'S' (Standard) - depends on your Delhivery plan
 * @return array ['success'=>bool, 'tat'=>int|null, 'expected_date'=>string|null, 'raw'=>mixed, 'message'=>string?]
 */
function delhivery_get_tat($origin_pin, $dest_pin, $weight_kg = 1.0, $mode = 'E') {
    // Use your token config. Define DELHIVERY_API_TOKEN in config or environment.
    $token = defined('DELHIVERY_API_TOKEN') ? DELHIVERY_API_TOKEN : (getenv('DELHIVERY_API_TOKEN') ?: '');
    if (!$token) {
        return ['success'=>false, 'message'=>'Delhivery API token not configured'];
    }

    $o_pin = urlencode($origin_pin);
    $d_pin = urlencode($dest_pin);
    $cgm = urlencode($weight_kg);
    $md = urlencode($mode);

    // Example endpoint — adjust if Delhivery provides a different TAT endpoint for your account
    $url = "https://track.delhivery.com/api/kinko/v1/invoice/charges/.json?o_pin={$o_pin}&d_pin={$d_pin}&cgm={$cgm}&md={$md}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Token {$token}",
            "Accept: application/json"
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        return ['success'=>false, 'message'=>"CURL error: $err"];
    }
    if ($http_code < 200 || $http_code >= 300) {
        return ['success'=>false, 'message'=>"HTTP {$http_code} from Delhivery", 'raw'=>$resp];
    }

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        return ['success'=>false, 'message'=>'Invalid JSON from Delhivery', 'raw'=>$resp];
    }

    // Try to pick tat / expected delivery if present (response formats vary)
    $tat = null;
    $expected_date = null;
    // common shapes: data['delivery_codes'][0]['postal_code']['tat'] etc.
    if (!empty($data['delivery_codes'][0]['postal_code'] ?? null)) {
        $pc = $data['delivery_codes'][0]['postal_code'];
        if (!empty($pc['tat'])) $tat = (int)$pc['tat'];
        if (!empty($pc['expected_delivery_date'])) $expected_date = $pc['expected_delivery_date'];
    }

    // alternative keys
    if ($tat === null) {
        // search entire payload for tat-like keys
        $flat = json_encode($data);
        if (preg_match('/"tat"\s*:\s*([0-9]+)/i', $flat, $m)) $tat = (int)$m[1];
        if (preg_match('/"expected_delivery_date"\s*:\s*"([^"]+)"/i', $flat, $m2)) $expected_date = $m2[1];
    }

    return ['success'=>true, 'tat'=> $tat, 'expected_date'=>$expected_date, 'raw'=>$data];
}
