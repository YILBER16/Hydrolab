<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\DashboardSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private DashboardSnapshotService $snapshot)
    {
    }

    public function index(Request $request): Response
    {
        $hours = $this->snapshot->normalizeHours((int) $request->integer('hours', 24));
        $device = Device::query()->with(['sensors.latestReading'])->first();

        return Inertia::render('Dashboard', $this->snapshot->forDevice($device, $hours));
    }

    public function live(Request $request): JsonResponse
    {
        $hours = $this->snapshot->normalizeHours((int) $request->integer('hours', 24));
        $device = Device::query()->with(['sensors.latestReading'])->first();

        return response()->json(
            $this->snapshot->forDevice($device, $hours, $request->boolean('chart', true))
        );
    }
}
