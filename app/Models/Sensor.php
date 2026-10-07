<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sensor extends Model
{
    protected $fillable = [
        'device_id',
        'type',
        'name',
        'unit',
        'min_threshold',
        'max_threshold',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_threshold' => 'decimal:2',
            'max_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function latestReading(): HasOne
    {
        return $this->hasOne(Reading::class)->latestOfMany('recorded_at');
    }
}
