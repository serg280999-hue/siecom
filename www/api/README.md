# API Integration (Aviagram, no DB)

## Dateien
- `create_checkout.php` – startet Zahlung und legt Rate-Limit pro IP an (`api/ratelimit/`).
- `webhook.php` – принимает статус платежа и отправляет уведомление в Telegram.
- `config.php` – локальная конфигурация (не коммитится). Используйте `config.php.example` как шаблон.
- `prices.php` – карта цен по лендингам (в евроцентах), подключается из `config.php` через `PRICES_FILE`.

## Schnelltest (ohne echte Secrets)
1. Checkout anstoßen (ersetzt HOST):
   ```bash
   curl -X POST https://YOUR-PHP-DOMAIN/api/create_checkout.php \
     -H "Content-Type: application/json" \
     -d '{
       "name": "Max Mustermann",
       "phone": "+49 151 2345678",
       "address": "Berlin, Teststr. 1",
       "quantity": 1,
      "landing": "lp-003",
      "page_url": "https://omniklad.com/landings/lp-003/",
      "utm": {"source":"test","campaign":"demo"}
    }'
  ```

2. Webhook payload simulieren:
   ```bash
   curl -X POST https://YOUR-PHP-DOMAIN/api/webhook.php \
     -H "Content-Type: application/json" \
     -d '{
       "orderId": "demo-123",
       "amount": "499",
       "status": "received",
       "method": "card",
       "currency": "EUR-GT",
       "type": "payment",
       "createdAt": "2025-12-13T10:00:00Z"
     }'
   ```

## Neuen Landing hinzufügen
- Ordner `landings/lp-XXX` erstellen und фронт отправляет URL с сегментом `lp-XXX`.
- В `api/prices.php` добавить строку `"lp-XXX" => 12345` (цена в евроцентах).
- Задеплоить обновлённый `prices.php` (и при необходимости фронт), без БД и сторонних зависимостей.
