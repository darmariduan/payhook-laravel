# Changelog

## v1.0.0 - 2026-10-30

- Rilis pertama.
- Route bawaan `POST /payhook/webhook` dan `POST /payhook/heartbeat`.
- Verifikasi Bearer / API Key / Basic, HMAC-SHA256, toleransi timestamp, dan anti-replay nonce.
- Idempotensi per `event_id` (listener dipanggil sekali, aman untuk retry PayHook).
- Event `PaymentReceived`, `PaymentSent`, `HeartbeatReceived`.
- Request uji dari tombol Test tidak memicu event secara default.
- Heartbeat selalu memakai Bearer (dengan token/secret heartbeat opsional terpisah).
- Helper `PayHook::uniqueAmount()`, `PayHook::isOnline()`, `PayHook::devices()`, `PayHook::sign()`.
