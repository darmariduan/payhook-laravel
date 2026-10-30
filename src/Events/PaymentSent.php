<?php

namespace PayHook\Laravel\Events;

use PayHook\Laravel\Data\PaymentEvent;

/**
 * Uang keluar terdeteksi oleh PayHook (event_type: payment.outgoing).
 * Hanya dikirim bila opsi "Teruskan juga uang keluar" aktif di aplikasi.
 */
class PaymentSent
{
    public function __construct(public readonly PaymentEvent $payment)
    {
    }
}
