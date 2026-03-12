<?php
$ch = curl_init("http://localhost:8080/auth/login");
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "OPTIONS");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Origin: http://localhost:8081',
    'Access-Control-Request-Method: POST',
    'Access-Control-Request-Headers: Content-Type, Authorization'
]);
$response = curl_exec($ch);
if ($response === false) {
    echo "CURL ERROR: " . curl_error($ch) . "\n";
    exit(1);
}
$info = curl_getinfo($ch);
echo "HTTP_CODE: " . $info['http_code'] . "\n";
echo "HEADERS+BODY:\n";
echo $response;
curl_close($ch);
