<?php

namespace PayHook\Laravel\Data;

use PayHook\Laravel\Exceptions\InvalidPayload;

/**
 * Payload heartbeat dari perangkat PayHook.
 */
final class Heartbeat
{
    public function __construct(
        public readonly string $deviceId,
        public readonly string $appVersion,
        public readonly bool $listenerConnected,
        public readonly bool $batteryOptimized,
        public readonly int $lastEventAt,
        public readonly int $sentAt,
        public readonly int $receivedAt,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data, int $receivedAt): self
    {
        if (($data['type'] ?? null) !== 'heartbeat') {
            throw new InvalidPayload('Bukan payload heartbeat');
        }
        $device = $data['device_id'] ?? '';
        if (! is_string($device) || preg_match('/^[A-Za-z0-9_.:-]{1,100}$/', $device) !== 1) {
            throw new InvalidPayload('device_id tidak valid');
        }
        $version = is_string($data['app_version'] ?? null) ? mb_substr($data['app_version'], 0, 20) : '';
        $version = preg_replace('/[^0-9A-Za-z._-]/', '', $version) ?? '';

        return new self(
            deviceId: $device,
            appVersion: $version,
            listenerConnected: ($data['listener_connected'] ?? false) === true,
            batteryOptimized: ($data['battery_optimized'] ?? false) === true,
            lastEventAt: is_int($data['last_event_at'] ?? null) ? max(0, $data['last_event_at']) : 0,
            sentAt: is_int($data['sent_at'] ?? null) ? max(0, $data['sent_at']) : 0,
            receivedAt: $receivedAt,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'device_id' => $this->deviceId,
            'app_version' => $this->appVersion,
            'listener_connected' => $this->listenerConnected,
            'battery_optimized' => $this->batteryOptimized,
            'last_event_at' => $this->lastEventAt,
            'sent_at' => $this->sentAt,
            'received_at' => $this->receivedAt,
        ];
    }
}
