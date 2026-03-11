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

        $backgroundImageUrl = $setting->background_image;
        if ($backgroundImageUrl && ! Str::startsWith($backgroundImageUrl, ['http://', 'https://'])) {
            $backgroundImageUrl = url(Storage::url($backgroundImageUrl));
        }

        return [
            'id' => $setting->id,
            'school_name' => $setting->school_name,
            'slogan' => $setting->slogan,
            'description' => $setting->description,
            'school_logo' => $setting->school_logo,
            'school_logo_url' => $logoUrl,
            'background_image' => $setting->background_image,
            'background_image_url' => $backgroundImageUrl,
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
            'description' => ['nullable', 'string'],
            'school_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'school_logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:10240'],
            'background_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:10240'],
        ]);

        $setting = Setting::query()->first();
        $logoPath = $setting?->school_logo;
        $backgroundImagePath = $setting?->background_image;

        if ($request->hasFile('school_logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            $logoPath = $request->file('school_logo')->store('settings/logo', 'public');
        }

        if ($request->hasFile('background_image')) {
            if ($backgroundImagePath) {
                Storage::disk('public')->delete($backgroundImagePath);
            }

            $backgroundImagePath = $request->file('background_image')->store('settings/background', 'public');
        }

        $payload = [
            'school_name' => $data['school_name'] ?? null,
            'slogan' => $data['slogan'] ?? null,
            'description' => $data['description'] ?? null,
            'school_color' => $data['school_color'] ?? null,
            'school_logo' => $logoPath,
            'background_image' => $backgroundImagePath,
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
