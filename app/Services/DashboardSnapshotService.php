<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Reading;
use App\Models\Sensor;

class DashboardSnapshotService
{
    /**
     * @return array{device: ?array, sensors: array, hours: int, chart: array, harvest: ?array, dailyAdvice: ?array}
     */
    public function forDevice(?Device $device, int $hours, bool $includeChart = true): array
    {
        $hours = $this->normalizeHours($hours);

        $sensors = $device
            ? $device->sensors->map(function (Sensor $sensor) {
                $latest = $sensor->latestReading;
                $value = $latest ? (float) $latest->value : null;

                $sparkline = Reading::query()
                    ->where('sensor_id', $sensor->id)
                    ->orderByDesc('recorded_at')
                    ->limit(16)
                    ->pluck('value')
                    ->reverse()
                    ->map(fn ($v) => (float) $v)
                    ->values()
                    ->all();

                return [
                    'id' => $sensor->id,
                    'type' => $sensor->type,
                    'name' => $sensor->name,
                    'unit' => $sensor->unit,
                    'value' => $value,
                    'recorded_at' => $latest?->recorded_at?->toIso8601String(),
                    'min_threshold' => $sensor->min_threshold !== null ? (float) $sensor->min_threshold : null,
                    'max_threshold' => $sensor->max_threshold !== null ? (float) $sensor->max_threshold : null,
                    'is_active' => (bool) $sensor->is_active,
                    'status' => $this->statusFor($sensor, $value),
                    'sparkline' => $sparkline,
                ];
            })->values()->all()
            : [];

        return [
            'device' => $device ? $this->devicePayload($device) : null,
            'sensors' => $sensors,
            'hours' => $hours,
            'chart' => $includeChart && $device
                ? $this->chartData($device, $hours)
                : ['labels' => [], 'series' => [], 'fallback' => false],
            'harvest' => $device ? app(HarvestProjectionService::class)->forDevice($device) : null,
            'dailyAdvice' => $device && $device->daily_advice ? [
                'text' => $device->daily_advice,
                'source' => $device->daily_advice_source,
                'date' => $device->daily_advice_date?->toDateString(),
                'generated_at' => $device->daily_advice_at?->toIso8601String(),
                'is_today' => $device->daily_advice_date?->toDateString() === now()->toDateString(),
            ] : null,
            'server_time' => now()->toIso8601String(),
        ];
    }

    public function normalizeHours(int $hours): int
    {
        return in_array($hours, [1, 6, 24, 168, 720], true) ? $hours : 24;
    }

    public function devicePayload(Device $device): array
    {
        $cropId = $device->active_crop ?: 'general';
        $crops = HydroponicAdvisorService::crops();
        $crop = $crops[$cropId] ?? $crops['general'];

        return [
            'id' => $device->id,
            'name' => $device->name,
            'location' => $device->location,
            'is_online' => $device->isOnline(),
            'is_active' => (bool) $device->is_active,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'pump_on' => $device->pump_on,
            'active_crop' => $cropId,
            'active_crop_label' => $crop['label'],
            'active_crop_notes' => $crop['notes'],
            'planted_at' => $device->planted_at?->toDateString(),
            'harvest_days' => $device->harvest_days ?: ($crop['harvest_days'] ?? 40),
            'last_ai_advice' => $device->last_ai_advice,
            'last_ai_source' => $device->last_ai_source,
            'last_ai_alert_at' => $device->last_ai_alert_at?->toIso8601String(),
            'last_ai_alert_types' => $device->last_ai_alert_types ?? [],
        ];
    }

    public function statusFor(Sensor $sensor, ?float $value): string
    {
        if ($value === null) {
            return 'unknown';
        }

        if ($sensor->min_threshold !== null && $value < (float) $sensor->min_threshold) {
            return 'low';
        }

        if ($sensor->max_threshold !== null && $value > (float) $sensor->max_threshold) {
            return 'high';
        }

        return 'ok';
    }

    public function chartData(Device $device, int $hours): array
    {
        $since = now()->subHours($hours);
        $sensorIds = $device->sensors->pluck('id');

        if ($sensorIds->isEmpty()) {
            return [
                'labels' => [],
                'series' => [],
                'fallback' => false,
            ];
        }

        $bucketMinutes = match (true) {
            $hours <= 1 => 2,
            $hours <= 6 => 10,
            $hours <= 24 => 30,
            $hours <= 168 => 180,
            default => 720,
        };

        $bucketSeconds = $bucketMinutes * 60;

        $readings = Reading::query()
            ->whereIn('sensor_id', $sensorIds)
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')
            ->get(['sensor_id', 'value', 'recorded_at']);

        $fallback = false;

        if ($readings->isEmpty()) {
            $fallback = true;
            $readings = Reading::query()
                ->whereIn('sensor_id', $sensorIds)
                ->orderByDesc('recorded_at')
                ->limit(48)
                ->get(['sensor_id', 'value', 'recorded_at'])
                ->sortBy('recorded_at')
                ->values();
        }

        $buckets = [];

        foreach ($readings as $reading) {
            $timestamp = $reading->recorded_at->copy()->setTimestamp(
                (int) (floor($reading->recorded_at->getTimestamp() / $bucketSeconds) * $bucketSeconds)
            );

            $key = $timestamp->toIso8601String();
            $buckets[$key] ??= [
                'label' => $fallback || $hours >= 168
                    ? $timestamp->format('d/m H:i')
                    : $timestamp->format('H:i'),
                'values' => [],
            ];
            $buckets[$key]['values'][$reading->sensor_id][] = (float) $reading->value;
        }

        ksort($buckets);

        $labels = array_values(array_map(fn ($bucket) => $bucket['label'], $buckets));

        $series = $device->sensors->map(function (Sensor $sensor) use ($buckets) {
            $data = [];

            foreach ($buckets as $bucket) {
                $values = $bucket['values'][$sensor->id] ?? [];
                $data[] = $values === [] ? null : round(array_sum($values) / count($values), 2);
            }

            return [
                'id' => $sensor->id,
                'type' => $sensor->type,
                'name' => $sensor->name,
                'unit' => $sensor->unit,
                'data' => $data,
            ];
        })->values()->all();

        return [
            'labels' => $labels,
            'series' => $series,
            'fallback' => $fallback,
        ];
    }
}
