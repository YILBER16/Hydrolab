<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Support\Facades\Log;
use Throwable;

class CropAlertService
{
    public function __construct(private HydroponicAdvisorService $advisor)
    {
    }

    /**
     * Apply crop ideal ranges to the device sensors (temperature + pH for now).
     */
    public function applyCropToDevice(Device $device, string $crop): Device
    {
        $crop = $this->advisor->normalizeCrop($crop);
        $profile = HydroponicAdvisorService::crops()[$crop];

        $device->forceFill(['active_crop' => $crop])->save();

        $device->sensors()->each(function (Sensor $sensor) use ($profile) {
            if (! isset($profile[$sensor->type]) || ! is_array($profile[$sensor->type])) {
                return;
            }

            [$min, $max] = $profile[$sensor->type];

            $sensor->forceFill([
                'min_threshold' => $min,
                'max_threshold' => $max,
            ])->save();
        });

        return $device->refresh()->load('sensors.latestReading');
    }

    /**
     * After new readings, call Gemini only if monitored params are out of range.
     *
     * @param  list<array{type: string, value: float}>  $createdReadings
     */
    public function maybeAlert(Device $device, array $createdReadings = []): ?array
    {
        $device->loadMissing('sensors.latestReading');

        $watched = config('hydrolab.ai_alert_sensor_types', ['temperature', 'ph']);
        $outOfRange = [];

        foreach ($device->sensors as $sensor) {
            if (! in_array($sensor->type, $watched, true)) {
                continue;
            }

            $latest = $sensor->latestReading;
            if (! $latest) {
                continue;
            }

            $value = (float) $latest->value;
            $status = app(DashboardSnapshotService::class)->statusFor($sensor, $value);

            if (in_array($status, ['low', 'high'], true)) {
                $outOfRange[] = [
                    'type' => $sensor->type,
                    'name' => $sensor->name,
                    'value' => $value,
                    'unit' => $sensor->unit,
                    'status' => $status,
                    'min' => $sensor->min_threshold !== null ? (float) $sensor->min_threshold : null,
                    'max' => $sensor->max_threshold !== null ? (float) $sensor->max_threshold : null,
                ];
            }
        }

        if ($outOfRange === []) {
            return null;
        }

        $cooldown = max(1, (int) config('hydrolab.ai_alert_cooldown_minutes', 45));
        $types = collect($outOfRange)->pluck('type')->sort()->values()->all();
        $sameAlertRecently = $device->last_ai_alert_at
            && $device->last_ai_alert_at->greaterThan(now()->subMinutes($cooldown))
            && ($device->last_ai_alert_types ?? []) === $types;

        if ($sameAlertRecently) {
            return [
                'skipped' => true,
                'reason' => 'cooldown',
                'out_of_range' => $outOfRange,
                'advice' => $device->last_ai_advice,
                'source' => $device->last_ai_source,
            ];
        }

        try {
            $result = $this->advisor->analyze($device, $device->active_crop ?: 'general');
        } catch (Throwable $e) {
            Log::warning('Hydrolab crop AI alert failed', [
                'device_id' => $device->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        $device->forceFill([
            'last_ai_advice' => $result['text'],
            'last_ai_source' => $result['source'],
            'last_ai_alert_at' => now(),
            'last_ai_alert_types' => $types,
        ])->save();

        return [
            'skipped' => false,
            'out_of_range' => $outOfRange,
            'advice' => $result['text'],
            'source' => $result['source'],
            'created_readings' => $createdReadings,
        ];
    }
}
