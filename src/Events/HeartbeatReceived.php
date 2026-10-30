<?php

namespace PayHook\Laravel\Events;

use PayHook\Laravel\Data\Heartbeat;

class HeartbeatReceived
{
    public function __construct(public readonly Heartbeat $heartbeat)
    {
    }
}
