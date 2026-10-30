# PayHook for Laravel

Terima pembayaran otomatis dari aplikasi Android **[PayHook](https://payhook.freehost.id)** di aplikasi Laravel Anda.
PayHook mengubah notifikasi bank/e-wallet (QRIS, transfer, DANA, GoPay, BNI, BRI, dan lain-lain) menjadi webhook.
Paket ini menangani bagian server: verifikasi keamanan, anti-duplikat, dan event yang siap Anda pakai.

```php
Event::listen(function (PaymentReceived $e) {
    Invoice::where('unique_total', $e->payment->amount)->where('status', 'unpaid')->first()?->markPaid();
});
```

## Fitur

- Route siap pakai: `POST /payhook/webhook` dan `POST /payhook/heartbeat`
- Verifikasi token (Bearer, API Key, atau Basic) dengan perbandingan waktu-konstan
- Verifikasi tanda tangan **HMAC-SHA256**, toleransi waktu, dan **anti-replay** (nonce)
- **Idempotensi**: listener dipanggil **sekali per `event_id`** walaupun PayHook mengirim ulang (retry)
- Tombol **Test Webhook** di aplikasi dibalas `200` tanpa memicu event (tidak bisa menandai invoice lunas)
- Event: `PaymentReceived` (uang masuk), `PaymentSent` (uang keluar), `HeartbeatReceived`
- Pemantauan perangkat: `PayHook::isOnline($deviceId)`
- Helper nominal unik: `PayHook::uniqueAmount(100000, fn ($a) => ...)` menghasilkan misalnya `100347`

## Persyaratan

- PHP 8.2 atau lebih baru
- Laravel 11, 12, atau 13

## Instalasi

```bash
composer require darmariduan/payhook-laravel
php artisan vendor:publish --tag=payhook-config   # opsional
```

Isi `.env` dengan nilai yang **sama persis** dengan pengaturan webhook di aplikasi PayHook:

```dotenv
PAYHOOK_TOKEN=token-bearer-dari-aplikasi
PAYHOOK_SECRET=secret-key-hmac-dari-aplikasi
```

Lalu di aplikasi PayHook isi URL webhook:

```
https://domain-anda.com/payhook/webhook
```

> Bila aplikasi Anda berjalan di beberapa server, set `PAYHOOK_CACHE_STORE=redis` (atau `database`)
> agar pencegahan duplikat dan replay berlaku di semua server.

## Menangani pembayaran

Daftarkan listener (misalnya di `AppServiceProvider::boot()`):

```php
use Illuminate\Support\Facades\Event;
use PayHook\Laravel\Events\PaymentReceived;

Event::listen(function (PaymentReceived $event) {
    $p = $event->payment;

    // $p->amount        int     25000
    // $p->source        string  "BNI Merchant"
    // $p->counterparty  string  "GOPAY" (pengirim; bisa kosong)
    // $p->eventId       string  ID unik event
    // $p->packageName   string  "id.co.bni.merchant"
    // $p->raw           array   payload asli

    DB::transaction(function () use ($p) {
        $invoice = Invoice::where('unique_total', $p->amount)
            ->where('status', 'unpaid')
            ->lockForUpdate()
            ->first();

        $invoice?->update(['status' => 'paid', 'paid_event_id' => $p->eventId]);
    });
});
```

Bila listener melempar exception, paket membalas HTTP 500 sehingga PayHook **mencoba lagi** otomatis.
Event yang berhasil diproses tidak akan dipicu dua kali.

> Perlindungan duplikat disimpan di cache. Sebagai lapisan kedua, simpan `paid_event_id` dengan
> *unique index* sehingga `cache:clear` pun tidak bisa membuat invoice dibayar dua kali.

### Tombol Test di aplikasi

Secara default request uji (`"is_test": true`) dibalas `200 {"test": true}` **tanpa** memicu event,
sehingga kasir tidak bisa menandai pesanan lunas dengan tombol Test. Untuk pengembangan, set
`PAYHOOK_DISPATCH_TEST_EVENTS=true` lalu periksa `$event->payment->isTest` di listener.

### Uang keluar

Aktifkan opsi **"Teruskan juga uang keluar"** di aplikasi, lalu dengarkan `PaymentSent`:

```php
Event::listen(function (\PayHook\Laravel\Events\PaymentSent $e) {
    Expense::create(['amount' => $e->payment->amount, 'to' => $e->payment->counterparty]);
});
```

## Nominal unik

Agar setiap invoice bisa dicocokkan otomatis, tambahkan kode unik kecil ke totalnya:

```php
use PayHook\Laravel\Facades\PayHook;

$total = PayHook::uniqueAmount(100000, fn (int $amount) =>
    Invoice::where('unique_total', $amount)->where('status', 'unpaid')->exists()
);
// misalnya 100347
```

Tambahkan *unique index* pada kolom nominal unik untuk invoice yang belum dibayar (atau cek ulang di
dalam transaksi) karena dua invoice yang dibuat bersamaan bisa mendapat nominal yang sama.

## Heartbeat (pantau HP PayHook)

Di aplikasi: **Pengaturan → Heartbeat Monitoring → Tambah**, lalu isi URL `https://domain-anda.com/payhook/heartbeat`
dengan token dan secret yang sama.

Aplikasi selalu mengirim heartbeat dengan **Bearer token**, jadi token heartbeat wajib diisi. Bila webhook Anda
memakai API Key / Basic, isi `PAYHOOK_HEARTBEAT_TOKEN` (dan opsional `PAYHOOK_HEARTBEAT_SECRET`) khusus untuk heartbeat.

```php
PayHook::isOnline('ph-1a2b3c4d');     // true jika heartbeat < 15 menit dan listener aktif
PayHook::lastHeartbeat('ph-1a2b3c4d'); // ['app_version' => '1.8.0', 'received_at' => ..., ...]
PayHook::devices();                    // semua perangkat yang pernah melapor
```

Contoh peringatan terjadwal:

```php
Schedule::call(function () {
    foreach (PayHook::devices() as $d) {
        if (! PayHook::isOnline($d['device_id'])) {
            // kirim email / WhatsApp ke admin
        }
    }
})->everyFiveMinutes();
```

## Route kustom

Matikan route bawaan (`PAYHOOK_ROUTES=false`) lalu daftarkan sendiri:

```php
use PayHook\Laravel\Http\Controllers\WebhookController;

Route::post('/api/pembayaran/payhook', WebhookController::class)->middleware('payhook.verify');
```

## Konfigurasi

| ENV | Default | Keterangan |
|---|---|---|
| `PAYHOOK_AUTH` | `bearer` | `bearer`, `api_key`, `basic`, atau `none` (wajib pakai secret) |
| `PAYHOOK_TOKEN` | – | Token dari aplikasi. **Wajib** kecuali auth `none` |
| `PAYHOOK_API_KEY_HEADER` | `X-API-Key` | Nama header untuk auth `api_key` |
| `PAYHOOK_SECRET` | – | Secret HMAC. Jika diisi, signature **wajib** valid |
| `PAYHOOK_TOLERANCE` | `300` | Selisih jam maksimum (detik) |
| `PAYHOOK_ROUTES` | `true` | Daftarkan route bawaan |
| `PAYHOOK_ROUTE_PREFIX` | `payhook` | Prefix URL |
| `PAYHOOK_CACHE_STORE` | default app | Cache untuk idempotensi, nonce, dan heartbeat |
| `PAYHOOK_IDEMPOTENCY_TTL` | `604800` | Lama `event_id` diingat (detik) |
| `PAYHOOK_PROCESSING_TTL` | `300` | Kunci "sedang diproses" (≥ waktu maksimum listener) |
| `PAYHOOK_DISPATCH_TEST_EVENTS` | `false` | Picu event untuk tombol Test |
| `PAYHOOK_DISPATCH_OUTGOING` | `true` | Picu `PaymentSent` untuk uang keluar |
| `PAYHOOK_OFFLINE_AFTER` | `900` | Batas heartbeat (detik) sebelum dianggap offline |
| `PAYHOOK_HEARTBEAT_TOKEN` | = `PAYHOOK_TOKEN` | Bearer token khusus heartbeat |
| `PAYHOOK_HEARTBEAT_SECRET` | = `PAYHOOK_SECRET` | Secret HMAC khusus heartbeat |

## Keamanan

- Paket **menolak semua request** bila token belum diisi (gagal tertutup).
- Selalu isi `PAYHOOK_SECRET`. Tanpa HMAC, siapa pun yang mengetahui token bisa memalsukan pembayaran.
- Dengan HMAC aktif, request yang diputar ulang (timestamp + signature sama) ditolak `409`, walaupun header nonce diubah.
- Untuk lebih dari satu server, gunakan cache bersama (`redis`/`database`) agar idempotensi & anti-replay konsisten.
- Gunakan HTTPS. PayHook juga hanya mau mengirim ke URL `https://`.
- Jangan hanya mengandalkan nominal untuk transaksi bernilai besar. Cocokkan juga dengan mutasi rekening secara berkala.

## Pengujian

```bash
composer install
composer test
```

## Lisensi

MIT
