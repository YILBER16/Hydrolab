<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        $token = config('hydrolab.device_token') ?: Str::random(40);

        $device = Device::query()->updateOrCreate(
            ['name' => 'Hydrolab ESP32'],
            [
                'api_token' => $token,
                'location' => 'Laboratorio',
                'is_active' => true,
            ]
        );

        $sensors = [
            [
                'type' => 'temperature',
                'name' => 'Temperatura',
                'unit' => '°C',
                'min_threshold' => 18,
                'max_threshold' => 28,
            ],
            [
                'type' => 'humidity',
                'name' => 'Humedad',
                'unit' => '%',
                'min_threshold' => 40,
                'max_threshold' => 80,
            ],
            [
                'type' => 'ph',
                'name' => 'pH',
                'unit' => 'pH',
                'min_threshold' => 5.5,
                'max_threshold' => 6.5,
            ],
            [
                'type' => 'ec',
                'name' => 'Conductividad (EC)',
                'unit' => 'mS/cm',
                'min_threshold' => 1.0,
                'max_threshold' => 2.5,
            ],
            [
                'type' => 'water_level',
                'name' => 'Nivel de agua',
                'unit' => '%',
                'min_threshold' => 20,
                'max_threshold' => 100,
            ],
        ];

        foreach ($sensors as $sensor) {
            Sensor::query()->updateOrCreate(
                [
                    'device_id' => $device->id,
                    'type' => $sensor['type'],
                ],
                [
                    'name' => $sensor['name'],
                    'unit' => $sensor['unit'],
                    'min_threshold' => $sensor['min_threshold'],
                    'max_threshold' => $sensor['max_threshold'],
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info("Device token: {$device->api_token}");
    }
}
