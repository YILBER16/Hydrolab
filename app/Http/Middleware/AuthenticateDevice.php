<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken()
            ?? $request->header('X-Device-Token')
            ?? $request->input('device_token');

        if (! $token) {
            return response()->json(['message' => 'Device token required.'], 401);
        }

        $device = Device::query()
            ->where('api_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return response()->json(['message' => 'Invalid device token.'], 401);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
