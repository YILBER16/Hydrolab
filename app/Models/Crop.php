<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Crop extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'notes',
        'temperature_min',
        'temperature_max',
        'ph_min',
        'ph_max',
        'harvest_days',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'temperature_min' => 'float',
            'temperature_max' => 'float',
            'ph_min' => 'float',
            'ph_max' => 'float',
            'harvest_days' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function toProfile(): array
    {
        return [
            'label' => $this->name,
            'notes' => $this->notes ?: '',
            'temperature' => [(float) $this->temperature_min, (float) $this->temperature_max],
            'ph' => [(float) $this->ph_min, (float) $this->ph_max],
            'harvest_days' => (int) $this->harvest_days,
            'is_system' => (bool) $this->is_system,
        ];
    }

    public function toOption(): array
    {
        return [
            'id' => $this->slug,
            'db_id' => $this->id,
            'label' => $this->name,
            'notes' => $this->notes ?: '',
            'temperature' => [(float) $this->temperature_min, (float) $this->temperature_max],
            'ph' => [(float) $this->ph_min, (float) $this->ph_max],
            'harvest_days' => (int) $this->harvest_days,
            'is_system' => (bool) $this->is_system,
        ];
    }

    public static function makeSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'cultivo';
        $slug = $base;
        $i = 2;

        while (
            static::query()
                ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * Seed the built-in crops if the table is empty.
     */
    public static function ensureDefaults(): void
    {
        if (static::query()->exists()) {
            return;
        }

        (new \Database\Seeders\CropSeeder)->run();
    }
}
