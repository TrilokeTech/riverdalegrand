<?php
ob_start();
// ── Riverdale Grand Chatbot Lead Handler ──
$phone      = isset($_GET["phone"])       ? trim($_GET["phone"])       : "";
$name       = isset($_GET["name"])        ? trim($_GET["name"])        : "";
$config     = isset($_GET["config"])      ? trim($_GET["config"])      : ""; // river / city-amenity facing
$budget     = isset($_GET["budget"])      ? trim($_GET["budget"])      : "";
$visit_date = isset($_GET["visit-date"])  ? trim($_GET["visit-date"])  : "";
$visit_time = isset($_GET["visit-time"])  ? trim($_GET["visit-time"])  : "";
$status_in  = isset($_GET["status"])      ? trim($_GET["status"])      : "";
$source     = "chatbot";
$email      = isset($_GET["email"])       ? trim($_GET["email"])       : "";
$debug      = isset($_GET['debug']) && $_GET['debug'] == '1';

if ($phone == "") {
    if ($debug) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'phone empty']);
    }
    exit;
}

// ── Normalize phone: produce digits-only with country code and E.164 (+) form
$phone_digits = preg_replace('/\D/', '', $phone);             // strip non-digits
$phone_digits = preg_replace('/^0+/', '', $phone_digits);     // remove leading zeros
if (strlen($phone_digits) == 10) {
    $phone_digits = '91' . $phone_digits;                      // assume India if 10 digits
}
$phone_e164 = '+' . $phone_digits;

// ── Campaign is always the same for this project ──
$campaign = 'Riverdale Grand Website Chatbot Lead';

// ── Lead status: use what the chatbot sent; fall back to date-based guess if missing ──
if ($status_in != "") {
    $lead_status = $status_in;                 // "New Lead" or "Site Visit Request"
} else {
    $lead_status = ($visit_date != "") ? 'Site Visit Request' : 'New Lead';
}

// ── Decide: is this a NEW lead (POST) or an UPDATE to an existing lead (PUT)? ──
// If visit-date or visit-time is present, we treat this as an update to a lead
// that was already created earlier (during the name/phone/email step).
$is_update = ($visit_date != "" || $visit_time != "");
$http_method = $is_update ? "PUT" : "POST";

// ── 1. Recally CRM ──
$api_key = '72aad864-9d6e-41b6-8a77-de055ccf4b7f';

$crm_url   = 'https://api.recally.co/'
     . '?api-token='    . urlencode($api_key)
     . '&name='       . urlencode($name)
     . '&email='      . urlencode($email)
    . '&mobile='     . urlencode($phone_digits)
     . '&campaign='   . urlencode($campaign)
     . '&leadstatus='     . urlencode($lead_status)
     . '&city='       . urlencode('Pune')
     . '&which-configuration-are-you-looking-for='       . urlencode($config)
     . '&date='        . urlencode($visit_date)
     . '&what-is-your-planned-budget-for-this-property=' . urlencode($budget)
     . '&time='       . urlencode($visit_time)
     . '&leadsource='                                   . urlencode($source);

$curl = curl_init($crm_url);
curl_setopt($curl, CURLOPT_URL,            $crm_url);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST,  $http_method);   // POST for new lead, PUT for update
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POSTREDIR,      1);
curl_setopt($curl, CURLOPT_HTTPHEADER,     ["Content-Type: application/x-www-form-urlencoded"]);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
$recallResponse = curl_exec($curl);
$recallHttpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$recallError    = curl_error($curl);
curl_close($curl);

// ── VAPI CALL (only for NEW leads) ──
// Invoke the voice assistant only when the lead status is "New Lead"
if ($lead_status === 'New Lead') {
    $vapiData = [
        "phoneNumberId" => "fdb3c327-ea0c-42a1-9fa6-7b68ddad4d22",
        "assistantId"   => "6a90306e-b03a-4e7c-b9df-9ba1103bfc0e",
            "customer"      => [
            "number" => $phone_e164,
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
}

// ── Debug logging / JSON output ──
$debugInfo = [
    'recally' => [
        'url' => $crm_url,
        'response' => isset($recallResponse) ? $recallResponse : null,
        'http_code' => isset($recallHttpCode) ? $recallHttpCode : null,
        'error' => isset($recallError) ? $recallError : null
    ],
    'vapi' => [
        'request' => isset($vapiData) ? $vapiData : null,
        'response' => isset($vapiResponse) ? $vapiResponse : null,
        'http_code' => isset($vapiHttpCode) ? $vapiHttpCode : null,
        'error' => isset($vapiError) ? $vapiError : null
    ]
];

@file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'debug.log', date('Y-m-d H:i:s') . ' ' . json_encode($debugInfo, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

if ($debug) {
    header('Content-Type: application/json');
    echo json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── 2. Google Sheet — disabled for now ──
// (curl block removed. Re-add later if/when sheet logging is needed.)

// ── 3. Email via Gmail SMTP — disabled for now ──
// (rg_send_mail block removed. Re-add later if/when email notifications are needed.)

exit;
?>