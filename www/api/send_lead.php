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
$landing = trim((string)($body['landing'] ?? ''));
$pageUrl = isset($body['page_url']) ? (string)$body['page_url'] : '';
$utm = $body['utm'] ?? [];

if ($landing === '') {
    $landing = extract_landing_from_url($pageUrl);
}

$validationErrors = [];
if ($name === '') {
    $validationErrors[] = 'missing_name';
}
if (digits_len($phone) < 7) {
    $validationErrors[] = 'invalid_phone';
}
if ($landing !== 'lp-003sl') {
    $validationErrors[] = 'invalid_landing';
}

if (!empty($validationErrors)) {
    json_response(400, ['ok' => false, 'error' => 'validation_error']);
    exit;
}

$telegramLines = [
    '📞 New contact request',
    'Landing: ' . $landing,
    'Name: ' . $name,
    'Phone: ' . $phone,
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
    'redirect_url' => '../lp-003sl/thankyou.html',
]);
