<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Log;
use Throwable;

class DailyAdviceService
{
    public function __construct(
        private HydroponicAdvisorService $advisor,
        private GeminiService $gemini,
        private HarvestProjectionService $harvest,
    ) {
    }

    /**
     * Ensure there is a daily tip for today. Generates at most once per calendar day
     * unless $force is true (manual request).
     */
    public function ensureForToday(?Device $device, bool $force = false): ?array
    {
        if (! $device) {
            return null;
        }

        $today = now()->toDateString();

        if (! $force && $device->daily_advice_date?->toDateString() === $today && filled($device->daily_advice)) {
            return $this->payload($device);
        }

        return $this->generate($device, $force);
    }

    public function generate(Device $device, bool $manual = false): array
    {
        $device->loadMissing('sensors.latestReading');
        $crop = $device->active_crop ?: 'general';
        $context = $this->advisor->context($device, $crop);
        $harvest = $this->harvest->forDevice($device);

        $source = 'local';
        $text = $this->localTip($context, $harvest, $manual);

        if ($context['gemini_configured']) {
            try {
                $text = $this->gemini->dailyTip([
                    'cultivo' => $context['crop_label'],
                    'notas_cultivo' => $context['crop_notes'],
                    'parametros' => $context['summary'],
                    'bomba_encendida' => $context['device']['pump_on'] ?? null,
                    'esp32_conectado' => $context['device']['is_online'] ?? false,
                    'proyeccion_cosecha' => $harvest,
                    'solicitud' => $manual ? 'manual' : 'automatico_diario',
                ]);
                $source = 'gemini';
            } catch (Throwable $e) {
                Log::warning('Hydrolab daily advice Gemini failed', [
                    'device_id' => $device->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $device->forceFill([
            'daily_advice' => $text,
            'daily_advice_source' => $source,
            'daily_advice_date' => now()->toDateString(),
            'daily_advice_at' => now(),
        ])->save();

        return $this->payload($device->refresh());
    }

    /**
     * @param  array{crop_label: string, summary: array, has_live_data: bool, device: ?array}  $context
     */
    private function localTip(array $context, ?array $harvest, bool $manual): string
    {
        $summary = collect($context['summary'] ?? []);
        $temperature = $summary->firstWhere('type', 'temperature');
        $ph = $summary->firstWhere('type', 'ph');
        $crop = $context['crop_label'];

        if (! ($context['has_live_data'] ?? false)) {
            return "Hoy no hay lecturas del ESP32 para {$crop}. Revisa WiFi y token; mientras tanto mantén el tanque limpio y la bomba ciclando.";
        }

        if ($temperature && ($temperature['status'] ?? null) === 'high') {
            return "La temperatura del agua está alta para {$crop}. Busca sombra o refresca un poco el tanque y vuelve a medir en unas horas.";
        }

        if ($temperature && ($temperature['status'] ?? null) === 'low') {
            return "El agua está fría para {$crop}. Protege el tanque de corrientes; el crecimiento puede ir más lento.";
        }

        if ($ph && ($ph['status'] ?? null) === 'high') {
            return "El pH está alto. Bájalo de forma gradual para que {$crop} absorba mejor los nutrientes.";
        }

        if ($ph && ($ph['status'] ?? null) === 'low') {
            return "El pH está bajo. Ajústalo poco a poco hacia 5.5–6.5 según el cultivo.";
        }

        if ($harvest && ($harvest['status'] ?? null) === 'ready') {
            return "Según la proyección, {$crop} ya está en ventana de cosecha. Revisa tamaño y color de hojas antes de cortar.";
        }

        if ($harvest && ($harvest['remaining_days'] ?? null) !== null) {
            return "{$crop} va {$harvest['status_label']}: cosecha estimada en {$harvest['remaining_days']} día(s). Hoy prioriza temperatura y pH estables.";
        }

        $prefix = $manual ? 'Revisión manual:' : 'Consejo del día:';

        return "{$prefix} {$crop} está dentro de rangos medidos. Mantén circulación de la bomba y anota cualquier cambio visual en las raíces.";
    }

    private function payload(Device $device): array
    {
        return [
            'text' => $device->daily_advice,
            'source' => $device->daily_advice_source,
            'date' => $device->daily_advice_date?->toDateString(),
            'generated_at' => $device->daily_advice_at?->toIso8601String(),
            'is_today' => $device->daily_advice_date?->toDateString() === now()->toDateString(),
        ];
    }
}
