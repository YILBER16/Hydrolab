<?php

namespace App\Http\Controllers;

use App\Models\Crop;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CropController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $crop = Crop::query()->create([
            'slug' => Crop::makeSlug($validated['name']),
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
            'temperature_min' => $validated['temperature_min'],
            'temperature_max' => $validated['temperature_max'],
            'ph_min' => $validated['ph_min'],
            'ph_max' => $validated['ph_max'],
            'harvest_days' => $validated['harvest_days'],
            'is_system' => false,
            'is_active' => true,
        ]);

        return back()->with('success', "Cultivo \"{$crop->name}\" creado. Ya puedes seleccionarlo.");
    }

    public function update(Request $request, Crop $crop): RedirectResponse
    {
        $validated = $this->validated($request, $crop);

        $payload = [
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
            'temperature_min' => $validated['temperature_min'],
            'temperature_max' => $validated['temperature_max'],
            'ph_min' => $validated['ph_min'],
            'ph_max' => $validated['ph_max'],
            'harvest_days' => $validated['harvest_days'],
        ];

        if (! $crop->is_system) {
            $payload['slug'] = Crop::makeSlug($validated['name'], $crop->id);
        }

        $crop->update($payload);

        return back()->with('success', "Cultivo \"{$crop->name}\" actualizado.");
    }

    public function destroy(Crop $crop): RedirectResponse
    {
        if ($crop->is_system) {
            return back()->withErrors(['crop' => 'Los cultivos del sistema no se pueden eliminar.']);
        }

        $inUse = Device::query()->where('active_crop', $crop->slug)->exists();

        if ($inUse) {
            return back()->withErrors([
                'crop' => 'Ese cultivo está en uso por el dispositivo. Cambia el cultivo activo antes de eliminarlo.',
            ]);
        }

        $name = $crop->name;
        $crop->delete();

        return back()->with('success', "Cultivo \"{$name}\" eliminado.");
    }

    /**
     * @return array{
     *     name: string,
     *     notes: ?string,
     *     temperature_min: float|int|string,
     *     temperature_max: float|int|string,
     *     ph_min: float|int|string,
     *     ph_max: float|int|string,
     *     harvest_days: int
     * }
     */
    private function validated(Request $request, ?Crop $crop = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('crops', 'name')->ignore($crop?->id),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'temperature_min' => ['required', 'numeric'],
            'temperature_max' => ['required', 'numeric', 'gte:temperature_min'],
            'ph_min' => ['required', 'numeric'],
            'ph_max' => ['required', 'numeric', 'gte:ph_min'],
            'harvest_days' => ['required', 'integer', 'min:7', 'max:180'],
        ]);
    }
}
