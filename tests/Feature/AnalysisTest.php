<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Reading;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_page_shows_live_temperature(): void
    {
        [$device, $sensor] = $this->makeDevice();

        Reading::query()->create([
            'sensor_id' => $sensor->id,
            'value' => 31.44,
            'recorded_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('analysis'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Analysis')
                ->where('hasLiveData', true)
                ->where('sensors.0.type', 'temperature')
                ->where('sensors.0.value', 31.44)
                ->where('geminiConfigured', false)
            );
    }

    public function test_analysis_uses_temperature_to_recommend_cooling(): void
    {
        [$device, $sensor] = $this->makeDevice();

        Reading::query()->create([
            'sensor_id' => $sensor->id,
            'value' => 31.44,
            'recorded_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->from(route('analysis'))
            ->post(route('analysis.run'), ['crop' => 'lechuga'])
            ->assertRedirect(route('analysis', ['crop' => 'lechuga']))
            ->assertSessionHas('analysis.source', 'local');

        $text = session('analysis')['text'] ?? '';

        $this->assertStringContainsString('31.44', $text);
        $this->assertStringContainsString('Temperatura alta', $text);
        $this->assertStringContainsString('Lechuga', $text);
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
            'last_seen_at' => now(),
            'pump_on' => true,
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
