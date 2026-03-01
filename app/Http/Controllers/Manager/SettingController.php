<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    private function transformSetting(?Setting $setting): ?array
    {
        if (! $setting) {
            return null;
        }

        $logoUrl = $setting->school_logo;
        if ($logoUrl && ! Str::startsWith($logoUrl, ['http://', 'https://'])) {
            $logoUrl = url(Storage::url($logoUrl));
        }

        return [
            'id' => $setting->id,
            'school_name' => $setting->school_name,
            'slogan' => $setting->slogan,
            'school_logo' => $setting->school_logo,
            'school_logo_url' => $logoUrl,
            'school_color' => $setting->school_color,
        ];
    }

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->transformSetting(Setting::query()->first()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_name' => ['nullable', 'string', 'max:255'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'school_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'school_logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:10240'],
        ]);

        $setting = Setting::query()->first();
        $logoPath = $setting?->school_logo;

        if ($request->hasFile('school_logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            $logoPath = $request->file('school_logo')->store('settings/logo', 'public');
        }

        $payload = [
            'school_name' => $data['school_name'] ?? null,
            'slogan' => $data['slogan'] ?? null,
            'school_color' => $data['school_color'] ?? null,
            'school_logo' => $logoPath,
        ];

        if ($setting) {
            $setting->update($payload);
            $setting->refresh();
        } else {
            $setting = Setting::query()->create($payload);
        }

        return response()->json([
            'data' => $this->transformSetting($setting),
        ], $setting->wasRecentlyCreated ? 201 : 200);
    }
}
