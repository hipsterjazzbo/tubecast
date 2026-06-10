<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\GlobalSetting;

final class SettingsRepository
{
    public function get(string $key, ?string $default = null): ?string
    {
        $setting = GlobalSetting::select()
            ->where('settingKey = ?', $key)
            ->first();

        return $setting?->value ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        $existing = GlobalSetting::select()
            ->where('settingKey = ?', $key)
            ->first();

        if ($existing !== null) {
            $existing->value = $value ?? '';
            $existing->save();

            return;
        }

        GlobalSetting::create(
            settingKey: $key,
            value: $value ?? '',
        );
    }
}
