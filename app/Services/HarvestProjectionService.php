<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Reading;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class HarvestProjectionService
{
    public function forDevice(?Device $device): ?array
    {
        if (! $device || ! $device->planted_at) {
            return null;
        }

        $crop = $device->active_crop ?: 'general';
        $profile = HydroponicAdvisorService::crops()[$crop]
            ?? HydroponicAdvisorService::crops()['general']
            ?? ['harvest_days' => 40, 'label' => 'Cultivo'];
        $plannedDays = (int) ($device->harvest_days ?: ($profile['harvest_days'] ?? 40));
        $plannedDays = max(7, min(180, $plannedDays));

        $plantedAt = $device->planted_at->copy()->startOfDay();
        $today = now()->startOfDay();
        $elapsed = max(0, $plantedAt->diffInDays($today));
        $delayDays = $this->estimateDelayDays($device, $plantedAt);
        $effectiveDays = $plannedDays + $delayDays;
        $remaining = max(0, $effectiveDays - $elapsed);
        $harvestDate = $plantedAt->copy()->addDays($effectiveDays);
        $progress = min(100, (int) round(($elapsed / max(1, $effectiveDays)) * 100));

        $status = 'on_track';
        $statusLabel = 'A tiempo';

        if ($elapsed >= $effectiveDays) {
            $status = 'ready';
            $statusLabel = 'Lista para cosechar';
            $progress = 100;
            $remaining = 0;
        } elseif ($delayDays >= 3) {
            $status = 'delayed';
            $statusLabel = 'Retrasada';
        } elseif ($delayDays === 0 && $elapsed > 5) {
            $outOfRangeShare = $this->outOfRangeShare($device, $plantedAt);
            if ($outOfRangeShare === 0.0) {
                $status = 'ahead';
                $statusLabel = 'En buen ritmo';
            }
        }

        $cropMeta = $profile;

        return [
            'crop' => $crop,
            'crop_label' => $cropMeta['label'],
            'planted_at' => $plantedAt->toDateString(),
            'planned_days' => $plannedDays,
            'delay_days' => $delayDays,
            'effective_days' => $effectiveDays,
            'elapsed_days' => $elapsed,
            'remaining_days' => $remaining,
            'progress_percent' => $progress,
            'harvest_date' => $harvestDate->toDateString(),
            'harvest_date_label' => $harvestDate->locale('es')->isoFormat('D MMM YYYY'),
            'status' => $status,
            'status_label' => $statusLabel,
            'summary' => $this->summary($cropMeta['label'], $remaining, $statusLabel, $delayDays),
        ];
    }

    private function estimateDelayDays(Device $device, CarbonInterface $plantedAt): int
    {
        $share = $this->outOfRangeShare($device, $plantedAt);

        if ($share >= 0.35) {
            return 5;
        }
        if ($share >= 0.2) {
            return 3;
        }
        if ($share >= 0.1) {
            return 1;
        }

        return 0;
    }

    private function outOfRangeShare(Device $device, CarbonInterface $plantedAt): float
    {
        $device->loadMissing('sensors');
        $watched = config('hydrolab.ai_alert_sensor_types', ['temperature', 'ph']);
        $snapshot = app(DashboardSnapshotService::class);

        $total = 0;
        $bad = 0;

        foreach ($device->sensors as $sensor) {
            if (! in_array($sensor->type, $watched, true)) {
                continue;
            }

            $readings = Reading::query()
                ->where('sensor_id', $sensor->id)
                ->where('recorded_at', '>=', Carbon::parse($plantedAt)->subDay())
                ->orderByDesc('recorded_at')
                ->limit(48)
                ->get(['value']);

            foreach ($readings as $reading) {
                $total++;
                $status = $snapshot->statusFor($sensor, (float) $reading->value);
                if (in_array($status, ['low', 'high'], true)) {
                    $bad++;
                }
            }
        }

        if ($total === 0) {
            return 0.0;
        }

        return $bad / $total;
    }

    private function summary(string $cropLabel, int $remaining, string $statusLabel, int $delayDays): string
    {
        if ($remaining === 0) {
            return "El cultivo de {$cropLabel} ya alcanzó la ventana estimada de cosecha.";
        }

        $delayNote = $delayDays > 0
            ? " Se sumaron {$delayDays} día(s) por parámetros fuera de rango."
            : '';

        return "Cosecha estimada en {$remaining} día(s) ({$statusLabel}).{$delayNote}";
    }
}
