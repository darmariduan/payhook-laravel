<?php

namespace PayHook\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Event;
use PayHook\Laravel\Events\HeartbeatReceived;
use PayHook\Laravel\Facades\PayHook;
use PayHook\Laravel\Tests\TestCase;

class HeartbeatTest extends TestCase
{
    public function test_heartbeat_marks_device_online(): void
    {
        Event::fake([HeartbeatReceived::class]);
        $this->assertFalse(PayHook::isOnline('ph-device1'));

        $this->payhookPost('/payhook/heartbeat', [
            'type' => 'heartbeat',
            'device_id' => 'ph-device1',
            'app_version' => '1.8.0',
            'listener_connected' => true,
            'battery_optimized' => false,
            'last_event_at' => 0,
            'sent_at' => 1,
        ])->assertOk();

        Event::assertDispatched(HeartbeatReceived::class);
        $this->assertTrue(PayHook::isOnline('ph-device1'));
        $this->assertSame('1.8.0', PayHook::lastHeartbeat('ph-device1')['app_version']);
        $this->assertCount(1, PayHook::devices());
    }

    public function test_listener_disconnected_counts_as_offline(): void
    {
        $this->payhookPost('/payhook/heartbeat', [
            'type' => 'heartbeat', 'device_id' => 'ph-device2', 'listener_connected' => false,
        ])->assertOk();
        $this->assertFalse(PayHook::isOnline('ph-device2'));
    }

    public function test_heartbeat_uses_bearer_even_when_webhook_uses_api_key(): void
    {
        config(['payhook.auth' => 'api_key', 'payhook.heartbeat.token' => 'hb-token']);
        $this->payhookPost('/payhook/heartbeat', ['type' => 'heartbeat', 'device_id' => 'ph-hb3', 'listener_connected' => true],
            ['Authorization' => 'Bearer hb-token'])->assertOk();
        $this->payhookPost('/payhook/heartbeat', ['type' => 'heartbeat', 'device_id' => 'ph-hb3'],
            ['Authorization' => 'Bearer test-token'])->assertStatus(401);
    }

    public function test_device_is_remembered_after_going_quiet(): void
    {
        $this->payhookPost('/payhook/heartbeat', ['type' => 'heartbeat', 'device_id' => 'ph-old', 'listener_connected' => true])->assertOk();
        $this->travel(3)->hours();
        $this->assertFalse(PayHook::isOnline('ph-old'));
        $this->assertNotNull(PayHook::lastHeartbeat('ph-old'));
        $this->assertCount(1, PayHook::devices());
    }

    public function test_heartbeat_requires_auth(): void
    {
        $this->payhookPost('/payhook/heartbeat', ['type' => 'heartbeat', 'device_id' => 'ph-x'],
            ['Authorization' => 'Bearer salah'])->assertStatus(401);
    }
}
