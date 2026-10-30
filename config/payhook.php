<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Autentikasi
    |--------------------------------------------------------------------------
    |
    | Samakan dengan pengaturan webhook di aplikasi PayHook.
    | - auth: "bearer" (header Authorization: Bearer <token>), "api_key"
    |   (header kustom, default X-API-Key), atau "none" (TIDAK disarankan).
    | - token: token yang sama dengan isian "Bearer Token"/"API Key" di PayHook.
    |
    */

    'auth' => env('PAYHOOK_AUTH', 'bearer'),

    'token' => env('PAYHOOK_TOKEN'),

    'api_key_header' => env('PAYHOOK_API_KEY_HEADER', 'X-API-Key'),

    /*
    |--------------------------------------------------------------------------
    | Tanda tangan HMAC (sangat disarankan)
    |--------------------------------------------------------------------------
    |
    | Isi dengan "Secret Key" yang sama di PayHook. Bila diisi, setiap request
    | WAJIB membawa header X-Payhook-Timestamp + X-Payhook-Signature yang valid,
    | dan X-Payhook-Nonce dipakai untuk menolak request yang diputar ulang (replay).
    |
    */

    'secret' => env('PAYHOOK_SECRET'),

    // Selisih waktu maksimum (detik) antara jam HP dan server.
    'tolerance' => (int) env('PAYHOOK_TOLERANCE', 300),

    /*
    |--------------------------------------------------------------------------
    | Route bawaan
    |--------------------------------------------------------------------------
    |
    | POST /{prefix}/{webhook_path}   → event PaymentReceived / PaymentSent
    | POST /{prefix}/{heartbeat_path} → event HeartbeatReceived
    |
    | Matikan bila ingin mendaftarkan route sendiri dengan middleware
    | "payhook.verify" dan controller bawaan.
    |
    */

    'routes' => [
        'enabled' => env('PAYHOOK_ROUTES', true),
        'prefix' => env('PAYHOOK_ROUTE_PREFIX', 'payhook'),
        'webhook_path' => 'webhook',
        'heartbeat_path' => 'heartbeat',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotensi & penyimpanan
    |--------------------------------------------------------------------------
    |
    | PayHook bisa mengirim ulang event yang sama (retry). event_id disimpan di
    | cache selama idempotency_ttl detik sehingga listener Anda hanya dipanggil
    | sekali per event. Gunakan cache store bersama (redis/database) bila
    | aplikasi berjalan di lebih dari satu server.
    |
    */

    'cache_store' => env('PAYHOOK_CACHE_STORE'),

    'idempotency_ttl' => (int) env('PAYHOOK_IDEMPOTENCY_TTL', 60 * 60 * 24 * 7),

    // Lama kunci "sedang diproses" (detik). Harus >= waktu maksimum listener Anda
    // (mis. max_execution_time) agar event yang sama tidak diproses paralel.
    'processing_ttl' => (int) env('PAYHOOK_PROCESSING_TTL', 300),

    // Event dengan "is_test": true (tombol Test di aplikasi) secara default TIDAK
    // memicu event (dibalas 200 {"test": true}) agar tidak bisa menandai invoice lunas.
    // Set true hanya saat pengembangan; listener lalu wajib mengecek $payment->isTest.
    'dispatch_test_events' => env('PAYHOOK_DISPATCH_TEST_EVENTS', false),

    // Teruskan event uang keluar (payment.outgoing) sebagai PaymentSent.
    'dispatch_outgoing' => env('PAYHOOK_DISPATCH_OUTGOING', true),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat
    |--------------------------------------------------------------------------
    |
    | Perangkat dianggap offline bila tidak mengirim heartbeat lebih dari
    | offline_after detik. Cek dengan PayHook::isOnline($deviceId).
    |
    */

    'heartbeat' => [
        'offline_after' => (int) env('PAYHOOK_OFFLINE_AFTER', 900),
        'max_devices' => 100,

        // Aplikasi PayHook selalu mengirim heartbeat dengan Bearer token. Bila webhook
        // Anda memakai API Key/Basic, isi token heartbeat terpisah di sini.
        // Kosong = memakai PAYHOOK_TOKEN / PAYHOOK_SECRET.
        'token' => env('PAYHOOK_HEARTBEAT_TOKEN'),
        'secret' => env('PAYHOOK_HEARTBEAT_SECRET'),
    ],

    // Ukuran body maksimum (byte) yang diterima.
    'max_body_bytes' => 65536,
];
