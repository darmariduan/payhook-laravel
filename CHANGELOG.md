# Changelog

## v1.1.0 - 2026-11-05

- `PaymentEvent::$currency`: kode ISO 4217 dari field `currency` (PayHook v1.10+). Payload lama tanpa field ini dianggap `"IDR"`.
- `PaymentEvent::$amountDecimal`: nominal persis sebagai string (mis. `"12.5"`) dari field `amount_decimal`; payload lama = `amount`.
- `currency` yang bukan kode tiga huruf ditolak (`InvalidPayload`). `amount_decimal` yang tidak cocok dengan `amount` diabaikan.
- Sepenuhnya kompatibel: field lama tidak berubah, parameter konstruktor baru opsional.

## v1.0.0 - 2026-10-30

- Rilis pertama.
- Route bawaan `POST /payhook/webhook` dan `POST /payhook/heartbeat`.
- Verifikasi Bearer / API Key / Basic, HMAC-SHA256, toleransi timestamp, dan anti-replay nonce.
- Idempotensi per `event_id` (listener dipanggil sekali, aman untuk retry PayHook).
- Event `PaymentReceived`, `PaymentSent`, `HeartbeatReceived`.
- Request uji dari tombol Test tidak memicu event secara default.
- Heartbeat selalu memakai Bearer (dengan token/secret heartbeat opsional terpisah).
- Helper `PayHook::uniqueAmount()`, `PayHook::isOnline()`, `PayHook::devices()`, `PayHook::sign()`.
