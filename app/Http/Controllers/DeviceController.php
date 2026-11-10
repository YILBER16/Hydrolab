<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\CropAlertService;
use App\Services\DashboardSnapshotService;
use App\Services\HydroponicAdvisorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function index(DashboardSnapshotService $snapshot): Response
    {
        $device = Device::query()->with(['sensors.latestReading'])->first();
        $payload = $snapshot->forDevice($device, 24, includeChart: false);

        return Inertia::render('Device', [
            'device' => $device ? [
                ...$payload['device'],
                'token_preview' => Str::mask($device->api_token, '•', 6, -4),
            ] : null,
            'sensors' => $payload['sensors'],
            'crops' => HydroponicAdvisorService::cropOptions(),
        ]);
    }

    public function updateCrop(Request $request, CropAlertService $alerts): RedirectResponse
    {
        $device = Device::query()->with('sensors')->first();

        if (! $device) {
            return back()->withErrors(['crop' => 'No hay dispositivo configurado.']);
        }

        $validated = $request->validate([
            'crop' => ['required', 'string', Rule::in(array_keys(HydroponicAdvisorService::crops()))],
            'temperature_min' => ['required', 'numeric'],
            'temperature_max' => ['required', 'numeric', 'gte:temperature_min'],
            'ph_min' => ['required', 'numeric'],
            'ph_max' => ['required', 'numeric', 'gte:ph_min'],
        ]);

        $alerts->applyCropToDevice($device, $validated['crop'], [
            'temperature' => [$validated['temperature_min'], $validated['temperature_max']],
            'ph' => [$validated['ph_min'], $validated['ph_max']],
        ]);

        return back()->with('success', 'Cultivo y rangos guardados. Hydrolab comparará automáticamente las lecturas del ESP32.');
    }
}
