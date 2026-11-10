<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\DailyAdviceService;
use App\Services\DashboardSnapshotService;
use App\Services\HydroponicAdvisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardSnapshotService $snapshot,
        private DailyAdviceService $dailyAdvice,
    ) {
    }

    public function index(Request $request): Response
    {
        $hours = $this->snapshot->normalizeHours((int) $request->integer('hours', 24));
        $device = Device::query()->with(['sensors.latestReading'])->first();

        if ($device) {
            $this->dailyAdvice->ensureForToday($device);
            $device->refresh();
        }

        return Inertia::render('Dashboard', [
            ...$this->snapshot->forDevice($device, $hours),
            'crops' => HydroponicAdvisorService::cropOptions(),
        ]);
    }

    public function live(Request $request): JsonResponse
    {
        $hours = $this->snapshot->normalizeHours((int) $request->integer('hours', 24));
        $device = Device::query()->with(['sensors.latestReading'])->first();

        return response()->json(
            $this->snapshot->forDevice($device, $hours, $request->boolean('chart', true))
        );
    }

    public function refreshDailyAdvice(): RedirectResponse
    {
        $device = Device::query()->with(['sensors.latestReading'])->first();

        if (! $device) {
            return back()->withErrors(['daily_advice' => 'No hay dispositivo configurado.']);
        }

        $this->dailyAdvice->ensureForToday($device, force: true);

        return back()->with('success', 'Consejo del día actualizado.');
    }
}
