<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\CropAlertService;
use App\Services\HydroponicAdvisorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalysisController extends Controller
{
    public function __construct(private HydroponicAdvisorService $advisor)
    {
    }

    public function index(Request $request): Response
    {
        $device = Device::query()->with(['sensors.latestReading'])->first();
        $defaultCrop = $device?->active_crop ?: 'general';
        $crop = $this->advisor->normalizeCrop((string) $request->string('crop', $defaultCrop));
        $context = $this->advisor->context($device, $crop);
        $analysis = session('analysis');

        return Inertia::render('Analysis', [
            'device' => $context['device'],
            'sensors' => $context['sensors'],
            'crops' => HydroponicAdvisorService::cropOptions(),
            'selectedCrop' => $crop,
            'geminiConfigured' => $context['gemini_configured'],
            'hasLiveData' => $context['has_live_data'],
            'analysis' => is_array($analysis) ? $analysis['text'] ?? null : ($analysis ?? $device?->last_ai_advice),
            'analysisSource' => is_array($analysis) ? $analysis['source'] ?? null : (session('analysis_source') ?? $device?->last_ai_source),
            'error' => session('analysis_error'),
        ]);
    }

    public function analyze(Request $request): RedirectResponse
    {
        $device = Device::query()->with(['sensors.latestReading'])->first();

        if (! $device) {
            return back()->with('analysis_error', 'No hay dispositivo configurado.');
        }

        $crop = $this->advisor->normalizeCrop(
            (string) $request->string('crop', $device->active_crop ?: 'general')
        );

        if ($device->active_crop !== $crop) {
            app(CropAlertService::class)->applyCropToDevice($device, $crop);
            $device->refresh();
        }

        $result = $this->advisor->analyze($device, $crop);

        $device->forceFill([
            'last_ai_advice' => $result['text'],
            'last_ai_source' => $result['source'],
            'last_ai_alert_at' => now(),
        ])->save();

        return redirect()
            ->route('analysis', ['crop' => $crop])
            ->with([
                'analysis' => $result,
                'analysis_source' => $result['source'],
            ]);
    }
}
