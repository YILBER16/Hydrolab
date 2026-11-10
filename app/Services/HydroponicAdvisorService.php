<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Reading;
use App\Models\Sensor;
use Throwable;

class HydroponicAdvisorService
{
    /**
     * @return array<string, array{
     *     label: string,
     *     notes: string,
     *     temperature: array{0: float, 1: float},
     *     ph: array{0: float, 1: float},
     *     harvest_days: int
     * }>
     */
    public static function crops(): array
    {
        return [
            'general' => [
                'label' => 'Cultivo general',
                'notes' => 'Rangos del sistema Hydrolab. Adecuado si aún no defines el cultivo.',
                'temperature' => [18.0, 28.0],
                'ph' => [5.5, 6.5],
                'harvest_days' => 40,
            ],
            'lechuga' => [
                'label' => 'Lechuga',
                'notes' => 'Prefiere agua fresca. Temperatura ideal 18-24 °C, pH 5.5-6.5, EC 0.8-1.2 mS/cm.',
                'temperature' => [18.0, 24.0],
                'ph' => [5.5, 6.5],
                'harvest_days' => 35,
            ],
            'albahaca' => [
                'label' => 'Albahaca',
                'notes' => 'Tolera un poco más de calor. Temperatura 20-26 °C, pH 5.5-6.5, EC 1.0-1.6 mS/cm.',
                'temperature' => [20.0, 26.0],
                'ph' => [5.5, 6.5],
                'harvest_days' => 32,
            ],
            'tomate' => [
                'label' => 'Tomate cherry',
                'notes' => 'Necesita más nutrientes. Temperatura 18-26 °C, pH 5.5-6.5, EC 2.0-3.5 mS/cm.',
                'temperature' => [18.0, 26.0],
                'ph' => [5.5, 6.5],
                'harvest_days' => 70,
            ],
        ];
    }

    /**
     * @return list<array{id: string, label: string, notes: string, temperature: array{0: float, 1: float}, ph: array{0: float, 1: float}, harvest_days: int}>
     */
    public static function cropOptions(): array
    {
        return collect(self::crops())->map(fn (array $crop, string $id) => [
            'id' => $id,
            'label' => $crop['label'],
            'notes' => $crop['notes'],
            'temperature' => $crop['temperature'],
            'ph' => $crop['ph'],
            'harvest_days' => $crop['harvest_days'],
        ])->values()->all();
    }

    public function __construct(
        private DashboardSnapshotService $snapshot,
        private GeminiService $gemini,
    ) {
    }

    /**
     * @return array{crop: string, crop_label: string, device: ?array, sensors: array, summary: array, has_live_data: bool}
     */
    public function context(?Device $device, string $crop = 'general'): array
    {
        $crop = $this->normalizeCrop($crop);
        $snapshot = $this->snapshot->forDevice($device, 24, includeChart: false);
        $crops = self::crops();

        $summary = [];

        if ($device) {
            $summary = $device->sensors->map(function (Sensor $sensor) {
                $latest = $sensor->latestReading;
                $value = $latest ? (float) $latest->value : null;
                $recent = Reading::query()
                    ->where('sensor_id', $sensor->id)
                    ->where('recorded_at', '>=', now()->subHours(24))
                    ->orderBy('recorded_at')
                    ->get(['value', 'recorded_at']);

                $values = $recent->pluck('value')->map(fn ($v) => (float) $v);

                return [
                    'name' => $sensor->name,
                    'type' => $sensor->type,
                    'unit' => $sensor->unit,
                    'latest' => $value,
                    'latest_at' => $latest?->recorded_at?->toIso8601String(),
                    'status' => $this->snapshot->statusFor($sensor, $value),
                    'min_24h' => $values->isNotEmpty() ? round($values->min(), 2) : null,
                    'max_24h' => $values->isNotEmpty() ? round($values->max(), 2) : null,
                    'avg_24h' => $values->isNotEmpty() ? round($values->avg(), 2) : null,
                    'samples_24h' => $values->count(),
                    'min_threshold' => $sensor->min_threshold !== null ? (float) $sensor->min_threshold : null,
                    'max_threshold' => $sensor->max_threshold !== null ? (float) $sensor->max_threshold : null,
                ];
            })->values()->all();
        }

        $hasLiveData = collect($summary)->contains(
            fn (array $sensor) => $sensor['latest'] !== null
        );

        return [
            'crop' => $crop,
            'crop_label' => $crops[$crop]['label'],
            'crop_notes' => $crops[$crop]['notes'],
            'device' => $snapshot['device'],
            'sensors' => $snapshot['sensors'],
            'summary' => $summary,
            'has_live_data' => $hasLiveData,
            'gemini_configured' => filled(config('services.gemini.key')),
        ];
    }

