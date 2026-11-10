<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'name',
        'api_token',
        'location',
        'is_active',
        'last_seen_at',
        'pump_on',
        'active_crop',
        'last_ai_advice',
        'last_ai_source',
        'last_ai_alert_at',
        'last_ai_alert_types',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'pump_on' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_ai_alert_at' => 'datetime',
            'last_ai_alert_types' => 'array',
        ];
    }

    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }

    public function isOnline(int $minutes = 5): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->greaterThan(now()->subMinutes($minutes));
    }
}
