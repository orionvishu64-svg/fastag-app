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
