<?php

namespace App\Http\Controllers;

use App\Models\Device;
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
        $crop = $this->advisor->normalizeCrop((string) $request->string('crop', 'general'));
        $context = $this->advisor->context($device, $crop);
        $analysis = session('analysis');

        return Inertia::render('Analysis', [
            'device' => $context['device'],
            'sensors' => $context['sensors'],
            'crops' => collect(HydroponicAdvisorService::crops())->map(fn (array $crop, string $id) => [
                'id' => $id,
                'label' => $crop['label'],
                'notes' => $crop['notes'],
            ])->values()->all(),
            'selectedCrop' => $crop,
            'geminiConfigured' => $context['gemini_configured'],
            'hasLiveData' => $context['has_live_data'],
            'analysis' => is_array($analysis) ? $analysis['text'] ?? null : $analysis,
            'analysisSource' => is_array($analysis) ? $analysis['source'] ?? null : session('analysis_source'),
            'error' => session('analysis_error'),
        ]);
    }

    public function analyze(Request $request): RedirectResponse
    {
        $device = Device::query()->with(['sensors.latestReading'])->first();

        if (! $device) {
            return back()->with('analysis_error', 'No hay dispositivo configurado.');
        }

        $crop = $this->advisor->normalizeCrop((string) $request->string('crop', 'general'));
        $result = $this->advisor->analyze($device, $crop);

        return redirect()
            ->route('analysis', ['crop' => $crop])
            ->with([
                'analysis' => $result,
                'analysis_source' => $result['source'],
            ]);
    }
}
