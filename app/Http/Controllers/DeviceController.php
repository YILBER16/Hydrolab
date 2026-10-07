<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\DashboardSnapshotService;
use Illuminate\Support\Str;
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
        ]);
    }
}
