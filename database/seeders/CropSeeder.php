<?php

namespace Database\Seeders;

use App\Models\Crop;
use Illuminate\Database\Seeder;

class CropSeeder extends Seeder
{
    public function run(): void
    {
        $crops = [
            [
                'slug' => 'general',
                'name' => 'Cultivo general',
                'notes' => 'Rangos del sistema Hydrolab. Adecuado si aún no defines el cultivo.',
                'temperature_min' => 18.0,
                'temperature_max' => 28.0,
                'ph_min' => 5.5,
                'ph_max' => 6.5,
                'harvest_days' => 40,
                'is_system' => true,
            ],
            [
                'slug' => 'lechuga',
                'name' => 'Lechuga',
                'notes' => 'Prefiere agua fresca. Temperatura ideal 18-24 °C, pH 5.5-6.5, EC 0.8-1.2 mS/cm.',
                'temperature_min' => 18.0,
                'temperature_max' => 24.0,
                'ph_min' => 5.5,
                'ph_max' => 6.5,
                'harvest_days' => 35,
                'is_system' => true,
            ],
            [
                'slug' => 'albahaca',
                'name' => 'Albahaca',
                'notes' => 'Tolera un poco más de calor. Temperatura 20-26 °C, pH 5.5-6.5, EC 1.0-1.6 mS/cm.',
                'temperature_min' => 20.0,
                'temperature_max' => 26.0,
                'ph_min' => 5.5,
                'ph_max' => 6.5,
                'harvest_days' => 32,
                'is_system' => true,
            ],
            [
                'slug' => 'tomate',
                'name' => 'Tomate cherry',
                'notes' => 'Necesita más nutrientes. Temperatura 18-26 °C, pH 5.5-6.5, EC 2.0-3.5 mS/cm.',
                'temperature_min' => 18.0,
                'temperature_max' => 26.0,
                'ph_min' => 5.5,
                'ph_max' => 6.5,
                'harvest_days' => 70,
                'is_system' => true,
            ],
        ];

        foreach ($crops as $crop) {
            Crop::query()->updateOrCreate(
                ['slug' => $crop['slug']],
                [...$crop, 'is_active' => true],
            );
        }
    }
}
