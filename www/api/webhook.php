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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$payload = read_json_body();
if (!is_array($payload)) {
    http_response_code(400);
    exit;
}

$orderId = $payload['orderId'] ?? '';
$amount = $payload['amount'] ?? '';
$status = strtolower((string)($payload['status'] ?? ''));
$method = $payload['method'] ?? '';
$currency = $payload['currency'] ?? '';
$createdAt = $payload['createdAt'] ?? '';

$statusIcon = '❔';
if ($status === 'received') {
    $statusIcon = '✅ PAID';
} elseif ($status === 'pending') {
    $statusIcon = '⏳ PENDING';
} elseif ($status === 'canceled') {
    $statusIcon = '❌ CANCELED';
} elseif ($status === 'timeout') {
    $statusIcon = '⌛ TIMEOUT';
}

$lines = [
    $statusIcon,
    'Order ID: ' . $orderId,
    'Amount: ' . $amount . ' ' . $currency,
    'Method: ' . $method,
];

if ($createdAt !== '') {
    $lines[] = 'Created: ' . $createdAt;
}

try {
    tg_send(TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID, implode("\n", $lines));
} catch (Throwable $e) {
    safe_log('Telegram webhook send failed: ' . $e->getMessage());
}

http_response_code(204);