    /**
     * @return array{text: string, source: string, crop: string}
     */
    public function analyze(?Device $device, string $crop = 'general'): array
    {
        $context = $this->context($device, $crop);
        $source = 'local';
        $text = $this->localAdvice($context);

        if ($context['gemini_configured']) {
            try {
                $text = $this->gemini->analyzeHydroponicSystem($this->geminiPayload($context));
                $source = 'gemini';
            } catch (Throwable) {
                $text = $this->localAdvice($context)."\n\n(Gemini no respondió; se usó el análisis local de Hydrolab.)";
            }
        }

        return [
            'text' => $text,
            'source' => $source,
            'crop' => $context['crop'],
        ];
    }

    /**
     * @param  array{crop: string, crop_label: string, crop_notes: string, device: ?array, summary: array}  $context
     */
    public function localAdvice(array $context): string
    {
        $device = $context['device'];
        $summary = $context['summary'];
        $cropLabel = $context['crop_label'];
        $crop = $context['crop'];
        $idealTemp = self::crops()[$crop]['temperature'];

        $lines = [];
        $live = collect($summary)->whereNotNull('latest');
        $alerts = $live->whereIn('status', ['low', 'high']);
        $temperature = collect($summary)->firstWhere('type', 'temperature');

        if ($live->isEmpty()) {
            $lines[] = '1. Estado general';
            $lines[] = "Aún no hay lecturas del ESP32 para analizar el cultivo de {$cropLabel}.";
            $lines[] = '';
            $lines[] = '2. Datos faltantes';
            $lines[] = 'Conecta el dispositivo y espera a que envíe temperatura (y el resto de sensores cuando los tengas).';
            $lines[] = '';
            $lines[] = '3. Recomendaciones';
            $lines[] = '- Verifica WiFi, token e IP en el sketch del ESP32.';
            $lines[] = '- Abre Dashboard y confirma que aparezca la temperatura.';
            $lines[] = '';
            $lines[] = '4. Prioridad';
            $lines[] = 'Alta: recuperar la conexión del ESP32 antes de ajustar el cultivo.';

            return implode("\n", $lines);
        }

        if ($alerts->isEmpty()) {
            $estado = "El cultivo de {$cropLabel} está dentro de los rangos medidos.";
        } else {
            $estado = "El cultivo de {$cropLabel} necesita atención: ".$alerts->count().' parámetro(s) fuera de rango.';
        }

        if ($device) {
            $estado .= $device['is_online']
                ? ' El ESP32 está conectado.'
                : ' El ESP32 aparece desconectado; las lecturas pueden no ser actuales.';
        }

        $lines[] = '1. Estado general';
        $lines[] = $estado;
        $lines[] = '';
        $lines[] = '2. Parámetros';

        foreach ($summary as $sensor) {
            if ($sensor['latest'] === null) {
                $lines[] = "- {$sensor['name']}: sin datos. No se inventa un valor.";
                continue;
            }

            $status = match ($sensor['status']) {
                'high' => 'alto',
                'low' => 'bajo',
                default => 'óptimo',
            };
            $range = ($sensor['min_threshold'] !== null && $sensor['max_threshold'] !== null)
                ? "rango {$sensor['min_threshold']}–{$sensor['max_threshold']} {$sensor['unit']}"
                : 'sin rango configurado';
            $lines[] = sprintf(
                '- %s: %s %s (%s, %s). Últimas 24 h: mín %s / máx %s / media %s (%d lecturas).',
                $sensor['name'],
                $sensor['latest'],
                $sensor['unit'],
                $status,
                $range,
                $sensor['min_24h'] ?? '—',
                $sensor['max_24h'] ?? '—',
                $sensor['avg_24h'] ?? '—',
                $sensor['samples_24h'],
            );
        }

        if ($device) {
            $pump = match ($device['pump_on']) {
                true => 'encendida',
                false => 'apagada',
                default => 'sin dato (el ESP32 aún no envía pump_on)',
            };
            $lines[] = "- Bomba: {$pump}.";
        }

        $lines[] = '';
        $lines[] = '3. Recomendaciones concretas';

        if ($temperature && $temperature['latest'] !== null) {
            $temp = (float) $temperature['latest'];
            if ($temp > $idealTemp[1] || $temperature['status'] === 'high') {
                $lines[] = "- Temperatura alta ({$temp} °C). Para {$cropLabel} el agua debería estar entre {$idealTemp[0]} y {$idealTemp[1]} °C.";
                $lines[] = '- Mueve el tanque a sombra o un lugar más fresco y evita que el sol caliente el depósito.';
                $lines[] = '- Renueva parte del agua con agua más fría (poco a poco, no de golpe).';
                $lines[] = '- Mantén la bomba circulando: el agua en movimiento se calienta menos y oxigena las raíces.';
                $lines[] = '- Si supera 30 °C de forma continua, las raíces pueden estresarse y aparecer algas o pudrición.';
            } elseif ($temp < $idealTemp[0] || $temperature['status'] === 'low') {
                $lines[] = "- Temperatura baja ({$temp} °C). El rango ideal para {$cropLabel} es {$idealTemp[0]}–{$idealTemp[1]} °C.";
                $lines[] = '- Protege el tanque de corrientes frías. Un cultivo lento suele indicar agua demasiado fría.';
            } else {
                $lines[] = "- Temperatura en {$temp} °C: adecuada para {$cropLabel}.";
            }
        }

        $ph = collect($summary)->firstWhere('type', 'ph');
        if (! $ph || $ph['latest'] === null) {
            $lines[] = '- pH: todavía no hay sensor. Mide el pH del tanque (ideal 5.5–6.5) para que las plantas absorban nutrientes.';
        }

        $ec = collect($summary)->firstWhere('type', 'ec');
        if (! $ec || $ec['latest'] === null) {
            $lines[] = '- EC/nutrientes: sin sensor. Revisa que la solución nutritiva no esté demasiado diluida ni concentrada.';
        }

        $water = collect($summary)->firstWhere('type', 'water_level');
        if (! $water || $water['latest'] === null) {
            $lines[] = '- Nivel de agua: sin sensor. Confirma a simple vista que las raíces no queden al aire.';
        }

        if ($device && $device['pump_on'] === false) {
            $lines[] = '- La bomba está apagada. En hidropónico las raíces necesitan oxígeno y circulación; no la dejes mucho tiempo detenida.';
        }

        $lines[] = '- '.$context['crop_notes'];
        $lines[] = '';
        $lines[] = '4. Prioridad de acciones';

        if ($temperature && $temperature['status'] === 'high') {
            $lines[] = 'Alta: bajar la temperatura del agua.';
            $lines[] = 'Media: vigilar bomba y nivel del tanque.';
            $lines[] = 'Baja: conectar pH y EC cuando puedas, para afinar nutrientes.';
        } elseif ($alerts->isNotEmpty()) {
            $lines[] = 'Alta: corregir los parámetros fuera de rango.';
            $lines[] = 'Media: confirmar que la bomba cicla bien.';
            $lines[] = 'Baja: añadir los sensores que aún no envían datos.';
        } else {
            $lines[] = 'Alta: ninguna corrección urgente con los datos actuales.';
            $lines[] = 'Media: seguir enviando temperatura cada 15 s y revisar el tanque.';
            $lines[] = 'Baja: incorporar pH, EC y nivel para un diagnóstico completo.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{crop: string, crop_label: string, crop_notes: string, device: ?array, summary: array}  $context
     * @return array<string, mixed>
     */
    private function geminiPayload(array $context): array
    {
        return [
            'cultivo' => $context['crop_label'],
            'notas_cultivo' => $context['crop_notes'],
            'dispositivo' => $context['device']['name'] ?? null,
            'ubicacion' => $context['device']['location'] ?? null,
            'esp32_conectado' => $context['device']['is_online'] ?? false,
            'bomba_encendida' => $context['device']['pump_on'] ?? null,
            'ultima_conexion' => $context['device']['last_seen_at'] ?? null,
            'parametros' => $context['summary'],
        ];
    }

    public function normalizeCrop(string $crop): string
    {
        return array_key_exists($crop, self::crops()) ? $crop : 'general';
    }
}
