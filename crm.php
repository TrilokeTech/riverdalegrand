<?php

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

$phone = filter_input(INPUT_GET, 'phone', FILTER_SANITIZE_STRING);
$name  = filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING);
$email = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_EMAIL);
$debug = (isset($_GET['debug']) && $_GET['debug'] == '1');

$thankyou = 'https://riverdalegrand.duville.com/thank-you.html';

if ($phone == "") {
    if ($debug) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'phone empty']);
    }
} else {
    $url = 'https://api.recally.co/?api-token=72aad864-9d6e-41b6-8a77-de055ccf4b7f&name=' . rawurlencode($name) . '&email=' . rawurlencode($email) . '&mobile=' . rawurlencode($phone) . '&campaign=' . rawurlencode('Riverdale Grand Landing Page');

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POSTREDIR, 1);
    $headers = array();
    $headers[] = "Content-Type: application/x-www-form-urlencoded";
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($curl);
    $recallHttpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $recallError = curl_error($curl);
    curl_close($curl);

    /*
    |--------------------------------------------------------------------------
    | VAPI CALL
    |--------------------------------------------------------------------------
    */
       // Add 91 if not already present
    if (!preg_match('/^91/', $phone)) {
        $phone = '91' . $phone;
    }
    
    $vapiData = [
        "phoneNumberId" => "fdb3c327-ea0c-42a1-9fa6-7b68ddad4d22",
        "assistantId"   => "6a90306e-b03a-4e7c-b9df-9ba1103bfc0e",
        "customer"      => [
            "number" => "+" . $phone,
            "name"   => $name
        ],
        "assistantOverrides" => [
            "variableValues" => [
                "name" => $name
            ]
        ]
    ];
    $vapiCurl = curl_init();
    curl_setopt_array($vapiCurl, [
        CURLOPT_URL            => "https://api.vapi.ai/call",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($vapiData),
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer cc6b2716-7ec4-464b-a790-8652b81e1756",
            "Content-Type: application/json"
        ]
    ]);
    $vapiResponse = curl_exec($vapiCurl);
    $vapiHttpCode = curl_getinfo($vapiCurl, CURLINFO_HTTP_CODE);
    $vapiError    = curl_error($vapiCurl);
    curl_close($vapiCurl);

    $debugInfo = [
        'recally' => [
            'url' => $url,
            'response' => $resp,
            'http_code' => $recallHttpCode,
            'error' => $recallError
        ],
        'vapi' => [
            'request' => $vapiData,
            'response' => $vapiResponse,
            'http_code' => $vapiHttpCode,
            'error' => $vapiError
        ]
    ];

    // Append debug info to a log file
    $logLine = date('Y-m-d H:i:s') . " " . json_encode($debugInfo, JSON_UNESCAPED_SLASHES) . "\n";
    @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'debug.log', $logLine, FILE_APPEND | LOCK_EX);

    if ($debug) {
        header('Content-Type: application/json');
        echo json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header("Location: $thankyou");
}
?>









