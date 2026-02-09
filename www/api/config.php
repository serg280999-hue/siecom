<?php
// Beispielkonfiguration für Aviagram Checkout

define('PAYMENT_HOST_URL', 'https://avia.app');
define('PAYMENT_CURRENCY', 'EUR-LP');
define('PAYMENT_METHOD', 'card');
define('CLIENT_ID', '2a4a4dc235697b51f1b62a63bf7aba35');
define('CLIENT_SECRET', 'fb521c45aa0fab706f0f77ac4929e44');
define('WEBHOOK_URL', 'https://omniklad.com/api/webhook.php');
define('TELEGRAM_BOT_TOKEN', '8363138226:AAFoxyk5pm3Z859AC_A');
define('TELEGRAM_CHAT_ID', '-1003511');
define('RATE_LIMIT_SECONDS', 5);
define('ENV', 'prod');

define('PRICES_FILE', __DIR__ . '/prices.php');
define('ALLOWED_ORIGINS', ['https://omniklad.com']);
