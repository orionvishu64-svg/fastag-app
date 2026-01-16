<?php
// socket_emit.php
function socket_emit($room, $event, $payload_assoc) {
    $url = "http://127.0.0.1:3000/emit";
    $token = 'APSFASTAG1234'; // <-- must match systemd SOCKET_API_TOKEN
    $body = json_encode([
        'token' => $token,
        'room'  => $room,
        'event' => $event,
        'payload'=> $payload_assoc
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'Content-Length: ' . strlen($body)
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    return [$resp, $err];
}
?>
