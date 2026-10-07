<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Reading;
use App\Models\Sensor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('History', $this->payload($request));
    }

    public function live(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    /**
     * @return array{sensors: mixed, filters: array, selectedSensor: ?array, readings: array}
     */
    private function payload(Request $request): array
    {
        $device = Device::query()->with('sensors')->first();

        $sensorOptions = $device
            ? $device->sensors->map(fn (Sensor $s) => [
                'id' => $s->id,
                'type' => $s->type,
                'name' => $s->name,
                'unit' => $s->unit,
            ])->values()
            : collect();

        $sensorId = $request->integer('sensor_id') ?: $sensorOptions->first()['id'] ?? null;
        $hours = (int) $request->integer('hours', 24);
        $hours = in_array($hours, [1, 6, 24, 168, 720], true) ? $hours : 24;

        $selectedSensor = $sensorId
            ? Sensor::query()->find($sensorId)
            : null;

        $readings = [];
        $fallback = false;

        if ($selectedSensor) {
            $query = Reading::query()
                ->where('sensor_id', $selectedSensor->id)
                ->where('recorded_at', '>=', now()->subHours($hours))
                ->orderBy('recorded_at');

            $readings = $this->mapReadings($query->get(['value', 'recorded_at']));

            if ($readings === []) {
                $fallback = true;
                $readings = $this->mapReadings(
                    Reading::query()
                        ->where('sensor_id', $selectedSensor->id)
                        ->orderByDesc('recorded_at')
                        ->limit(48)
                        ->get(['value', 'recorded_at'])
                        ->sortBy('recorded_at')
                        ->values()
                );
            }
        }

        return [
            'sensors' => $sensorOptions,
            'filters' => [
                'sensor_id' => $sensorId,
                'hours' => $hours,
            ],
            'selectedSensor' => $selectedSensor ? [
                'id' => $selectedSensor->id,
                'name' => $selectedSensor->name,
                'type' => $selectedSensor->type,
                'unit' => $selectedSensor->unit,
            ] : null,
            'readings' => $readings,
            'fallback' => $fallback,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Reading>  $readings
     * @return array<int, array{value: float, recorded_at: string}>
     */
    private function mapReadings($readings): array
    {
        return $readings
            ->map(fn (Reading $r) => [
                'value' => (float) $r->value,
                'recorded_at' => $r->recorded_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
