<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Reading;
use App\Services\CropAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class ReadingController extends Controller
{
    public function ping(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $device->update(['last_seen_at' => now()]);

        return response()->json([
            'message' => 'Hydrolab bridge OK',
            'device' => $device->name,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function store(Request $request, CropAlertService $cropAlerts): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $validated = $request->validate([
            'recorded_at' => ['nullable', 'date'],
            'pump_on' => ['nullable', 'boolean'],
            'readings' => ['required', 'array', 'min:1'],
            'readings.*.type' => ['required', 'string'],
            'readings.*.value' => ['required', 'numeric'],
        ]);

        $recordedAt = isset($validated['recorded_at'])
            ? Carbon::parse($validated['recorded_at'])
            : now();

        $sensors = $device->sensors()
            ->where('is_active', true)
            ->get()
            ->keyBy('type');

        $created = [];
        $unknown = [];

        DB::transaction(function () use ($request, $validated, $sensors, $recordedAt, $device, &$created, &$unknown) {
            foreach ($validated['readings'] as $item) {
                $sensor = $sensors->get($item['type']);

                if (! $sensor) {
                    $unknown[] = $item['type'];
                    continue;
                }

                $reading = Reading::create([
                    'sensor_id' => $sensor->id,
                    'value' => $item['value'],
                    'recorded_at' => $recordedAt,
                ]);

                $created[] = [
                    'type' => $sensor->type,
                    'value' => (float) $reading->value,
                    'unit' => $sensor->unit,
                    'recorded_at' => $reading->recorded_at->toIso8601String(),
                ];
            }

            $device->forceFill([
                'last_seen_at' => now(),
                ...($request->exists('pump_on') ? ['pump_on' => (bool) $validated['pump_on']] : []),
            ])->save();
        });

        if ($created === [] && $unknown !== []) {
            throw ValidationException::withMessages([
                'readings' => ['No known sensors matched. Unknown types: '.implode(', ', $unknown)],
            ]);
        }

        $device->refresh();

        $alert = $cropAlerts->maybeAlert($device, $created);

        return response()->json([
            'message' => 'Readings stored.',
            'stored' => count($created),
            'readings' => $created,
            'unknown_types' => $unknown,
            'pump_on' => $device->pump_on,
            'device_online' => $device->isOnline(),
            'active_crop' => $device->active_crop,
            'ai_alert' => $alert,
        ], 201);
    }
}
