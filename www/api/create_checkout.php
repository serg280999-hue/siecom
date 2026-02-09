<?php

declare(strict_types=1);

$configPath = __DIR__ . '/config.php';

if (!file_exists($configPath)) {
    http_response_code(500);
    echo 'Server misconfigured';
    exit;
}

require $configPath;
require __DIR__ . '/helpers.php';

$allowedOrigins = defined('ALLOWED_ORIGINS') ? ALLOWED_ORIGINS : (defined('ALLOWED_ORIGIN') ? [ALLOWED_ORIGIN] : []);
cors($allowedOrigins);

$pricesFile = defined('PRICES_FILE') ? PRICES_FILE : null;
if (!$pricesFile || !file_exists($pricesFile)) {
    http_response_code(500);
    echo 'Server misconfigured';
    exit;
}

$prices = require $pricesFile;
if (!is_array($prices) || empty($prices)) {
    http_response_code(500);
    echo 'Server misconfigured';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$body = read_json_body();
if (!$body) {
    json_response(400, ['ok' => false, 'error' => 'invalid_json']);
    exit;
}

$name = trim((string)($body['name'] ?? ''));
$phone = trim((string)($body['phone'] ?? ''));
$address = trim((string)($body['address'] ?? ''));
$quantity = (int)($body['quantity'] ?? 0);
$landing = trim((string)($body['landing'] ?? ''));
$pageUrl = isset($body['page_url']) ? (string)$body['page_url'] : '';
$utm = $body['utm'] ?? [];

if ($landing === '') {
    $landing = extract_landing_from_url($pageUrl);
}

// --- Validation (and notify Telegram even for incomplete attempts) ---
$validationErrors = [];
if ($name === '') {
    $validationErrors[] = 'missing_name';
}
if (digits_len($phone) < 7) {
    $validationErrors[] = 'invalid_phone';
}
if ($address === '') {
    $validationErrors[] = 'missing_address';
}
if ($quantity < 1) {
    $validationErrors[] = 'invalid_quantity';
}

if (!empty($validationErrors)) {
    // Send as much as we have (so you can see drop-offs / user behavior)
    $telegramLines = [
        '⚠️ Incomplete order attempt',
        'Landing: ' . ($landing !== '' ? $landing : '(unknown)'),
        'Name: ' . ($name !== '' ? $name : '(empty)'),
        'Phone: ' . ($phone !== '' ? $phone : '(empty)'),
        'Address: ' . ($address !== '' ? $address : '(empty)'),
        'Quantity: ' . ($quantity > 0 ? (string)$quantity : '(empty)'),
        'Errors: ' . implode(', ', $validationErrors),
    ];
    if ($pageUrl !== '') {
        $telegramLines[] = 'Page: ' . $pageUrl;
    }
    if (!empty($utm) && is_array($utm)) {
        $telegramLines[] = 'UTM: ' . json_encode($utm, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    // Best-effort: do not break checkout if Telegram is temporarily unavailable
    try {
        tg_send(TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID, implode("\n", $telegramLines));
    } catch (Throwable $e) {
        safe_log('TG send failed (validation): ' . $e->getMessage());
    }

    json_response(400, ['ok' => false, 'error' => 'validation_error']);
    exit;
}

if ($landing === '' || $landing === null) {
    json_response(400, ['ok' => false, 'error' => 'missing_landing']);
    exit;
}

if (!array_key_exists($landing, $prices)) {
    json_response(400, ['ok' => false, 'error' => 'unknown_landing']);
    exit;
}

$amountCents = (int) $prices[$landing] * $quantity;
$amountStr = number_format($amountCents / 100, 2, '.', '');

$rateDir = __DIR__ . '/ratelimit';
if (!is_dir($rateDir)) {
    mkdir($rateDir, 0755, true);
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = $rateDir . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $ip);
$now = time();
if (file_exists($rateFile)) {
    $last = (int)file_get_contents($rateFile);
    if ($last && ($now - $last) < RATE_LIMIT_SECONDS) {
        json_response(429, ['ok' => false, 'error' => 'rate_limited']);
        exit;
    }
}
file_put_contents($rateFile, (string)$now);

$auth = base64_encode(CLIENT_ID . ':' . CLIENT_SECRET);
$gatewayUrl = rtrim(PAYMENT_HOST_URL, '/') . '/api/payment/createForm';

$gatewayResponse = http_post_json(
    $gatewayUrl,
    [
        'Authorization' => 'Basic ' . $auth,
        'Content-Type' => 'application/json',
    ],
    [
        'amount' => $amountStr,
        'currency' => PAYMENT_CURRENCY,
        'payment_method' => PAYMENT_METHOD,
        'webhook_url' => WEBHOOK_URL,
    ],
    15
);

$gatewayData = $gatewayResponse['json'] ?? null;

$orderId = '';
$redirectUrl = '';

if (is_array($gatewayData)) {
    $orderId = (string)($gatewayData['order_id'] ?? $gatewayData['orderId'] ?? '');
    $redirectUrl = (string)($gatewayData['redirect_url'] ?? '');
}

if (($gatewayResponse['status'] ?? 500) >= 300 || $orderId === '' || $redirectUrl === '') {
    safe_log('Payment gateway error: ' . ($gatewayResponse['body'] ?? ''));
    json_response(502, ['ok' => false, 'error' => 'payment_gateway_error']);
    exit;
}

$telegramLines = [
    '🧾 New order',
    'Landing: ' . $landing,
    'Order ID: ' . $orderId,
    'Name: ' . $name,
    'Phone: ' . $phone,
    'Address: ' . $address,
    'Quantity: ' . $quantity,
    'Amount: ' . $amountStr . ' ' . PAYMENT_CURRENCY,
];

if ($pageUrl !== '') {
    $telegramLines[] = 'Page: ' . $pageUrl;
}
if (!empty($utm) && is_array($utm)) {
    $telegramLines[] = 'UTM: ' . json_encode($utm, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

tg_send(TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID, implode("\n", $telegramLines));

json_response(200, [
    'ok' => true,
    'order_id' => $orderId,
    'redirect_url' => $redirectUrl,
]);
