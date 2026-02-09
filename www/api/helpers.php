<?php

function json_response(int $code, array $data): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
}

function read_json_body(): ?array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function cors($allowedOrigins): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $origins = is_array($allowedOrigins) ? $allowedOrigins : [$allowedOrigins];
    $isAllowed = $origin !== '' && in_array($origin, $origins, true);

    if ($isAllowed) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if (!$isAllowed) {
            http_response_code(403);
            exit;
        }
        http_response_code(204);
        exit;
    }
}

function digits_len(string $phone): int
{
    return strlen(preg_replace('/\D+/', '', $phone));
}

function http_post_json(string $url, array $headers, array $body, int $timeoutSeconds = 10): array
{
    $httpHeaders = [];
    foreach ($headers as $key => $value) {
        $httpHeaders[] = $key . ': ' . $value;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $httpHeaders),
            'content' => json_encode($body),
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
        ],
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? 'HTTP/1.1 500';
    preg_match('/\s(\d{3})\s?/', $statusLine, $matches);
    $statusCode = isset($matches[1]) ? (int) $matches[1] : 500;

    $json = json_decode($responseBody ?? '', true);

    return [
        'status' => $statusCode,
        'body' => $responseBody,
        'json' => is_array($json) ? $json : null,
    ];
}

function tg_send(string $token, string $chatId, string $text): void
{
    if (empty($token) || empty($chatId)) {
        return;
    }

    $url = 'https://api.telegram.org/bot' . urlencode($token) . '/sendMessage';
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded",
            'content' => http_build_query($payload),
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ];

    @file_get_contents($url, false, stream_context_create($options));
}

function safe_log(string $message): void
{
    error_log($message);
}

function extract_landing_from_url(?string $url): ?string
{
    if (empty($url)) {
        return null;
    }

    $parts = parse_url($url);
    $path = $parts['path'] ?? '';
    if ($path === '') {
        return null;
    }

    $segments = array_values(array_filter(explode('/', $path), 'strlen'));
    $landingsIndex = array_search('landings', $segments, true);
    if ($landingsIndex === false) {
        return null;
    }

    $targetIndex = $landingsIndex + 1;
    return $segments[$targetIndex] ?? null;
}
