<?php

namespace PayHook\Laravel\Events;

use PayHook\Laravel\Data\PaymentEvent;

/**
 * Uang masuk terdeteksi oleh PayHook (event_type: payment.incoming).
 * Listener dipanggil sekali per event_id (idempoten).
 */
class PaymentReceived
{
    public function __construct(public readonly PaymentEvent $payment)
    {
    }
}
