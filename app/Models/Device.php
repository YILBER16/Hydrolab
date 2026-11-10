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
        'planted_at',
        'harvest_days',
        'last_ai_advice',
        'last_ai_source',
        'last_ai_alert_at',
        'last_ai_alert_types',
        'daily_advice',
        'daily_advice_source',
        'daily_advice_date',
        'daily_advice_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'pump_on' => 'boolean',
            'last_seen_at' => 'datetime',
            'planted_at' => 'date',
            'harvest_days' => 'integer',
            'last_ai_alert_at' => 'datetime',
            'last_ai_alert_types' => 'array',
            'daily_advice_date' => 'date',
            'daily_advice_at' => 'datetime',
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
