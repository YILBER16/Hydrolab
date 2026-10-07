<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    protected $fillable = [
        'sensor_id',
        'value',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:3',
            'recorded_at' => 'datetime',
        ];
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }
}
