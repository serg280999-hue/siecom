<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$body = read_json_body();
if ($body === null) {
    json_response(400, ['ok' => false, 'error' => 'invalid_json']);
    exit;
}

$order = trim((string)($body['order'] ?? ''));
$title = trim((string)($body['title'] ?? ''));
$qtyRaw = str_replace(',', '.', trim((string)($body['qty'] ?? '')));
$priceRaw = str_replace(',', '.', trim((string)($body['price'] ?? '')));
$shipRaw = str_replace(',', '.', trim((string)($body['ship'] ?? '0')));

if ($order === '') {
    json_response(400, ['ok' => false, 'error' => 'order_is_required']);
    exit;
}
if ($title === '') {
    json_response(400, ['ok' => false, 'error' => 'title_is_required']);
    exit;
}

if (!is_numeric($qtyRaw) || (float)$qtyRaw <= 0) {
    json_response(400, ['ok' => false, 'error' => 'qty_must_be_greater_than_zero']);
    exit;
}
if (!is_numeric($priceRaw) || (float)$priceRaw < 0) {
    json_response(400, ['ok' => false, 'error' => 'price_must_be_non_negative']);
    exit;
}
if (!is_numeric($shipRaw) || (float)$shipRaw < 0) {
    json_response(400, ['ok' => false, 'error' => 'ship_must_be_non_negative']);
    exit;
}

$qty = (float)$qtyRaw;
$price = (float)$priceRaw;
$ship = (float)$shipRaw;

$total = round(($qty * $price) + $ship, 2);
$amount = number_format($total, 2, '.', '');

$hostUrl = defined('PAYMENT_HOST_URL') ? PAYMENT_HOST_URL : '';
$clientId = defined('CLIENT_ID') ? CLIENT_ID : '';
$clientSecret = defined('CLIENT_SECRET') ? CLIENT_SECRET : '';
$webhookUrl = defined('WEBHOOK_URL') ? WEBHOOK_URL : '';

if ($hostUrl === '' || $clientId === '' || $clientSecret === '' || $webhookUrl === '') {
    json_response(500, ['ok' => false, 'error' => 'server_misconfigured']);
    exit;
}

$gatewayUrl = rtrim($hostUrl, '/') . '/api/payment/createForm';
$auth = base64_encode($clientId . ':' . $clientSecret);

$gatewayResponse = http_post_json(
    $gatewayUrl,
    [
        'Authorization' => 'Basic ' . $auth,
        'Content-Type' => 'application/json',
    ],
    [
        'amount' => $amount,
        'currency' => 'EUR-GT',
        'payment_method' => 'card',
        'webhook_url' => $webhookUrl,
    ],
    12
);

$status = (int)($gatewayResponse['status'] ?? 500);
$gatewayData = $gatewayResponse['json'] ?? null;

if (!is_array($gatewayData)) {
    json_response(502, ['ok' => false, 'error' => 'payment_gateway_invalid_json']);
    exit;
}

$orderId = trim((string)($gatewayData['orderId'] ?? $gatewayData['order_id'] ?? ''));
$redirectUrl = trim((string)($gatewayData['redirect_url'] ?? ''));

if ($status >= 300 || $redirectUrl === '') {
    json_response(502, ['ok' => false, 'error' => 'payment_gateway_error']);
    exit;
}

json_response(200, [
    'ok' => true,
    'redirect_url' => $redirectUrl,
    'orderId' => $orderId,
]);
