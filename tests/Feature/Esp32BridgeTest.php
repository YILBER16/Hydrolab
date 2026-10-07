<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Reading;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Esp32BridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_can_store_temperature_and_pump_state(): void
    {
        [$device, $sensor] = $this->makeDevice();

        $response = $this->postJson('/api/v1/readings', [
            'pump_on' => true,
            'readings' => [
                ['type' => 'temperature', 'value' => 24.31],
            ],
        ], [
            'X-Device-Token' => $device->api_token,
        ]);

        $response->assertCreated()
            ->assertJsonPath('stored', 1)
            ->assertJsonPath('pump_on', true)
            ->assertJsonPath('readings.0.type', 'temperature');

        $this->assertDatabaseHas('readings', [
            'sensor_id' => $sensor->id,
        ]);

        $this->assertTrue($device->fresh()->pump_on);
        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_live_endpoint_requires_authentication(): void
    {
        $this->getJson('/dashboard/live')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_read_live_snapshot(): void
    {
        [$device, $sensor] = $this->makeDevice();

        Reading::query()->create([
            'sensor_id' => $sensor->id,
            'value' => 22.5,
            'recorded_at' => now(),
        ]);

        $device->update([
            'last_seen_at' => now(),
            'pump_on' => false,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/dashboard/live?hours=24')
            ->assertOk()
            ->assertJsonPath('device.name', $device->name)
            ->assertJsonPath('device.pump_on', false)
            ->assertJsonPath('device.is_online', true)
            ->assertJsonPath('sensors.0.type', 'temperature')
            ->assertJsonPath('sensors.0.value', 22.5);
    }

    public function test_live_snapshot_falls_back_to_latest_temperature(): void
    {
        [$device, $sensor] = $this->makeDevice();

        Reading::query()->create([
            'sensor_id' => $sensor->id,
            'value' => 25.06,
            'recorded_at' => now()->subDays(30),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/dashboard/live?hours=24')
            ->assertOk()
            ->assertJsonPath('sensors.0.type', 'temperature')
            ->assertJsonPath('sensors.0.value', 25.06)
            ->assertJsonPath('chart.fallback', true)
            ->assertJsonPath('chart.series.0.type', 'temperature');
    }

    public function test_history_live_returns_temperature_readings(): void
    {
        [$device, $sensor] = $this->makeDevice();

        Reading::query()->create([
            'sensor_id' => $sensor->id,
            'value' => 23.4,
            'recorded_at' => now()->subDays(10),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/history/live?sensor_id='.$sensor->id.'&hours=24')
            ->assertOk()
            ->assertJsonPath('fallback', true)
            ->assertJsonPath('readings.0.value', 23.4)
            ->assertJsonPath('selectedSensor.type', 'temperature');
    }

    /**
     * @return array{0: Device, 1: Sensor}
     */
    private function makeDevice(): array
    {
        $device = Device::query()->create([
            'name' => 'Hydrolab ESP32',
            'api_token' => 'hydrolab-esp32-dev-token-change-me',
            'location' => 'Laboratorio',
            'is_active' => true,
        ]);

        $sensor = Sensor::query()->create([
            'device_id' => $device->id,
            'type' => 'temperature',
            'name' => 'Temperatura',
            'unit' => '°C',
            'min_threshold' => 18,
            'max_threshold' => 28,
            'is_active' => true,
        ]);

        return [$device, $sensor];
    }
}
