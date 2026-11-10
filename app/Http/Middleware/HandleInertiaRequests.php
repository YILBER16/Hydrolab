<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $device = $request->user()
            ? Device::query()->first()
            : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'hydrolabDevice' => $device ? [
                'name' => $device->name,
                'is_online' => $device->isOnline(),
                'pump_on' => $device->pump_on,
            ] : null,
        ];
    }
}
